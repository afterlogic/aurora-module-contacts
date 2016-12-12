<?php

class ContactsModule extends AApiModule
{
	public $oApiContactsManager = null;
	
	protected $aSettingsMap = array(
		'ContactsPerPage' => array(20, 'int'),
		'ImportContactsLink' => array('', 'string'),
	);
	
	protected $aImportExportFormats = ['csv', 'vcf'];
	
	public function init() 
	{
		$this->incClass('contact');
		$this->incClass('group-contact');
		$this->incClass('group');
		$this->incClass('vcard-helper');
		$this->incClass('enum');

		$this->oApiContactsManager = $this->GetManager();
		
		$this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
		$this->subscribeEvent('AdminPanelWebclient::DeleteEntity::before', array($this, 'onBeforeDeleteEntity'));
		
		$this->setObjectMap('CUser', array(
				'ContactsPerPage' => array('int', $this->getConfig('ContactsPerPage', 20)),
			)
		);
	}
	
	/**
	 * Obtaines list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$aStorages = array();
		$this->broadcastEvent('GetStorage', $aStorages);
		
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
			'ImportExportFormats' => $this->aImportExportFormats,
		);
	}
	
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
	
	public function Export($Type, $Filters = [], $GroupUUID = '', $ContactUUIDs = [])
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aFilters = $this->prepareFilters($Filters);
		
		$sOutput = $this->oApiContactsManager->export($Type, $aFilters, $GroupUUID, $ContactUUIDs);
		
		if (false !== $sOutput)
		{
			header('Pragma: public');
			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename="export.' . $Type . '";');
			header('Content-Transfer-Encoding: binary');
		}
		
		echo $sOutput;
	}
	
	/**
	 * @return array
	 */
	public function GetGroups()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$iUserId = \CApi::getAuthenticatedUserId();
		
		return $this->oApiContactsManager->getGroups($iUserId);
	}
	
	/**
	 * 
	 * @param string $UUID
	 * @return \CGroup
	 */
	public function GetGroup($UUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->getGroup($UUID);
	}
	
	/**
	 * 
	 * @param string $UUID
	 * @return array
	 */
	public function GetGroupEvents($UUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return [];
	}	
	
	public function GetApiContactsManager()
	{
		return $this->oApiContactsManager;
	}
	
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
	
	/**
	 * 
	 * @param int $Offset
	 * @param int $Limit
	 * @param int $SortField
	 * @param int $SortOrder
	 * @param string $Search
	 * @param string $GroupUUID
	 * @param array $Filters
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
	 * 
	 * @param string $UUID
	 * @return \CContact
	 */
	public function GetContact($UUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->getContact($UUID);
	}

	/**
	 * @param array $Emails
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
	 * 
	 * @param string $Search
	 * @param string $Storage
	 * @param bool $PhoneOnly
	 * @return array
	 */
	public function GetSuggestions($Search, $Storage = '', $PhoneOnly = false)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->GetContacts(0, 20, EContactSortField::Frequency, ESortOrder::ASC, $Search);
	}	
	
	/**
	 * 
	 * @param string $ContactUUID
	 * @return bool
	 */
	public function DeleteSuggestion($ContactUUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return true;
	}	
	
	/**
	 * 
	 * @param array $Contact
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
		$oContact->IdUser = $oUser->iId;
		$oContact->IdTenant = $oUser->IdTenant;

		$oContact->populate($Contact);

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
	 * 
	 * @param array $Contact
	 * @return bool
	 */
	public function UpdateContact($Contact)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oContact = $this->oApiContactsManager->getContact($Contact['UUID']);
		$oContact->populate($Contact);
		
		return $this->oApiContactsManager->updateContact($oContact);
	}
	
	/**
	 * 
	 * @param array $UUIDs Array of strings
	 * @return bool
	 */
	public function DeleteContacts($UUIDs)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->deleteContacts($UUIDs);
	}	
	
	/**
	 * 
	 * @param array $UUIDs
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function UpdateSharedContacts($UUIDs)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		return true;
	}	
	
	/**
	 * 
	 * @param string $File
	 * @return array
	 */
	public function AddContactsFromFile($File)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		return true;
	}	
	
	/**
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
	 * @param array $Group
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
	 * 
	 * @param string $UUID
	 * @return bool
	 */
	public function DeleteGroup($UUID)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->deleteGroups([$UUID]);
	}
	
	/**
	 * 
	 * @param string $GroupUUID
	 * @param array $ContactUUIDs Array of strings
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
	 * 
	 * @param string $GroupUUID
	 * @param array $ContactUUIDs array of integers
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
	
	public function UploadContacts($UploadData, $Storage, $GroupUUID)
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

			if (in_array($sFileType, $this->aImportExportFormats))
			{
				$oApiFileCacheManager = \CApi::GetSystemManager('filecache');
				$sSavedName = 'import-post-' . md5($UploadData['name'] . $UploadData['tmp_name']);
				if ($oApiFileCacheManager->moveUploadedFile($oUser->sUUID, $sSavedName, $UploadData['tmp_name']))
				{
					$iParsedCount = 0;

					$iImportedCount = $this->oApiContactsManager->import(
						$oUser->iId,
						$oUser->IdTenant,
						$sFileType,
						$oApiFileCacheManager->generateFullFilePath($oUser->sUUID, $sSavedName),
						$iParsedCount,
						$Storage,
						$GroupUUID
					);

					if (false !== $iImportedCount && -1 !== $iImportedCount)
					{
						$aResponse['ImportedCount'] = $iImportedCount;
						$aResponse['ParsedCount'] = $iParsedCount;
					}
					else
					{
						$sError = 'unknown';
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
				throw new \System\Exceptions\AuroraApiException(\System\Notifications::IncorrectFileExtension);
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
	
    public function onGetMobileSyncInfo(&$aData)
	{
		$iUserId = \CApi::getAuthenticatedUserId();
		$oDavModule = \CApi::GetModuleDecorator('Dav');

		$sDavLogin = $oDavModule->GetLogin();
		$sDavServer = $oDavModule->GetServerUrl();

		$aData['Dav']['Contacts'] = [
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
		
		$aData['Dav']['Contacts'] = array(
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
}
