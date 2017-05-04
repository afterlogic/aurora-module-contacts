<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

/**
 * CApiContactsManager class summary
 * 
 * @package ContactsMain
 */
class CApiContactsManager extends \Aurora\System\Managers\AbstractManager
{
	private $oEavManager = null;

	/**
	 * @param \Aurora\System\Managers\GlobalManager &$oManager
	 */
	public function __construct(\Aurora\System\Managers\GlobalManager &$oManager, $sForcedStorage = 'db', \Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct('', $oManager, $oModule);

		if ($oModule instanceof \Aurora\System\Module\AbstractModule)
		{
			$this->oEavManager = \Aurora\System\Api::GetSystemManager('eav', $sForcedStorage);
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
	 * 
	 * @param string $sEmail
	 * @return \CContact
	 */
	public function getContactByEmail($iUserId, $sEmail)
	{
		$oContact = null;
		$aViewAttrs = array();
		$aFilters = array(
			'$AND' => array(
				'ViewEmail' => array($sEmail, '='),
				'IdUser' => array($iUserId, '='),
			)
		);
		$aOrderBy = array('FullName');
		$aContacts = $this->oEavManager->getEntities('CContact', $aViewAttrs, 0, 0, $aFilters, $aOrderBy);
		if (count($aContacts) > 0)
		{
			$oContact = $aContacts[0];
			$oContact->GroupsContacts = $this->getGroupContacts(null, $oContact->UUID);
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
			$aGroupContact = $this->getGroupContacts(null, $oContact->UUID);
			
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
					return $oGroupContact->UUID; 
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
	 * @param int $iLimit The upper limit for total number of contacts returned. Default value is **20**.
	 * @param array $aFilters
	 * @param string $sGroupUUID
	 * @param array $aContactUUIDs
	 * 
	 * @return array|bool
	 */
	public function getContacts($iSortField = EContactSortField::Name, $iSortOrder = ESortOrder::ASC,
		$iOffset = 0, $iLimit = 20, $aFilters = array(), $sGroupUUID = '', $aContactUUIDs = array())
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
		
		$sSortField = 'FullName';
		switch ($iSortField)
		{
			case EContactSortField::Email:
				$sSortField = 'ViewEmail';
				break;
			case EContactSortField::Frequency:
				$sSortField = 'Frequency';
				break;
		}
		
		$aViewAttrs = array();
		$aOrderBy = array($sSortField);
		return $this->oEavManager->getEntities('CContact', $aViewAttrs, $iOffset, $iLimit, 
				$aFilters, $aOrderBy, $iSortOrder, $aContactUUIDs);
	}

	/**
	 * Returns list of user's groups. 
	 * 
	 * @param int $iUserId User ID 
	 * 
	 * @return array|bool
	 */
	public function getGroups($iUserId, $aFilters = [])
	{
		$aViewAttrs = array();
		if (count($aFilters) > 0)
		{
			$aFilters['IdUser'] = array($iUserId, '=');
			$aFilters = array('$AND' => $aFilters);
		}
		else
		{
			$aFilters = array('IdUser' => array($iUserId, '='));
		}
		$aOrderBy = array('Name');
		return $this->oEavManager->getEntities('CGroup', $aViewAttrs, 0, 0, $aFilters, 'Name');
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
				$oGroupContact->ContactUUID = $oContact->UUID;
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
				$oGroupContact->GroupUUID = $oGroup->UUID;
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
				$aEntitiesUUIDs[] = $oGroupContact->UUID;
			}
		}
		
		return $this->oEavManager->deleteEntities($aEntitiesUUIDs);
	}

	public function getGroupContacts($sGroupUUID = null, $sContactUUID = null)
	{
		$aViewAttrs = array('GroupUUID', 'ContactUUID');
		$aFilters = array();
		if (is_string($sGroupUUID) && $sGroupUUID !== '')
		{
			$aFilters = array('GroupUUID' => $sGroupUUID);
		}
		if (is_string($sContactUUID) && $sContactUUID !== '')
		{
			$aFilters = array('ContactUUID' => $sContactUUID);
		}
		return $this->oEavManager->getEntities('CGroupContact', $aViewAttrs, 0, 0, $aFilters);
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
	 * @param string $sGroupUUID Group identifier to be used 
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
				$oGroupContact = \CGroupContact::createInstance('CGroupContact', $this->GetModule()->GetName());
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
	 * @param string $sGroupUUID Group identifier
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
				$aIdEntitiesToDelete[] = $oGroupContact->UUID;
			}
		}
		
		return $this->oEavManager->deleteEntities($aIdEntitiesToDelete);
	}
}
