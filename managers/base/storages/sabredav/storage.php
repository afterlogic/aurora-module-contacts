<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * @package ContactsBase
 * @subpackage Storages
 */
class CApiContactsBaseSabredavStorage extends CApiContactsBaseStorage
{
	/**
	 * @var string
	 */
	public $Principal;

	/**
	 * @var CAccount
	 */
//	protected $Account;

	/**
	 * @var $oApiUsersManager CApiUsersManager
	 */
	protected $ApiUsersManager;

	protected $aAddressBooksCache;
	protected $aContactItemsCache;
	protected $aGroupItemsCache;
	protected $ContactsCache;
//	protected $AccountsCache;

	/**
	 * @param CApiGlobalManager &$oManager
	 */
	public function __construct(AApiManager &$oManager)
	{
		parent::__construct('sabredav', $oManager);

//		$this->Account = null;

		$this->aAddressBooksCache = array();
		$this->aContactItemsCache = array();
		$this->aGroupItemsCache = array();

		$this->ContactsCache = array();
		$this->GroupsCache = array();
//		$this->AccountsCache = array();

		$this->ApiUsersManager = CApi::GetSystemManager('users');
	}

	/**
	 * @param CAccount $oAccount
	 */
	public function InitByAccount()
	{
		$bResult = false;
		$oUser = \CApi::getAuthenticatedUser();
//		if ($oAccount && (!$this->Account || $this->Account->Email !== $oAccount->Email))
		if ($oUser)
		{
//			$this->Account = $oAccount;
			$this->aAddressBooksCache = array();
			$this->aContactItemsCache = array();
			$this->aGroupItemsCache = array();

			$this->ContactsCache = array();
			$this->GroupsCache = array();

//			\Afterlogic\DAV\Server::getInstance()->setAccount($oAccount);
//			$aPrincipalProperties = \Afterlogic\DAV\Backend::Principal()->getPrincipalByPath(\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $oAccount->Email);
			$aPrincipalProperties = \Afterlogic\DAV\Backend::Principal()->getPrincipalByPath(\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $oUser->sUUID);
			if ($aPrincipalProperties)
			{
				if (isset($aPrincipalProperties['uri']))
				{
					$this->Principal = $aPrincipalProperties['uri'];
				}
			}
			$bResult = true;
		}

//		if ($this->Account)
//		{
//			$bResult = true;
//		}

		return $bResult;
	}

//	protected function GetDefaultAccountByUserId($iUserId)
//	{
//		if (!isset($this->AccountsCache[$iUserId]))
//		{
//			$iAccountId = $this->ApiUsersManager->getDefaultAccountId($iUserId);
//			$oAccount = $this->ApiUsersManager->getAccountById($iAccountId);
//			$this->AccountsCache[$iUserId] = $oAccount;
//		}
//
//		return $this->AccountsCache[$iUserId];
//	}


	/**
	}
	 * @param int $iUserId
	 */
	public function init($iUserId)
	{
//		$oAccount = $this->GetDefaultAccountByUserId($iUserId);
		return $this->InitByAccount();
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mContactId
	 * @param string $sAddressBookName
	 * @return CContact | false
	 */
	public function getContactById($iUserId, $mContactId, $sAddressBookName = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME)
	{
		$oContact = false;
		if($this->init($iUserId))
		{
			$oAddressBook = $this->getAddressBook($iUserId, $sAddressBookName);
			$oContactItem = $this->geItem($iUserId, $oAddressBook, $mContactId);
			if ($oContactItem)
			{
				$sVCardData = $oContactItem->get();
				if ($sVCardData)
				{
					$oContact = new CContact();
					$oContact->InitFromVCardStr($iUserId, $sVCardData);
					$oContact->IdContact = $mContactId;
					$oContact->ETag = trim($oContactItem->getETag(), '"');
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
	 * @param int $iSharedTenantId = null
	 * @return CContact|bool
	 */
	public function getContactByStrId($iUserId, $sContactStrId, $iSharedTenantId = null)
	{
		return $this->getContactById($iUserId, $sContactStrId);
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sContactStrId
	 * @return CContact
	 */
	public function GetSuggestContactByEmail($iUserId, $sContactStrId)
	{
		return $this->getContactByEmail($iUserId, $sContactStrId);
	}	

	/**
	 * @param CContact $oContact
	 * @return array|bool
	 */
	public function getContactGroupIds($oContact)
	{
		return $oContact->GroupIds;
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
				$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
				if ($oAddressBook)
				{
					if (!isset($this->aGroupItemsCache[$oAddressBook->getName()]))
					{
						$this->getItems($iUserId, $oAddressBook);
					}

					if (isset($this->aGroupItemsCache[$oAddressBook->getName()][$mGroupId]))
					{
						$bResult = new CGroup();
						$bResult->IdUser = $iUserId;
						$bResult->IdGroup = $mGroupId;
						$bResult->IdGroupStr = $mGroupId;
						$bResult->Name =  $mGroupId;
					}
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
	 * @param mixed $iUserId
	 * @param string $sName
	 * @return bool|array
	 */
	protected function getAddressBook($iUserId, $sName)
	{
		$oAddressBook = false;
		if ($this->init($iUserId))
		{
			if (!isset($this->aAddressBooksCache[$iUserId][$sName]))
			{
				$oUserAddressBooks = new \Afterlogic\DAV\CardDAV\AddressBookHome(
					\Afterlogic\DAV\Backend::Carddav(), $this->Principal);

				if ($oUserAddressBooks->childExists($sName))
				{
					$this->aAddressBooksCache[$iUserId][$sName] = $oUserAddressBooks->getChild($sName);
				}
			}

			if (isset($this->aAddressBooksCache[$iUserId][$sName]))
			{
				$oAddressBook = $this->aAddressBooksCache[$iUserId][$sName];
			}
		}

		return $oAddressBook;
	}

	/**
	 * @param int $iUserId
	 * @param \Afterlogic\DAV\CardDAV\AddressBook $oAddressBook
	 * @return bool|array
	 */
	protected function getObjectItems($iUserId, $oAddressBook)
	{
		$mResult = false;
		$sName = null;
		if ($oAddressBook)
		{
			$sName = $oAddressBook->getName();
			if (!isset($this->aContactItemsCache[$sName]))
			{
				$this->init($iUserId);

				$this->aContactItemsCache[$sName] = array();
				foreach ($oAddressBook->getChildren() as $oChild)
				{
					$this->aContactItemsCache[$sName][$oChild->getName()] = $oChild;
				}
			}

			$mResult = $this->aContactItemsCache[$sName];
		}
		return $mResult;
	}

	/**
	 * @param int $iUserId
	 * @param \Sabre\CardDAV\AddressBook $oAddressBook
	 * @param string $sId
	 * @return bool | \Sabre\DAV\Card
	 */
	protected function geItem($iUserId, $oAddressBook, $sId)
	{
		$bResult = false;
		$sName = null;
		if ($oAddressBook)
		{
			$sName = $oAddressBook->getName();
			if (isset($this->aContactItemsCache[$sName][$sId]))
			{
				$bResult = $this->aContactItemsCache[$sName][$sId];
			}
			else
			{
				if ($oAddressBook->childExists($sId))
				{
					$bResult = $oAddressBook->getChild($sId);
				}
			}
		}
		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param \Afterlogic\DAV\CardDAV\AddressBook
	 * @param string $sSearch
	 * @param string $sFirstCharacter = ''
	 * @param int $sGroupId
	 * @return bool|array
	 */
	protected function getItems($iUserId, $oAddressBook, $sSearch = '', $sFirstCharacter = '', $sGroupId = null)
	{
		$aResult = array();
		$sName = null;
		if ($this->init($iUserId) && $oAddressBook)
		{

			$sName = $oAddressBook->getName();

			if (!empty($sGroupId))
			{
				unset($this->ContactsCache[$sName]);
			}

			if (isset($this->ContactsCache[$sName]))
			{
				$aResult = $this->ContactsCache[$sName];
			}
			else
			{
				$aItems = $this->getObjectItems($iUserId, $oAddressBook);

				foreach ($aItems as $oItem)
				{
					$sItemId = $oItem->getName();
					$oVCard = false;
					try
					{
						$oVCard = \Sabre\VObject\Reader::read($oItem->get());
					}
					catch(Exception $ex)
					{
						CApi::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
					}
					if ($oVCard)
					{
						$sFullName = $sFirstName = $sLastName = $sTitle = $sNickName = '';
						if (isset($oVCard->FN))
						{
							$sFullName = (string)$oVCard->FN;
						}
						if (isset($oVCard->N))
						{
							$aNames = $oVCard->N->getParts();
							if (!empty($aNames[0]))
							{
								$sLastName = $aNames[0];
							}
							if (!empty($aNames[1]))
							{
								$sFirstName = $aNames[1];
							}
							if (!empty($aNames[3]))
							{
								$sTitle = $aNames[3];
							}
						}
						if (isset($oVCard->NICKNAME))
						{
							$sNickName = (string)$oVCard->NICKNAME;
						}

						$bFindEmail = false;
						if (isset($oVCard->EMAIL))
						{
							foreach($oVCard->EMAIL as $oEmail)
							{
								if (stripos((string)$oEmail, $sSearch) !== false)
								{
									$bFindEmail = true;
									break;
								}
							}
						}

						$sCategories = '';
						if (isset($oVCard->CATEGORIES))
						{
							$sCategories = (string)$oVCard->CATEGORIES;
							$aCategories = explode(',', (string)$oVCard->CATEGORIES);
							foreach($aCategories as $sCategory)
							{
								$sCategory = trim($sCategory);
								if (!empty($sCategory))
								{
									$this->aGroupItemsCache[$sName][$sCategory][$sItemId] = $sItemId;
								}
							}
						}

						if (!empty($sItemId) && (empty($sSearch) || stripos($sFullName, $sSearch) !== false ||
							stripos($sFirstName, $sSearch) !== false ||
							stripos($sLastName, $sSearch) !== false ||
							stripos($sNickName, $sSearch) !== false ||
							stripos($sTitle, $sSearch) !== false || $bFindEmail) &&
							(empty($sGroupId) || (!empty($sGroupId) && strpos($sCategories, $sGroupId) !== false)))
						{
							$oContactItem = new CContactListItem();
							$oContactItem->InitBySabreCardDAVCard($oVCard);
							$oContactItem->Id = $sItemId;
							$oContactItem->ETag = $oItem->getETag();
							$aResult[] = $oContactItem;
							unset($oContactItem);
						}
					}
					unset($oVCard);
				}
				$this->ContactsCache[$sName] = $aResult;
			}
		}

		return $aResult;
	}

	/**
	 * @param int $iUserId
	 * @param \Afterlogic\DAV\CardDAV\AddressBook
	 * @return bool|array
	 */
	protected function initGroupItems($iUserId, $oAddressBook)
	{
		if ($this->init($iUserId))
		{
			$aItems = $this->getObjectItems($iUserId, $oAddressBook);

			foreach ($aItems as $oItem)
			{
				$sItemId = $oItem->getName();
				$oVCard = false;
				try
				{
					$oVCard = \Sabre\VObject\Reader::read($oItem->get());
				}
				catch(Exception $ex)
				{
					CApi::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
				}
				if ($oVCard)
				{
					if (isset($oVCard->CATEGORIES))
					{
						$aCategories = $oVCard->CATEGORIES->getParts();
						foreach($aCategories as $sCategory)
						{
							$sCategory = trim($sCategory);
							if (!empty($sCategory))
							{
								$this->aGroupItemsCache[$oAddressBook->getName()][$sCategory][$sItemId] = $sItemId;
							}
						}
					}
				}
				unset($oVCard);
			}
		}
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
			EContactSortField::Email => 'Email',
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
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @return bool|array
	 */
	public function getContactItemsWithoutOrder($iUserId, $iOffset, $iRequestLimit)
	{
		$aResult = array();

		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$aContactItems = $this->getObjectItems($iUserId, $oAddressBook);

		foreach ($aContactItems as $oItem)
		{
			$sItemId = $oItem->getName();
			$oVCard = null;
			try
			{
				$oVCard = \Sabre\VObject\Reader::read($oItem->get());
			}
			catch(Exception $ex)
			{
				CApi::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
			}
			if (isset($oVCard))
			{
				$oContactItem = new CContactListItem();
				$oContactItem->InitBySabreCardDAVCard($oVCard);
				$oContactItem->Id = $oItem->getName();

				$aResult[] = $oContactItem;
				unset($oContactItem);
			}
			unset($oVCard);
		}

		if ($iOffset < 0 &&  $iRequestLimit < 0)
		{
			return $aResult;
		}
		else
		{
			return array_slice($aResult, $iOffset, $iRequestLimit);
		}
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
	 * @param int $iTenantId = null
	 * @param bool $bAll = false
	 * @return bool|array
	 */
	public function getContactItems($iUserId, $iSortField, $iSortOrder, $iOffset, $iRequestLimit, $sSearch, $sFirstCharacter, $iGroupId, $iTenantId = null, $bAll = false)
	{
		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$aResult = $this->getItems($iUserId, $oAddressBook, $sSearch, $sFirstCharacter, $iGroupId);
		$this->sortItems($aResult, $iSortField, $iSortOrder);

		return array_slice($aResult, $iOffset, $iRequestLimit);
	}
	
	/**
	 * @param int $iUserId
	 * @return bool|array
	 */
	public function GetContactItemObjects($iUserId)
	{
		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		return $this->getObjectItems($iUserId, $oAddressBook);
	}	

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @param int $iGroupId
	 * @param int $iTenantId
	 * @return int
	 */
	public function getContactItemsCount($iUserId, $sSearch, $sFirstCharacter, $iGroupId, $iTenantId = null, $bAll = false)
	{
		$iCount = 0;
		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		if (false !== $oAddressBook)
		{
			$iCount = count($this->getItems($iUserId, $oAddressBook, $sSearch, $sFirstCharacter, $iGroupId));
		}
		return $iCount;
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
				foreach ($oContact->GroupIds as $sGroupId)
				{
					$oContactItem = new CContactListItem();
					$oContactItem->Id = (string) $sGroupId;
					$oContactItem->Name = (string) $sGroupId;
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
			$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
			if (false !== $oAddressBook)
			{
				$sName = $oAddressBook->getName();
				if (!isset($this->aGroupItemsCache[$sName]))
				{
					$this->getItems($iUserId, $oAddressBook);
				}

				if (isset($this->aGroupItemsCache[$sName]))
				{
					$aItems = $this->aGroupItemsCache[$sName];
					foreach ($aItems as $sKey => $aIds)
					{
						$aContactIds = array();
						foreach($aIds as $sContactId)
						{
							$aContactIds[] = $sContactId;
						}
						$oContactItem = new CContactListItem();
						$oContactItem->Id = $sKey;
						$oContactItem->Name = $sKey;
						$oContactItem->IsGroup = true;

						if (empty($sContactId) || !empty($sContactId) && in_array($sContactId, $aContactIds))
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
		}
		return $aResult;
	}

	protected function searchContactItemsByEmail($sUserId, $sEmail, $oAddressBook)
	{
		$aResult = array();

		$aContactItems = $this->getItems($sUserId, $oAddressBook, $sEmail);
		foreach($aContactItems as $oContactItem)
		{
			$aResult[] = $oContactItem->Id;
		}

		return $aResult;
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
		return count($this->getGroupItemsWithoutOrder($iUserId, $sSearch, $sFirstCharacter));
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param int $iRequestLimit
	 * @param bool $bPhoneOnly = false
	 * @return bool|array
	 */
	public function GetSuggestContactItems($iUserId, $sSearch, $iRequestLimit, $bPhoneOnly = false)
	{
		$aResult = array();
		$this->init($iUserId);

		$oDefaultAB = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$oCollectedAB = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME);

		$aCollectedContactItems = $this->getObjectItems($iUserId, $oCollectedAB);
		$aDefaultContactItems = $this->getObjectItems($iUserId, $oDefaultAB);

		$aContactItems = array_merge($aDefaultContactItems, $aCollectedContactItems);

		foreach ($aContactItems as $oItem)
		{
			$sItemId = $oItem->getName();
			$oVCard = null;
			try
			{
				$oVCard = \Sabre\VObject\Reader::read($oItem->get());
			}
			catch(Exception $ex)
			{
				CApi::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
			}
			if (isset($oVCard))
			{
				$oContactItem = new CContactListItem();
				$oContactItem->InitBySabreCardDAVCard($oVCard);
				$oContactItem->Id = $oItem->getName();

				if (empty($sSearch) ||
					stripos($oContactItem->Name, $sSearch) !== false ||
					stripos($oContactItem->Email, $sSearch) !== false)
				{
					$aResult[] = $oContactItem;
				}
				unset($oContactItem);
			}

			unset($oVCard);
		}

		$this->sortItems($aResult, EContactSortField::Frequency, ESortOrder::ASC);

		return array_slice($aResult, 0, $iRequestLimit);
	}

	/**
	 * @param CContact $oContact
	 * @param int $iUserId
	 * @return string
	 */
	public function updateContactUserId($oContact, $iUserId)
	{
		$bResult = false;
		
		$sAddressBook = $oContact->Storage === 'shared' ? \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME : \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
		$oAddressBookFrom = $this->getAddressBook($oContact->IdUser, $sAddressBook);
		$oContactItem = $this->geItem($oContact->IdUser, $oAddressBookFrom, $oContact->IdContactStr);
		if ($oContactItem)
		{
			$oAddressBookTo = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
			if ($oAddressBookTo)
			{
				try
				{
					$sData = $oContactItem->get();
					$oContactItem->delete();
					$oAddressBookTo->createFile($oContact->IdContactStr, $sData);
					$bResult = true;
				}
				catch (Exception $ex)
				{
					CApi::Log($ex->getTraceAsString());
					$bResult = false;
				}
			}
		}
		return $bResult;
	}
	
	/**
	 * @param CContact $oContact
	 * @return bool
	 */
	public function updateContact($oContact)
	{
		$bResult = false;
		$iUserId = $oContact->IdUser;
		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$oContactItem = $this->geItem($iUserId, $oAddressBook, $oContact->IdContactStr);
		if ($oContactItem)
		{
			$sData = $oContactItem->get();
/*
			$sETag = md5($sData);
			if ($oContact->ETag !== $sETag)
			{
 				throw new CApiBaseException(Errs::Sabre_PreconditionFailed);
			}
 */

			$oVCard = \Sabre\VObject\Reader::read($sData);
			if ($oVCard)
			{
				CApiContactsVCardHelper::UpdateVCardFromContact($oContact, $oVCard);
				$oContactItem->put($oVCard->serialize());
				$bResult = true;
			}
			unset($oVCard);
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
			// TODO sasha
//			$oAddressBook = $this->getAddressBook($oGroup->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
//
//			if ($oAddressBook)
//			{
//				$aContactIds = $oGroup->ContactIds;
//				foreach ($aContactIds as $sContactId)
//				{
//					if ($oAddressBook->childExists($sContactId))
//					{
//						$oContact = $oAddressBook->GetChild($sContactId);
//						$vCard = \Sabre\VObject\Reader::read($oContact->get());
//
//						$sCategories = '';
//						if (isset($vCard->CATEGORIES))
//						{
//							$sCategories = $vCard->CATEGORIES->getParts();
//							$aResultCategories = array();
//							foreach ($aCategories as $sCategory)
//							{
//								if ($sCategory === $sGroupId)
//								{
//									$aResultCategories[] = $sGroupName;
//								}
//								else
//								{
//									$aResultCategories[] = $sCategory;
//								}
//							}
//							if (!in_array($sGroupId, $aResultCategories))
//							{
//								$aResultCategories[] = $sGroupName;
//							}
//							$sCategories = implode(',', array_unique($aResultCategories));
//						}
//						else
//						{
//							$vCard->add(new \Sabre\VObject\Property('CATEGORIES'));
//							$sCategories = $sGroupName;
//						}
//
//						$vCard->CATEGORIES->setValue($sCategories);
//						$oContact->put($vCard->serialize());
//					}
//				}
//
//				$aContactIds = $oGroup->DeletedContactIds;
//				foreach ($aContactIds as $sContactId)
//				{
//					if ($oAddressBook->childExists($sContactId))
//					{
//						$oContact = $oAddressBook->GetChild($sContactId);
//						$vCard = \Sabre\VObject\Reader::read($oContact->get());
//
//						$aResultCategories = array();
//						if (isset($vCard->CATEGORIES))
//						{
//							$sCategories = (string)$vCard->CATEGORIES;
//							if (strpos($sCategories, $sGroupId) !== false)
//							{
//								$aCategories = $vCard->CATEGORIES->getParts());
//								foreach($aCategories as $sCategory)
//								{
//									if ($oGroup->IdGroup !== $sCategory)
//									{
//										$aResultCategories[] = $sCategory;
//									}
//								}
//							}
//							$vCard->CATEGORIES->setValue(array_unique($aResultCategories));
//							$oContact->put($vCard->serialize());
//						}
//					}
//				}
//			}
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
			$oAddressBook = $this->getAddressBook($oContact->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
			if ($oAddressBook)
			{
				$sUUID = \Sabre\DAV\UUIDUtil::getUUID();
				if (empty($oContact->IdContactStr))
				{
					$oContact->IdContactStr = $sUUID. '.vcf';
				}

				$oVCard = new \Sabre\VObject\Component\VCard();
				CApiContactsVCardHelper::UpdateVCardFromContact($oContact, $oVCard);

				$oAddressBook->createFile($oContact->IdContactStr, $oVCard->serialize());
				$bResult = true;
			}

			$oAddressBook = $this->getAddressBook($oContact->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME);
			$aContactIds = $this->searchContactItemsByEmail($oContact->IdUser, $oContact->GetViewEmail(), $oAddressBook);

			$this->deleteContactsByAddressBook($oContact->IdUser, $aContactIds, $oAddressBook);
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
	 * @param array $aContactIds
	 * @param \Afterlogic\DAV\CardDAV\AddressBook
	 * @return bool
	 */
	protected function deleteContactsByAddressBook($iUserId, $aContactIds, $oAddressBook)
	{
		$this->init($iUserId);

		if ($oAddressBook)
		{
			foreach($aContactIds as $sContactId)
			{
				if ($oAddressBook->childExists($sContactId))
				{
					$oContact = $oAddressBook->GetChild($sContactId);
					$oContact->delete();
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @return bool
	 */
	public function deleteContacts($iUserId, $aContactIds)
	{
		$this->init($iUserId);
		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		return $this->deleteContactsByAddressBook($iUserId, $aContactIds, $oAddressBook);
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @return bool
	 */
	public function deleteSuggestContacts($iUserId, $aContactIds)
	{
		$this->init($iUserId);
		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME);
		return $this->deleteContactsByAddressBook($iUserId, $aContactIds, $oAddressBook);
	}
	
	/**
	 * @param int $iUserId
	 * @param array $aGroupIds
	 * @return bool
	 */
	public function deleteGroups($iUserId, $aGroupIds)
	{
		$this->init($iUserId);

		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$sName = $oAddressBook->getName();
		if ($oAddressBook)
		{
			$this->getItems($iUserId, $oAddressBook);

			foreach($aGroupIds as $sGroupId)
			{
				if (isset($this->aGroupItemsCache[$sName][$sGroupId]))
				{
					$aContactIds = $this->aGroupItemsCache[$sName][$sGroupId];
					foreach ($aContactIds as $sContactId)
					{
						if ($oAddressBook->childExists($sContactId))
						{
							$oContact = $oAddressBook->GetChild($sContactId);
							$oVCard = \Sabre\VObject\Reader::read($oContact->get());

							if (isset($oVCard->CATEGORIES))
							{
								if (strpos($sCategories, $sGroupId) !== false)
								{
									$aCategories = $oVCard->CATEGORIES->getParts();
									$aResultCategories = array();
									foreach($aCategories as $sCategory)
									{
										$sCategory = trim($sCategory);
										if ($sCategory !== $sGroupId)
										{
											$aResultCategories[] = $sCategory;
										}
									}
									$oVCard->CATEGORIES->setValue($aResultCategories);
									$oContact->put($oVCard->serialize());
									$this->aContactItemsCache[$sName][$oContact->getName()] = $oContact;
								}
							}
						}
					}
					unset($this->aGroupItemsCache[$sName][$sGroupId]);
				}
			}
			return true;
		}
		return false;
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

		$oDefautltAB = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$oCollectedAB = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME);
		
		$aCollectedContactItems = $this->getObjectItems($iUserId, $oCollectedAB);

		foreach ($aEmails as $sEmail => $sName)
		{
			$mFindContact = false;
			if ($aCollectedContactItems)
			{
				foreach ($aCollectedContactItems as $oCollectedContactItem)
				{
					$oVCard = \Sabre\VObject\Reader::read($oCollectedContactItem->get());
					if (isset($oVCard->EMAIL))
					{
						foreach ($oVCard->EMAIL as $oEmail)
						{
							if (strtolower((string)$oEmail) == strtolower($sEmail))
							{
								$mFindContact = $oCollectedContactItem;
								break;
							}
						}
					}
					unset($oVCard);
				}
			}

			$aDefaultContactIds = $this->searchContactItemsByEmail($iUserId, $sEmail, $oDefautltAB);
			if (count($aDefaultContactIds) === 0)
			{
				if ($mFindContact === false)
				{
					$sUUID = \Sabre\DAV\UUIDUtil::getUUID();
					$oContact = new CContact();
					$oContact->FullName = $sName;
					$oContact->PersonalEmail = $sEmail;
					$oContact->IdContactStr = $sUUID;

					$oVCard = new \Sabre\VObject\Component\VCard();
					$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'} = '1';
					CApiContactsVCardHelper::UpdateVCardFromContact($oContact, $oVCard);

					$oCollectedAB->createFile($sUUID . '.vcf', $oVCard->serialize());
					$bResult = true;
				}
				else if ($mFindContact instanceof \Sabre\CardDAV\Card)
				{
					$oVCard = \Sabre\VObject\Reader::read($mFindContact->get());
					if (isset($oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'}))
					{
						$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'} = (int)$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'}->getValue() + 1;
					}
					else
					{
						$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'} = '1';
					}
					$mFindContact->put($oVCard->serialize());
					unset($oVCard);
				}
			}
			else
			{
				foreach($aDefaultContactIds as $sDefaultContactId)
				{
					$mDefaultContact = $this->geItem($iUserId, $oDefautltAB, $sDefaultContactId);
					if ($mDefaultContact !== false)
					{
						$oVCard = \Sabre\VObject\Reader::read($mDefaultContact->get());
						if (isset($oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'}))
						{
							$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'} = (int)$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'}->getValue() + 1;
						}
						else
						{
							$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'} = '1';
						}
						$mDefaultContact->put($oVCard->serialize());
						unset($oVCard);
					}
				}

				if ($mFindContact instanceof \Sabre\CardDAV\Card)
				{
					$mFindContact->delete();
				}
			}
		}

		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @return bool
	 */
//	public function DeleteContactsExceptIds($iUserId, $aContactIds)
//	{
//		$this->init($iUserId);
//
//		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
//		if ($oAddressBook)
//		{
//			$aContactItems = $this->getObjectItems($iUserId);
//			foreach ($aContactItems as $oContactItem)
//			{
//				$vCard = \Sabre\VObject\Reader::read($oContactItem->get());
//				if (isset($vCard->UID) && !in_array((string)$vCard->UID, $aContactIds))
//				{
//					$oContactItem->delete();
//				}
//			}
//			return true;
//		}
//		return false;
//	}

	/**
	 * @param int $iUserId
	 * @param array $aGroupIds
	 * @return bool
	 */
//	public function DeleteGroupsExceptIds($iUserId, $aGroupIds)
//	{
//		return true;
//	}

	/**
	 * @return bool
	 */
	public function flushContacts()
	{
		return true;

	}

	/**
	 * @param CAccount $oAccount
	 * @return bool
	 */
	public function clearAllContactsAndGroups($oAccount)
	{
		$bResult = false;
		$this->InitByAccount();

		$oAddressBooks = new \Sabre\CardDAV\UserAddressBooks(
			\Afterlogic\DAV\Backend::Carddav(), $this->Principal);

		foreach ($oAddressBooks->getChildren() as $oAddressBook)
		{
			if ($oAddressBook && $oAddressBook instanceof \Sabre\CardDAV\AddressBook)
			{
				try
				{
					$oAddressBook->delete();
					$bResult = true;
				}
				catch (Exception $ex)
				{
					CApi::Log($ex->getTraceAsString());
					$bResult = false;
				}
			}
		}
		return $bResult;
	}

	/**
	 * @param CGroup $oGroup
	 * @param array $aContactIds
	 * @return bool
	 */
	public function addContactsToGroup($oGroup, $aContactIds)
	{
		$bResult = true;
		
		if ($oGroup && is_array($aContactIds))
		{
			foreach ($aContactIds as $sContactId)
			{
				$oContact = $this->getContactById($oGroup->IdUser, $sContactId);
				if ($oContact && !in_array($oGroup->Name, $oContact->GroupIds))
				{
					$aGroupIds = $oContact->GroupIds;
					array_push($aGroupIds, $oGroup->Name);
					$oContact->GroupIds = $aGroupIds;
					$bResult = $this->updateContact($oContact);
				}
			}
		}
		else
		{
			$bResult = false;
		}
		
		return (bool) $bResult;
	}

	/**
	 * @param CGroup $oGroup
	 * @param array $aContactIds
	 * @return bool
	 */
	public function removeContactsFromGroup($oGroup, $aContactIds)
	{
		$bResult = true;
		
		if ($oGroup && is_array($aContactIds))
		{
			foreach ($aContactIds as $sContactId)
			{
				$oContact = $this->getContactById($oGroup->IdUser, $sContactId);
				if ($oContact)
				{
					$aGroupIds = $oContact->GroupIds;
					$oContact->GroupIds = array_diff($aGroupIds, array($oGroup->Name));
					$bResult = $this->updateContact($oContact);
				}
			}
		}
		else
		{
			$bResult = false;
		}
		
		return (bool) $bResult;
	}
}
