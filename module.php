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
	 * @param int $IdGroup
	 * @return \CGroup
	 */
	public function GetGroup($IdGroup)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->getGroup($IdGroup);
	}
	
	/**
	 * 
	 * @param int $IdGroup
	 * @return array
	 */
	public function GetGroupEvents($IdGroup)
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
	 * @param int $IdGroup
	 * @param array $Filters
	 * @return array
	 */
	public function GetContacts($Offset = 0, $Limit = 20, $SortField = EContactSortField::Name, $SortOrder = ESortOrder::ASC, $Search = '', $IdGroup = 0, $Filters = array())
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

		$iCount = $this->oApiContactsManager->getContactsCount($aFilters, $IdGroup);
		$aContacts = $this->oApiContactsManager->getContacts($SortField, $SortOrder, $Offset, $Limit, $aFilters, $IdGroup);
		
		$aList = array();
		if (is_array($aContacts))
		{
			foreach ($aContacts as $oContact)
			{
				$aList[] = array(
					'Id' => $oContact->iId,
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
	 * @param int $IdContact
	 * @return \CContact
	 */
	public function GetContact($IdContact)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->getContact($IdContact);
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
	 * @param int $IdContact
	 * @return bool
	 */
	public function DeleteSuggestion($IdContact)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return true;
	}	
	
	/**
	 * 
	 * @param array $Contact
	 * @return boolean
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

		$this->oApiContactsManager->createContact($oContact);
		return $oContact ? array(
			'IdContact' => $oContact->iId
		) : false;
	}	
	
	/**
	 * 
	 * @param array $Contact
	 * @return bool
	 */
	public function UpdateContact($Contact)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oContact = $this->oApiContactsManager->getContact($Contact['IdContact']);
		$oContact->populate($Contact);
		
		return $this->oApiContactsManager->updateContact($oContact);
	}
	
	/**
	 * 
	 * @param array $ContactIds Array of string
	 * @return bool
	 */
	public function DeleteContacts($ContactIds)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->deleteContacts($ContactIds);
	}	
	
	/**
	 * 
	 * @param array $ContactIds
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function UpdateShared($ContactIds)
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
		return $oGroup ? array(
			'IdGroup' => $oGroup->iId
		) : false;
	}	
	
	/**
	 * 
	 * @param array $Group
	 * @return boolean
	 */
	public function UpdateGroup($Group)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oGroup = $this->oApiContactsManager->getGroup($Group['IdGroup']);
		if ($oGroup)
		{
			$oGroup->populate($Group);
			return $this->oApiContactsManager->updateGroup($oGroup);
		}

		return false;
	}	
	
	/**
	 * 
	 * @param int $IdGroup
	 * @return bool
	 */
	public function DeleteGroup($IdGroup)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->deleteGroups([$IdGroup]);
	}
	
	/**
	 * 
	 * @param int $IdGroup
	 * @param array $ContactIds Array of integers
	 * @return boolean
	 */
	public function AddContactsToGroup($IdGroup, $ContactIds)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (is_array($ContactIds) && !empty($ContactIds))
		{
			return $this->oApiContactsManager->addContactsToGroup((int) $IdGroup, $ContactIds);
		}
		
		return true;
	}
	
	/**
	 * 
	 * @param int $IdGroup
	 * @param array $ContactIds array of integers
	 * @return boolean
	 */
	public function RemoveContactsFromGroup($IdGroup, $ContactIds)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if (is_array($ContactIds) && !empty($ContactIds))
		{
			return $this->oApiContactsManager->removeContactsFromGroup($IdGroup, $ContactIds);
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

					$oContact->IdContact = 0;

					$bContactExists = false;
					if (0 < strlen($oContact->ViewEmail))
					{
						$aLocalContacts = $this->GetContactsByEmails([$oContact->ViewEmail]);
						$oLocalContact = count($aLocalContacts) > 0 ? $aLocalContacts[0] : null;
						if ($oLocalContact)
						{
							$oContact->IdContact = $oLocalContact->IdContact;
							$bContactExists = true;
						}
					}

					$sTemptFile = md5($sData).'.vcf';
					if ($oApiFileCache && $oApiFileCache->put($oAccount, $sTemptFile, $sData)) {
						
						$oVcard = CApiMailVcard::createInstance();

						$oVcard->Uid = $oContact->IdContact;
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
				$aGroupIds = [];
				foreach ($aGroups as $oGroup)
				{
					$aGroupIds[] = $oGroup->iId;
				}
				$this->oApiContactsManager->deleteGroups($aGroupIds);
			}
		}
	}
	
}