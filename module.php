<?php
/**
 * @copyright Copyright (c) 2016, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

class ContactsModule extends AApiModule
{
	public $oApiContactsManager = null;
	
	protected $aSettingsMap = array(
		'ContactsPerPage' => array(20, 'int'),
		'ImportContactsLink' => array('', 'string'),
	);
	
	protected $aImportExportFormats = ['csv'];
	
	/**
	 * Initializes Contacts Module.
	 * 
	 * @ignore
	 */
	public function init() 
	{
		$this->incClass('contact');
		$this->incClass('group-contact');
		$this->incClass('group');
		$this->incClass('enum');

		$this->oApiContactsManager = $this->GetManager();
		
		$this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
		$this->subscribeEvent('AdminPanelWebclient::DeleteEntity::before', array($this, 'onBeforeDeleteEntity'));
		$this->subscribeEvent('Contacts::Import', array($this, 'onImportCsv'));
		
		$this->setObjectMap('CUser', array(
				'ContactsPerPage' => array('int', $this->getConfig('ContactsPerPage', 20)),
			)
		);
	}
	
	/***** public functions *****/
	/**
	 * Returns API contacts manager.
	 * @return \CApiContactsManager
	 */
	public function GetApiContactsManager()
	{
		return $this->oApiContactsManager;
	}
	/***** public functions *****/
	
	/***** public functions might be called with web API *****/
	/**
	 * Obtaines list of module settings for authenticated user.
	 * @return array
	 */
	public function GetSettings()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$aStorages = array();
		$this->broadcastEvent('GetStorage', $aStorages);
		
		$aFormats = [];
		$this->broadcastEvent('GetImportExportFormats', $aFormats);
		
		$oUser = \CApi::getAuthenticatedUser();
		$ContactsPerPage = $this->getConfig('ContactsPerPage', 20);
		if ($oUser && $oUser->Role === \EUserRole::NormalUser && isset($oUser->{$this->GetName().'::ContactsPerPage'}))
		{
			$ContactsPerPage = $oUser->{$this->GetName().'::ContactsPerPage'};
		}
		
		return array(
			'ContactsPerPage' => $ContactsPerPage,
			'ImportContactsLink' => $this->getConfig('ImportContactsLink', ''),
			'Storages' => $aStorages,
			'EContactsPrimaryEmail' => (new \EContactsPrimaryEmail)->getMap(),
			'EContactsPrimaryPhone' => (new \EContactsPrimaryPhone)->getMap(),
			'EContactsPrimaryAddress' => (new \EContactsPrimaryAddress)->getMap(),
			'EContactSortField' => (new \EContactSortField)->getMap(),
			'ImportExportFormats' => array_merge($this->aImportExportFormats, $aFormats),
		);
	}
	
	/**
	 * Updates module's settings - saves them to config.json file or to user settings in db.
	 * @param int $ContactsPerPage Count of contacts per page.
	 * @return boolean
	 */
	public function UpdateSettings($ContactsPerPage)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \CApi::getAuthenticatedUser();
		if ($oUser)
		{
			if ($oUser->Role === \EUserRole::NormalUser)
			{
				$oCoreDecorator = \CApi::GetModuleDecorator('Core');
				$oUser->{$this->GetName().'::ContactsPerPage'} = $ContactsPerPage;
				return $oCoreDecorator->UpdateUserObject($oUser);
			}
			if ($oUser->Role === \EUserRole::SuperAdmin)
			{
				$oSettings =& CApi::GetSettings();
				$oSettings->SetConf('ContactsPerPage', $ContactsPerPage);
				return $oSettings->Save();
			}
		}
		
		return false;
	}
	
	/**
	 * Exports specified contacts to a file with specified format.
	 * @param string $Format File format that should be used for export.
	 * @param array $Filters Filters for obtaining specified contacts.
	 * @param string $GroupUUID UUID of group that should contain contacts for export.
	 * @param array $ContactUUIDs List of UUIDs of contacts that should be exported.
	 */
	public function Export($Format, $Filters = [], $GroupUUID = '', $ContactUUIDs = [])
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aFilters = $this->prepareFilters($Filters);
		
		$aContacts = $this->oApiContactsManager->getContacts(EContactSortField::Name, ESortOrder::ASC, 0, 0, $aFilters, $GroupUUID, $ContactUUIDs);
		
		$sOutput = '';
		
		if ($Format === 'csv')
		{
			$this->incClass('csv-formatter');
			$this->incClass('csv-parser');
			$this->incClass('csv-sync');

			if (class_exists('CApiContactsSyncCsv'))
			{
				$oSync = new CApiContactsSyncCsv();
				$sOutput = $oSync->Export($aContacts);
			}
		}
		else
		{
			$aArgs = [
				'Format' => $Format,
				'Contacts' => $aContacts,
			];
			$this->broadcastEvent('GetExportOutput', $aArgs, $sOutput);
		}
		
		if (is_string($sOutput) && !empty($sOutput))
		{
			header('Pragma: public');
			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename="export.' . $Format . '";');
			header('Content-Transfer-Encoding: binary');
		}
		
		echo $sOutput;
	}
	
	/**
	 * Returns all groups for authenticated user.
	 * @return array
	 */
	public function GetGroups()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$iUserId = \CApi::getAuthenticatedUserId();
		
		return $this->oApiContactsManager->getGroups($iUserId);
	}
	
	/**
	 * Returns group with specified UUID.
	 * @param string $UUID UUID of group to return.
	 * @return \CGroup
	 */
	public function GetGroup($UUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->getGroup($UUID);
	}
	
	/**
	 * Returns list of contacts for specified parameters.
	 * @param int $Offset Offset of contacts list.
	 * @param int $Limit Limit of result contacts list.
	 * @param int $SortField Name of field order by.
	 * @param int $SortOrder Sorting direction.
	 * @param string $Search Search string.
	 * @param string $GroupUUID UUID of group that should contain all returned contacts.
	 * @param array $Filters Other conditions for obtaining contacts list.
	 * @return array
	 */
	public function GetContacts($Offset = 0, $Limit = 20, $SortField = EContactSortField::Name, $SortOrder = ESortOrder::ASC, $Search = '', $GroupUUID = '', $Filters = array())
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);

		$aFilters = $this->prepareFilters($Filters);
		
		if (!empty($Search))
		{
			$aSearchFilters = [
				'FullName' => ['%'.$Search.'%', 'LIKE'],
				'PersonalEmail' => ['%'.$Search.'%', 'LIKE'],
				'BusinessEmail' => ['%'.$Search.'%', 'LIKE'],
				'OtherEmail' => ['%'.$Search.'%', 'LIKE'],
			];
			if (count($aFilters) > 0)
			{
				$aFilters = [
					'$AND' => [
						'1$OR' => $aFilters, 
						'2$OR' => $aSearchFilters
					]
				];
			}
			else
			{
				$aFilters = [
					'$OR' => $aSearchFilters
				];
			}
		}
		elseif (count($aFilters) > 1)
		{
			$aFilters = ['$OR' => $aFilters];
		}

		$iCount = $this->oApiContactsManager->getContactsCount($aFilters, $GroupUUID);
		$aContacts = $this->oApiContactsManager->getContacts($SortField, $SortOrder, $Offset, $Limit, $aFilters, $GroupUUID);
		
		$aList = array();
		if (is_array($aContacts))
		{
			foreach ($aContacts as $oContact)
			{
				$aList[] = array(
					'UUID' => $oContact->sUUID,
					'IdUser' => $oContact->IdUser,
					'Name' => $oContact->FullName,
					'Email' => $oContact->ViewEmail,
					'Storage' => $oContact->Storage,
				);
			}
		}

		return array(
			'ContactCount' => $iCount,
			'List' => \CApiResponseManager::GetResponseObject($aList)
		);		
	}	
	
	/**
	 * Returns contact with specified UUID.
	 * @param string $UUID UUID of contact to return.
	 * @return \CContact
	 */
	public function GetContact($UUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->getContact($UUID);
	}

	/**
	 * Returns list of contacts with specified emails.
	 * @param array $Emails List of emails of contacts to return.
	 * @return array
	 */
	public function GetContactsByEmails($Emails)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \CApi::getAuthenticatedUser();
		
		$aFilters = [
			'$AND' => [
				'IdUser' => [$oUser->iId, '='],
				'ViewEmail' => [$Emails, 'IN']
			]
		];
		
		$aContacts = $this->oApiContactsManager->getContacts(EContactSortField::Name, ESortOrder::ASC, 0, 0, $aFilters, 0);
		
		return $aContacts;
	}	
	
	/**
	 * Creates contact with specified parameters.
	 * @param array $Contact Parameters of contact to create.
	 * @param int $iUserId Identificator of user that should own a new contact.
	 * @return bool|string
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreateContact($Contact, $iUserId = 0)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \CApi::getAuthenticatedUser();
		
		if ($iUserId > 0 && $iUserId !== $oUser->iId)
		{
			\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
			
			$oCoreDecorator = \CApi::GetModuleDecorator('Core');
			if ($oCoreDecorator)
			{
				$oUser = $oCoreDecorator->GetUser($iUserId);
			}
		}
		
		$oContact = \CContact::createInstance();
		$oContact->Populate($Contact, $oUser);

		$mResult = $this->oApiContactsManager->createContact($oContact);
		return $mResult && $oContact ? $oContact->sUUID : false;
	}	
	
	/**
	 * 
	 * @param int $iUserId
	 * @param string $VCard
	 * @return bool|string
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function CreateContactFromVCard($iUserId, $VCard, $UUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \CApi::getAuthenticatedUser();
		
		if ($iUserId > 0 && $iUserId !== $oUser->iId)
		{
			\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
			
			$oCoreDecorator = \CApi::GetModuleDecorator('Core');
			if ($oCoreDecorator)
			{
				$oUser = $oCoreDecorator->GetUser($iUserId);
			}
		}

		if ($iUserId > 0)
		{
			$oCoreDecorator = \CApi::GetModuleDecorator('Core');
			if ($oCoreDecorator)
			{
				$oUser = $oCoreDecorator->GetUser($iUserId);
				if ($oUser instanceof \CUser)
				{
					$oContact = \CContact::createInstance();
					$oContact->IdUser = $oUser->iId;
					$oContact->IdTenant = $oUser->IdTenant;
					$oContact->Storage = 'personal';

					$oContact->InitFromVCardStr($iUserId, $VCard, $UUID);

					$mResult = $this->oApiContactsManager->createContact($oContact);
					return $mResult && $oContact ? $oContact->sUUID : false;
				}
			}
		}
	}	
	
	/**
	 * Updates contact with specified parameters.
	 * @param array $Contact Parameters of contact to update.
	 * @return bool
	 */
	public function UpdateContact($Contact)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oContact = $this->oApiContactsManager->getContact($Contact['UUID']);
		$oContact->Populate($Contact);
		
		return $this->oApiContactsManager->updateContact($oContact);
	}
	
	/**
	 * 
	 * @param int $iUserId
	 * @param string $VCard
	 * @return bool|string
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function UpdateContactFromVCard($iUserId, $VCard, $UUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oUser = \CApi::getAuthenticatedUser();
		
		if ($iUserId > 0 && $iUserId !== $oUser->iId)
		{
			\CApi::checkUserRoleIsAtLeast(\EUserRole::SuperAdmin);
			
			$oCoreDecorator = \CApi::GetModuleDecorator('Core');
			if ($oCoreDecorator)
			{
				$oUser = $oCoreDecorator->GetUser($iUserId);
			}
		}

		if ($iUserId > 0)
		{
			$oCoreDecorator = \CApi::GetModuleDecorator('Core');
			if ($oCoreDecorator)
			{
				$oUser = $oCoreDecorator->GetUser($iUserId);
				if ($oUser instanceof \CUser)
				{
					$oContact = $this->oApiContactsManager->getContact($UUID);

					$oContact->InitFromVCardStr($iUserId, $VCard, $UUID);
					
					$mResult = $this->oApiContactsManager->updateContact($oContact);
					return $mResult && $oContact ? $oContact->sUUID : false;
				}
			}
		}
	}	
	
	/**
	 * Deletes contacts with specified UUIDs.
	 * @param array $UUIDs Array of strings - UUIDs of contacts to delete.
	 * @return bool
	 */
	public function DeleteContacts($UUIDs)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->deleteContacts($UUIDs);
	}	
	
	/**
	 * Creates group with specified parameters.
	 * @param array $Group Parameters of group to create.
	 * @return array
	 */
	public function CreateGroup($Group)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oGroup = \CGroup::createInstance();
		$oGroup->IdUser = \CApi::getAuthenticatedUserId();

		$oGroup->populate($Group);

		$this->oApiContactsManager->createGroup($oGroup);
		return $oGroup ? $oGroup->sUUID : false;
	}	
	
	/**
	 * 
	 * Updates group with specified parameters.
	 * @param array $Group Parameters of group to update.
	 * @return boolean
	 */
	public function UpdateGroup($Group)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oGroup = $this->oApiContactsManager->getGroup($Group['UUID']);
		if ($oGroup)
		{
			$oGroup->populate($Group);
			return $this->oApiContactsManager->updateGroup($oGroup);
		}

		return false;
	}	
	
	/**
	 * Deletes group with specified UUID.
	 * @param string $UUID UUID of group to delete.
	 * @return bool
	 */
	public function DeleteGroup($UUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->deleteGroups([$UUID]);
	}
	
	/**
	 * Adds specified contacts to specified group.
	 * @param string $GroupUUID UUID of group.
	 * @param array $ContactUUIDs Array of strings - UUIDs of contacts to add to group.
	 * @return boolean
	 */
	public function AddContactsToGroup($GroupUUID, $ContactUUIDs)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (is_array($ContactUUIDs) && !empty($ContactUUIDs))
		{
			return $this->oApiContactsManager->addContactsToGroup($GroupUUID, $ContactUUIDs);
		}
		
		return true;
	}
	
	/**
	 * Removes specified contacts from specified group.
	 * @param string $GroupUUID UUID of group.
	 * @param array $ContactUUIDs Array of strings - UUIDs of contacts to remove from group.
	 * @return boolean
	 */
	public function RemoveContactsFromGroup($GroupUUID, $ContactUUIDs)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (is_array($ContactUUIDs) && !empty($ContactUUIDs))
		{
			return $this->oApiContactsManager->removeContactsFromGroup($GroupUUID, $ContactUUIDs);
		}
		
		return true;
	}	
	
	/**
	 * Imports contacts from file with specified format.
	 * @param array $UploadData Array of uploaded file data.
	 * @param string $Storage Storage name.
	 * @param array $GroupUUID Group UUID.
	 * @return string
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function Import($UploadData, $Storage, $GroupUUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$sError = '';
		$aResponse = array(
			'ImportedCount' => 0,
			'ParsedCount' => 0
		);
		
		$oUser = \CApi::getAuthenticatedUser();

		if (is_array($UploadData))
		{
			$sFileType = strtolower(\api_Utils::GetFileExtension($UploadData['name']));

			$oApiFileCacheManager = \CApi::GetSystemManager('filecache');
			$sSavedName = 'import-post-' . md5($UploadData['name'] . $UploadData['tmp_name']);
			if ($oApiFileCacheManager->moveUploadedFile($oUser->sUUID, $sSavedName, $UploadData['tmp_name']))
			{
				$aArgs = [
					'Format' => $sFileType,
					'User' => $oUser,
					'TempFileName' => $oApiFileCacheManager->generateFullFilePath($oUser->sUUID, $sSavedName),
					'Storage' => $Storage,
					'GroupUUID' => $GroupUUID,
				];

				$mImportResult = [];
				$this->broadcastEvent('Import', $aArgs, $mImportResult);

				if (is_array($mImportResult) && count($mImportResult) === 2)
				{
					$aResponse['ImportedCount'] = $mImportResult['ImportedCount'];
					$aResponse['ParsedCount'] = $mImportResult['ParsedCount'];
				}
				else
				{
					throw new \System\Exceptions\AuroraApiException(\System\Notifications::IncorrectFileExtension);
				}

				$oApiFileCacheManager->clear($oUser->sUUID, $sSavedName);
			}
			else
			{
				$sError = 'unknown';
			}
		}
		else
		{
			$sError = 'unknown';
		}

		if (0 < strlen($sError))
		{
			$aResponse['Error'] = $sError;
		}

		return $aResponse;
	}
	
//	public function GetGroupEvents($UUID)
//	{
//		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		
//		return [];
//	}	
	
//	public function GetSuggestions($Search, $Storage = '', $PhoneOnly = false)
//	{
//		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		
//		return $this->GetContacts(0, 20, EContactSortField::Frequency, ESortOrder::ASC, $Search);
//	}	
	
//	public function DeleteSuggestion($ContactUUID)
//	{
//		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		
//		return true;
//	}	
	
//	public function UpdateSharedContacts($UUIDs)
//	{
//		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		return true;
//	}	
	
//	public function AddContactsFromFile($File)
//	{
//		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		return true;
//	}	
	/***** public functions might be called with web API *****/
	
	/***** private functions *****/
	private function prepareFilters($aRawFilters)
	{
		$aFilters = [];
		
		if (is_array($aRawFilters))
		{
			$iAndIndex = 1;
			$iOrIndex = 1;
			foreach ($aRawFilters as $aSubFilters)
			{
				if (is_array($aSubFilters))
				{
					foreach ($aSubFilters as $sKey => $a2ndSubFilters)
					{
						if (is_array($a2ndSubFilters))
						{
							$sNewKey = $sKey;
							if ($sKey === '$AND')
							{
								$sNewKey = $iAndIndex.'$AND';
								$iAndIndex++;
							}
							if ($sKey === '$OR')
							{
								$sNewKey = $iOrIndex.'$OR';
								$iOrIndex++;
							}
							$aFilters[$sNewKey] = $a2ndSubFilters;
						}
					}
				}
			}
		}
		
		return $aFilters;
	}
	
	public function onGetBodyStructureParts($aParts, &$aResultParts)
	{
		foreach ($aParts as $oPart)
		{
			if ($oPart instanceof \MailSo\Imap\BodyStructure && 
					($oPart->ContentType() === 'text/vcard' || $oPart->ContentType() === 'text/x-vcard'))
			{
				$aResultParts[] = $oPart;
			}
		}
	}
	
	public function onImportCsv($aArgs, &$mImportResult)
	{
		if ($aArgs['Format'] === 'csv')
		{
			$mImportResult['ParsedCount'] = 0;
			$mImportResult['ImportedCount'] = 0;
			
			$this->incClass('csv-formatter');
			$this->incClass('csv-parser');
			$this->incClass('csv-sync');

			if (class_exists('CApiContactsSyncCsv'))
			{
				$oSync = new CApiContactsSyncCsv();
				$oSync->Import($aArgs, $mImportResult);
			}
		}
	}
	
	public function onExtendMessageData($oAccount, &$oMessage, $aData)
	{
		$oApiCapa = /* @var CApiCapabilityManager */ $this->oApiCapabilityManager;
		$oApiFileCache = /* @var CApiFilecacheManager */ CApi::GetSystemManager('filecache');

		foreach ($aData as $aDataItem) {
			
			if ($aDataItem['Part'] instanceof \MailSo\Imap\BodyStructure && 
					($aDataItem['Part']->ContentType() === 'text/vcard' || 
					$aDataItem['Part']->ContentType() === 'text/x-vcard')) {
				$sData = $aDataItem['Data'];
				if (!empty($sData) && $oApiCapa->isContactsSupported($oAccount)) {
					
					$oContact = \CContact::createInstance();
					$oContact->InitFromVCardStr($oAccount->IdUser, $sData);

					$oContact->sUUID = '';

					$bContactExists = false;
					if (0 < strlen($oContact->ViewEmail))
					{
						$aLocalContacts = $this->GetContactsByEmails([$oContact->ViewEmail]);
						$oLocalContact = count($aLocalContacts) > 0 ? $aLocalContacts[0] : null;
						if ($oLocalContact)
						{
							$oContact->sUUID = $oLocalContact->sUUID;
							$bContactExists = true;
						}
					}

					$sTemptFile = md5($sData).'.vcf';
					if ($oApiFileCache && $oApiFileCache->put($oAccount, $sTemptFile, $sData)) {
						
						$oVcard = CApiMailVcard::createInstance();

						$oVcard->Uid = $oContact->sUUID;
						$oVcard->File = $sTemptFile;
						$oVcard->Exists = !!$bContactExists;
						$oVcard->Name = $oContact->FullName;
						$oVcard->Email = $oContact->ViewEmail;

						$oMessage->addExtend('VCARD', $oVcard);
					} else {
						
						CApi::Log('Can\'t save temp file "'.$sTemptFile.'"', ELogLevel::Error);
					}					
				}
			}
		}
	}	
	
    public function onGetMobileSyncInfo($aArgs, &$mResult)
	{
		$iUserId = \CApi::getAuthenticatedUserId();
		$oDavModule = \CApi::GetModuleDecorator('Dav');

		$sDavLogin = $oDavModule->GetLogin();
		$sDavServer = $oDavModule->GetServerUrl();

		$mResult['Dav']['Contacts'] = [
			[
				'Name' => $this->i18N('LABEL_PERSONAL_CONTACTS', $iUserId),
				'Url' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME
			],
			[
				'Name' => $this->i18N('LABEL_COLLECTED_ADDRESSES', $iUserId),
				'Url' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME
			],
			[
				'Name' => $this->i18N('LABEL_SHARED_ADDRESS_BOOK', $iUserId),
				'Url' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME
			],
			[
				'Name' => $this->i18N('LABEL_GLOBAL_ADDRESS_BOOK', $iUserId),
				'Url' => $sDavServer.'/gab'
			]
		];
		
		$mResult['Dav']['Contacts'] = array(
			'PersonalContactsUrl' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME,
			'CollectedAddressesUrl' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME,
			'SharedWithAllUrl' => $sDavServer.'/addressbooks/'.$sDavLogin.'/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME,
			'GlobalAddressBookUrl' => $sDavServer.'/gab'
		);
	}
	
	public function onBeforeDeleteEntity(&$aArgs, &$mResult)
	{
		if ($aArgs['Type'] === 'User')
		{
			$aGroups = $this->oApiContactsManager->getGroups($aArgs['Id']);
			if (count($aGroups) > 0)
			{
				$aGroupUUIDs = [];
				foreach ($aGroups as $oGroup)
				{
					$aGroupUUIDs[] = $oGroup->sUUID;
				}
				$this->oApiContactsManager->deleteGroups($aGroupUUIDs);
			}
		}
	}
	/***** private functions *****/
}
