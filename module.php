<?php

class ContactsModule extends AApiModule
{
	public $oApiContactsManager = null;
	
	protected $aSettingsMap = array(
		'ContactsPerPage' => array(20, 'int'),
		'ImportContactsLink' => array('', 'string'),
	);
	
	public function init() 
	{
		$this->incClass('contact');
		$this->incClass('group-contact');
		$this->incClass('group');
		$this->incClass('vcard-helper');
		$this->incClass('enum');

		$this->oApiContactsManager = $this->GetManager('main');
		
		$this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
		$this->subscribeEvent('AdminPanelWebclient::DeleteEntity::before', array($this, 'onBeforeDeleteEntity'));
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
		
		return array(
			'ContactsPerPage' => $this->getConfig('ContactsPerPage', 20),
			'ImportContactsLink' => $this->getConfig('ImportContactsLink', ''),
			'Storages' => $aStorages,
			'EContactsPrimaryEmail' => (new \EContactsPrimaryEmail)->getMap(),
			'EContactsPrimaryPhone' => (new \EContactsPrimaryPhone)->getMap(),
			'EContactsPrimaryAddress' => (new \EContactsPrimaryAddress)->getMap(),
			'EContactSortField' => (new \EContactSortField)->getMap(),
		);
	}
	
	private function downloadContacts($sSyncType)
	{
		$oAccount = $this->getDefaultAccountFromParam();
		if ($this->oApiCapabilityManager->isContactsSupported($oAccount))
		{
			$sOutput = $this->oApiContactsManager->export($oAccount->IdUser, $sSyncType);
			if (false !== $sOutput)
			{
				header('Pragma: public');
				header('Content-Type: text/csv');
				header('Content-Disposition: attachment; filename="export.' . $sSyncType . '";');
				header('Content-Transfer-Encoding: binary');

				return $sOutput;
			}
		}
		return false;
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

		$aFilters = [];
		if (is_array($Filters))
		{
			$iAndIndex = 1;
			$iOrIndex = 1;
			foreach ($Filters as $aSubFilters)
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
		
		if (!empty($Search))
		{
			$aSearchFilters = [
				'FullName' => ['%'.$Search.'%', 'LIKE'],
				'PersonalEmail' => ['%'.$Search.'%', 'LIKE'],
				'BusinessEmail' => ['%'.$Search.'%', 'LIKE'],
				'OtherEmail' => ['%'.$Search.'%', 'LIKE'],
			];
			if (count($aFilters) > 1)
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
	
	public function DownloadContactsAsCSV()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->downloadContacts('csv');
	}
	
	public function DownloadContactsAsVCF()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->downloadContacts('vcf');
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
	
	public function UploadContacts()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getDefaultAccountFromParam();

		if (!$this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}
		
		$aFileData = $this->getParamValue('FileData', null);
		$sAdditionalData = $this->getParamValue('AdditionalData', '{}');
		$aAdditionalData = @json_decode($sAdditionalData, true);

		$sError = '';
		$aResponse = array(
			'ImportedCount' => 0,
			'ParsedCount' => 0
		);

		if (is_array($aFileData)) {
			
			$sFileType = strtolower(\api_Utils::GetFileExtension($aFileData['name']));
			$bIsCsvVcfExtension  = $sFileType === 'csv' || $sFileType === 'vcf';

			if ($bIsCsvVcfExtension) {
				
				$oApiFileCacheManager = \CApi::GetSystemManager('filecache');
				$sSavedName = 'import-post-' . md5($aFileData['name'] . $aFileData['tmp_name']);
				if ($oApiFileCacheManager->moveUploadedFile($oAccount, $sSavedName, $aFileData['tmp_name'])) {
						$iParsedCount = 0;

						$iImportedCount = $this->oApiContactsManager->import(
							$oAccount->IdUser,
							$sFileType,
							$oApiFileCacheManager->generateFullFilePath($oAccount, $sSavedName),
							$iParsedCount
						);

					if (false !== $iImportedCount && -1 !== $iImportedCount) {
						
						$aResponse['ImportedCount'] = $iImportedCount;
						$aResponse['ParsedCount'] = $iParsedCount;
					} else {
						
						$sError = 'unknown';
					}

					$oApiFileCacheManager->clear($oAccount, $sSavedName);
				} else {
					
					$sError = 'unknown';
				}
			} else {
				
				throw new \System\Exceptions\AuroraApiException(\System\Notifications::IncorrectFileExtension);
			}
		}
		else {
			
			$sError = 'unknown';
		}

		if (0 < strlen($sError)) {
			
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
			$aGroups = $this->oApiContactsManager->getGroups($aArgs['UUID']);
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
