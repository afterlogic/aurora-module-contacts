<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts;

use Aurora\Modules\Contacts\Classes\CTag;
use Aurora\Modules\Contacts\Enums\StorageType;
use Aurora\Modules\Contacts\Models\Group;
use Aurora\Modules\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @ignore
 */
class Manager extends \Aurora\System\Managers\AbstractManager
{
	private $oEavManager = null;

	/**
	 * @param \Aurora\System\Module\AbstractModule $oModule
	 */
	public function __construct(\Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct($oModule);

		if ($oModule instanceof \Aurora\System\Module\AbstractModule)
		{
			$this->oEavManager = \Aurora\System\Managers\Eav::getInstance();
		}
	}

	/**
	 *
	 * @param string $sUUID
	 * @return \Aurora\Modules\Contacts\Models\Contact
	 */
	public function getContact($sUUID)
	{
		return Contact::where('UUID', $sUUID)->first();
	}

	/**
	 *
	 * @param string $sEmail
	 * @return \Aurora\Modules\Contacts\Classes\Contact
	 */
	public function getContactByEmail($iUserId, $sEmail)
	{
		return Contact::where('IdUser', $iUserId)
			->where('ViewEmail', $sEmail)
			->orderBy('FullName')
			->first();
	}

	/**
	 * Returns group item identified by its ID.
	 *
	 * @param string $sUUID Group ID
	 *
	 * @return \Aurora\Modules\Contacts\Models\Group
	 */
	public function getGroup($sUUID)
	{
		return Group::firstWhere('UUID', $sUUID);
	}

	/**
	 * Returns group item identified by its name.
	 *
	 * @param string $sName Group name
	 *
	 * @return \Aurora\Modules\Contacts\Classes\Group
	 */
	public function getGroupByName($sName, $iUserId)
	{
		return Group::where('Name', $sName)->where('IdUser', $iUserId)->first();
	}

	/**
	 * Updates contact information. Using this method is required to finalize changes made to the contact object.
	 *
	 * @param \Aurora\Modules\Contacts\Models\Contact $oContact  Contact object to be updated
	 * @param bool $bUpdateFromGlobal
	 *
	 * @return bool
	 */
	public function updateContact($oContact)
	{
		$oContact->DateModified = date('Y-m-d H:i:s');
		$oContact->calculateETag();
		$res = $oContact->save();
		if ($res)
		{
			if ($oContact->Storage === 'personal' || $oContact->Storage === 'addressbook')
			{
				$this->updateCTag($oContact->IdUser, $oContact->getStorageWithId());
			}
			else
			{
				$this->updateCTag($oContact->IdTenant, $oContact->getStorageWithId());
			}
		}

		return $res;
	}

	/**
	 *
	 * @param type $oContact
	 */
	public function updateContactGroups($oContact)
	{
		$aGroupContact = $this->getGroupContacts(null, $oContact->UUID);

		$compare_func = function($oGroupContact1, $oGroupContact2) {
			if ($oGroupContact1->GroupUUID === $oGroupContact2->GroupUUID)
			{
				return 0;
			}
			if ($oGroupContact1->GroupUUID > $oGroupContact2->GroupUUID)
			{
				return -1;
			}
			return 1;
		};

		$aGroupContactToDelete = array_udiff($aGroupContact, $oContact->GroupsContacts, $compare_func);
		$aGroupContactUUIDsToDelete = array_map(
			function($oGroupContact) {
				return $oGroupContact->UUID;
			},
			$aGroupContactToDelete
		);
		$this->oEavManager->deleteEntities($aGroupContactUUIDsToDelete);

		$aGroupContactToAdd = array_udiff($oContact->GroupsContacts, $aGroupContact, $compare_func);
		foreach ($aGroupContactToAdd as $oGroupContact)
		{
			$this->oEavManager->saveEntity($oGroupContact);
		}
	}

	/**
	 * Updates group information. Using this method is required to finalize changes made to the group object.
	 *
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 *
	 * @return bool
	 */
	public function updateGroup($oGroup)
	{
		$res = false;
		if ($oGroup instanceof Models\Group)
		{
			$res = $oGroup->save();
			if ($res)
			{
				$this->updateCTag($oGroup->IdUser, 'personal');
				// foreach ($oGroup->GroupContacts as $oGroupContact)
				// {
				// 	$oGroupContact->GroupUUID = $oGroup->UUID;
				// 	$res = $oGroupContact->save();
				// }
			}
		}

		return $res;
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
	public function getContactsCount($Filters = null, $sGroupUUID = '')
	{
		$oQuery = ($Filters instanceof Builder) ? $Filters : Contact::query();
		if (!empty($sGroupUUID))
		{
			$oGroup = Group::firstWhere('UUID', $sGroupUUID);
			if ($oGroup) {
				$oQuery->whereHas('Groups', function ($oSubQuery) use ($oGroup) {
					return $oSubQuery->where('Groups.Id', $oGroup->Id);
				});
			}
		}
		return $oQuery->count();
	}

	/**
	 * Returns list of contacts within specified range, sorted according to specified requirements.
	 *
	 * @param int $iSortField Sort field. Accepted values:
	 *
	 *		\Aurora\Modules\Contacts\Enums\SortField::Name
	 *		\Aurora\Modules\Contacts\Enums\SortField::Email
	 *		\Aurora\Modules\Contacts\Enums\SortField::Frequency
	 *
	 * Default value is **\Aurora\Modules\Contacts\Enums\SortField::Email**.
	 * @param int $iSortOrder Sorting order. Accepted values:
	 *
	 *		\Aurora\System\Enums\SortOrder::ASC
	 *		\Aurora\System\Enums\SortOrder::DESC,
	 *
	 * for ascending and descending respectively. Default value is **\Aurora\System\Enums\SortOrder::ASC**.
	 * @param int $iOffset Ordinal number of the contact item the list stars with. Default value is **0**.
	 * @param int $iLimit The upper limit for total number of contacts returned. Default value is **20**.
	 * @param Builder $oFilters
	 * @param array $aViewAttrs
	 *
	 * @return array|bool
	 */
	public function getContacts($iSortField = \Aurora\Modules\Contacts\Enums\SortField::Name, $iSortOrder = \Aurora\System\Enums\SortOrder::ASC,
		$iOffset = 0, $iLimit = 20, $oFilters = null, $aViewAttrs = array())
	{
		$sSortField = 'FullName';
		switch ($iSortField)
		{
			case \Aurora\Modules\Contacts\Enums\SortField::Email:
				$sSortField = 'ViewEmail';
				break;
			case \Aurora\Modules\Contacts\Enums\SortField::Frequency:
				$sSortField = 'AgeScore';
				$oFilters->select(Capsule::schema()->getConnection()->raw('*, (Frequency/CEIL(DATEDIFF(CURDATE() + INTERVAL 1 DAY, DateModified)/30)) as AgeScore'));
				break;
		}
		if ($iOffset > 0) {
			$oFilters = $oFilters->offset($iOffset);
		}
		if ($iLimit > 0) {
			$oFilters = $oFilters->limit($iLimit);
		}

		return $oFilters
			->orderBy($sSortField, $iSortOrder === \Aurora\System\Enums\SortOrder::ASC ? 'asc' : 'desc')
			->get();
	}

		/**
	 * Returns list of contacts within specified range, sorted according to specified requirements.
	 *
	 * @param int $iSortField Sort field. Accepted values:
	 *
	 *		\Aurora\Modules\Contacts\Enums\SortField::Name
	 *		\Aurora\Modules\Contacts\Enums\SortField::Email
	 *		\Aurora\Modules\Contacts\Enums\SortField::Frequency
	 *
	 * Default value is **\Aurora\Modules\Contacts\Enums\SortField::Email**.
	 * @param int $iSortOrder Sorting order. Accepted values:
	 *
	 *		\Aurora\System\Enums\SortOrder::ASC
	 *		\Aurora\System\Enums\SortOrder::DESC,
	 *
	 * for ascending and descending respectively. Default value is **\Aurora\System\Enums\SortOrder::ASC**.
	 * @param int $iOffset Ordinal number of the contact item the list stars with. Default value is **0**.
	 * @param int $iLimit The upper limit for total number of contacts returned. Default value is **20**.
	 * @param Builder $oFilters
	 * @param array $aViewAttrs
	 *
	 * @return array|bool
	 */
	public function getContactsAsArray($iSortField = \Aurora\Modules\Contacts\Enums\SortField::Name, $iSortOrder = \Aurora\System\Enums\SortOrder::ASC,
		$iOffset = 0, $iLimit = 20, $oFilters = null, $aViewAttrs = array())
	{
		return $this->getContacts($iSortField, $iSortOrder, $iOffset, $iLimit, $oFilters, $aViewAttrs)->toArray();
	}

	/**
	 * Returns uid list of contacts.

	 * @param Builder $oFilters

	 *
	 * @return array|bool
	 */
	public function getContactUids(Builder $oFilters)
	{
		return $oFilters->get()->map(
			function($oContact) {
				return $oContact->UUID;
			}
		)->toArray();
	}

	/**
	 * Returns list of user's groups.
	 *
	 * @param int $iUserId User ID
	 *
	 * @return array|bool
	 */
	public function getGroups($iUserId, $oFilters = null)
	{
		$oQuery = $oFilters instanceof Builder ? $oFilters : Group::query();
		return $oQuery->where('IdUser', $iUserId)->orderBy('Name')->get();
	}

	/**
	 * The method is used for saving created contact to the database.
	 *
	 * @param \Aurora\Modules\Contacts\Models\Contact $oContact
	 *
	 * @return bool
	 */
	public function createContact($oContact)
	{
		$res = false;
		
		$oQuery = Contact::where('IdUser', $oContact->IdUser)
			->where('UUID', $oContact->UUID);

		if (!$oContact->Storage === StorageType::AddressBook) {
			$oQuery = $oQuery->where('AddressBookId', $oContact->AddressBookId);
		}

		if (!$oQuery->exists()) {

			$oContact->DateModified = date('Y-m-d H:i:s');
			$oContact->calculateETag();
			$res = $oContact->save();

			if ($res)
			{
				if ($oContact->Storage === 'personal' || $oContact->Storage === 'addressbook')
				{
					$this->updateCTag($oContact->IdUser, $oContact->getStorageWithId());
				}
				else
				{
					$this->updateCTag($oContact->IdTenant, $oContact->getStorageWithId());
				}

				// foreach ($oContact->GroupsContacts as $oGroupContact)
				// {
				// 	$oGroupContact->ContactUUID = $oContact->UUID;
				// 	$oGroupContact->save();
				// }
			}
		}

		return $res;
	}

	/**
	 * The method is used for saving created group to the database.
	 *
	 * @param \Aurora\Modules\Contacts\Models\Group $oGroup
	 *
	 * @return bool
	 */
	public function createGroup($oGroup)
	{
		return $oGroup->save();
	}

	/**
	 * Deletes one or multiple contacts from address book.
	 *
	 * @param array $aContactUUIDs Array of strings
	 *
	 * @return bool
	 */
	public function deleteContacts($iIdUser, $sStorage, $aContactUUIDs)
	{
		$mResult = !!Contact::whereIn('UUID', $aContactUUIDs)->delete();

		if ($mResult) {
			$oUser = \Aurora\Modules\Core\Module::getInstance()->GetUserUnchecked($iIdUser);
			if ($oUser instanceof \Aurora\Modules\Core\Models\User) {
				$iIdUser = $sStorage === 'personal' || $sStorage === 'addressbook' ? $oUser->Id : $oUser->IdTenant;
			}
//			$this->updateCTag($iIdUser, $sStorage);
		}
		return $mResult;
	}

	/**
	 * Deletes specific groups from address book.
	 *
	 * @param array $aGroupUUIDs array of strings - groups identifiers.
	 *
	 * @return bool
	 */
	public function deleteGroups($aGroupUUIDs)
	{
		$oQuery = Group::whereIn('UUID', $aGroupUUIDs);
		$aGroups = $oQuery->get();
		foreach ($aGroups as $oGroup) {
			foreach ($oGroup->Contacts as $oContact) {
				if ($oContact->Storage === 'personal' || $oContact->Storage === 'addressbook') {
					$this->updateCTag($oContact->IdUser, $oContact->getStorageWithId());
				} else {
					$this->updateCTag($oContact->IdTenant, $oContact->getStorageWithId());
				}
				$oContact->DateModified = date('Y-m-d H:i:s');
				$oContact->calculateETag();
				$oContact->save();
			}
		}
		return !!$oQuery->delete();
	}

	public function deleteGroupsByUserId($iUserId)
	{
		return !!Group::where('IdUser', $iUserId)->delete();
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

		$oGroup = $this->getGroup($sGroupUUID);
		$aContacts = Contact::whereIn('UUID', $aContactUUIDs)->get();
		$oGroup->Contacts()->sync(
			$oGroup->Contacts->merge(
				$aContacts
			)->map(function ($oContact) {
				return $oContact->Id;
			})
		);
		$aContacts->each(function ($oContact) {
			if ($oContact->Storage === 'personal' || $oContact->Storage === 'addressbook') {
				$this->updateCTag($oContact->IdUser, $oContact->getStorageWithId());
			} else {
				$this->updateCTag($oContact->IdTenant, $oContact->getStorageWithId());
			}

			$oContact->DateModified = date('Y-m-d H:i:s');
			$oContact->calculateETag();
			$oContact->save();
		});

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
		$oGroup = $this->getGroup($sGroupUUID);
		$aContacts = Contact::whereIn('UUID', $aContactUUIDs)->get();
		$aContactIds = $aContacts->map(function ($oContact) {
			return $oContact->Id;
		});
		$oGroup->Contacts()->detach($aContactIds);
		$aContacts->each(function ($oContact) {
			if ($oContact->Storage === 'personal' || $oContact->Storage === 'addressbook') {
				$this->updateCTag($oContact->IdUser, $oContact->getStorageWithId());
			} else {
				$this->updateCTag($oContact->IdTenant, $oContact->getStorageWithId());
			}

			$oContact->DateModified = date('Y-m-d H:i:s');
			$oContact->calculateETag();
			$oContact->save();
		});

		return true;
	}

	public function getCTags($iUserId, $Storage)
	{
		return Models\CTag::where([
			['UserId', '=', $iUserId],
			['Storage', '=', $Storage]
		])->get();
	}


	public function getCTag($iUserId, $Storage)
	{
		return Models\CTag::firstWhere([
			['UserId', '=', $iUserId],
			['Storage', '=', $Storage]
		]);

	}

	public function updateCTag($iUserId, $Storage)
	{
		$oCTag = $this->getCTag($iUserId, $Storage);
		if ($oCTag instanceof Models\CTag) {
			$oCTag->increment('CTag');
		} else {
			$oCTag = new Models\CTag();
			$oCTag->UserId = $iUserId;
			$oCTag->Storage = $Storage;
			$oCTag->CTag = 1;
			$oCTag->save();
		}
	}

	public function deleteContactsByUserId($iUserId, $Storage = null)
	{
		$aFilter = [
			['IdUser', '=', $iUserId]
		];
		if (isset($Storage)) {
			$aFilter[] = ['Storage', '=', $Storage];
		}
		Models\Contact::where($aFilter)->delete();
	}

	public function deleteCTagsByUserId($iUserId, $Storage = null)
	{
		$aFilter = [
			['UserId', '=', $iUserId]
		];
		if (isset($Storage)) {
			$aFilter[] = ['Storage', '=', $Storage];
		}
		Models\CTag::where($aFilter)->delete();
	}
}
