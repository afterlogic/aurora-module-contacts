<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @package ContactsBase
 * @subpackage Storages
 */
class CApiContactsBaseCarddavStorage extends CApiContactsBaseStorage
{
	/**
	 * @var CDAVClient
	 */
	protected $Dav;

	/**
	 * @var api_Settings
	 */
	protected $Settings;

	/**
	 * @var bool
	 */
	protected $Connected;

	/**
	 * @var string
	 */
	protected $User;

	/**
	 * @var string
	 */
	protected $AddressBookHomeSet;

	/**
	 * @var string
	 */
	protected $TimeZone;

	/**
	 * @var string
	 */
	protected $DbPrefix;

	/**
	 * @var PDO
	 */
	protected $Pdo;

	/**
	 * @var CAccount
	 */
	protected $Account;

	/**
	 * @var $oApiUsersManager CApiUsersManager
	 */
	protected $ApiUsersManager;

	/**
	 * @var CApiDavManager
	 */
	protected $ApiDavManager;

	/**
	 * @var array
	 */
	protected $aAddressBooksCache;

	/**
	 * @var array
	 */
	protected $aGroupItemsCache;

	/**
	 * @var array
	 */
	protected $ContactsCache;

	/**
	 * @var array
	 */
	protected $GroupsCache;

	/**
	 * @param CApiGlobalManager &$oManager
	 */
	public function __construct(CApiGlobalManager &$oManager)
	{
		parent::__construct('carddav', $oManager);
		CApi::Inc('common.dav.client');

		$this->Dav = null;
		$this->Settings = CApi::GetSettings();
		$this->Pdo = CApi::GetPDO();
		$this->User = null;
		$this->Account = null;
		$this->Connected = false;
		$this->aAddressBooksCache = array();
		$this->aGroupItemsCache = array();
		$this->ContactsCache = array();
		$this->GroupsCache = array();

		$this->DbPrefix = $this->Settings->GetConf('DBPrefix');

		$this->ApiUsersManager = CApi::GetCoreManager('users');
		$this->ApiDavManager = CApi::Manager('dav');
	}

	/**
	 * @param CAccount $oAccount
	 */
	public function InitByAccount($oAccount)
	{
		$bResult = false;

		if (($oAccount != null && $this->Dav == null && ($this->User != $oAccount->Email)) ||
		    ($this->Account->Email != $oAccount->Email))
		{
			$this->Account = $oAccount;
			$this->User = $oAccount->Email;
			$this->TimeZone = $oAccount->getDefaultStrTimeZone();
			$this->aAddressBooksCache = array();
			$this->aGroupItemsCache = array();
			$this->ContactsCache = array();

			$this->Url = $this->ApiDavManager ? $this->ApiDavManager->getServerUrl() : '';

			$this->Dav = new CDAVClient($this->Url, $this->User, $oAccount->IncomingMailPassword);

			if ($this->Dav->Connect())
			{
				$this->Connected = true;
				$this->Principal = $this->Dav->GetCurrentPrincipal();
				$this->AddressBookHomeSet = $this->Dav->GetAddressBookHomeSet($this->Principal);
			}
		}
		if ($this->Account)
		{
			$bResult = true;
		}

		return $bResult;
	}

	/**
	}
	 * @param int $iUserId
	 */
	public function init($iUserId)
	{
		$iAccountId = $this->ApiUsersManager->getDefaultAccountId($iUserId);
		$oAccount = $this->ApiUsersManager->getAccountById($iAccountId);

		return $this->InitByAccount($oAccount);
	}

	/**
	 * @param int $iUserId
	 * @param string $sAddressBook
	 * @param string $sSearch
	 * @param string $sFirstCharacter = ''
	 * @param int $sGroupId
	 * @return bool|array
	 */
	protected function getItems($iUserId, $sAddressBook, $sSearch = '', $sFirstCharacter = '', $sGroupId = null)
	{
		$aResult = array();
		if ($this->init($iUserId))
		{
			if (!empty($sGroupId))
			{
				unset($this->ContactsCache[$sAddressBook]);
			}

			if (isset($this->ContactsCache[$sAddressBook]))
			{
				$aResult = $this->ContactsCache[$sAddressBook];
			}
			else
			{
				$aItems = $this->Dav->GetVcards($this->AddressBookHomeSet . $sAddressBook, $sSearch, $sGroupId);

				foreach ($aItems as $aItem)
				{
					$sItemId = $aItem['href'];
					$vCard = false;
					try
					{
						$vCard = \Sabre\VObject\Reader::read($aItem['data']);
					}
					catch(Exception $ex)
					{
						CApi::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
					}
					if ($vCard)
					{
						$sUid = '';
						if (isset($vCard->UID))
						{
							$sUid = $vCard->UID->value;
						}

						$sCategories = '';
						$aCategories = array();
						if (isset($vCard->CATEGORIES))
						{
							$sCategories = $vCard->CATEGORIES->value;
							$aCategories = explode(',', $vCard->CATEGORIES->value);
							foreach($aCategories as $sCategory)
							{
								$sCategory = trim($sCategory);
								if (!empty($sCategory))
								{
									$this->aGroupItemsCache[$sAddressBook][$sCategory][$sUid] = $sUid;
								}
							}
						}

						if	(empty($sGroupId) || (!empty($sGroupId) && in_array($sGroupId, $aCategories)))
						{
							$oContactItem = new CContactListItem();
							$oContactItem->InitBySabreCardDAVCard($vCard);
							$aResult[] = $oContactItem;
							unset($oContactItem);
						}
					}
					unset($vCard);
				}
				$this->ContactsCache[$sAddressBook] = $aResult;
			}
		}

		return $aResult;

	}

	/**
	 * @param int $iUserId
	 * @param string $sAddressBook
	 * @param string $sId
	 * @return bool | \Sabre\DAV\Card
	 */
	protected function getItem($iUserId, $sAddressBook, $sId)
	{
		$bResult = false;
		if (isset($this->aContactItemsCache[$sAddressBook][$sId]))
		{
			$bResult = $this->aContactItemsCache[$sAddressBook][$sId];
		}
		else
		{
			$aItem = $this->Dav->GetVcardByUid($sId, $this->AddressBookHomeSet . $sAddressBook);
			if (0 < count($aItem))
			{
				$bResult = $aItem[0];
			}
		}
		return $bResult;
	}

	public function ___qSortCallback($a, $b)
	{
		$sSortField = $GLOBALS['ItemsSortField'];
		$iSortOrder = $GLOBALS['ItemsSortOrder'];

		if ($a->{$sSortField} === $b->{$sSortField})
		{
			return 0;
		}
		else if (ESortOrder::ASC == $iSortOrder)
		{
			return ($a->{$sSortField} > $b->{$sSortField}) ? -1 : 1;
		}
		else
		{
			return ($a->{$sSortField} < $b->{$sSortField}) ? -1 : 1;
		}
	}

	/**
	 * @param array $aItems
	 * @param int $iSortField
	 * @param int $iSortOrder
	 */
	protected function sortItems(&$aItems, $iSortField, $iSortOrder)
	{
		$aMapSortField = array(
			EContactSortField::EMail => 'Email',
			EContactSortField::Name => 'Name',
			EContactSortField::Frequency => 'Frequency'
		);

		if (!isset($aMapSortField[$iSortField]))
		{
			return;
		}

		$GLOBALS['ItemsSortField'] = $aMapSortField[$iSortField];
		$GLOBALS['ItemsSortOrder'] = $iSortOrder;

		// Sort
		usort($aItems, array(&$this, '___qSortCallback'));

		unset($GLOBALS['ItemsSortField']);
		unset($GLOBALS['ItemsSortOrder']);
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @param string $sContactId
	 * @return bool|array
	 */
	protected function getGroupItemsWithoutOrder($iUserId, $sSearch = '', $sFirstCharacter = '', $sContactId = '')
	{
		$aResult = array();
		$this->init($iUserId);

		if (!empty($sContactId))
		{
			$oContact = $this->getContactById($iUserId, $sContactId);
			if ($oContact)
			{
				foreach ($oContact->GroupsIds as $sGroupId)
				{
					$oContactItem = new CContactListItem();
					$oContactItem->Id = $sGroupId;
					$oContactItem->Name = $sGroupId;
					$oContactItem->IsGroup = true;

					if ($sSearch == '' || stripos($oContactItem->Name, $sSearch) !== false)
					{
						$aResult[] = $oContactItem;
					}
					unset($oContactItem);
				}
			}
		}
		else
		{
			$sName = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
			if (!isset($this->aGroupItemsCache[$sName]))
			{
				$this->getItems($iUserId, $sName);
			}

			if (isset($this->aGroupItemsCache[$sName]))
			{
				$aItems = $this->aGroupItemsCache[$sName];
				foreach ($aItems as $sKey => $aIds)
				{
					$aContactsIds = array();
					foreach($aIds as $sContactsId)
					{
						$aContactsIds[] = $sContactsId;
					}
					$oContactItem = new CContactListItem();
					$oContactItem->Id = $sKey;
					$oContactItem->Name = $sKey;
					$oContactItem->IsGroup = true;

					if (empty($sContactId) || !empty($sContactId) && in_array($sContactId, $aContactsIds))
					{
						if ($sSearch == '' || stripos($oContactItem->Name, $sSearch) !== false)
						{
							$aResult[] = $oContactItem;
						}
					}
					unset($oContactItem);
				}
			}
		}
		return $aResult;
	}


	/**
	 * @param int $iUserId
	 * @param array $aContactsIds
	 * @param string $sAddressBook
	 * @return bool
	 */
	protected function deleteContactsByAddressBook($iUserId, $aContactsIds, $sAddressBook)
	{
		$this->init($iUserId);

		foreach($aContactsIds as $sContactId)
		{
			$aItem = $this->getItem($iUserId, $sAddressBook, $sContactId);

			if ($aItem)
			{
				$sUrl = $this->AddressBookHomeSet . $sAddressBook . '/' . $aItem['href'];
				$this->Dav->DeleteItem($sUrl);
			}
		}
		return true;
	}

	protected function searchContactItemsByEmail($sUserId, $sEmail, $sAddressBook)
	{
		$aResult = array();

		$aContactItems = $this->getItems($sUserId, $sAddressBook, $sEmail);
		foreach($aContactItems as $oContactItem)
		{
			$aResult[] = $oContactItem->Id;
		}

		return $aResult;
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mContactId
	 * @return CContact | false
	 */
	public function getContactById($iUserId, $mContactId)
	{
		$oContact = false;
		if($this->init($iUserId))
		{
			$oContactItem = $this->getItem($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME, $mContactId);
			if ($oContactItem)
			{
				$sVCardData = $oContactItem['data'];
				if ($sVCardData)
				{
					$oContact = new CContact();
					$oContact->InitFromVCardStr($iUserId, $sVCardData);
					$oContact->IdContact = $mContactId;
					$oContact->ETag = $oContactItem['etag'];
				}
			}
		}
		return $oContact;
	}

	/**
	 * @param int $iUserId
	 * @param string $sEmail
	 * @return CContact|bool
	 */
	public function getContactByEmail($iUserId, $sEmail)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sContactStrId
	 * @return CContact|bool
	 */
	public function getContactByStrId($iUserId, $sContactStrId)
	{
		return $this->getContactById($iUserId, $sContactStrId);
	}

	/**
	 * @param CContact $oContact
	 * @return array|bool
	 */
	public function getContactGroupsIds($oContact)
	{
		return $oContact->GroupsIds;
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mGroupId
	 * @return array|bool
	 */
	public function GetGroupContactsIds($iUserId, $mGroupId)
	{
		$oGroup = false;;
		if (!empty($mGroupId))
		{
			$oGroup = $this->getGroupById($iUserId, $mGroupId);
			if ($oGroup)
			{
				return $oGroup->ContactsIds;
			}
		}
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mGroupId
	 * @return CGroup
	 */
	public function getGroupById($iUserId, $mGroupId)
	{
		$bResult = false;

		if (!isset($this->GroupsCache[$mGroupId]))
		{
			if($this->init($iUserId))
			{
				$sAddressBook = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
				if (!isset($this->aGroupItemsCache[$sAddressBook]))
				{
					$this->getItems($iUserId, $sAddressBook);
				}

				if (isset($this->aGroupItemsCache[$sAddressBook][$mGroupId]))
				{
					$bResult = new CGroup();
					$bResult->IdUser = $iUserId;
					$bResult->IdGroup = $mGroupId;
					$bResult->IdGroupStr = $mGroupId;
					$bResult->Name =  $mGroupId;

					$aItems = $this->aGroupItemsCache[$sAddressBook][$mGroupId];
					$aContactsIds = array();
					foreach ($aItems as $sContactsId)
					{
						$aContactsIds[] = $sContactsId;
					}
					$bResult->ContactsIds = $aContactsIds;
				}
			}
		}

		if (isset($this->GroupsCache[$mGroupId]))
		{
			$bResult = $this->GroupsCache[$mGroupId];
		}

		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sGroupStrId
	 * @return CGroup
	 */
	public function getGroupByStrId($iUserId, $sGroupStrId)
	{
		return $this->getGroupById($iUserId, $sGroupStrId);
	}

	/**
	 * @param int $iUserId
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @return bool|array
	 */
	public function getContactItemsWithoutOrder($iUserId, $iOffset, $iRequestLimit)
	{
		$aResult = array();

		$sAddressBook = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
		$aContactItems = $this->getItems($iUserId, $sAddressBook);

		foreach ($aContactItems as $aItem)
		{
			$sItemId = $aItem['href'];
			$vCard = null;
			try
			{
				$vCard = \Sabre\VObject\Reader::read($aItem['data']);
			}
			catch(Exception $ex)
			{
				CApi::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
			}
			if (isset($vCard))
			{
				$oContactItem = new CContactListItem();
				$oContactItem->InitBySabreCardDAVCard($vCard);

				$aResult[] = $oContactItem;
				unset($oContactItem);
			}
			unset($vCard);
		}

		return array_slice($aResult, $iOffset, $iRequestLimit);
	}

	/**
	 * @param int $iUserId
	 * @param int $iSortField
	 * @param int $iSortOrder
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @param int $iGroupId
	 * @return bool|array
	 */
	public function getContactItems($iUserId, $iSortField, $iSortOrder, $iOffset, $iRequestLimit, $sSearch, $sFirstCharacter, $iGroupId, $iTenantId = null, $bAll = false)
	{
		$aResult = $this->getItems($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME, $sSearch, $sFirstCharacter, $iGroupId);
		$this->sortItems($aResult, $iSortField, $iSortOrder);

		return array_slice($aResult, $iOffset, $iRequestLimit);
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @param int $iGroupId
	 * @return int
	 */
	public function getContactItemsCount($iUserId, $sSearch, $sFirstCharacter, $iGroupId, $iTenantId = null, $bAll = false)
	{
		return count($this->getItems($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME, $sSearch, $sFirstCharacter, $iGroupId));
	}

	/**
	 * @param int $iUserId
	 * @param int $iSortField
	 * @param int $iSortOrder
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @param string $sContactId
	 * @return bool|array
	 */
	public function getGroupItems($iUserId, $iSortField, $iSortOrder, $iOffset, $iRequestLimit, $sSearch, $sFirstCharacter, $sContactId)
	{
		$aResult = $this->getGroupItemsWithoutOrder($iUserId, $sSearch, $sFirstCharacter, $sContactId);
		$this->sortItems($aResult, $iSortField, $iSortOrder);
		return array_slice($aResult, $iOffset, $iRequestLimit);
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @return int
	 */
	public function getGroupItemsCount($iUserId, $sSearch, $sFirstCharacter)
	{
		$iCount = count($this->getGroupItemsWithoutOrder($iUserId, $sSearch, $sFirstCharacter));
		return $iCount;
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param int $iRequestLimit
	 * @return bool|array
	 */
	public function GetSuggestContactItems($iUserId, $sSearch, $iRequestLimit)
	{
		$aResult = array();
		$this->init($iUserId);

		$sDefaultAB = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
		$sCollectedAB = \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME;

		$aCollectedContactItems = $this->Dav->GetVcards($this->AddressBookHomeSet . $sCollectedAB);
		$aDefaultContactItems = $this->Dav->GetVcards($this->AddressBookHomeSet . $sDefaultAB);

		$aContactItems = array_merge($aDefaultContactItems, $aCollectedContactItems);

		foreach ($aContactItems as $oItem)
		{
			$sItemId = $oItem['href'];
			$vCard = null;
			try
			{
				$vCard = \Sabre\VObject\Reader::read($oItem['data']);
			}
			catch(Exception $ex)
			{
				CApi::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
			}
			if (isset($vCard))
			{
				$oContactItem = new CContactListItem();
				$oContactItem->InitBySabreCardDAVCard($vCard);

				if (empty($sSearch) ||
					stripos($oContactItem->Name, $sSearch) !== false ||
					stripos($oContactItem->Email, $sSearch) !== false)
				{
					$aResult[] = $oContactItem;
				}
				unset($oContactItem);
			}

			unset($vCard);
		}

		$this->sortItems($aResult, EContactSortField::Frequency, ESortOrder::ASC);

		return array_slice($aResult, 0, $iRequestLimit);
	}

	/**
	 * @param CContact $oContact
	 * @return bool
	 */
	public function updateContact($oContact)
	{
		$bResult = false;
		$this->init($oContact->IdUser);
		$oContactItem = $this->getItem($oContact->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME, $oContact->IdContact);
		if ($oContactItem)
		{
			try
			{
				$vCard = \Sabre\VObject\Reader::read($oContactItem['data']);
				if ($vCard)
				{
					CApiContactsVCardHelper::UpdateVCardFromContact($oContact, $vCard);
					$this->Dav->UpdateItem($this->AddressBookHomeSet . \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME . '/' . $oContactItem['href'], $vCard->serialize(), $oContact->ETag);
					$bResult = true;
				}
				unset($vCard);
			}
			catch (\Sabre\DAV\Exception $oException)
			{
				throw new CApiBaseException(
					false !== strpos($oException->getMessage(), 'errorcode 412')
						? Errs::Sabre_PreconditionFailed : Errs::Sabre_Exception, $oException);
			}
		}

		return $bResult;
	}

	/**
	 * @param CGroup $oGroup
	 * @return bool
	 */
	public function updateGroup($oGroup)
	{
		$bResult = false;
		$this->init($oGroup->IdUser);


		$sGroupId = null;
		$sGroupName = $oGroup->Name;
		if (!empty($oGroup->IdGroup))
		{
			$sGroupId = $oGroup->IdGroup;
		}
		else
		{
			$sGroupId = $oGroup->Name;
		}

		if (!empty($sGroupId))
		{
			$sAddressBook = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;

			$aContactIds = $oGroup->ContactsIds;
			foreach ($aContactIds as $sContactId)
			{
				$oContact = $this->getItem($oGroup->IdUser, $sAddressBook, $sContactId);
				$vCard = \Sabre\VObject\Reader::read($oContact['data']);

				$sCategories = '';
				if (isset($vCard->CATEGORIES))
				{
					$sCategories = $vCard->CATEGORIES->value;
					$aCategories = explode(',', $vCard->CATEGORIES->value);
					$aResultCategories = array();
					foreach ($aCategories as $sCategory)
					{
						if ($sCategory === $sGroupId)
						{
							$aResultCategories[] = $sGroupName;
						}
						else
						{
							$aResultCategories[] = $sCategory;
						}
					}
					if (!in_array($sGroupId, $aResultCategories))
					{
						$aResultCategories[] = $sGroupName;
					}
					$sCategories = implode(',', array_unique($aResultCategories));
				}
				else
				{
					$vCard->add(new \Sabre\VObject\Property('CATEGORIES'));
					$sCategories = $sGroupName;
				}

				$vCard->CATEGORIES->value = $sCategories;
				$this->Dav->UpdateItem($this->AddressBookHomeSet . \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME . '/' . $oContact['href'], $vCard->serialize(), $oContact['etag']);
			}

			$aContactIds = $oGroup->DeletedContactsIds;
			foreach ($aContactIds as $sContactId)
			{
				$oContact = $this->getItem($oGroup->IdUser, $sAddressBook, $sContactId);

				$vCard = \Sabre\VObject\Reader::read($oContact['data']);

				$sCategories = '';
				if (isset($vCard->CATEGORIES))
				{
					$sCategories = $vCard->CATEGORIES->value;
					if (strpos($sCategories, $sGroupId) !== false)
					{
						$aCategories = explode(',', $vCard->CATEGORIES->value);
						$aResultCategories = array();
						foreach($aCategories as $sCategory)
						{
							if ($oGroup->IdGroup !== $sCategory)
							{
								$aResultCategories[] = $sCategory;
							}
						}
						$sCategories = implode(',', array_unique($aResultCategories));
					}
					if (empty($sCategories))
					{
						unset($vCard->CATEGORIES);
					}
					else
					{
						$vCard->CATEGORIES->value = $sCategories;
					}
					$this->Dav->UpdateItem($this->AddressBookHomeSet . \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME . '/' . $oContact['href'], $vCard->serialize(), $oContact['etag']);
				}
			}
			$bResult = true;
		}
		return $bResult;
	}

	/**
	 * @param CContact $oContact
	 * @return bool
	 */
	public function createContact($oContact)
	{
		$bResult = false;
		if (isset($oContact))
		{
			$this->init($oContact->IdUser);
			$sUUID = \Sabre\DAV\UUIDUtil::getUUID();
			if (empty($oContact->IdContact))
			{
				$oContact->IdContact = $sUUID;
			}

			$vCard = new \Sabre\VObject\Component('VCARD');
			CApiContactsVCardHelper::UpdateVCardFromContact($oContact, $vCard);

			$sUrl = $this->AddressBookHomeSet . \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME . '/' . $oContact->IdContact . '.vcf';
			$this->Dav->CreateItem($sUrl, $vCard->serialize());
			$bResult = true;
/*
			$oContact->initBeforeChange();
			$sEmail = $oContact->ViewEmail;
			$oAddressBook = $this->getAddressBook($oContact->IdUser, 'Collected');
			$aContactsIds = $this->searchContactItemsByEmail($oContact->IdUser, $sEmail, $oAddressBook);

			$this->deleteContactsByAddressBook($oContact->IdUser, $aContactsIds, $oAddressBook);
 */
		}

		return $bResult;
	}

	/**
	 * @param CGroup $oGroup
	 * @return bool
	 */
	public function createGroup($oGroup)
	{
		return $this->updateGroup($oGroup);
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactsIds
	 * @return bool
	 */
	public function deleteContacts($iUserId, $aContactsIds)
	{
		$this->init($iUserId);
		return $this->deleteContactsByAddressBook($iUserId, $aContactsIds, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
	}

	/**
	 * @param int $iUserId
	 * @param array $aGroupsIds
	 * @return bool
	 */
	public function deleteGroups($iUserId, $aGroupsIds)
	{
		$this->init($iUserId);

		$sName = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
		$this->getItems($iUserId, $sName);

		foreach($aGroupsIds as $sGroupsId)
		{
			if (isset($this->aGroupItemsCache[$sName][$sGroupsId]))
			{
				$aContactIds = $this->aGroupItemsCache[$sName][$sGroupsId];
				foreach ($aContactIds as $sContactId)
				{
					$aContact = $this->getItem($iUserId, $sName, $sContactId);
					$vCard = \Sabre\VObject\Reader::read($aContact['data']);

					$sCategories = '';
					if (isset($vCard->CATEGORIES))
					{
						$sCategories = $vCard->CATEGORIES->value;
						if (strpos($sCategories, $sGroupsId) !== false)
						{
							$aCategories = explode(',', $vCard->CATEGORIES->value);
							$aResultCategories = array();
							foreach($aCategories as $sCategory)
							{
								$sCategory = trim($sCategory);
								if ($sCategory !== $sGroupsId)
								{
									$aResultCategories[] = $sCategory;
								}
							}
							$sResultCategories = implode(',', $aResultCategories);
							if (empty($sResultCategories))
							{
								unset($vCard->CATEGORIES);
							}
							else
							{
								$vCard->CATEGORIES->value = $sResultCategories;
							}
							$sUrl = $this->AddressBookHomeSet . $sName . '/' . $aContact['href'];
							$this->Dav->UpdateItem($sUrl, $vCard->serialize(), $aContact['etag']);
							$this->aContactItemsCache[$sName][$sContactId] = $aContact;
						}
					}
				}
				unset($this->aGroupItemsCache[$sName][$sGroupsId]);
			}
		}
		return true;
	}

	/**
	 * @param int $iUserId
	 * @param string $sEmail
	 * @return bool
	 */
	public function updateSuggestTable($iUserId, $aEmails)
	{
		$bResult = false;
		$this->init($iUserId);

		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @return bool
	 */
	public function DeleteContactsExceptIds($iUserId, $aContactIds)
	{
		return true;
	}

	/**
	 * @param int $iUserId
	 * @param array $aGroupIds
	 * @return bool
	 */
	public function DeleteGroupsExceptIds($iUserId, $aGroupIds)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function flushContacts()
	{
		return true;

	}
}
