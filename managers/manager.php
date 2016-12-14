<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * CApiContactsManager class summary
 * 
 * @package ContactsMain
 */
class CApiContactsManager extends AApiManager
{
	private $oEavManager = null;

	/**
	 * @param CApiGlobalManager &$oManager
	 */
	public function __construct(CApiGlobalManager &$oManager, $sForcedStorage = 'db', AApiModule $oModule = null)
	{
		parent::__construct('', $oManager, $oModule);

		if ($oModule instanceof AApiModule)
		{
			$this->oEavManager = \CApi::GetSystemManager('eav', 'db');
		}
	}
	
	/**
	 * 
	 * @param string $sUUID
	 * @return \CContact
	 */
	public function getContact($sUUID)
	{
		$oContact = $this->oEavManager->getEntity($sUUID);
		if ($oContact)
		{
			$oContact->GroupsContacts = $this->getGroupContacts(null, $sUUID);
		}
		return $oContact;
	}
	
	/**
	 * Returns group item identified by its ID.
	 * 
	 * @param string $sUUID Group ID 
	 * 
	 * @return CGroup
	 */
	public function getGroup($sUUID)
	{
		return $this->oEavManager->getEntity($sUUID);
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
			$aGroupContact = $this->getGroupContacts(null, $oContact->sUUID);
			
			function compare_func($oGroupContact1, $oGroupContact2)
			{
				if ($oGroupContact1->GroupUUID === $oGroupContact2->GroupUUID)
				{
					return 0;
				}
				if ($oGroupContact1->GroupUUID > $oGroupContact2->GroupUUID)
				{
					return -1;
				}
				return 1;
			}

			$aGroupContactToDelete = array_udiff($aGroupContact, $oContact->GroupsContacts, 'compare_func');
			$aGroupContactUUIDsToDelete = array_map(
				function($oGroupContact) { 
					return $oGroupContact->sUUID; 
				}, 
				$aGroupContactToDelete
			);
			$this->oEavManager->deleteEntities($aGroupContactUUIDsToDelete);
			
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
	 * @param string $sGroupUUID. Default value is **''**.
	 * @param int $iTenantId Group ID. Default value is null.
	 * @param bool $bAll Default value is null
	 * 
	 * @return int
	 */
	public function getContactsCount($aFilters = [], $sGroupUUID = '')
	{
		$aContactUUIDs = [];
		if (!empty($sGroupUUID))
		{
			$aGroupContact = $this->getGroupContacts($sGroupUUID);
			foreach ($aGroupContact as $oGroupContact)
			{
				$aContactUUIDs[] = $oGroupContact->ContactUUID;
			}
			
			if (empty($aContactUUIDs))
			{
				return 0;
			}
		}
		
		return $this->oEavManager->getEntitiesCount(
			'CContact', 
			$aFilters,
			$aContactUUIDs
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
	 * @param string $sGroupUUID
	 * @param array $aContactUUIDs
	 * 
	 * @return array|bool
	 */
	public function getContacts($iSortField = EContactSortField::Name, $iSortOrder = ESortOrder::ASC,
		$iOffset = 0, $iRequestLimit = 20, $aFilters = [], $sGroupUUID = '', $aContactUUIDs = [])
	{
		if (empty($aContactUUIDs) && !empty($sGroupUUID))
		{
			$aGroupContact = $this->getGroupContacts($sGroupUUID);
			foreach ($aGroupContact as $oGroupContact)
			{
				$aContactUUIDs[] = $oGroupContact->ContactUUID;
			}
			
			if (empty($aContactUUIDs))
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
			$aContactUUIDs
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
				$oGroupContact->ContactUUID = $oContact->sUUID;
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
				$oGroupContact->GroupUUID = $oGroup->sUUID;
				$res = $this->oEavManager->saveEntity($oGroupContact);
			}
		}

		return $res;
	}

	/**
	 * Deletes one or multiple contacts from address book.
	 * 
	 * @param array $aContactUUIDs Array of strings
	 * 
	 * @return bool
	 */
	public function deleteContacts($aContactUUIDs)
	{
		$aEntitiesUUIDs = [];
		
		foreach ($aContactUUIDs as $sContactUUID)
		{
			$aEntitiesUUIDs[] = $sContactUUID;
			$aGroupContact = $this->getGroupContacts(null, $sContactUUID);
			foreach ($aGroupContact as $oGroupContact)
			{
				$aEntitiesUUIDs[] = $oGroupContact->sUUID;
			}
		}
		
		return $this->oEavManager->deleteEntities($aEntitiesUUIDs);
	}

	public function getGroupContacts($sGroupUUID = null, $sContactUUID = null)
	{
		$aFilters = [];
		if (is_string($sGroupUUID) && $sGroupUUID !== '')
		{
			$aFilters = ['GroupUUID' => $sGroupUUID];
		}
		if (is_string($sContactUUID) && $sContactUUID !== '')
		{
			$aFilters = ['ContactUUID' => $sContactUUID];
		}
		return $this->oEavManager->getEntities(
			'CGroupContact', 
			['GroupUUID', 'ContactUUID'],
			0,
			0,
			$aFilters
		);
	}
	
	/**
	 * Deletes specific groups from address book.
	 * 
	 * @param array $aGroupUUIDs array of strings - groups identificators.
	 * 
	 * @return bool
	 */
	public function deleteGroups($aGroupUUIDs)
	{
		$aEntitiesUUIDs = [];
		
		foreach ($aGroupUUIDs as $sGroupUUID)
		{
			$aEntitiesUUIDs[] = $sGroupUUID;
			$aGroupContact = $this->getGroupContacts($sGroupUUID);
			foreach ($aGroupContact as $oGroupContact)
			{
				$aEntitiesUUIDs[] = $oGroupContact->sContactUUID;
			}
		}
		
		return $this->oEavManager->deleteEntities($aEntitiesUUIDs);
	}

	/**
	 * Adds one or multiple contacts to the specific group. 
	 * 
	 * @param string $sGroupUUID Group identificator to be used 
	 * @param array $aContactUUIDs Array of integers
	 * 
	 * @return bool
	 */
	public function addContactsToGroup($sGroupUUID, $aContactUUIDs)
	{
		$res = true;
		
		$aCurrGroupContact = $this->getGroupContacts($sGroupUUID);
		$aCurrContactUUIDs = array_map(
			function($oGroupContact) { 
				return $oGroupContact->ContactUUID; 
			}, 
			$aCurrGroupContact
		);
		
		foreach ($aContactUUIDs as $sContactUUID)
		{
			if (!in_array($sContactUUID, $aCurrContactUUIDs))
			{
				$oGroupContact = \CGroupContact::createInstance();
				$oGroupContact->GroupUUID = $sGroupUUID;
				$oGroupContact->ContactUUID = $sContactUUID;
				$res = $this->oEavManager->saveEntity($oGroupContact) || $res;
			}
		}
		
		return $res;
	}

	/**
	 * The method deletes one or multiple contacts from the group. 
	 * 
	 * @param string $sGroupUUID Group identificator
	 * @param array $aContactUUIDs Array of integers
	 * 
	 * @return bool
	 */
	public function removeContactsFromGroup($sGroupUUID, $aContactUUIDs)
	{
		$aCurrGroupContact = $this->getGroupContacts($sGroupUUID);
		$aIdEntitiesToDelete = array();
		
		foreach ($aCurrGroupContact as $oGroupContact)
		{
			if (in_array($oGroupContact->ContactUUID, $aContactUUIDs))
			{
				$aIdEntitiesToDelete[] = $oGroupContact->sUUID;
			}
		}
		
		return $this->oEavManager->deleteEntities($aIdEntitiesToDelete);
	}
}
