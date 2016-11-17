<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * Class CApiContactsGlobalManager is used for work with global contacts.
 * 
 * @package ContactsGlobal
 */
class CApiContactsGlobalManager extends AApiManagerWithStorage
{
	/**
	 * Creates a new instance of the object.
	 * 
	 * @param CApiGlobalManager &$oManager Global manager object.
	 * @param string $sForcedStorage = ''. If it is specified it is used as storage name instead of name obtained by type.
	 */
	public function __construct(CApiGlobalManager &$oManager, $sForcedStorage = 'db', $oModule = null)
	{
		parent::__construct('global', $oManager, $sForcedStorage, $oModule);
	}

	/**
	 * Obtains default account for another account of it's bundle.
	 * 
	 * @param CAccount $oAccount Account object belonging to the found default account.
	 *
	 * @return CAccount | null
	 */
	protected function _getDefaultAccount(CAccount $oAccount)
	{
		$oDefAccount = null;
		if ($oAccount->IsDefaultAccount)
		{
			$oDefAccount = $oAccount;
		}
		else
		{
			$oApiUsersManager = /* @var CApiUsersManager */ CApi::GetSystemManager('users');
			if ($oApiUsersManager)
			{
				$iIdAccount = $oApiUsersManager->getDefaultAccountId($oAccount->IdUser);
				if (0 < $iIdAccount)
				{
					$oDefAccount = $oApiUsersManager->getAccountById($iIdAccount);
				}
			}
		}

		return $oDefAccount;
	}

	/**
	 * Obtains count of all global contacts found by search string for specified account.
	 * 
	 * @param CAccount $oAccount Account object.
	 * @param string $sSearch = ''. Search string.
	 * 
	 * @return int
	 */
	public function getContactItemsCount($oAccount, $sSearch = '')
	{
		$iResult = 0;
		try
		{
			$oDefAccount = $this->_getDefaultAccount($oAccount);
			if ($oDefAccount)
			{
				$iResult = $this->oStorage->getContactItemsCount($oDefAccount, $sSearch);
			}
		}
		catch (CApiBaseException $oException)
		{
			$iResult = 0;
			$this->setLastException($oException);
		}

		return $iResult;
	}

	/**
	 * 
	 * @param int $iSortField
	 * @param int $iSortOrder
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @param array $aFilters
	 * @param int $iIdGroup
	 * @return array
	 */
	public function getContactItems($iSortField = EContactSortField::Name, $iSortOrder = ESortOrder::ASC,
		$iOffset = 0, $iRequestLimit = 20, $aFilters = array(), $iIdGroup = 0)
	{
		return array();
		
//		$mResult = false;
//		try
//		{
//			$mResult = array();
//			$oDefAccount = $this->_getDefaultAccount($oAccount);
//			if ($oDefAccount)
//			{
//				$mResult = $this->oStorage->getContactItems($oDefAccount,
//					$iSortField, $iSortOrder, $iOffset, $iRequestLimit, $sSearch);
//			}
//		}
//		catch (CApiBaseException $oException)
//		{
//			$mResult = false;
//			$this->setLastException($oException);
//		}
//
//		return $mResult;
	}

	/**
	 * Obtains contact by identifier for specified account.
	 * 
	 * @param CAccount $oAccount Account object.
	 * @param mixed $mGlobalContactId Global contact identifier.
	 * @param bool $bIgnoreHideInGab = false. If **true** all global contacts will be checked including marked as "hide in global address book".
	 * 
	 * @return CContact|bool
	 */
	public function getContactById($oAccount, $mGlobalContactId, $bIgnoreHideInGab = false)
	{
		$oContact = null;
		try
		{
			$oDefAccount = $this->_getDefaultAccount($oAccount);
			if ($oDefAccount)
			{
				$oContact = $this->oStorage->getContactById($oDefAccount, $mGlobalContactId, $bIgnoreHideInGab);
			}
		}
		catch (CApiBaseException $oException)
		{
			$oContact = false;
			$this->setLastException($oException);
		}

		return $oContact;
	}

	/**
	 * Obtains contact by type identifier for specified account.
	 * 
	 * @param CAccount $oAccount Account object.
	 * @param mixed $mGlobalContactTypeId Global contact type identifier.
	 * @param bool $bIgnoreHideInGab = false. If **true** all global contacts will be checked including marked as "hide in global address book".
	 * 
	 * @return CContact|bool
	 */
	public function getContactByTypeId($oAccount, $mGlobalContactTypeId, $bIgnoreHideInGab = false)
	{
		$oContact = null;
		try
		{
			$oDefAccount = $this->_getDefaultAccount($oAccount);
			if ($oDefAccount)
			{
				$oContact = $this->oStorage->getContactByTypeId($oDefAccount, $mGlobalContactTypeId, $bIgnoreHideInGab);
			}
		}
		catch (CApiBaseException $oException)
		{
			$oContact = false;
			$this->setLastException($oException);
		}

		return $oContact;
	}

	/**
	 * Obtains contact by mailing list identifier.
	 * 
	 * @param mixed $iMailingListID Mailing list identifier.
	 * 
	 * @return CContact|bool
	 */
	public function getContactByMailingListId($iMailingListID)
	{
		$oContact = null;
		try
		{
			$oContact = $this->oStorage->getContactByMailingListId($iMailingListID);
		}
		catch (CApiBaseException $oException)
		{
			$oContact = false;
			$this->setLastException($oException);
		}

		return $oContact;
	}

	/**
	 * Obtains contact by email for specified account.
	 * 
	 * @param CAccount $oAccount Account object.
	 * @param string $sEmail Contact email.
	 * 
	 * @return CContact|bool
	 */
	public function getContactByEmail($oAccount, $sEmail)
	{
		$oContact = null;
		try
		{
			$oDefAccount = $this->_getDefaultAccount($oAccount);
			if ($oDefAccount)
			{
				$oContact = $this->oStorage->getContactByEmail($oDefAccount, $sEmail);
			}
		}
		catch (CApiBaseException $oException)
		{
			$oContact = false;
			$this->setLastException($oException);
		}

		return $oContact;
	}

	/**
	 * Updates contact data by contact object.
	 * 
	 * @param CContact $oContact Contact object.
	 * 
	 * @return bool
	 */
	public function updateContact($oContact)
	{
		$bResult = false;
		try
		{
			if ($oContact->validate() && 'global' === $oContact->Storage)
			{
				$bResult = $this->oStorage->updateContact($oContact);
				if ($bResult)
				{
					/* @var $oApiUsersManager CApiUsersManager */
					$oApiUsersManager = CApi::GetSystemManager('users');
					$iAccountId = $oApiUsersManager->getDefaultAccountId($oContact->IdTypeLink);

					$oAccount = null;
					if (0 < $iAccountId)
					{
						$oAccount = $oApiUsersManager->getAccountById($iAccountId);
					}

					if ($oAccount && (
						('global-accounts' === $oContact->Storage && (string) $oAccount->IdUser === (string) $oContact->IdTypeLink)
						||
						('global-mailinglist' === $oContact->Storage && (string) $oAccount->IdAccount === (string) $oContact->IdTypeLink)
					))
					{
						if ($oAccount->FriendlyName !== $oContact->FullName)
						{
							$oAccount->FriendlyName = $oContact->FullName;
							$oAccount->FlushObsolete('FriendlyName');

							$oApiUsersManager->updateAccount($oAccount);
						}

						/* @var $oApiContactsManager CApiContactsMainManager */
						$oApiContactsManager = CApi::Manager('contacts');
						if ($oApiContactsManager)
						{
							$oApiContactsManager->synchronizeExternalContacts($oAccount, true);
						}
					}
				}
			}
		}
		catch (CApiBaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}

	/**
	 * Looks for contacts in the address book belonging to the user or the mailing list, 
	 * and if not found, adds them to the address book.
	 * 
	 * @return bool
	 */
	public function syncMissingGlobalContacts()
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->syncMissingGlobalContacts();
		}
		catch (CApiBaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}
}
