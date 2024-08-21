<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts;

use Afterlogic\DAV\Backend;
use Afterlogic\DAV\Constants;
use Aurora\Api;
use Aurora\Modules\Contacts\Enums\Access;
use Aurora\Modules\Contacts\Enums\StorageType;
use Aurora\Modules\Contacts\Enums\SortField;
use Aurora\System\Enums\SortOrder;
use Aurora\Modules\Contacts\Classes\Contact;
use Aurora\Modules\Contacts\Classes\VCard\Helper;
use Aurora\Modules\Contacts\Models\ContactCard;
use Aurora\Modules\Contacts\Classes\Group;
use Aurora\Modules\Core\Module as CoreModule;
use Aurora\System\Exceptions\ApiException;
use Aurora\System\Notifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Capsule\Manager as Capsule;
use Sabre\DAV\UUIDUtil;
use Sabre\DAV\PropPatch;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    protected $aImportExportFormats = ['csv', 'vcf'];

    protected $userPublicIdToDelete = null;

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     * Initializes Contacts Module.
     *
     * @ignore
     */
    public function init()
    {
        $this->subscribeEvent('Mail::AfterUseEmails', array($this, 'onAfterUseEmails'));
        $this->subscribeEvent('Mail::GetBodyStructureParts', array($this, 'onGetBodyStructureParts'));
        $this->subscribeEvent('Core::DeleteUser::before', array($this, 'onBeforeDeleteUser'));
        $this->subscribeEvent('Core::DeleteUser::after', array($this, 'onAfterDeleteUser'));

        $this->subscribeEvent('System::toResponseArray::after', array($this, 'onContactToResponseArray'));

        $this->denyMethodsCallByWebApi([
            'UpdateContactObject'
        ]);
    }

    /***** public functions might be called with web API *****/
    /**
     * @apiDefine Contacts Contacts Module
     * Main Contacts module. It provides PHP and Web APIs for managing contacts.
     */

    /**
     * @api {post} ?/Api/ GetSettings
     * @apiName GetSettings
     * @apiGroup Contacts
     * @apiDescription Obtains list of module settings for authenticated user.
     *
     * @apiHeader {string} [Authorization] "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=GetSettings} Method Method name
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetSettings'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name.
     * @apiSuccess {string} Result.Method Method name.
     * @apiSuccess {mixed} Result.Result List of module settings in case of success, otherwise **false**.
     * @apiSuccess {int} Result.Result.ContactsPerPage=20 Count of contacts that will be displayed on one page.
     * @apiSuccess {string} Result.Result.ImportContactsLink=&quot;&quot; Link for learning more about CSV format.
     * @apiSuccess {array} Result.Result.Storages='[]' List of storages wich will be shown in the interface.
     * @apiSuccess {array} Result.Result.ImportExportFormats='[]' List of formats that can be used for import and export contacts.
     * @apiSuccess {array} Result.Result.\Aurora\Modules\Contacts\Enums\PrimaryEmail='[]' Enumeration with primary email values.
     * @apiSuccess {array} Result.Result.\Aurora\Modules\Contacts\Enums\PrimaryPhone='[]' Enumeration with primary phone values.
     * @apiSuccess {array} Result.Result.\Aurora\Modules\Contacts\Enums\PrimaryAddress='[]' Enumeration with primary address values.
     * @apiSuccess {array} Result.Result.\Aurora\Modules\Contacts\Enums\SortField='[]' Enumeration with sort field values.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetSettings',
     *	Result: { ContactsPerPage: 20, ImportContactsLink: '', Storages: ['personal', 'team'],
     * ImportExportFormats: ['csv', 'vcf'], \Aurora\Modules\Contacts\Enums\PrimaryEmail: {'Personal': 0, 'Business': 1, 'Other': 2},
     * \Aurora\Modules\Contacts\Enums\PrimaryPhone: {'Mobile': 0, 'Personal': 1, 'Business': 2},
     * \Aurora\Modules\Contacts\Enums\PrimaryAddress: {'Personal': 0, 'Business': 1},
     * \Aurora\Modules\Contacts\Enums\SortField: {'Name': 1, 'Email': 2, 'Frequency': 3} }
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetSettings',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Obtains list of module settings for authenticated user.
     * @return array
     */
    public function GetSettings()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
        $oUser = \Aurora\System\Api::getAuthenticatedUser();

        $aResult = [
            'AllowAddressBooksManagement' => $this->oModuleSettings->AllowAddressBooksManagement,
            'ImportContactsLink' => $this->oModuleSettings->ImportContactsLink,
            'PrimaryEmail' => (new Enums\PrimaryEmail())->getMap(),
            'PrimaryPhone' => (new Enums\PrimaryPhone())->getMap(),
            'PrimaryAddress' => (new Enums\PrimaryAddress())->getMap(),
            'SortField' => (new SortField())->getMap(),
            'ImportExportFormats' => $this->aImportExportFormats,
            'SaveVcfServerModuleName' => \Aurora\System\Api::GetModuleManager()->ModuleExists('DavContacts') ? 'DavContacts' : '',
            'ContactsPerPage' => $this->oModuleSettings->ContactsPerPage,
            'ContactsSortBy' => $this->oModuleSettings->ContactsSortBy
        ];

        if ($oUser && $oUser->isNormalOrTenant()) {
            if (null !== $oUser->getExtendedProp(self::GetName() . '::ContactsPerPage')) {
                $aResult['ContactsPerPage'] = $oUser->getExtendedProp(self::GetName() . '::ContactsPerPage');
            }

            $aResult['Storages'] = self::Decorator()->GetStorages();
        }

        return $aResult;
    }

    public function IsDisplayedStorage($Storage)
    {
        return true;
    }

    /**
     * @deprecated since version 9.7.2
     */
    public function GetContactStorages()
    {
        return $this->Decorator()->GetStorages();
    }

    public function GetStorageDisplayName($Storage)
    {
        $result = '';

        switch($Storage) {
            case Enums\StorageType::All:
                $result = $this->i18N('LABEL_STORAGE_ALL');
                break;
            case Enums\StorageType::Personal:
                $result = $this->i18N('LABEL_STORAGE_PERSONAL');
                break;
            case Enums\StorageType::Collected:
                $result = $this->i18N('LABEL_STORAGE_COLLECTED');
                break;
            case Enums\StorageType::Team:
                $result = $this->i18N('LABEL_STORAGE_TEAM');
                break;
            case Enums\StorageType::Shared:
                $result = $this->i18N('LABEL_STORAGE_SHARED');
                break;
        }

        return $result;
    }

    protected function GetStorageDisplayNameOverride($sStorageName, $sSotrageId)
    {
        $result = $sStorageName;

        switch(true) {
            case $sSotrageId === Enums\StorageType::Personal && $sStorageName === Constants::ADDRESSBOOK_DEFAULT_DISPLAY_NAME:
                $result = $this->i18N('LABEL_STORAGE_PERSONAL');
                break;
            case $sSotrageId === Enums\StorageType::Collected && $sStorageName === Constants::ADDRESSBOOK_COLLECTED_DISPLAY_NAME:
                $result = $this->i18N('LABEL_STORAGE_COLLECTED');
                break;
            case $sSotrageId === Enums\StorageType::Team && $sStorageName === Constants::ADDRESSBOOK_TEAM_DISPLAY_NAME:
                $result = $this->i18N('LABEL_STORAGE_TEAM');
                break;
            case $sSotrageId === Enums\StorageType::Shared && $sStorageName === Constants::ADDRESSBOOK_SHARED_WITH_ALL_DISPLAY_NAME:
                $result = $this->i18N('LABEL_STORAGE_SHARED');
                break;
        }

        return $result;
    }

    public function GetStorages()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $iUserId = \Aurora\System\Api::getAuthenticatedUserId();

        $aAddressBooks = $this->Decorator()->GetAddressBooks($iUserId);

        foreach ($aAddressBooks as &$oAddressBook) {
            $oAddressBook['DisplayName'] = $this->GetStorageDisplayNameOverride($oAddressBook['DisplayName'], $oAddressBook['Id']);
        }

        $aStoragesOrder = [
            StorageType::Personal,
            StorageType::Collected,
            StorageType::Shared,
            StorageType::Team
        ];
        return $this->sortAddressBooks($aAddressBooks, $aStoragesOrder);
    }

    protected function sortAddressBooks($aAddressBooks, $aOrder = [])
    {
        $priority_books = array();
        $non_priority_books = array();

        // Loop through the address books and check their ids
        foreach ($aAddressBooks as $book) {
            $id = $book['Id'];

            if (in_array($id, $aOrder)) {
                $priority_books[] = $book;
            } else {
                $non_priority_books[] = $book;
            }
        }

        // Sort the priority books array by the order of the priority ids array
        usort($priority_books, function ($a, $b) use ($aOrder) {
            // Get the index of the ids in the priority ids array
            $index_a = array_search($a['Id'], $aOrder);
            $index_b = array_search($b['Id'], $aOrder);

            // Compare the indexes
            return $index_a - $index_b;
        });

        // Sort the non-priority books array by the DisplayName property in ascending order
        usort($non_priority_books, function ($a, $b) {
            // Compare the names
            return strcmp($a['DisplayName'], $b['DisplayName']);
        });

        // Merge the two arrays and return the result
        return array_merge($priority_books, $non_priority_books);
    }

    protected function _getContacts($iSortField = SortField::Name, $iSortOrder = SortOrder::ASC, $iOffset = 0, $iLimit = 20, $oFilters = null)
    {
        $sSortField = 'FullName';
        $sSortFieldSecond = 'ViewEmail';
        $sSortOrder = $iSortOrder === SortOrder::ASC ? 'asc' : 'desc';
        switch ($iSortField) {
            case SortField::Email:
                $sSortField = 'ViewEmail';
                $sSortFieldSecond = 'FullName';
                break;
            case SortField::Frequency:
                $sSortField = 'AgeScore';
                // $oFilters->select(Capsule::connection()->raw('*, (Frequency/CEIL(DATEDIFF(CURDATE() + INTERVAL 1 DAY, DateModified)/30)) as AgeScore'));
                break;
            case SortField::FirstName:
                $sSortField = 'FirstName';
                break;
            case SortField::LastName:
                $sSortField = 'LastName';
                break;
            case SortField::Name:
                $sSortField = 'FullName';
                break;
        }
        if ($iOffset > 0) {
            $oFilters->offset($iOffset);
        }
        if ($iLimit > 0) {
            $oFilters->limit($iLimit);
        }

        $oFilters
            ->orderBy(Capsule::connection()->raw("CASE WHEN `$sSortField` = '' THEN 1 ELSE 0 END"))
            ->orderBy($sSortField, $sSortOrder)
            ->orderBy($sSortFieldSecond, $sSortOrder)
        ;

        return $oFilters->get();
    }

    /**
     * @api {post} ?/Api/ UpdateSettings
     * @apiName UpdateSettings
     * @apiGroup Contacts
     * @apiDescription Updates module's settings - saves them to config.json file.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=UpdateSettings} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **ContactsPerPage** *int* Count of contacts per page.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'UpdateSettings',
     *	Parameters: '{ ContactsPerPage: 10 }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {bool} Result.Result Indicates if settings were updated successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'UpdateSettings',
     *	Result: true
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'UpdateSettings',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Updates module's settings - saves them to config.json file or to user settings in db.
     * @param int $ContactsPerPage Count of contacts per page.
     * @return boolean
     */
    public function UpdateSettings($ContactsPerPage)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $bResult = false;

        $oUser = \Aurora\System\Api::getAuthenticatedUser();
        if ($oUser) {
            if ($oUser->isNormalOrTenant()) {
                $oUser->setExtendedProp(self::GetName() . '::ContactsPerPage', $ContactsPerPage);
                return CoreModule::Decorator()->UpdateUserObject($oUser);
            }
            if ($oUser->isAdmin()) {
                $this->setConfig('ContactsPerPage', $ContactsPerPage);
                $bResult = $this->saveModuleConfig();
            }
        }

        return $bResult;
    }

    /**
     * @api {post} ?/Api/ Export
     * @apiName Export
     * @apiGroup Contacts
     * @apiDescription Exports specified contacts to a file with specified format.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=Export} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **Format** *string* File format that should be used for export.<br>
     * &emsp; **Filters** *array* Filters for obtaining specified contacts.<br>
     * &emsp; **GroupUUID** *string* UUID of group that should contain contacts for export.<br>
     * &emsp; **ContactUUIDs** *array* List of UUIDs of contacts that should be exported.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'Export',
     *	Parameters: '{ Format: "csv", Filters: [], GroupUUID: "", ContactUUIDs: [] }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {mixed} Result.Result Contents of CSV or VCF file in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * contents of CSV or VCF file
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'Export',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Exports specified contacts to a file with specified format.
     * @param string $Format File format that should be used for export.
     * @param Builder $Filters Filters for obtaining specified contacts.
     * @param string $GroupUUID UUID of group that should contain contacts for export.
     * @param array $ContactUUIDs List of UUIDs of contacts that should be exported.
     * @param bool $AddressBookId
     */
    public function Export($UserId, $Storage, $Format, Builder $Filters = null, $GroupUUID = '', $ContactUUIDs = [], $AddressBookId = null)
    {
        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $sOutput = '';

        if (!empty($GroupUUID)) {
            $oGroup = self::Decorator()->GetGroup($UserId, $GroupUUID);
            if ($oGroup) {
                $ContactUUIDs = (is_array($ContactUUIDs) && count($ContactUUIDs) > 0) ? array_intersect(
                    $oGroup->Contacts,
                    $ContactUUIDs
                ) : $oGroup->Contacts;
            }
        }

        if (is_array($ContactUUIDs)) {
            $query = $this->getGetContactsQueryBuilder($UserId, $Storage, $AddressBookId, $Filters, false, true);
            if ($Format === 'vcf') {
                if (count($ContactUUIDs) > 0) {
                    $query = $query->whereIn('contacts_cards.CardId', $ContactUUIDs);
                }
                $rows = $query->select('carddata')->pluck('carddata')->toArray();
                foreach ($rows as $row) {
                    $sOutput .= $row;
                }
            } elseif ($Format === 'csv') {
                $oSync = new Classes\Csv\Sync();
                if (count($ContactUUIDs) === 0) {
                    $ContactUUIDs = $query->select('CardId')->pluck('CardId')->toArray();
                }
                $aContacts = self::Decorator()->GetContactsByUids($UserId, $ContactUUIDs);
                $sOutput = $oSync->Export($aContacts);
            }
        }

        if (is_string($sOutput) && !empty($sOutput)) {
            $fileName = 'export';
            $aStorages = self::Decorator()->GetStorages();
            foreach ($aStorages as $aStorage) {
                if ($aStorage['Id'] === $Storage) {
                    $fileName = isset($aStorage['DisplayName']) ? $aStorage['DisplayName'] : $aStorage['Id'];
                    break;
                }
            }

            header('Pragma: public');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $fileName . '.' . $Format . '";');
            header('Content-Transfer-Encoding: binary');
        }

        echo $sOutput;
    }

    public function GetContactAsVCF($UserId, $Contact)
    {
        Api::CheckAccess($UserId);
        $oVCard = new \Sabre\VObject\Component\VCard();
        Classes\VCard\Helper::UpdateVCardFromContact($Contact, $oVCard);
        return $oVCard->serialize();
    }

    /**
     * @api {post} ?/Api/ GetGroups
     * @apiName GetGroups
     * @apiGroup Contacts
     * @apiDescription Returns all groups for authenticated user.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=GetGroups} Method Method name
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetGroups'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {mixed} Result.Result List of groups in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetGroups',
     *	Result: [{ City: '', Company: '', Contacts: [], Country: '', Email: '', Fax: '', IdUser: 3,
     * IsOrganization: false, Name: 'group_name', Phone: '', State: '', Street: '', UUID: 'uuid_value',
     * Web: '', Zip: '' }]
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetGroups',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Returns all groups for authenticated user.
     * @return array
     */
    public function GetGroups($UserId = null, $UUIDs = [], $Search = '')
    {
        $result = [];
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        Api::CheckAccess($UserId);

        $aArgs = [
            'UserId' => $UserId,
            'Storage' => StorageType::Personal,
            'AddressBookId' => 0
        ];

        if ($this->populateContactArguments($aArgs)) {
            $query = Capsule::connection()->table('contacts_cards')
                ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
                ->select('adav_cards.id as UUID', 'carddata');

            $query->where(function ($whereQuery) use ($UserId, $aArgs, $query) {
                $this->prepareFiltersFromStorage($UserId, StorageType::Personal, $aArgs['AddressBookId'], $query, $whereQuery);
            })->where('IsGroup', true);

            if (is_array($UUIDs) && count($UUIDs) > 0) {
                $query->whereIn('adav_cards.id', $UUIDs);
            }

            if (!empty($Search)) {
                $query->where('FullName', 'LIKE', "%$Search%");
            }

            $groups = $query->get();

            foreach ($groups as $group) {
                $groupObj = new Group();
                $groupObj->IdUser = $UserId;
                $groupObj->populate(Helper::GetGroupDataFromVcard(
                    \Sabre\VObject\Reader::read(
                        $group->carddata,
                        \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
                    ),
                    $group->UUID
                ));
                $result[] = $groupObj;
            }
        }

        return $result;
    }

    /**
     * @api {post} ?/Api/ GetGroup
     * @apiName GetGroup
     * @apiGroup Contacts
     * @apiDescription Returns group with specified UUID.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=GetGroup} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **$UUID** *string* UUID of group to return.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetGroup',
     *	Parameters: '{ UUID: "group_uuid" }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {mixed} Result.Result Group object in case of success, otherwise **false**.
     * @apiSuccess {string} Result.Result.City=&quot;&quot;
     * @apiSuccess {string} Result.Result.Company=&quot;&quot;
     * @apiSuccess {array} Result.Result.Contacts='[]'
     * @apiSuccess {string} Result.Result.Country=&quot;&quot;
     * @apiSuccess {string} Result.Result.Email=&quot;&quot;
     * @apiSuccess {string} Result.Result.Fax=&quot;&quot;
     * @apiSuccess {int} Result.Result.IdUser=0
     * @apiSuccess {bool} Result.Result.IsOrganization=false
     * @apiSuccess {string} Result.Result.Name=&quot;&quot;
     * @apiSuccess {string} Result.Result.Phone=&quot;&quot;
     * @apiSuccess {string} Result.Result.Street=&quot;&quot;
     * @apiSuccess {string} Result.Result.UUID=&quot;&quot;
     * @apiSuccess {string} Result.Result.Web=&quot;&quot;
     * @apiSuccess {string} Result.Result.Zip=&quot;&quot;
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetGroup',
     *	Result: { City: '', Company: 'group_company', Contacts: [], Country: '', Email: '', Fax: '',
     * IdUser: 3, IsOrganization: true, Name: 'group_name', Phone:'', State:'', Street:'',
     * UUID: 'group_uuid', Web:'', Zip: '' }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetGroup',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Returns group with specified UUID.
     * @param string $UUID UUID of group to return.
     * @return \Aurora\Modules\Contacts\Classes\Group
     */
    public function GetGroup($UserId, $UUID)
    {
        $mResult = false;

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        Api::CheckAccess($UserId);

        $oUser = Api::getUserById($UserId);
        if ($oUser instanceof \Aurora\Modules\Core\Models\User) {
            $query = Capsule::connection()->table('contacts_cards')
                ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
                ->join('adav_addressbooks', 'adav_cards.addressbookid', '=', 'adav_addressbooks.id')
                ->select('adav_cards.id as card_id', 'adav_cards.uri as card_uri', 'adav_addressbooks.id as addressbook_id', 'carddata');

            $aArgs = [
                'UUID' => $UUID,
                'UserId' => $UserId
            ];

            $query->where(function ($q) use ($aArgs, $query) {
                $aArgs['Query'] = $query;
                $this->broadcastEvent(self::GetName() . '::ContactQueryBuilder', $aArgs, $q);
            });

            $row = $query->where('contacts_cards.IsGroup', true)->first();
            if ($row) {
                if (!self::Decorator()->CheckAccessToAddressBook($oUser, $row->addressbook_id, Access::Read)) {
                    throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
                }

                $mResult = new Group();
                $mResult->IdUser = $UserId;
                $mResult->Id = $row->card_id;

                $mResult->populate(
                    Helper::GetGroupDataFromVcard(
                        \Sabre\VObject\Reader::read(
                            $row->carddata,
                            \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
                        ),
                        $row->card_uri
                    )
                );

                $mResult->UUID = $UUID;
            }
        }

        return $mResult;
    }

    /**
     * @api {post} ?/Api/ GetContacts
     * @apiName GetContacts
     * @apiGroup Contacts
     * @apiDescription Returns list of contacts for specified parameters.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=GetContacts} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **Offset** *int* Offset of contacts list.<br>
     * &emsp; **Limit** *int* Limit of result contacts list.<br>
     * &emsp; **SortField** *int* Name of field order by.<br>
     * &emsp; **SortOrder** *int* Sorting direction.<br>
     * &emsp; **Storage** *string* Storage value.<br>
     * &emsp; **Search** *string* Search string.<br>
     * &emsp; **GroupUUID** *string* UUID of group that should contain all returned contacts.<br>
     * &emsp; **Filters** *array* Other conditions for obtaining contacts list.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetContacts',
     *	Parameters: '{ Offset: 0, Limit: 20, SortField: 1, SortOrder: 0, Storage: "personal",
     *		Search: "", GroupUUID: "", Filters: [] }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {mixed} Result.Result Object with contacts data in case of success, otherwise **false**.
     * @apiSuccess {int} Result.Result.ContactCount Count of contacts that are obtained with specified conditions.
     * @apiSuccess {array} Result.Result.List List of contacts objects.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetContacts',
     *	Result: '{ "ContactCount": 6, "List": [{ "UUID": "contact_uuid", "IdUser": 3, "Name": "",
     *		"Email": "contact@email.com", "Storage": "personal" }] }'
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetContacts',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Returns list of contacts for specified parameters.
     * @param string $Storage Storage type of contacts.
     * @param int $Offset Offset of contacts list.
     * @param int $Limit Limit of result contacts list.
     * @param int $SortField Name of field order by.
     * @param int $SortOrder Sorting direction.
     * @param string $Search Search string.
     * @param string $GroupUUID UUID of group that should contain all returned contacts.
     * @param Builder $Filters Other conditions for obtaining contacts list.
     * @param bool $WithGroups Indicates whether contact groups should be included in the contact list
     * @param bool $WithoutTeamContactsDuplicates Do not show a contact from the global address book if the contact with the same email address already exists in personal address book
     * @param bool $Suggestions
     * @param bool $AddressBookId
     * @return array
     */
    public function GetContacts($UserId, $Storage = '', $Offset = 0, $Limit = 20, $SortField = SortField::Name, $SortOrder = SortOrder::ASC, $Search = '', $GroupUUID = '', Builder $Filters = null, $WithGroups = false, $WithoutTeamContactsDuplicates = false, $Suggestions = false, $AddressBookId = null)
    {
        // $Storage is used by subscribers to prepare filters.
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        Api::CheckAccess($UserId);

        $oUser = Api::getUserById($UserId);
        $aContacts = [];
        if (self::Decorator()->CheckAccessToAddressBook($oUser, $AddressBookId, Access::Read)) {
            $query = $this->getGetContactsQueryBuilder($UserId, $Storage, $AddressBookId, $Filters, $Suggestions);

            if (!empty($Search)) {
                $query = $query->where(function ($query) use ($Search) {
                    $query->where('FullName', 'LIKE', "%$Search%")
                    ->orWhere('PersonalEmail', 'LIKE', "%$Search%")
                    ->orWhere('BusinessEmail', 'LIKE', "%$Search%")
                    ->orWhere('OtherEmail', 'LIKE', "%$Search%")
                    ->orWhere('BusinessCompany', 'LIKE', "%$Search%");
                });
            }

            if (!empty($GroupUUID)) {
                $oGroup = self::Decorator()->GetGroup($UserId, $GroupUUID);
                if ($oGroup) {
                    $contacts = $oGroup->Contacts;
                    if (count($contacts) === 0) {
                        $contacts = [null];
                    }
                    $query->whereIn('adav_cards.id', $contacts);
                }
            }

            $count = $query->count();

            $aContacts = $this->_getContacts($SortField, $SortOrder, $Offset, $Limit, $query)->toArray();

            $aContactsColection = collect($aContacts);
            if ($Storage === StorageType::All) {
                $personalContacsCollection = $aContactsColection->filter(function ($aContact) {
                    return (isset($aContact['IsTeam'], $aContact['Shared']) && !$aContact['IsTeam'] && !$aContact['Shared']);
                });

                if ($WithoutTeamContactsDuplicates) {
                    foreach ($aContacts as $key => $aContact) {
                        $sViewEmail = $aContact['ViewEmail'];
                        if (isset($aContact['IsTeam']) && $aContact['IsTeam'] && $personalContacsCollection->unique()->contains('ViewEmail', $sViewEmail)) {
                            unset($aContacts[$key]);
                        } elseif (isset($aContact['Auto']) && $aContact['Auto']) { // is collected contact
                            foreach ($aContacts as $subKey => $aSubContact) {
                                if (isset($aContact['IsTeam']) && $aContact['IsTeam'] && $aSubContact['ViewEmail'] === $sViewEmail) {
                                    $aContacts[$subKey]['AgeScore'] = $aContacts[$key]['AgeScore'];
                                    unset($aContacts[$key]);
                                }
                                if (isset($aContact['IsTeam']) && !$aContact['IsTeam'] &&
                                    isset($aContact['Shared']) && !$aContact['Shared'] &&
                                    isset($aContact['Auto']) && !$aContact['Auto'] &&
                                    $aSubContact['ViewEmail'] === $sViewEmail) {
                                    unset($aContacts[$key]);
                                }
                            }
                        }
                    }
                } else {
                    foreach ($aContacts as $key => $aContact) {
                        $sViewEmail = $aContact['ViewEmail'];

                        if (isset($aContact['IsTeam']) && $aContact['IsTeam']) {
                            $personalContact = $personalContacsCollection->unique()->filter(function ($contact) use ($sViewEmail) {
                                return strtolower($contact['ViewEmail']) === strtolower($sViewEmail);
                            })->first(); // Find collected contact with same email

                            if ($personalContact) {
                                $aContacts[$key]['Frequency'] = $personalContact['Frequency'];

                                if (isset($personalContact['Auto']) && $personalContact['Auto']) { // is collected contact
                                    $aContacts = array_filter($aContacts, function ($contact) use ($sViewEmail) {
                                        return (strtolower($contact['ViewEmail']) === strtolower($sViewEmail) && !$contact['Auto']) ||
                                            strtolower($contact['ViewEmail']) !== strtolower($sViewEmail);
                                    }); // remove all collected contacts
                                }
                            }
                        }
                    }
                }
            }

            // resolve addressbooks' numeric ids to text text ids
            $aAddressbooksMap = self::Decorator()->GetStoragesMapToAddressbooks();
            $aAddressBooks = [];
            $aPersonalAddressBooks = Backend::Carddav()->getAddressBooksForUser(Constants::PRINCIPALS_PREFIX . $oUser->PublicId);
            foreach ($aPersonalAddressBooks as $oAddressBook) {
                $aAddressBooks[$oAddressBook['id']] = $oAddressBook;
            }

            foreach($aContacts as &$aContact) {
                $aContact['UUID'] = (string)$aContact['UUID'];

                if (!isset($aAddressBooks[$aContact['Storage']])) {
                    $aAddressBooks[$aContact['Storage']] = Backend::Carddav()->getAddressBookById($aContact['Storage']);
                }

                $StorageTextId = false;
                if ($aAddressBooks[$aContact['Storage']]) {
                    $StorageTextId = array_search($aAddressBooks[$aContact['Storage']]['uri'], $aAddressbooksMap);
                }

                $aContact['AddressBookId'] = (int) $aContact['Storage'];
                $aContact['Storage'] = $StorageTextId ? $StorageTextId : (StorageType::AddressBook . '-' . $aContact['Storage']);
            }
            // end ids resolve

            if ($WithGroups) {
                $groups = self::Decorator()->GetGroups($UserId, [], $Search);

                if (is_array($groups) && count($groups) > 0) {
                    $groupContactsUuids = [];
                    $contactsUuids = [];
                    array_map(function ($item) use (&$groupContactsUuids, &$contactsUuids) {
                        if (is_array($item->Contacts) && count($item->Contacts) > 0) {
                            $groupContactsUuids[$item->UUID] = $item->Contacts;
                            $contactsUuids = array_merge($contactsUuids, $item->Contacts);
                        }
                    }, $groups);

                    $groupContacts = [];
                    $contactsUuids = array_unique($contactsUuids);

                    if (count($contactsUuids) > 0) {
                        foreach (self::Decorator()->GetContactsByUids($UserId, $contactsUuids) as $groupContact) {
                            $groupContacts[$groupContact->UUID] = $groupContact;
                        }

                        $aGroupUsersList = [];

                        foreach ($groups as $group) {
                            $aGroupContactsEmails = [];
                            if (is_array($group->Contacts)) {
                                foreach ($group->Contacts as $contactUuid) {
                                    if (isset($groupContacts[$contactUuid])) {
                                        $oContact = $groupContacts[$contactUuid];
                                        $aGroupContactsEmails[] = $oContact->FullName ? "\"{$oContact->FullName}\" <{$oContact->ViewEmail}>" : $oContact->ViewEmail;
                                    }
                                }

                                $aGroupUsersList[] = [
                                    'UUID' => (string)$group->UUID,
                                    'IdUser' => $group->IdUser,
                                    'FullName' => $group->Name,
                                    'FirstName' => '',
                                    'LastName' => '',
                                    'ViewEmail' => implode(', ', $aGroupContactsEmails),
                                    'Storage' => '',
                                    'Frequency' => 0,
                                    'DateModified' => '',
                                    'IsGroup' => true,
                                ];
                            }
                        }
                        $aContacts = array_merge($aContacts, $aGroupUsersList);
                    }
                }
            }
        } else {
            throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
        }

        return [
            'ContactCount' => $count,
            'List' => \Aurora\System\Managers\Response::GetResponseObject(array_values($aContacts))
        ];
    }

    public function GetContactSuggestions($UserId, $Storage, $Limit = 20, $SortField = SortField::Name, $SortOrder = SortOrder::ASC, $Search = '', $WithGroups = false, $WithoutTeamContactsDuplicates = false, $WithUserGroups = false)
    {
        $WithoutTeamContactsDuplicates = false;
        // $Storage is used by subscribers to prepare filters.
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        Api::CheckAccess($UserId);

        $aResult = array(
            'ContactCount' => 0,
            'List' => []
        );

        $aContacts = $this->Decorator()->GetContacts($UserId, $Storage, 0, $Limit, $SortField, $SortOrder, $Search, '', null, $WithGroups, $WithoutTeamContactsDuplicates, true);
        $aResultList = $aContacts['List'];

        $aResult['List'] = $aResultList;
        $aResult['ContactCount'] = count($aResultList);

        if ($WithUserGroups) {
            $oUser = CoreModule::Decorator()->GetUserWithoutRoleCheck($UserId);
            if ($oUser) {
                $aGroups = CoreModule::Decorator()->GetGroups($oUser->IdTenant, $Search);
                foreach ($aGroups['Items'] as $aGroup) {
                    $aGroup['IsGroup'] = true;
                    $aResult['List'][] = $aGroup;

                    $aResult['ContactCount']++;
                }
            }
        }

        return $aResult;
    }

    /**
     * This method used as trigger for subscibers. Check these modules: PersonalContacts, SharedContacts, TeamContacts
     */
    public function CheckAccessToObject($User, $Contact, $Access = null)
    {
        return true;
    }

    public function CheckAccessToAddressBook($User, $AddressBookId, $Access = null)
    {
        return true;
    }

    /**
     * @api {post} ?/Api/ GetContact
     * @apiName GetContact
     * @apiGroup Contacts
     * @apiDescription Returns contact with specified UUID.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=GetContact} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **UUID** *string* UUID of contact to return.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetContact',
     *	Parameters: '{ UUID: "contact_uuid" }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {mixed} Result.Result Object with contact data in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetContact',
     *	Result: '{ "IdUser": 3, "UUID": "group_uuid", "Storage": "personal", "FullName": "", "PrimaryEmail": 0,
     * "PrimaryPhone": 1, "PrimaryAddress": 0, "FirstName": "", "LastName": "", "NickName": "", "Skype": "",
     * "Facebook": "", "PersonalEmail": "contact@email.com", "PersonalAddress": "", "PersonalCity": "",
     * "PersonalState": "", "PersonalZip": "", "PersonalCountry": "", "PersonalWeb": "", "PersonalFax": "",
     * "PersonalPhone": "", "PersonalMobile": "123-234-234", "BusinessEmail": "", "BusinessCompany": "",
     * "BusinessAddress": "", "BusinessCity": "", "BusinessState": "", "BusinessZip": "", "BusinessCountry": "",
     * "BusinessJobTitle": "", "BusinessDepartment": "", "BusinessOffice": "", "BusinessPhone": "",
     * "BusinessFax": "", "BusinessWeb": "", "OtherEmail": "", "Notes": "", "BirthDay": 0, "BirthMonth": 0,
     * "BirthYear": 0, "ETag": "", "GroupUUIDs": ["group1_uuid", "group2_uuid"] }'
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetContact',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Returns contact with specified UUID.
     * @param string $UUID UUID of contact to return.
     * @return \Aurora\Modules\Contacts\Classes\Contact
     */
    public function GetContact($UUID, $UserId = null)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        Api::CheckAccess($UserId);

        $oUser = Api::getUserById($UserId);

        $mResult = false;

        if ($oUser instanceof \Aurora\Modules\Core\Models\User) {
            $query = Capsule::connection()->table('contacts_cards')
                ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
                ->join('adav_addressbooks', 'adav_cards.addressbookid', '=', 'adav_addressbooks.id')
                ->select('adav_cards.uri as card_uri', 'adav_addressbooks.id as addressbook_id', 'Properties', 'carddata', 'etag');

            $aArgs = [
                'UUID' => $UUID,
                'UserId' => $UserId
            ];
            $query->where(function ($q) use ($aArgs, $query) {
                $aArgs['Query'] = $query;
                $this->broadcastEvent(self::GetName() . '::ContactQueryBuilder', $aArgs, $q);
            });

            $row = $query->first();
            if ($row) {
                if (!self::Decorator()->CheckAccessToAddressBook($oUser, $row->addressbook_id, Access::Read)) {
                    throw new ApiException(Notifications::AccessDenied, null, 'AccessDenied');
                }

                $mResult = new Contact();
                $mResult->Id = $UUID;
                $mResult->InitFromVCardStr($UserId, $row->carddata);
                $mResult->ETag = \trim($row->etag, '"');

                $storagesMapToAddressbooks = self::Decorator()->GetStoragesMapToAddressbooks();
                $addressbook = Backend::Carddav()->getAddressBookById($row->addressbook_id);

                $key = false;
                if ($addressbook) {
                    $key = array_search($addressbook['uri'], $storagesMapToAddressbooks);
                }

                $mResult->Storage = $key !== false ? $key : (string) $row->addressbook_id;
                $mResult->AddressBookId = (int) $row->addressbook_id;
                if ($mResult->Properties) {
                    $mResult->Properties = \json_decode($row->Properties);
                }
                $groups = self::Decorator()->GetGroups($UserId);
                foreach ($groups as $group) {
                    if (in_array($UUID, $group->Contacts)) {
                        $mResult->GroupUUIDs[] = $group->UUID;
                    }
                }
            }
        }

        return $mResult;
    }

    /**
     * @api {post} ?/Api/ GetContactsByEmails
     * @apiName GetContactsByEmails
     * @apiGroup Contacts
     * @apiDescription Returns list of contacts with specified emails.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=GetContactsByEmails} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **Emails** *array* List of emails of contacts to return.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetContactsByEmails',
     *	Parameters: '{ Emails: ["contact@email.com"] }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {mixed} Result.Result List of contacts in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetContactsByEmails',
     *	Result: [{ "IdUser": 3, "UUID": "group_uuid", "Storage": "personal", "FullName": "", "PrimaryEmail": 0,
     * "PrimaryPhone": 1, "PrimaryAddress": 0, "FirstName": "", "LastName": "", "NickName": "", "Skype": "",
     * "Facebook": "", "PersonalEmail": "contact@email.com", "PersonalAddress": "", "PersonalCity": "",
     * "PersonalState": "", "PersonalZip": "", "PersonalCountry": "", "PersonalWeb": "", "PersonalFax": "",
     * "PersonalPhone": "", "PersonalMobile": "123-234-234", "BusinessEmail": "", "BusinessCompany": "",
     * "BusinessAddress": "", "BusinessCity": "", "BusinessState": "", "BusinessZip": "", "BusinessCountry": "",
     * "BusinessJobTitle": "", "BusinessDepartment": "", "BusinessOffice": "", "BusinessPhone": "",
     * "BusinessFax": "", "BusinessWeb": "", "OtherEmail": "", "Notes": "", "BirthDay": 0, "BirthMonth": 0,
     * "BirthYear": 0, "ETag": "", "GroupUUIDs": ["group1_uuid", "group2_uuid"] }]
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'GetContactsByEmails',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Returns list of contacts with specified emails.
     * @param string $Storage storage of contacts.
     * @param array $Emails List of emails of contacts to return.
     * @return \Illuminate\Database\Eloquent\Collection|array
     */
    public function GetContactsByEmails($UserId, $Storage, $Emails, $Filters = null, $AsArray = true)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
        $aContacts = [];

        Api::CheckAccess($UserId);

        $oUser = Api::getUserById($UserId);
        $aArgs = [
            'UserId' => $UserId,
            'Storage' => $Storage,
            'AddressBookId' => null
        ];

        if ($this->populateContactArguments($aArgs)) {
            if (self::Decorator()->CheckAccessToAddressBook($oUser, $aArgs['AddressBookId'], Access::Read)) {
                $query = $this->getGetContactsQueryBuilder($UserId, $Storage, $aArgs['AddressBookId'], $Filters);
                $query->whereIn('ViewEmail', $Emails);

                $aContacts = $this->_getContacts(SortField::Name, SortOrder::ASC, 0, 0, $query);
                if ($AsArray) {
                    $aContacts = $aContacts->toArray();
                }
            }
        }

        return $aContacts;
    }

    /**
     * Returns list of contacts with specified uids.
     * @param int $UserId
     * @param array $Uids List of uids of contacts to return.
     * @return array
     */
    public function GetContactsByUids($UserId, $Uids)
    {
        $aResult = [];
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        Api::CheckAccess($UserId);

        if (is_array($Uids) && count($Uids) > 0) {
            $query = $this->getGetContactsQueryBuilder($UserId, StorageType::All);
            $aResult = $query->whereIn('contacts_cards.CardId', $Uids)->get()->all();

            $oUser = Api::getUserById($UserId);
            $aGroups = self::Decorator()->GetGroups($UserId);
            $aAddressbooksMap = self::Decorator()->GetStoragesMapToAddressbooks();
            $aAddressBooks = [];
            $aPersonalAddressBooks = Backend::Carddav()->getAddressBooksForUser(Constants::PRINCIPALS_PREFIX . $oUser->PublicId);
            foreach ($aPersonalAddressBooks as $oAddressBook) {
                $aAddressBooks[$oAddressBook['id']] = $oAddressBook;
            }

            foreach($aResult as $oContact) {
                $aGroupUUIDs = [];
                foreach ($aGroups as $oGroup) {
                    if (in_array($oContact->UUID, $oGroup->Contacts)) {
                        $aGroupUUIDs[] = $oGroup->UUID;
                    }
                }

                if (!isset($aAddressBooks[$oContact->Storage])) {
                    $aAddressBooks[$oContact->Storage] = Backend::Carddav()->getAddressBookById($oContact->Storage);
                }

                $StorageTextId = false;
                if ($aAddressBooks[$oContact->Storage]) {
                    $StorageTextId = array_search($aAddressBooks[$oContact->Storage]['uri'], $aAddressbooksMap);
                }
                $oContact->AddressBookId = (int) $oContact->Storage;
                $oContact->Storage = $StorageTextId ? $StorageTextId : StorageType::AddressBook . '-' . $oContact->Storage;

                $oContact->GroupUUIDs = $aGroupUUIDs;

                //TODO: remove this after refactoring API and client
                $oContact->UUID = (string)$oContact->UUID;
                $oContact->EntityId = $oContact->Id;
                $oContact->IdUser = $oContact->UserId;
                $oContact->IdTenant = $oUser->IdTenant;
                $oContact->UseFriendlyName = false;
            }
        } else {
            throw new ApiException(Notifications::InvalidInputParameter);
        }

        return $aResult;
    }

    /**
     * Returns list of contacts with specified emails.
     * @param string $Storage storage of contacts.
     * @param int|null $UserId
     * @param Builder $Filters
     * @return array
     */
    public function GetContactsInfo($Storage, $UserId = null, Builder $Filters = null)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        Api::CheckAccess($UserId);

        $aResult = [
            'CTag' => 0,
            'Info' => []
        ];

        $aArgs = [
            'UserId' => $UserId,
            'Storage' => $Storage,
            'AddressBookId' => 0
        ];

        if ($this->populateContactArguments($aArgs)) {
            if ((int) $aArgs['AddressBookId'] > 0) {
                $addressbook = Backend::Carddav()->getAddressBookById($aArgs['AddressBookId']);

                if ($addressbook) {
                    $aResult['CTag'] = (int) $addressbook['{http://sabredav.org/ns}sync-token'];
                }
            }
            $query = $this->getGetContactsQueryBuilder($UserId, $Storage, $aArgs['AddressBookId'], $Filters);

            $aContacts = $query->get(['UUID', 'ETag', 'Auto', 'Storage']);

            $storagesMapToAddressbooks = self::Decorator()->GetStoragesMapToAddressbooks();

            foreach ($aContacts as $oContact) {
                $StorageTextId = false;
                if (!empty($addressbook)) {
                    $StorageTextId = array_search($addressbook['uri'], $storagesMapToAddressbooks);
                }

                /**
                 * @var \Aurora\Modules\Contacts\Models\ContactCard $oContact
                 */
                $aResult['Info'][] = [
                    'UUID' => (string) $oContact->UUID,
                    'ETag' => $oContact->ETag,
                    'Storage' => $StorageTextId ? $StorageTextId : (string) $oContact->Storage,
                    'IsTeam' => $oContact->IsTeam,
                    'Shared' => $oContact->Shared,
                ];
            }
        }

        return $aResult;
    }

    /**
     * @api {post} ?/Api/ CreateContact
     * @apiName CreateContact
     * @apiGroup Contacts
     * @apiDescription Creates contact with specified parameters.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=CreateContact} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **Contact** *object* Parameters of contact to create.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'CreateContact',
     *	Parameters: '{ "Contact": { "UUID": "", "PrimaryEmail": 0, "PrimaryPhone": 0, "PrimaryAddress": 0,
     * "FullName": "second", "FirstName": "", "LastName": "", "NickName": "", "Storage": "personal",
     * "Skype": "", "Facebook": "", "PersonalEmail": "contact2@email.com", "PersonalAddress": "",
     * "PersonalCity": "", "PersonalState": "", "PersonalZip": "", "PersonalCountry": "", "PersonalWeb": "",
     * "PersonalFax": "", "PersonalPhone": "", "PersonalMobile": "", "BusinessEmail": "", "BusinessCompany": "",
     * "BusinessJobTitle": "", "BusinessDepartment": "", "BusinessOffice": "", "BusinessAddress": "",
     * "BusinessCity": "", "BusinessState": "", "BusinessZip": "", "BusinessCountry": "", "BusinessFax": "",
     * "BusinessPhone": "", "BusinessWeb": "", "OtherEmail": "", "Notes": "", "ETag": "", "BirthDay": 0,
     * "BirthMonth": 0, "BirthYear": 0, "GroupUUIDs": [] } }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {mixed} Result.Result New contact UUID in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'CreateContact',
     *	Result: 'new_contact_uuid'
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'CreateContact',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Creates contact with specified parameters.
     * @param array $Contact Parameters of contact to create.
     * @param int $UserId Identifier of user that should own a new contact.
     * @return bool|string
     * @throws \Aurora\System\Exceptions\ApiException
     */
    public function CreateContact($Contact, $UserId = null)
    {
        Api::CheckAccess($UserId);

        $oUser = CoreModule::getInstance()->GetUserWithoutRoleCheck($UserId);

        $mResult = false;

        if ($oUser instanceof \Aurora\Modules\Core\Models\User) {
            $oContact = new Classes\Contact();
            $oContact->IdUser = $oUser->Id;
            $oContact->IdTenant = $oUser->IdTenant;
            $oContact->populate($Contact);

            $oContact->Frequency = $this->getAutocreatedContactFrequencyAndDeleteIt($oUser->Id, $oContact->ViewEmail);

            $oVCard = new \Sabre\VObject\Component\VCard();
            Helper::UpdateVCardFromContact($oContact, $oVCard);

            if (self::Decorator()->CheckAccessToAddressBook($oUser, $oContact->AddressBookId, Access::Write)) {
                $cardUri = $oContact->UUID . '.vcf';
                $cardETag = Backend::Carddav()->createCard($oContact->AddressBookId, $cardUri, $oVCard->serialize());

                if ($cardETag) {
                    $newCard = Backend::Carddav()->getCard($oContact->AddressBookId, $cardUri);
                    if ($newCard) {
                        ContactCard::where('CardId', $newCard['id'])->update(['Frequency' => $oContact->Frequency]);

                        if (is_array($oContact->GroupUUIDs) && count($oContact->GroupUUIDs) > 0) {
                            $oGroups = self::Decorator()->GetGroups($UserId, $oContact->GroupUUIDs);
                            if ($oGroups) {
                                foreach ($oGroups as $oGroup) {
                                    $oGroup->Contacts = array_merge($oGroup->Contacts, [(string) $newCard['id']]);

                                    $this->UpdateGroupObject($UserId, $oGroup);
                                }
                            }
                        }

                        $mResult = [
                            'UUID' => (string) $newCard['id'],
                            'ETag' => \trim($newCard['etag'], '"')
                        ];
                    }
                }
            }
        }

        return $mResult;
    }

    /**
     * Obtains autocreated contact frequency if user have already created it.
     * Removes autocreated contact.
     * @param int $UserId User identifier.
     * @param string $sViewEmail View email of contact to create
     */
    private function getAutocreatedContactFrequencyAndDeleteIt($UserId, $sViewEmail)
    {
        Api::CheckAccess($UserId);

        $iFrequency = 0;

        $aArgs = [
            'UserId' => $UserId,
            'Storage' => StorageType::Collected,
            'AddressBookId' => 0
        ];

        if ($this->populateContactArguments($aArgs)) {
            $oQuery = ContactCard::where([
                ['AddressBookId', '=', $aArgs['AddressBookId']],
                ['ViewEmail', '=', $sViewEmail]
            ]);

            $oAutocreatedContacts = $this->_getContacts(
                SortField::Name,
                SortOrder::ASC,
                0,
                1,
                $oQuery
            );
            $oContact = $oAutocreatedContacts->first();
            if ($oContact instanceof ContactCard) {
                $card_uri = Capsule::connection()->table('adav_cards')
                    ->where('id', $oContact->CardId)
                    ->pluck('uri')->first();

                Backend::Carddav()->deleteCard($oContact->AddressBookId, $card_uri);
                $iFrequency = $oContact->Frequency;
            }
        }

        return $iFrequency;
    }

    /**
     * @api {post} ?/Api/ UpdateContact
     * @apiName UpdateContact
     * @apiGroup Contacts
     * @apiDescription Updates contact with specified parameters.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=UpdateContact} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **Contact** *array* Parameters of contact to update.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'UpdateContact',
     *	Parameters: '{ "Contact": { "UUID": "contact2_uuid", "PrimaryEmail": 0, "PrimaryPhone": 0,
     * "PrimaryAddress": 0, "FullName": "contact2", "FirstName": "", "LastName": "", "NickName": "",
     * "Storage": "personal", "Skype": "", "Facebook": "", "PersonalEmail": "contact2@email.com",
     * "PersonalAddress": "", "PersonalCity": "", "PersonalState": "", "PersonalZip": "", "PersonalCountry": "",
     * "PersonalWeb": "", "PersonalFax": "", "PersonalPhone": "", "PersonalMobile": "", "BusinessEmail": "",
     * "BusinessCompany": "", "BusinessJobTitle": "", "BusinessDepartment": "", "BusinessOffice": "",
     * "BusinessAddress": "", "BusinessCity": "", "BusinessState": "", "BusinessZip": "", "BusinessCountry": "",
     * "BusinessFax": "", "BusinessPhone": "", "BusinessWeb": "", "OtherEmail": "", "Notes": "", "ETag": "",
     * "BirthDay": 0, "BirthMonth": 0, "BirthYear": 0, "GroupUUIDs": [] } }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {bool} Result.Result Indicates if contact was updated successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'UpdateContact',
     *	Result: true
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'UpdateContact',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Updates contact with specified parameters.
     * @param array $Contact Parameters of contact to update.
     * @return array|bool
     */
    public function UpdateContact($UserId, $Contact)
    {
        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $oContact = self::Decorator()->GetContact($Contact['UUID'], $UserId);
        $oUser = Api::getUserById($UserId);
        if ($oContact && self::Decorator()->CheckAccessToAddressBook($oUser, $oContact->AddressBookId, Access::Write)) {
            $oContact->populate($Contact);
            $result = self::Decorator()->UpdateContactObject($oContact);
            if ($result) {
                if (is_array($oContact->GroupUUIDs)) {
                    $groups = self::Decorator()->GetGroups($UserId);
                    foreach ($groups as $group) {
                        if ($group) {
                            if (!in_array($group->UUID, $oContact->GroupUUIDs)) {
                                $group->Contacts = array_diff($group->Contacts, [$oContact->UUID]);
                            } else {
                                $group->Contacts = array_merge($group->Contacts, [$oContact->UUID]);
                            }
                            $this->UpdateGroupObject($UserId, $group);
                        }
                    }
                }

                return [
                    'UUID' => (string) $oContact->UUID,
                    'ETag' => $result
                ];
            } else {
                return false;
            }
        }

        return false;
    }

    public function MoveContactsToStorage($UserId, $FromStorage, $ToStorage, $UUIDs)
    {
        $result = false;

        if ($ToStorage === StorageType::Team) { // skip moving to team storage
            return false;
        }

        $query = Capsule::connection()
            ->table('contacts_cards')
            ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
            ->join('adav_addressbooks', 'adav_cards.addressbookid', '=', 'adav_addressbooks.id')
            ->select('adav_cards.uri as card_uri', 'adav_cards.id as card_id', 'adav_addressbooks.id as addressbook_id');

        $aArgs = [
            'UserId' => $UserId,
            'UUID' => $UUIDs
        ];

        // build a query to obtain the card_uri and card_id with checking access to the contact
        $cardsUris = $query->where(function ($q) use ($aArgs, $query) {
            $aArgs['Query'] = $query;
            $this->broadcastEvent('Contacts::ContactQueryBuilder', $aArgs, $q);
        })->pluck('card_uri', 'card_id')->toArray();

        $aArgsTo = [
            'UserId' => $UserId,
            'Storage' => $ToStorage,
            'AddressBookId' => 0
        ];

        $resultFrom = true;
        $resultTo = $this->populateContactArguments($aArgsTo);

        $ToAddressBookId = (int) $aArgsTo['AddressBookId']; // getting ToAddressBookId from ToStorage

        foreach ($cardsUris as $cardId => $cardUri) {
            $FromAddressBookId = 0;
            if ($FromStorage === StorageType::All) { // getting $FromAddressBookId from the contact
                $oContact = self::Decorator()->GetContact($cardId, $UserId);
                if ($oContact instanceof Contact) {
                    if ($oContact->Storage === StorageType::Team) { // skip the team contact
                        continue;
                    }
                    $FromAddressBookId = (int) $oContact->AddressBookId;
                }
            } else {
                $aArgsFrom = [
                    'UserId' => $UserId,
                    'Storage' => $FromStorage,
                    'AddressBookId' => 0
                ];

                $resultFrom = $this->populateContactArguments($aArgsFrom);

                $FromAddressBookId = (int) $aArgsFrom['AddressBookId'];
            }
            if ($FromAddressBookId != $ToAddressBookId && $resultFrom && $resultTo) { // do not allow contact to be moved to its own storage
                $result = $result && Backend::Carddav()->updateCardAddressBook($FromAddressBookId, $ToAddressBookId, $cardUri);
            }
        }

        return $result;
    }

    /**
     * !Not public
     * This method is restricted to be called by web API (see denyMethodsCallByWebApi method).
     * @param Contact $Contact
     * @return string|bool
     */
    public function UpdateContactObject($Contact)
    {
        $mResult = false;

        $oUser = \Aurora\System\Api::getAuthenticatedUser();
        $aStorageParts = \explode('-', $Contact->Storage);
        if (isset($aStorageParts[0], $aStorageParts[1]) && $aStorageParts[0] === StorageType::AddressBook) {
            $Contact->AddressBookId = (int) $aStorageParts[1];
            $Contact->Storage = StorageType::AddressBook;
        }

        $query = Capsule::connection()->table('contacts_cards')
            ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
            ->join('adav_addressbooks', 'adav_cards.addressbookid', '=', 'adav_addressbooks.id')
            ->select('adav_cards.uri as card_uri', 'adav_addressbooks.id as addressbook_id', 'carddata');

        $aArgs = [
            'UserId' => $oUser->Id,
            'UUID' => $Contact->Id
        ];

        // build a query to obtain the addressbook_id and card_uri with checking access to the contact
        $query->where(function ($q) use ($aArgs, $query) {
            $aArgs['Query'] = $query;
            $this->broadcastEvent(self::GetName() . '::ContactQueryBuilder', $aArgs, $q);
        });

        $row = $query->first();
        if ($row) {
            $oVCard = \Sabre\VObject\Reader::read($row->carddata);
            $uidVal = $oVCard->UID->getValue();
            if (empty($uidVal) || is_numeric($uidVal)) {
                $uriInfo = pathinfo($row->card_uri);
                if (isset($uriInfo['filename'])) {
                    $oVCard->UID = $uriInfo['filename'];
                }
            }

            Helper::UpdateVCardFromContact($Contact, $oVCard);
            $mResult = Backend::Carddav()->updateCard($row->addressbook_id, $row->card_uri, $oVCard->serialize());
            $mResult = str_replace('"', '', $mResult);
        }

        return $mResult;
    }

    /**
     * @api {post} ?/Api/ DeleteContacts
     * @apiName DeleteContacts
     * @apiGroup Contacts
     * @apiDescription Deletes contacts with specified UUIDs.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=DeleteContacts} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **UUIDs** *array* Array of strings - UUIDs of contacts to delete.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'DeleteContacts',
     *	Parameters: '{ UUIDs: ["uuid1", "uuid"] }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {bool} Result.Result Indicates if contacts were deleted successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'DeleteContacts',
     *	Result: true
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'DeleteContacts',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Deletes contacts with specified UUIDs.
     * @param int $UserId
     * @param string $Storage
     * @param array $UUIDs Array of strings - UUIDs of contacts to delete.
     * @return bool
     */
    public function DeleteContacts($UserId, $Storage, $UUIDs)
    {
        $mResult = false;
        Api::CheckAccess($UserId);
        $oUser = Api::getUserById($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $AddressBookId = $Storage; // It's trick for API compatibility. Method should accept numeric AddressBookId, but clients sends storage name as ID
        if (self::Decorator()->CheckAccessToAddressBook($oUser, $AddressBookId, Enums\Access::Write)) {
            $query = Capsule::connection()->table('contacts_cards')
                ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
                ->join('adav_addressbooks', 'adav_cards.addressbookid', '=', 'adav_addressbooks.id')
                ->select('adav_cards.id as card_id', 'adav_cards.uri as card_uri', 'adav_addressbooks.id as addressbook_id');

            $aArgs = [
                'UUID' => $UUIDs,
                'UserId' => $UserId
            ];
            $query->where(function ($q) use ($aArgs, $query) {
                $aArgs['Query'] = $query;
                $this->broadcastEvent(self::GetName() . '::ContactQueryBuilder', $aArgs, $q);
            });

            $rows = $query->distinct()->get()->all();

            $groups = self::Decorator()->GetGroups($UserId);
            $groupsToUpdate = [];

            foreach ($rows as $row) {
                Backend::Carddav()->deleteCard($row->addressbook_id, $row->card_uri);
                foreach ($groups as $group) {
                    if (($key = array_search($row->card_id, $group->Contacts)) !== false) {
                        unset($group->Contacts[$key]);
                        if (!in_array($group->UUID, $groupsToUpdate)) {
                            $groupsToUpdate[] = $group->UUID;
                        }
                    }
                }
            }

            foreach ($groups as $group) {
                if (in_array($group->UUID, $groupsToUpdate)) {
                    $this->UpdateGroupObject($UserId, $group);
                }
            }

            $mResult = true;
        }

        return $mResult;
    }

    /**
     * @api {post} ?/Api/ CreateGroup
     * @apiName CreateGroup
     * @apiGroup Contacts
     * @apiDescription Creates group with specified parameters.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=CreateGroup} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **Group** *object* Parameters of group to create.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'CreateGroup',
     *	Parameters: '{ "Group": { "UUID": "", "Name": "new_group_name", "IsOrganization": "0", "Email": "",
     * "Country": "", "City": "", "Company": "", "Fax": "", "Phone": "", "State": "", "Street": "",
     * "Web": "", "Zip": "", "Contacts": [] } }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {mixed} Result.Result New group UUID in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'CreateGroup',
     *	Result: 'new_group_uuid'
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'CreateGroup',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Creates group with specified parameters.
     * @param array $Group Parameters of group to create.
     * @return string|bool
     */
    public function CreateGroup($Group, $UserId = null)
    {
        $mResult = false;

        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        if (is_array($Group)) {
            \Aurora\System\Validator::validate($Group, [
                'Name'	=>	'required'
            ], [
                'required' => 'The :attribute field is required.'
            ]);

            $oGroup = new Classes\Group();
            $oGroup->IdUser = (int) $UserId;

            $oGroup->populate($Group);
            if (isset($Group['Contacts']) && is_array($Group['Contacts'])) {
                $oGroup->Contacts = $this->getContactsUUIDsFromIds($UserId, $Group['Contacts']);
            }

            $oVCard = new \Sabre\VObject\Component\VCard();
            Helper::UpdateVCardFromGroup($oGroup, $oVCard);

            $userPublicId = Api::getUserPublicIdById($UserId);
            $addressBook = Backend::Carddav()->getAddressBookForUser(Constants::PRINCIPALS_PREFIX . $userPublicId, Constants::ADDRESSBOOK_DEFAULT_NAME);
            $cardUri = $oGroup->UUID . '.vcf';

            if ($addressBook) {
                $cardETag = Backend::Carddav()->createCard($addressBook['id'], $cardUri, $oVCard->serialize());
                if ($cardETag) {
                    $newCard = Backend::Carddav()->getCard($addressBook['id'], $cardUri);
                    if ($newCard) {
                        $mResult = [
                            'UUID' => (string) $newCard['id'],
                            'ETag' => \trim($newCard['etag'], '"')
                        ];
                    }
                }
            }
        }

        return $mResult;
    }

    protected function getContactsUUIDsFromIds($UserId, $Ids)
    {
        if (is_array($Ids) && count($Ids) > 0) {
            $query = Capsule::connection()->table('contacts_cards')
                ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
                ->join('adav_addressbooks', 'adav_cards.addressbookid', '=', 'adav_addressbooks.id')
                ->select('adav_cards.uri as card_uri');

            $aArgs = [
                'UserId' => $UserId,
                'UUID' => $Ids
            ];

            // build a query to obtain the addressbook_id and card_uri with checking access to the contact
            $query->where(function ($q) use ($aArgs, $query) {
                $aArgs['Query'] = $query;
                $this->broadcastEvent(self::GetName() . '::ContactQueryBuilder', $aArgs, $q);
            });

            $contactsIds = $query->pluck('card_uri')->all();

            return array_map(function ($item) {
                $pathInfo = pathinfo($item);
                return $pathInfo['filename'];
            }, $contactsIds);
        } else {
            return [];
        }
    }

    protected function getContactsIdsFromUUIDs($UserId, $UUIDs)
    {
        $Uris = array_map(function ($item) {
            return $item . '.vcf';
        }, $UUIDs);

        $contactsIds = Capsule::connection()->table('adav_cards')
            ->join('adav_addressbooks', 'adav_cards.addressbookid', '=', 'adav_addressbooks.id')
            ->select('adav_cards.id as card_id')
            ->where('principaluri', Constants::PRINCIPALS_PREFIX . Api::getUserPublicIdById($UserId))
            ->whereIn('adav_cards.uri', $Uris)->get()->all();

        return array_map(function ($item) {
            return $item->card_id;
        }, $contactsIds);
    }

    /**
     * @api {post} ?/Api/ UpdateGroup
     * @apiName UpdateGroup
     * @apiGroup Contacts
     * @apiDescription Updates group with specified parameters.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=UpdateGroup} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **Group** *object* Parameters of group to update.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'UpdateGroup',
     *	Parameters: '{ "Group": { "UUID": "group_uuid", "Name": "group_name", "IsOrganization": "0",
     * "Email": "", "Country": "", "City": "", "Company": "", "Fax": "", "Phone": "", "State": "",
     * "Street": "", "Web": "", "Zip": "", "Contacts": [] } }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {bool} Result.Result Indicates if group was updated successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'UpdateGroup',
     *	Result: true
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'UpdateGroup',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    protected function UpdateGroupObject($UserId, $oGroup)
    {
        $mResult = false;

        if (is_array($oGroup->Contacts) && count($oGroup->Contacts)) {
            $oGroup->Contacts = $this->getContactsUUIDsFromIds($UserId, $oGroup->Contacts);
        }

        $query = Capsule::connection()->table('contacts_cards')
            ->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
            ->join('adav_addressbooks', 'adav_cards.addressbookid', '=', 'adav_addressbooks.id')
            ->select('adav_cards.uri as card_uri', 'adav_addressbooks.id as addressbook_id', 'carddata');

        $aArgs = [
            'UserId' => $UserId,
            'UUID' => $oGroup->Id
        ];

        // build a query to obtain the addressbook_id and card_uri with checking access to the contact
        $query->where(function ($q) use ($aArgs, $query) {
            $aArgs['Query'] = $query;
            $this->broadcastEvent(self::GetName() . '::ContactQueryBuilder', $aArgs, $q);
        });

        $row = $query->first();
        if ($row) {
            $oVCard = \Sabre\VObject\Reader::read($row->carddata);
            $uidVal = $oVCard->UID->getValue();
            if (empty($uidVal) || is_numeric($uidVal)) {
                $uriInfo = pathinfo($row->card_uri);
                if (isset($uriInfo['filename'])) {
                    $oVCard->UID = $uriInfo['filename'];
                }
            }
            Helper::UpdateVCardFromGroup($oGroup, $oVCard);
            $mResult = !!Backend::Carddav()->updateCard($row->addressbook_id, $row->card_uri, $oVCard->serialize());
        }

        return $mResult;
    }

    /**
     * Updates group with specified parameters.
     * @param array $Group Parameters of group to update.
     * @return boolean
     */
    public function UpdateGroup($UserId, $Group)
    {
        $mResult = false;

        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $oGroup = self::Decorator()->GetGroup($UserId, $Group['UUID']);
        if ($oGroup) {
            $oGroup->populate($Group);
            $mResult = $this->UpdateGroupObject($UserId, $oGroup);
        }

        return $mResult;
    }

    /**
     * @api {post} ?/Api/ DeleteGroup
     * @apiName DeleteGroup
     * @apiGroup Contacts
     * @apiDescription Deletes group with specified UUID.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=DeleteGroup} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **UUID** *string* UUID of group to delete.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'DeleteGroup',
     *	Parameters: '{ UUID: "group_uuid" }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {bool} Result.Result Indicates if group was deleted successfully.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'DeleteGroup',
     *	Result: true
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'DeleteGroup',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Deletes group with specified UUID.
     * @param string $UUID UUID of group to delete.
     * @return bool
     */
    public function DeleteGroup($UserId, $UUID)
    {
        Api::CheckAccess($UserId);

        return self::Decorator()->DeleteContacts($UserId, StorageType::Personal, [$UUID]);
    }

    /**
     * @api {post} ?/Api/ AddContactsToGroup
     * @apiName AddContactsToGroup
     * @apiGroup Contacts
     * @apiDescription Adds specified contacts to specified group.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=AddContactsToGroup} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **GroupUUID** *string* UUID of group.<br>
     * &emsp; **ContactUUIDs** *array* Array of strings - UUIDs of contacts to add to group.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'AddContactsToGroup',
     *	Parameters: '{ GroupUUID: "group_uuid", ContactUUIDs: ["contact1_uuid", "contact2_uuid"] }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {bool} Result.Result Indicates if contacts were successfully added to group.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'AddContactsToGroup',
     *	Result: true
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'AddContactsToGroup',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Adds specified contacts to specified group.
     * @param string $GroupUUID UUID of group.
     * @param array $ContactUUIDs Array of strings - UUIDs of contacts to add to group.
     * @return boolean
     */
    public function AddContactsToGroup($UserId, $GroupUUID, $ContactUUIDs)
    {
        $mResult = false;
        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        if (is_array($ContactUUIDs) && !empty($ContactUUIDs)) {

            $oGroup = self::Decorator()->GetGroup($UserId, $GroupUUID);
            if ($oGroup) {
                $aContacts = self::Decorator()->GetContactsByUids($UserId, $ContactUUIDs);
                $newContactUUIDs = array_map(function ($item) {
                    return $item->UUID;
                }, $aContacts);
                $oGroup->Contacts = array_merge($oGroup->Contacts, $newContactUUIDs);

                $mResult = $this->UpdateGroupObject($UserId, $oGroup);
            }
        }

        return $mResult;
    }

    /**
     * @api {post} ?/Api/ RemoveContactsFromGroup
     * @apiName RemoveContactsFromGroup
     * @apiGroup Contacts
     * @apiDescription Removes specified contacts from specified group.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=RemoveContactsFromGroup} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **GroupUUID** *string* UUID of group.<br>
     * &emsp; **ContactUUIDs** *array* Array of strings - UUIDs of contacts to remove from group.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'RemoveContactsFromGroup',
     *	Parameters: '{ GroupUUID: "group_uuid", ContactUUIDs: ["contact1_uuid", "contact2_uuid"] }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {bool} Result.Result Indicates if contacts were successfully removed from group.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'RemoveContactsFromGroup',
     *	Result: true
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'RemoveContactsFromGroup',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Removes specified contacts from specified group.
     * @param string $GroupUUID UUID of group.
     * @param array $ContactUUIDs Array of strings - UUIDs of contacts to remove from group.
     * @return boolean
     */
    public function RemoveContactsFromGroup($UserId, $GroupUUID, $ContactUUIDs)
    {
        $mResult = false;
        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        if (is_array($ContactUUIDs) && !empty($ContactUUIDs)) {
            $oGroup = self::Decorator()->GetGroup($UserId, $GroupUUID);
            if ($oGroup) {
                $aContacts = self::Decorator()->GetContactsByUids($UserId, $ContactUUIDs);
                $newContactUUIDs = array_map(function ($item) {
                    return $item->UUID;
                }, $aContacts);
                $oGroup->Contacts = array_diff($oGroup->Contacts, $newContactUUIDs);
                $mResult = $this->UpdateGroupObject($UserId, $oGroup);
            }
        }

        return $mResult;
    }

    /**
     * @api {post} ?/Api/ Import
     * @apiName Import
     * @apiGroup Contacts
     * @apiDescription Imports contacts from file with specified format.
     *
     * @apiHeader {string} Authorization "Bearer " + Authentication token which was received as the result of Core.Login method.
     * @apiHeaderExample {json} Header-Example:
     *	{
     *		"Authorization": "Bearer 32b2ecd4a4016fedc4abee880425b6b8"
     *	}
     *
     * @apiParam {string=Contacts} Module Module name
     * @apiParam {string=Import} Method Method name
     * @apiParam {string} Parameters JSON.stringified object <br>
     * {<br>
     * &emsp; **UploadData** *array* Array of uploaded file data.<br>
     * &emsp; **Storage** *string* Storage name.<br>
     * &emsp; **GroupUUID** *array* Group UUID.<br>
     * }
     *
     * @apiParamExample {json} Request-Example:
     * {
     *	Module: 'Contacts',
     *	Method: 'Import',
     *	Parameters: '{ "UploadData": { "tmp_name": "tmp_name_value", "name": "name_value" },
     *		"Storage": "personal", "GroupUUID": "" }'
     * }
     *
     * @apiSuccess {object[]} Result Array of response objects.
     * @apiSuccess {string} Result.Module Module name
     * @apiSuccess {string} Result.Method Method name
     * @apiSuccess {mixed} Result.Result Object with counts of imported and parsed contacts in case of success, otherwise **false**.
     * @apiSuccess {int} [Result.ErrorCode] Error code
     *
     * @apiSuccessExample {json} Success response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'Import',
     *	Result: { "ImportedCount" : 2, "ParsedCount": 3}
     * }
     *
     * @apiSuccessExample {json} Error response example:
     * {
     *	Module: 'Contacts',
     *	Method: 'Import',
     *	Result: false,
     *	ErrorCode: 102
     * }
     */

    /**
     * Imports contacts from file with specified format.
     * @param array $UploadData Array of uploaded file data.
     * @param array $GroupUUID Group UUID.
     * @return array
     * @throws \Aurora\System\Exceptions\ApiException
     */
    public function Import($UserId, $UploadData, $GroupUUID, $Storage = null)
    {
        Api::CheckAccess($UserId);

        $oUser = CoreModule::getInstance()->GetUserWithoutRoleCheck($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $aResponse = array(
            'ImportedCount' => 0,
            'ParsedCount' => 0
        );

        if (is_array($UploadData)) {
            $oApiFileCacheManager = new \Aurora\System\Managers\Filecache();
            $sTempFileName = 'import-post-' . md5($UploadData['name'] . $UploadData['tmp_name']);
            if ($oApiFileCacheManager->moveUploadedFile($oUser->UUID, $sTempFileName, $UploadData['tmp_name'], '', self::GetName())) {
                $sTempFilePath = $oApiFileCacheManager->generateFullFilePath($oUser->UUID, $sTempFileName, '', self::GetName());

                $aImportResult = array();

                $sFileExtension = strtolower(\Aurora\System\Utils::GetFileExtension($UploadData['name']));
                switch ($sFileExtension) {
                    case 'csv':
                        $oSync = new Classes\Csv\Sync();
                        $aImportResult = $oSync->Import($oUser->Id, $sTempFilePath, $GroupUUID, $Storage);
                        break;
                    case 'vcf':
                        $aImportResult = $this->importVcf($oUser->Id, $sTempFilePath, $Storage);
                        break;
                }

                if (is_array($aImportResult) && isset($aImportResult['ImportedCount']) && isset($aImportResult['ParsedCount'])) {
                    $aResponse['ImportedCount'] = $aImportResult['ImportedCount'];
                    $aResponse['ParsedCount'] = $aImportResult['ParsedCount'];
                } else {
                    throw new ApiException(Notifications::IncorrectFileExtension);
                }

                $oApiFileCacheManager->clear($oUser->UUID, $sTempFileName, '', self::GetName());
            } else {
                throw new ApiException(Notifications::UnknownError);
            }
        } else {
            throw new ApiException(Notifications::UnknownError);
        }

        return $aResponse;
    }

    public function UpdateSharedContacts($UserId, $UUIDs)
    {
        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
        return true;
    }

    public function AddContactsFromFile($UserId, $File)
    {
        Api::CheckAccess($UserId);

        $oUser = CoreModule::getInstance()->GetUserWithoutRoleCheck($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        if (empty($File)) {
            throw new ApiException(Notifications::InvalidInputParameter);
        }

        $oApiFileCache = new \Aurora\System\Managers\Filecache();

        $sTempFilePath = $oApiFileCache->generateFullFilePath($oUser->UUID, $File); // Temp files with access from another module should be stored in System folder
        $aImportResult = $this->importVcf($oUser->Id, $sTempFilePath);

        return $aImportResult;
    }

    /**
     *
     * @param int $UserId
     * @param string $UUID
     * @param string $FileName
     */
    public function SaveContactAsTempFile($UserId, $UUID, $FileName)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        Api::CheckAccess($UserId);

        $mResult = false;

        $oContact = self::Decorator()->GetContact($UUID, $UserId);
        if ($oContact) {
            $oVCard = new \Sabre\VObject\Component\VCard();
            Helper::UpdateVCardFromContact($oContact, $oVCard);
            $sVCardData = $oVCard->serialize();
            if ($sVCardData) {
                $sUUID = \Aurora\System\Api::getUserUUIDById($UserId);
                $sTempName = md5($sUUID . $UUID);
                $oApiFileCache = new \Aurora\System\Managers\Filecache();

                $oApiFileCache->put($sUUID, $sTempName, $sVCardData);
                if ($oApiFileCache->isFileExists($sUUID, $sTempName)) {
                    $mResult = \Aurora\System\Utils::GetClientFileResponse(
                        null,
                        $UserId,
                        $FileName,
                        $sTempName,
                        $oApiFileCache->fileSize($sUUID, $sTempName)
                    );
                }
            }
        }

        return $mResult;
    }
    /***** public functions might be called with web API *****/

    /***** private functions *****/
    private function importVcf($iUserId, $sTempFilePath, $sStorage = null)
    {
        $aImportResult = array(
            'ParsedCount' => 0,
            'ImportedCount' => 0,
            'ImportedUids' => []
        );
        // You can either pass a readable stream, or a string.
        $oHandler = fopen($sTempFilePath, 'r');
        $oSplitter = new \Sabre\VObject\Splitter\VCard($oHandler, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
        $oContactsDecorator = Module::Decorator();

        $aGroupsData = [];
        $aContactsData = [];
        while ($oVCard = $oSplitter->getNext()) {
            set_time_limit(30);

            $Uid = (string) $oVCard->UID;
            if (empty($Uid)) {
                $Uid = UUIDUtil::getUUID();
            }
            if ((isset($oVCard->KIND) && (string) $oVCard->KIND === 'GROUP') ||
                (isset($oVCard->{'X-ADDRESSBOOKSERVER-KIND'}) && (string) $oVCard->{'X-ADDRESSBOOKSERVER-KIND'} === 'GROUP')) {
                $aGroupsData[] = Classes\VCard\Helper::GetGroupDataFromVcard($oVCard, $Uid);
            } else {
                $aContactData = Classes\VCard\Helper::GetContactDataFromVcard($oVCard, $Uid);
                $oContact = self::Decorator()->GetContact($Uid, $iUserId);
                $aImportResult['ParsedCount']++;
                if (!$oContact) {
                    if (isset($sStorage)) {
                        $aContactData['Storage'] = $sStorage;
                    }
                    $aContactsData[$Uid] = $aContactData;
                }
            }
        }

        foreach ($aContactsData as $key => $aContactData) {
            $CreatedContactData = $oContactsDecorator->CreateContact($aContactData, $iUserId);
            if ($CreatedContactData) {
                $aImportResult['ImportedCount']++;
                $aImportResult['ImportedUids'][] = $CreatedContactData['UUID'];
                $aContactsData[$key]['NewUUID'] = $CreatedContactData['UUID'];
            }
        }

        foreach ($aGroupsData as $aGroupData) {
            if (isset($aGroupData['Contacts'])) {
                $aUuids = $aGroupData['Contacts'];
                $aGroupData['Contacts'] = [];
                foreach ($aUuids as $value) {
                    if (isset($aContactsData[$value])) {
                        $aGroupData['Contacts'][] = $aContactsData[$value]['NewUUID'];
                    }
                }
            }
            $oContactsDecorator->CreateGroup($aGroupData, $iUserId);
        }

        return $aImportResult;
    }

    protected function populateContactArguments(&$aArgs)
    {
        $mResult = false;
        $this->broadcastEvent('PopulateContactArguments', $aArgs, $mResult);
        return $mResult;
    }

    private function prepareFiltersFromStorage($UserId, $Storage = '', $AddressBookId = 0, &$Query = null, &$WhereQuery = null, $Suggestions = false)
    {
        $aArgs = [
            'UserId' => $UserId,
            'Storage' => $Storage,
            'AddressBookId' => $AddressBookId,
            'IsValid' => false,
            'Query' => $Query,
            'Suggestions' => $Suggestions
        ];

        $this->broadcastEvent('PrepareFiltersFromStorage', $aArgs, $WhereQuery);
        if (!$aArgs['IsValid']) {
            throw new ApiException(Notifications::InvalidInputParameter, null, 'Invalid Storage parameter value');
        }
        return $WhereQuery;
    }

    public function onAfterUseEmails($Args, &$Result)
    {
        $aAddresses = $Args['Emails'];
        $iUserId = $Args['IdUser'];
        foreach ($aAddresses as $sEmail => $sName) {
            try {
                $contactsColl = self::GetContactsByEmails($iUserId, StorageType::Personal, [$sEmail], null, false);

                $oContact = $contactsColl->first();
                if (!$oContact) {
                    $contactsColl = self::GetContactsByEmails($iUserId, StorageType::Collected, [$sEmail], null, false);
                    $oContact = $contactsColl->first();
                }

                if ($oContact) {
                    ContactCard::where('CardId', $oContact->Id)->update(['Frequency' => $oContact->Frequency + 1]);
                } else {
                    self::Decorator()->CreateContact([
                        'FullName' => $sName,
                        'PersonalEmail' => $sEmail,
                        'Auto' => true,
                        'Storage' => StorageType::Collected,
                    ], $iUserId);
                }
            } catch (\Exception $ex) {
            }
        }
    }

    public function onGetBodyStructureParts($aParts, &$aResultParts)
    {
        foreach ($aParts as $oPart) {
            if ($oPart instanceof \MailSo\Imap\BodyStructure &&
                    ($oPart->ContentType() === 'text/vcard' || $oPart->ContentType() === 'text/x-vcard')) {
                $aResultParts[] = $oPart;
                break;
            }
        }
    }

    public function onBeforeDeleteUser(&$aArgs, &$mResult)
    {
        if (isset($aArgs['UserId'])) {
            $this->userPublicIdToDelete = Api::getUserPublicIdById($aArgs['UserId']);
        }
    }

    public function onAfterDeleteUser(&$aArgs, &$mResult)
    {
        if ($mResult && $this->userPublicIdToDelete) {
            $abooks = Backend::Carddav()->getAddressBooksForUser(Constants::PRINCIPALS_PREFIX . $this->userPublicIdToDelete);
            if ($abooks) {
                foreach ($abooks as $book) {
                    Backend::Carddav()->deleteAddressBook($book['id']);
                }
            }
        }
    }

    public function onContactToResponseArray($aArgs, &$mResult)
    {
        if (isset($aArgs[0]) && $aArgs[0] instanceof Contact && is_array($mResult)) {
            $mResult['UUID'] = $mResult['Id'];
        }
    }
    /***** private functions *****/

    public function GetAddressBook($UserId, $UUID)
    {
        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $principalUri = Constants::PRINCIPALS_PREFIX . \Aurora\System\Api::getUserPublicIdById($UserId);

        return Backend::Carddav()->getAddressBookForUser($principalUri, $UUID);
    }

    public function GetAddressBooks($UserId = null)
    {
        $aResult = [];

        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        return $aResult;
    }

    public function CreateAddressBook($AddressBookName, $UserId = null, $UUID = null)
    {
        $mResult = false;

        Api::CheckAccess($UserId);

        if (isset($UUID)) {
            $sAddressBookUUID = $UUID;
        } else {
            $sAddressBookUUID = UUIDUtil::getUUID();
        }

        $userPublicId = Api::getUserPublicIdById($UserId);

        $iAddressBookId = Backend::Carddav()->createAddressBook(Constants::PRINCIPALS_PREFIX . $userPublicId, $sAddressBookUUID, ['{DAV:}displayname' => $AddressBookName]);

        if (is_numeric($iAddressBookId)) {
            $oAddressBook = Backend::Carddav()->getAddressBookById($iAddressBookId);
            if ($oAddressBook) {
                return [
                    'Id' => StorageType::AddressBook . '-' . $oAddressBook['id'],
                    'EntityId' => (int) $oAddressBook['id'],
                    'CTag' => (int) $oAddressBook['{http://sabredav.org/ns}sync-token'],
                    'Display' => true,
                    'Owner' => basename($oAddressBook['principaluri']),
                    'Order' => 1,
                    'DisplayName' => $oAddressBook['{DAV:}displayname'],
                    'Uri' => $oAddressBook['uri']
                ];

            }
        }

        return $mResult;
    }

    public function UpdateAddressBook($EntityId, $AddressBookName, $UserId = null)
    {
        $mResult = false;

        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        if ($this->CheckAccessToAddressBook($UserId, $EntityId, Access::Write)) {
            $propParch = new PropPatch([
                '{DAV:}displayname' => $AddressBookName
            ]);
            Backend::Carddav()->updateAddressBook($EntityId, $propParch);
            $mResult = $propParch->commit();
        }

        return $mResult;
    }

    public function DeleteAddressBook($EntityId, $UserId = null)
    {
        $mResult = false;

        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $userPublicId = Api::getUserPublicIdById($UserId);

        $abook = Capsule::connection()->table('adav_addressbooks')
            ->where('id', $EntityId)
            ->where('principaluri', Constants::PRINCIPALS_PREFIX . $userPublicId)
            ->first();

        if ($abook) {
            Backend::Carddav()->deleteAddressBook($EntityId);
            $mResult = true;
        }

        return $mResult;
    }

    public function DeleteUsersAddressBooks($UserId = null)
    {
        $mResult = false;

        Api::CheckAccess($UserId);

        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $userPublicId = Api::getUserPublicIdById($UserId);

        $abooks = Capsule::connection()->table('adav_addressbooks')
            ->where('principaluri', Constants::PRINCIPALS_PREFIX . $userPublicId)
            ->get();

        foreach ($abooks as $abook) {
            Backend::Carddav()->deleteAddressBook($abook->id);
            $mResult = true;
        }

        return $mResult;
    }

    public function GetStoragesMapToAddressbooks()
    {
        return [];
    }

    protected function getGetContactsQueryBuilder($UserId, $Storage = '', $AddressBookId = null, Builder $Filters = null, $Suggestions = false, $withGroups = false)
    {
        if ($Filters instanceof Builder) {
            $query = & $Filters;
        } else {
            $query = ContactCard::query();
        }

        $con = Capsule::connection();
        $query->join('adav_cards', 'contacts_cards.CardId', '=', 'adav_cards.id')
            ->select(
                'adav_cards.id as Id',
                'adav_cards.id as UUID',
                'adav_cards.uri as Uri',
                'adav_cards.addressbookid as Storage',
                'etag as ETag',
                $con->raw('FROM_UNIXTIME(lastmodified) as DateModified'),
                'PrimaryEmail',
                'PersonalEmail',
                'BusinessEmail',
                'OtherEmail',
                'BusinessCompany',
                'FullName',
                'FirstName',
                'LastName',
                'Frequency',
                'Properties',
                $con->raw('(Frequency/CEIL(DATEDIFF(CURDATE() + INTERVAL 1 DAY, FROM_UNIXTIME(lastmodified))/30)) as AgeScore'),
                $con->raw($UserId . ' as UserId')
            )
            ->where(function ($wherQuery) use ($UserId, $Storage, $AddressBookId, $query, $Suggestions) {
                $this->prepareFiltersFromStorage($UserId, $Storage, $AddressBookId, $query, $wherQuery, $Suggestions);
            });
        if (!$withGroups) {
            $query->where('IsGroup', false);
        }
        if ($Suggestions) {
            $query->where('Frequency', '>=', 0);
        }

        return $query;
    }
}
