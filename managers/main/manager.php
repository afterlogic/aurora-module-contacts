<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * CApiContactsMainManager class summary
 * 
 * @package ContactsMain
 */
class CApiContactsMainManager extends AApiManager
{
	/*
	 * @var $oApiContactsBaseManagerDAV CApiContactsBaseManager
	 */
	private $oApiContactsBaseManagerDAV;
	
	private $oEavManager = null;

	/**
	 * @param CApiGlobalManager &$oManager
	 */
	public function __construct(CApiGlobalManager &$oManager, $sForcedStorage = 'db', AApiModule $oModule = null)
	{
		parent::__construct('main', $oManager, $oModule);

		if ($oModule instanceof AApiModule)
		{
			$this->oApiContactsBaseManagerDAV = $oModule->GetManager('base', 'sabredav');
			$this->oEavManager = \CApi::GetSystemManager('eav', 'db');
		}
	}
	
	/**
	 * 
	 * @param int $iIdContact
	 * @return \CContact
	 */
	public function getContact($iIdContact)
	{
		$oContact = $this->oEavManager->getEntityById($iIdContact);
		if ($oContact)
		{
			$oContact->GroupsContacts = $this->getGroupContacts(null, $iIdContact);
		}
		return $oContact;
	}
	
	/**
	 * Returns group item identified by its ID.
	 * 
	 * @param int $iGroupId Group ID 
	 * 
	 * @return CGroup
	 */
	public function getGroup($iGroupId)
	{
		return $this->oEavManager->getEntityById($iGroupId);
	}
	
	/**
	 * Updates contact information. Using this method is required to finalize changes made to the contact object. 
	 * 
	 * @param CContact $oContact  Contact object to be updated 
	 * @param bool $bUpdateFromGlobal
	 * 
	 * @return bool
	 */
	public function updateContact($oContact)
	{
		$res = $this->oEavManager->saveEntity($oContact);
		if ($res)
		{
			$aGroupContact = $this->getGroupContacts(null, $oContact->iId);
			
			function compare_func($oGroupContact1, $oGroupContact2)
			{
				if ($oGroupContact1->IdGroup === $oGroupContact2->IdGroup)
				{
					return 0;
				}
				if ($oGroupContact1->IdGroup > $oGroupContact2->IdGroup)
				{
					return -1;
				}
				return 1;
			}

			$aGroupContactToDelete = array_udiff($aGroupContact, $oContact->GroupsContacts, 'compare_func');
			$aGroupContactIdsToDelete = array_map(
				function($oGroupContact) { 
					return $oGroupContact->iId; 
				}, 
				$aGroupContactToDelete
			);
			$this->oEavManager->deleteEntities($aGroupContactIdsToDelete);
			
			$aGroupContactToAdd = array_udiff($oContact->GroupsContacts, $aGroupContact, 'compare_func');
			foreach ($aGroupContactToAdd as $oGroupContact)
			{
				$this->oEavManager->saveEntity($oGroupContact);
			}
		}
		
		return $res;
	}
	
	/**
	 * Updates group information. Using this method is required to finalize changes made to the group object. 
	 * 
	 * @param CGroup $oGroup
	 *
	 * @return bool
	 */
	public function updateGroup($oGroup)
	{
		return $this->oEavManager->saveEntity($oGroup);
	}

	/**
	 * Returns list of contacts which match the specified criteria 
	 * 
	 * @param int $iUserId User ID 
	 * @param string $sSearch Search pattern. Default value is empty string.
	 * @param string $sFirstCharacter If specified, will only return contacts with names starting from the specified character. Default value is empty string.
	 * @param int $iGroupId Group ID. Default value is **0**.
	 * @param int $iTenantId Group ID. Default value is null.
	 * @param bool $bAll Default value is null
	 * 
	 * @return int
	 */
	public function getContactsCount($aFilters = array(), $iIdGroup = 0)
	{
		$aIdContact = array();
		if (is_numeric($iIdGroup) && $iIdGroup > 0)
		{
			$aGroupContact = $this->getGroupContacts($iIdGroup);
			foreach ($aGroupContact as $oGroupContact)
			{
				$aIdContact[] = $oGroupContact->IdContact;
			}
			
			if (empty($aIdContact))
			{
				return 0;
			}
		}
		
		return $this->oEavManager->getEntitiesCount(
			'CContact', 
			$aFilters,
			$aIdContact
		);
	}

	/**
	 * Returns list of contacts within specified range, sorted according to specified requirements. 
	 * 
	 * @param int $iSortField Sort field. Accepted values:
	 *
	 *		EContactSortField::Name
	 *		EContactSortField::Email
	 *		EContactSortField::Frequency
	 *
	 * Default value is **EContactSortField::Email**.
	 * @param int $iSortOrder Sorting order. Accepted values:
	 *
	 *		ESortOrder::ASC
	 *		ESortOrder::DESC,
	 *
	 * for ascending and descending respectively. Default value is **ESortOrder::ASC**.
	 * @param int $iOffset Ordinal number of the contact item the list stars with. Default value is **0**.
	 * @param int $iRequestLimit The upper limit for total number of contacts returned. Default value is **20**.
	 * @param array $aFilters
	 * @param int $iIdGroup
	 * 
	 * @return array|bool
	 */
	public function getContacts($iSortField = EContactSortField::Name, $iSortOrder = ESortOrder::ASC,
		$iOffset = 0, $iRequestLimit = 20, $aFilters = [], $iIdGroup = 0)
	{
		$aIdContact = array();
		if (is_numeric($iIdGroup) && $iIdGroup > 0)
		{
			$aGroupContact = $this->getGroupContacts($iIdGroup);
			foreach ($aGroupContact as $oGroupContact)
			{
				$aIdContact[] = $oGroupContact->IdContact;
			}
			
			if (empty($aIdContact))
			{
				return array();
			}
		}
		
		return $this->oEavManager->getEntities(
			'CContact', 
			array(),
			$iOffset,
			$iRequestLimit,
			$aFilters,
			$iSortField === EContactSortField::Name ? 'FullName' : 'ViewEmail',
			$iSortOrder,
			$aIdContact
		);
	}

	/**
	 * Returns list of user's groups. 
	 * 
	 * @param int $iUserId User ID 
	 * 
	 * @return array|bool
	 */
	public function getGroups($iUserId)
	{
		return $this->oEavManager->getEntities(
			'CGroup', 
			array(),
			0,
			0,
			array('IdUser' => $iUserId),
			'Name',
			ESortOrder::ASC
		);
	}

	/**
	 * The method is used for saving created contact to the database. 
	 * 
	 * @param CContact $oContact
	 * 
	 * @return bool
	 */
	public function createContact($oContact)
	{
		$res = $this->oEavManager->saveEntity($oContact);
		
		if ($res)
		{
			foreach ($oContact->GroupsContacts as $oGroupContact)
			{
				$oGroupContact->IdContact = $oContact->iId;
				$this->oEavManager->saveEntity($oGroupContact);
			}
		}

		return $res;
	}

	/**
	 * The method is used for saving created group to the database. 
	 * 
	 * @param CGroup $oGroup
	 * 
	 * @return bool
	 */
	public function createGroup($oGroup)
	{
		$res = $this->oEavManager->saveEntity($oGroup);
		
		if ($res)
		{
			foreach ($oGroup->GroupContacts as $oGroupContact)
			{
				$oGroupContact->IdGroup = $oGroup->iId;
				$res = $this->oEavManager->saveEntity($oGroupContact);
			}
		}

		return $res;
	}

	/**
	 * Deletes one or multiple contacts from address book.
	 * 
	 * @param array $aContactIds Array of integers
	 * 
	 * @return bool
	 */
	public function deleteContacts($aContactIds)
	{
		$aEntitiesIds = array();
		
		foreach ($aContactIds as $iContact)
		{
			$aEntitiesIds[] = $iContact;
			$aGroupContact = $this->getGroupContacts(null, $iContact);
			foreach ($aGroupContact as $oGroupContact)
			{
				$aEntitiesIds[] = $oGroupContact->iId;
			}
		}
		
		return $this->oEavManager->deleteEntities($aEntitiesIds);
	}

	public function getGroupContacts($iIdGroup = null, $iIdContact = null)
	{
		$aFilters = array();
		if (is_numeric($iIdGroup) && $iIdGroup > 0)
		{
			$aFilters = array('IdGroup' => $iIdGroup);
		}
		if (is_numeric($iIdContact) && $iIdContact > 0)
		{
			$aFilters = array('IdContact' => $iIdContact);
		}
		return $this->oEavManager->getEntities(
			'CGroupContact', 
			array('IdGroup', 'IdContact'),
			0,
			0,
			$aFilters
		);
	}
	
	/**
	 * Deletes specific groups from address book.
	 * 
	 * @param array $aGroupIds array of integers - groups identificators.
	 * 
	 * @return bool
	 */
	public function deleteGroups($aGroupIds)
	{
		$aEntitiesIds = [];
		
		foreach ($aGroupIds as $iId)
		{
			$aEntitiesIds[] = $iId;
			$aGroupContact = $this->getGroupContacts($iId);
			foreach ($aGroupContact as $oGroupContact)
			{
				$aEntitiesIds[] = $oGroupContact->iId;
			}
		}
		
		return $this->oEavManager->deleteEntities($aEntitiesIds);
	}

	/**
	 * Allows for importing data into user's address book.
	 * 
	 * @param int $iUserId User ID
	 * @param string $sSyncType Data source type. Currently, "csv" and "vcf" options are supported.
	 * @param string $sTempFileName Path to the file data are imported from.
	 * @param int $iParsedCount
	 * @param int $iGroupId
	 *
	 * @return int|false If importing is successful, number of imported entries is returned. 
	 */
	public function import($iUserId, $sSyncType, $sTempFileName, &$iParsedCount, $iGroupId)
	{
		$oApiUsersManager = CApi::GetSystemManager('users');
		$oAccount = $oApiUsersManager->getDefaultAccount($iUserId);

		if ($sSyncType === \EContactFileType::CSV)
		{
			$this->inc('helpers.'.$sSyncType.'.formatter');
			$this->inc('helpers.'.$sSyncType.'.parser');
			$this->inc('helpers.sync.'.$sSyncType);

			$sSyncClass = 'CApi'.ucfirst($this->GetManagerName()).'Sync'.ucfirst($sSyncType);
			if (class_exists($sSyncClass))
			{
				$oSync = new $sSyncClass($this);
				return $oSync->Import($iUserId, $sTempFileName, $iParsedCount, $iGroupId);
			}
		}
		else if ($sSyncType === \EContactFileType::VCF)
		{
			// You can either pass a readable stream, or a string.
			$oHandler = fopen($sTempFileName, 'r');
			$oSplitter = new \Sabre\VObject\Splitter\VCard($oHandler);
			while($oVCard = $oSplitter->getNext())
			{
				$oContact = new \CContact();

				$oContact->InitFromVCardObject($iUserId, $oVCard);

				if ($oAccount)
				{
					$oContact->IdTenant = $oAccount->IdTenant;
				}
				$oContact->GroupIds = array($iGroupId);

				if ($this->createContact($oContact))
				{
					$iParsedCount++;
				}
			}
			return $iParsedCount;
		}

		return false;
	}

	/**
	 * Allows for exporting data from user's address book. 
	 * 
	 * @param int $iUserId User ID 
	 * @param string $sSyncType Data source type. Currently, "csv" and "vcf" options are supported. 
	 * 
	 * @return string | bool
	 */
	public function export($iUserId, $sSyncType)
	{
		if ($sSyncType === \EContactFileType::CSV)
		{
			$this->incClass($sSyncType.'.formatter');
			$this->incClass($sSyncType.'.parser');
			$this->incClass('sync.'.$sSyncType);

			$sSyncClass = 'CApiContactsSync'.ucfirst($sSyncType);
			if (class_exists($sSyncClass))
			{
				$oSync = new $sSyncClass($this);
				return $oSync->Export($iUserId);
			}
		}
		else if ($sSyncType === \EContactFileType::VCF)
		{
            $sOutput = '';
			$aContactItems = $this->oApiContactsBaseManagerDAV->GetContactItemObjects($iUserId);
			if (is_array($aContactItems))
			{
				foreach ($aContactItems as $oContactItem)
				{
					$sOutput .= \Sabre\VObject\Reader::read($oContactItem->get())->serialize();
				}
			}
			return $sOutput;            
		}
		
		return false;
	}

	/**
	 * Adds one or multiple contacts to the specific group. 
	 * 
	 * @param int $iIdGroup Group identificator to be used 
	 * @param array $aContactIds Array of integers
	 * 
	 * @return bool
	 */
	public function addContactsToGroup($iIdGroup, $aContactIds)
	{
		$res = true;
		
		$aCurrGroupContact = $this->getGroupContacts($iIdGroup);
		$aCurrContactIds = array_map(
			function($oGroupContact) { 
				return $oGroupContact->IdContact; 
			}, 
			$aCurrGroupContact
		);
		
		foreach ($aContactIds as $iIdContact)
		{
			if (!in_array($iIdContact, $aCurrContactIds))
			{
				$oGroupContact = \CGroupContact::createInstance();
				$oGroupContact->IdGroup = $iIdGroup;
				$oGroupContact->IdContact = (int) $iIdContact;
				$res = $res || $this->oEavManager->saveEntity($oGroupContact);
			}
		}
		
		return $res;
	}

	/**
	 * The method deletes one or multiple contacts from the group. 
	 * 
	 * @param int $iIdGroup Group identificator
	 * @param array $aContactIds Array of integers
	 * 
	 * @return bool
	 */
	public function removeContactsFromGroup($iIdGroup, $aContactIds)
	{
		$aCurrGroupContact = $this->getGroupContacts($iIdGroup);
		$aIdEntitiesToDelete = array();
		
		foreach ($aCurrGroupContact as $oGroupContact)
		{
			if (in_array($oGroupContact->IdContact, $aContactIds))
			{
				$aIdEntitiesToDelete[] = $oGroupContact->iId;
			}
		}
		
		return $this->oEavManager->deleteEntities($aIdEntitiesToDelete);
	}
}
