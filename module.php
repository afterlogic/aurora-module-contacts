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
		$this->incClass('contact-list-item');
		$this->incClass('contact');
		$this->incClass('group-contact');
		$this->incClass('group');
		$this->incClass('vcard-helper');

		$this->oApiContactsManager = $this->GetManager('main');
		
		$this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
		$this->subscribeEvent('CreateAccount', array($this, 'onCreateAccountEvent'));
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
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
		
//		$sAuthToken = $this->getParamValue('AuthToken');
//		$iUserId = \CApi::getAuthenticatedUserId($sAuthToken);
//		$oAccount = $this->getDefaultAccountFromParam();

		$aList = false;
		//TODO use real user settings
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		$iUserId = \CApi::getAuthenticatedUserId();
		if ($iUserId > 0)
		{
			$aList = $this->oApiContactsManager->getGroupItems($iUserId);
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return $aList;
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
//		$oGroup = false;
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			$sGroupId = (string) $this->getParamValue('IdGroup', '');
//			$oGroup = $this->oApiContactsManager->getGroupById($oAccount->IdUser, $sGroupId);
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		return $oGroup;
	}
	
	/**
	 * 
	 * @param int $IdGroup
	 * @return array
	 */
	public function GetGroupEvents($IdGroup)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aEvents = array();
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			$sGroupId = (string) $this->getParamValue('IdGroup', '');
//			$aEvents = $this->oApiContactsManager->getGroupEvents($oAccount->IdUser, $sGroupId);
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}

		return $aEvents;
	}	
	
	/**
	 * 
	 * @param int $Offset
	 * @param int $Limit
	 * @param int $SortField
	 * @param int $SortOrder
	 * @param string $Search
	 * @param int $IdGroup
	 * @param int $Storage
	 * @return array
	 */
	public function GetContacts($Offset = 0, $Limit = 20, $SortField = EContactSortField::Name, $SortOrder = ESortOrder::ASC, $Search = '', $IdGroup = 0, $Storage = '', $Filters = array())
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);

		$aFilters = is_array($Filters) ? $Filters : [];
		
		if (!empty($Search))
		{
			if (count($aFilters) > 1)
			{
				$aFilters = [
					'$AND' => [
						'$OR' => $aFilters, 
						'$OR' => [
							'FullName' => ['%'.$Search.'%', 'LIKE'],
							'PersonalEmail' => ['%'.$Search.'%', 'LIKE'],
							'BusinessEmail' => ['%'.$Search.'%', 'LIKE'],
							'OtherEmail' => ['%'.$Search.'%', 'LIKE'],
						]
					]
				];
			}
			else
			{
				$aFilters = [
					'$OR' => [
						'FullName' => ['%'.$Search.'%', 'LIKE'],
						'PersonalEmail' => ['%'.$Search.'%', 'LIKE'],
						'BusinessEmail' => ['%'.$Search.'%', 'LIKE'],
						'OtherEmail' => ['%'.$Search.'%', 'LIKE'],
					]
				];
			}
		}
		elseif (count($aFilters) > 1)
		{
			$aFilters = ['$OR' => $aFilters];
		}

		$aContacts = $this->oApiContactsManager->getContactItems($SortField, $SortOrder, $Offset, $Limit, $aFilters, $IdGroup);
		
		$aList = array();
		if (is_array($aContacts))
		{
			foreach ($aContacts as $oContact)
			{
				$aList[] = array(
					'Id' => $oContact->iId,
					'Name' => $oContact->FullName,
					'Email' => $oContact->ViewEmail,
					'IsGroup' => false,
					'IsOrganization' => false,
					'ReadOnly' => false,
					'ItsMe' => false,
					'Storage' => $oContact->Storage,
				);
			}
		}

		return array(
			'ContactCount' => count($aList),
			'IdGroup' => $IdGroup,
			'Search' => $Search,
			'Storage' => $Storage,
			'List' => \CApiResponseManager::GetResponseObject($aList)
		);		
	}	
	
	/**
	 * 
	 * @param int $IdContact
	 * @param int $Storage
	 * @return \CContact
	 */
	public function GetContact($IdContact, $Storage)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->getContact($IdContact);
	}	

	/**
	 * 
	 * @param array $Emails Array of strings
	 * @param string $HandlerId
	 * @return array
	 */
	public function GetContactsByEmails($Emails, $HandlerId)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aResult = array();
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		$sEmails = (string) $this->getParamValue('Emails', '');
//		$aEmails = explode(',', $sEmails);
//
//		if (0 < count($aEmails))
//		{
//			$oApiContacts = $this->oApiContactsManager;
//			$oApiGlobalContacts = $this->GetManager('global');
//			
//			$bPab = $oApiContacts && $this->oApiCapabilityManager->isPersonalContactsSupported($oAccount);
//			$bGab = $oApiGlobalContacts && $this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true);
//
//			foreach ($aEmails as $sEmail)
//			{
//				$oContact = false;
//				$sEmail = trim($sEmail);
//				
//				if ($bPab)
//				{
//					$oContact = $oApiContacts->getContactByEmail($oAccount->IdUser, $sEmail);
//				}
//
//				if (!$oContact && $bGab)
//				{
//					$oContact = $oApiGlobalContacts->getContactByEmail($oAccount, $sEmail);
//				}
//
//				if ($oContact)
//				{
//					$aResult[$sEmail] = $oContact;
//				}
//			}
//		}

		return $aResult;
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
	 * @return array
	 */
//	public function GetContactByEmail()
//	{
//		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		
//		$oContact = false;
//		$oAccount = $this->getDefaultAccountFromParam();
//		
//		$sEmail = (string) $this->getParamValue('Email', '');
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount)) {
//			
//			$oContact = $this->oApiContactsManager->getContactByEmail($oAccount->IdUser, $sEmail);
//		}
//
//		if (!$oContact && $this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true)) {
//			
//			$oApiGContacts = $this->GetManager('global');
//			if ($oApiGContacts) {
//				
//				$oContact = $oApiGContacts->getContactByEmail($oAccount, $sEmail);
//			}
//		}
//
//		return $oContact;
//	}	
	
	/**
	 * 
	 * @param string $Search
	 * @param bool $GlobalOnly
	 * @param bool $PhoneOnly
	 * @return array
	 */
	public function GetSuggestions($Search, $GlobalOnly = false, $PhoneOnly = false)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->GetContacts(0, 20, 'PersonalEmail', ESortOrder::ASC, $Search);
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		$sSearch = (string) $this->getParamValue('Search', '');
//		$bGlobalOnly = '1' === (string) $this->getParamValue('GlobalOnly', '0');
//		$bPhoneOnly = '1' === (string) $this->getParamValue('PhoneOnly', '0');
//
//		$aList = array();
//		
//		$iSharedTenantId = null;
//		if ($this->oApiCapabilityManager->isSharedContactsSupported($oAccount) && !$bPhoneOnly)
//		{
//			$iSharedTenantId = $oAccount->IdTenant;
//		}
//
//		if ($this->oApiCapabilityManager->isContactsSupported($oAccount))
//		{
//			$aContacts = 	$this->oApiContactsManager->getSuggestItems($oAccount, $sSearch,
//					\CApi::GetConf('webmail.suggest-contacts-limit', 20), $bGlobalOnly, $bPhoneOnly, $iSharedTenantId);
//
//			if (is_array($aContacts))
//			{
//				$aList = $aContacts;
//			}
//		}
//
//		return array(
//			'Search' => $sSearch,
//			'List' => $aList
//		);
	}	
	
	/**
	 * 
	 * @param int $IdContact
	 * @param string $Storage
	 * @return bool
	 */
	public function DeleteSuggestion($IdContact, $Storage)
	{
		return true;
//		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		
//		$mResult = false;
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			$sContactId = (string) $this->getParamValue('IdContact', '');
//			$this->oApiContactsManager->resetContactFrequency($oAccount->IdUser, $sContactId);
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		return $mResult;
	}	
//	
//	public function UpdateSuggestTable()
//	{
//		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		
//		$oAccount = $this->getDefaultAccountFromParam();
//		$aEmails = $this->getParamValue('Emails', array());
//		$this->oApiContactsManager->updateSuggestTable($oAccount->IdUser, $aEmails);
//	}
	
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
		
		$bAllowPersonalContacts = true;
		if ($bAllowPersonalContacts)
		{
			$oContact = \CContact::createInstance();
			$oContact->IdUser = $oUser->iId;
			$oContact->IdTenant = $oUser->IdTenant;

			$oContact->populate($Contact);

			$this->oApiContactsManager->createContact($oContact);
			return $oContact ? array(
				'IdContact' => $oContact->iId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
		}

		return false;
	}	
	
	/**
	 * @return array
	 */
	public function UpdateContact($Contact)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oContact = $this->oApiContactsManager->getContact($Contact['IdContact']);
		$oContact->populate($Contact);
		if (!$this->oApiContactsManager->updateContact($oContact, false))
		{
			return false;
		}
		return true;
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		$bGlobal = '1' === $this->getParamValue('Global', '0');
//		$sContactId = $this->getParamValue('IdContact', '');
//
//		$bSharedToAll = '1' === $this->getParamValue('SharedToAll', '0');
//		$iTenantId = $bSharedToAll ? $oAccount->IdTenant : null;
//
//		if ($bGlobal && $this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true))
//		{
//			$oApiContacts = $this->GetManager('global');
//		}
//		else if (!$bGlobal && $this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			$oApiContacts = $this->oApiContactsManager;
//		}
//
//		if ($oApiContacts)
//		{
//			$oContact = $oApiContacts->getContactById($bGlobal ? $oAccount : $oAccount->IdUser, $sContactId, false, $iTenantId);
//			if ($oContact)
//			{
//				$this->populateContactObject($oContact);
//
//				if ($oApiContacts->updateContact($oContact))
//				{
//					return true;
//				}
//				else
//				{
//					switch ($oApiContacts->getLastErrorCode())
//					{
//						case \Errs::Sabre_PreconditionFailed:
//							throw new \System\Exceptions\AuroraApiException(
//								\System\Notifications::ContactDataHasBeenModifiedByAnotherApplication);
//					}
//				}
//			}
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
	}
	
	/**
	 * 
	 * @param array $ContactIds Array of string
	 * @param string $Storage
	 * @return bool
	 */
	public function DeleteContacts($ContactIds, $Storage)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		return $this->oApiContactsManager->deleteContacts($ContactIds);
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			$aContactIds = explode(',', $this->getParamValue('ContactIds', ''));
//			$aContactIds = array_map('trim', $aContactIds);
//			
//			$bSharedToAll = '1' === (string) $this->getParamValue('SharedToAll', '0');
//			$iTenantId = $bSharedToAll ? $oAccount->IdTenant : null;
//
//			return $this->oApiContactsManager->deleteContacts($oAccount->IdUser, $aContactIds, $iTenantId);
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		return false;
	}	
	
	/**
	 * 
	 * @param int $ContactIds
	 * @param string $Storage
	 * @return bool
	 * @throws \System\Exceptions\AuroraApiException
	 */
	public function UpdateShared($ContactIds, $Storage)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		return true;
//		$oAccount = $this->getDefaultAccountFromParam();
//		
//		$aContactIds = explode(',', $this->getParamValue('ContactIds', ''));
//		$aContactIds = array_map('trim', $aContactIds);
//		
//		$bSharedToAll = '1' === $this->getParamValue('SharedToAll', '0');
//		$iTenantId = $bSharedToAll ? $oAccount->IdTenant : null;
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			$oApiContacts = $this->oApiContactsManager;
//		}
//
//		if ($oApiContacts && $this->oApiCapabilityManager->isSharedContactsSupported($oAccount))
//		{
//			foreach ($aContactIds as $sContactId)
//			{
//				$oContact = $oApiContacts->getContactById($oAccount->IdUser, $sContactId, false, $iTenantId);
//				if ($oContact)
//				{
//					if ($oContact->SharedToAll)
//					{
//						$oApiContacts->updateContactUserId($oContact, $oAccount->IdUser);
//					}
//
//					$oContact->SharedToAll = !$oContact->SharedToAll;
//					$oContact->IdUser = $oAccount->IdUser;
//					$oContact->IdTenant = $oAccount->IdTenant;
//
//					if (!$oApiContacts->updateContact($oContact))
//					{
//						switch ($oApiContacts->getLastErrorCode())
//						{
//							case \Errs::Sabre_PreconditionFailed:
//								throw new \System\Exceptions\AuroraApiException(
//									\System\Notifications::ContactDataHasBeenModifiedByAnotherApplication);
//						}
//					}
//				}
//			}
//			
//			return true;
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		return false;
	}	
	
	/**
	 * @todo: waiting for mail module
	 * @param string $File
	 * @return array
	 */
//	public function AddContactsFromFile($File)
//	{
//		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
//		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		$mResult = false;
//
//		if (!$this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		$sTempFile = (string) $this->getParamValue('File', '');
//		if (empty($sTempFile))
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::InvalidInputParameter);
//		}
//
//		$oApiFileCache = /* @var $oApiFileCache \CApiFilecacheManager */ \CApi::GetSystemManager('filecache');
//		$sData = $oApiFileCache->get($oAccount, $sTempFile);
//		if (!empty($sData))
//		{
//			$oContact = \CContact::createInstance();
//			$oContact->InitFromVCardStr($oAccount->IdUser, $sData);
//
//			if ($this->oApiContactsManager->createContact($oContact))
//			{
//				$mResult = array(
//					'Uid' => $oContact->IdContact
//				);
//			}
//		}
//
//		return $mResult;
//	}	
	
	/**
	 * @return array
	 */
	public function CreateGroup($Group)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
			$oGroup = \CGroup::createInstance();
			$oGroup->IdUser = \CApi::getAuthenticatedUserId();
			
			$oGroup->populate($Group);
			
			$this->oApiContactsManager->createGroup($oGroup);
			return $oGroup ? array(
				'IdGroup' => $oGroup->iId
			) : false;
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		return false;
	}	
	
	/**
	 * @return array
	 */
	public function UpdateGroup($Group)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		$sGroupId = $this->getParamValue('IdGroup', '');
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
			$oGroup = $this->oApiContactsManager->getGroup($Group['IdGroup']);
			if ($oGroup)
			{
				$oGroup->populate($Group);
				return $this->oApiContactsManager->updateGroup($oGroup);
//				if ($this->oApiContactsManager->updateGroup($oGroup))
//				{
//					return true;
//				}
//				else
//				{
//					switch ($this->oApiContactsManager->getLastErrorCode())
//					{
//						case \Errs::Sabre_PreconditionFailed:
//							throw new \System\Exceptions\AuroraApiException(
//								\System\Notifications::ContactDataHasBeenModifiedByAnotherApplication);
//					}
//				}
			}
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(
//				\System\Notifications::ContactsNotAllowed);
//		}

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
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			$sGroupId = $this->getParamValue('IdGroup', '');
//
//			return $this->oApiContactsManager->deleteGroup($oAccount->IdUser, $sGroupId);
			return $this->oApiContactsManager->deleteGroup($IdGroup);
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		return false;
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
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
//		{
//			$sGroupId = (string) $this->getParamValue('IdGroup', '');
//
//			$aContactIds = $this->getParamValue('ContactIds', null);
//			if (!is_array($aContactIds))
//			{
//				return false;
//			}
//
//			$oGroup = $this->oApiContactsManager->getGroupById($oAccount->IdUser, $sGroupId);
//			if ($oGroup)
//			{
//				$aLocalContacts = array();
//				$aGlobalContacts = array();
//				
//				foreach ($aContactIds as $aItem)
//				{
//					if (is_array($aItem) && 2 === count($aItem))
//					{
//						if ('1' === $aItem[1])
//						{
//							$aGlobalContacts[] = $aItem[0];
//						}
//						else
//						{
//							$aLocalContacts[] = $aItem[0];
//						}
//					}
//				}
//
//				$bRes1 = true;
//				if (0 < count($aGlobalContacts))
//				{
//					$bRes1 = false;
//					if (!$this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true))
//					{
//						throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//					}
//
//					$bRes1 = $this->oApiContactsManager->addGlobalContactsToGroup($oAccount, $oGroup, $aGlobalContacts);
//				}
//
//				$bRes2 = true;
//				if (0 < count($aLocalContacts))
//				{
//					$bRes2 = $this->oApiContactsManager->addContactsToGroup($oGroup, $aLocalContacts);
//				}
//
//				return $bRes1 && $bRes2;
//			}
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		return false;
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
		
//		$oAccount = $this->getDefaultAccountFromParam();
//
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount) ||
//			$this->oApiCapabilityManager->isGlobalContactsSupported($oAccount, true))
//		{
//			$sGroupId = (string) $this->getParamValue('IdGroup', '');
//
//			$aContactIds = explode(',', $this->getParamValue('ContactIds', ''));
//			$aContactIds = array_map('trim', $aContactIds);
//
//			$oGroup = $this->oApiContactsManager->getGroupById($oAccount->IdUser, $sGroupId);
//			if ($oGroup)
//			{
//				return $this->oApiContactsManager->removeContactsFromGroup($oGroup, $aContactIds);
//			}
//
//			return false;
//		}
//		else
//		{
//			throw new \System\Exceptions\AuroraApiException(\System\Notifications::ContactsNotAllowed);
//		}
//
//		return false;
	}	
	
	public function SynchronizeExternalContacts()
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oAccount = $this->getParamValue('Account', null);
		if ($oAccount)
		{
			return $this->oApiContactsManager->SynchronizeExternalContacts($oAccount);
		}
		
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
					$oContact->initBeforeChange();

					$oContact->IdContact = 0;

					$bContactExists = false;
					if (0 < strlen($oContact->ViewEmail))
					{
						$oLocalContact = $this->oApiContactsManager->getContactByEmail($oAccount->IdUser, $oContact->ViewEmail);
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
	
	public function onCreateAccountEvent($oAccount)
	{
		if ($oAccount instanceof \CAccount)
		{
			$oContact = $this->oApiContactsManager->createContactObject();
			$oContact->BusinessEmail = $oAccount->Email;
			$oContact->PrimaryEmail = EContactsPrimaryEmail::Business;
			$oContact->FullName = $oAccount->FriendlyName;
			$oContact->Storage = 'global';

			$oContact->IdTypeLink = $oAccount->IdUser;
			$oContact->IdTenant = $oAccount->Domain ? $oAccount->Domain->IdTenant : 0;

			$this->oApiContactsManager->createContact($oContact);
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
	
	
}