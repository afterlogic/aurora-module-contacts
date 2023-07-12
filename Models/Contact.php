<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts\Models;

use Aurora\System\Classes\Model;
use Aurora\Modules\Contacts\Classes\VCard\Helper;
use Aurora\Modules\Contacts\Enums\StorageType;
use Aurora\Modules\Contacts\Models\Group;
use Aurora\Modules\Core\Models\User;
use Aurora\System\EventEmitter;

/**
 * Aurora\Modules\Contacts\Models\Contact
 *
 * @property integer $Id
 * @property string $UUID
 * @property integer $IdUser
 * @property integer $IdTenant
 * @property string $Storage
 * @property integer|null $AddressBookId
 * @property string $FullName
 * @property boolean $UseFriendlyName
 * @property integer $PrimaryEmail
 * @property integer $PrimaryPhone
 * @property integer $PrimaryAddress
 * @property string $ViewEmail
 * @property string $Title
 * @property string $FirstName
 * @property string $LastName
 * @property string $NickName
 * @property string $Skype
 * @property string $Facebook
 * @property string $PersonalEmail
 * @property string $PersonalAddress
 * @property string $PersonalCity
 * @property string $PersonalState
 * @property string $PersonalZip
 * @property string $PersonalCountry
 * @property string $PersonalWeb
 * @property string $PersonalFax
 * @property string $PersonalPhone
 * @property string $PersonalMobile
 * @property string $BusinessEmail
 * @property string $BusinessCompany
 * @property string $BusinessAddress
 * @property string $BusinessCity
 * @property string $BusinessState
 * @property string $BusinessZip
 * @property string $BusinessCountry
 * @property string $BusinessJobTitle
 * @property string $BusinessDepartment
 * @property string $BusinessOffice
 * @property string $BusinessPhone
 * @property string $BusinessFax
 * @property string $BusinessWeb
 * @property string $OtherEmail
 * @property string|null $Notes
 * @property integer $BirthDay
 * @property integer $BirthMonth
 * @property integer $BirthYear
 * @property string $ETag
 * @property boolean $Auto
 * @property integer $Frequency
 * @property string|null $DateModified
 * @property array|null $Properties
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Group> $Groups
 * @property-read int|null $groups_count
 * @property-read mixed $age_score
 * @property-read mixed $entity_id
 * @property-read mixed $notes
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\Contact firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Contact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Contact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Contact query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\Contact where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\Contact whereNotNull(string|array $columns, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereAddressBookId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereAuto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBirthDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBirthMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBirthYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessCompany($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessDepartment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessFax($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessJobTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessOffice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessWeb($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereBusinessZip($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereDateModified($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereETag($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereFacebook($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereIdTenant($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\Contact whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereNickName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereOtherEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePersonalAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePersonalCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePersonalCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePersonalEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePersonalFax($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePersonalMobile($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePersonalPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePersonalState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePersonalWeb($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePersonalZip($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePrimaryAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePrimaryEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact wherePrimaryPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereSkype($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereStorage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereUUID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereUseFriendlyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Contact whereViewEmail($value)
 */
class Contact extends Model
{
    public $GroupsContacts = array();

    public $ExtendedInformation = array();

    protected $foreignModel = User::class;
    protected $foreignModelIdColumn = 'IdUser'; // Column that refers to an external table

    protected $fillable = [
        'Id',
        'UUID',
        'IdUser',
        'IdTenant',
        'Storage',
        'AddressBookId',
        'FullName',
        'UseFriendlyName',
        'PrimaryEmail',
        'PrimaryPhone',
        'PrimaryAddress',
        'ViewEmail',

        'Title',
        'FirstName',
        'LastName',
        'NickName',
        'Skype',
        'Facebook',

        'PersonalEmail',
        'PersonalAddress',
        'PersonalCity',
        'PersonalState',
        'PersonalZip',
        'PersonalCountry',
        'PersonalWeb',
        'PersonalFax',
        'PersonalPhone',
        'PersonalMobile',

        'BusinessEmail',
        'BusinessCompany',
        'BusinessAddress',
        'BusinessCity',
        'BusinessState',
        'BusinessZip',
        'BusinessCountry',
        'BusinessJobTitle',
        'BusinessDepartment',
        'BusinessOffice',
        'BusinessPhone',
        'BusinessFax',
        'BusinessWeb',

        'OtherEmail',
        'Notes',

        'BirthDay',
        'BirthMonth',
        'BirthYear',

        'ETag',
        'Auto',
        'Frequency',
        'DateModified',
        'Properties'
    ];

    protected $casts = [
        'Properties' => 'array',
        'Auto' => 'boolean',
        'UseFriendlyName' => 'boolean'
    ];

    protected $appends = [
        'AgeScore'
    ];

    public function getAgeScoreAttribute()
    {
        return 0;
    }

    public function getNotesAttribute()
    {
        if (is_null($this->attributes['Notes'])) {
            $this->attributes['Notes'] = '';
        }

        return $this->attributes['Notes'];
    }

    /**
     * Adds groups to contact. Groups are specified by names.
     * @param array $aGroupNames List of group names.
     */
    protected function addGroupsFromNames($aGroupNames)
    {
        $aNonExistingGroups = [];
        if (is_array($aGroupNames) && count($aGroupNames) > 0) {
            $oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
            $oApiContactsManager = $oContactsDecorator ? $oContactsDecorator->GetApiContactsManager() : null;
            if ($oApiContactsManager) {
                foreach ($aGroupNames as $sGroupName) {
                    $oGroups = $oApiContactsManager->getGroups($this->IdUser, Group::where('Name', $sGroupName));
                    if ($oGroups && count($oGroups) > 0) {
                        $this->Groups()->sync(
                            $oGroups->map(function ($oGroup) {
                                return $oGroup->Id;
                            })->toArray(),
                            false
                        );
                    }

                    // Group shouldn't be created here.
                    // Very often after this populating contact will never be created.
                    // It can be used only for suggestion to create.
                    elseif (!empty($sGroupName)) {
                        $oGroup = new Group();
                        $oGroup->IdUser = $this->IdUser;
                        $oGroup->Name = $sGroupName;
                        $aNonExistingGroups[] = $oGroup;
                    }
                }
            }
        }

        return $aNonExistingGroups;
    }

    /**
     * Add group to contact.
     * @param array $aGroupUUIDs array of Group UUIDs.
     * @param array $aGroupNames array of Group Names.
     * @param bool $bCreateNonExistingGroups
     */
    public function addGroups($aGroupUUIDs, $aGroupNames, $bCreateNonExistingGroups = false)
    {
        if (is_array($aGroupUUIDs)) {
            $this->Groups()->sync(Group::whereIn('UUID', $aGroupUUIDs)
                ->get()->map(
                    function ($oGroup) {
                        return $oGroup->Id;
                    }
                )->toArray());
        }
        $aNonExistingGroups = [];
        if (is_array($aGroupNames)) {
            $aNonExistingGroups = $this->addGroupsFromNames($aGroupNames);
        }

        if ($bCreateNonExistingGroups && is_array($aNonExistingGroups) && count($aNonExistingGroups) > 0) {
            $oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
            $oApiContactsManager = $oContactsDecorator ? $oContactsDecorator->GetApiContactsManager() : null;
            if ($oApiContactsManager) {
                $oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
                $oApiContactsManager = $oContactsDecorator ? $oContactsDecorator->GetApiContactsManager() : null;
                if ($oApiContactsManager) {
                    $aGroupIds = [];
                    foreach ($aNonExistingGroups as $oGroup) {
                        $oApiContactsManager->createGroup($oGroup);
                        $aGroupIds[] = $oGroup->Id;
                    }
                    if (count($aGroupIds) > 0) {
                        $this->Groups()->sync($aGroupIds, false);
                    }
                }
            }
        }
    }

    /**
     * Returns value of email that is specified as primary.
     * @return string
     */
    protected function getViewEmail()
    {
        switch ((int) $this->PrimaryEmail) {
            default:
            case \Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal:
                return (string) $this->PersonalEmail;
            case \Aurora\Modules\Contacts\Enums\PrimaryEmail::Business:
                return (string) $this->BusinessEmail;
            case \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other:
                return (string) $this->OtherEmail;
        }
    }

    /**
     * Sets ViewEmail field.
     */
    public function SetViewEmail()
    {
        $this->ViewEmail = $this->getViewEmail();
    }

    /**
     * Inits contacts from Vcard string.
     * @param int $iUserId User identifier.
     * @param string $sData Vcard string.
     * @param string $sUid Contact UUID.
     */
    public function InitFromVCardStr($iUserId, $sData, $sUid = '')
    {
        $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserWithoutRoleCheck($iUserId);
        if ($oUser instanceof \Aurora\Modules\Core\Models\User) {
            $this->IdUser = $oUser->Id;
            $this->IdTenant = $oUser->IdTenant;
        }

        if (!empty($sUid)) {
            $this->UUID = $sUid;
        }

        $this->populate(
            Helper::GetContactDataFromVcard(
                \Sabre\VObject\Reader::read(
                    $sData,
                    \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
                )
            )
        );
    }

    /**
     * Populate contact with specified data.
     * @param array $aContact List of contact data.
     */
    public function populate($aContact, $bCreateNonExistingGroups = false)
    {
        $aNonExistingGroups = [];
        $aStorageParts = \explode('-', $aContact['Storage']);
        if (isset($aStorageParts[0]) && $aStorageParts[0] === StorageType::AddressBook) {
            $aContact['AddressBookId'] = (int) $aStorageParts[1];
            $aContact['Storage'] = StorageType::AddressBook;
        }
        parent::populate($aContact);

        if (!empty($aContact['UUID'])) {
            $this->UUID = $aContact['UUID'];
        } elseif (empty($this->UUID)) {
            $this->UUID = \Sabre\DAV\UUIDUtil::getUUID();
        }
        $this->SetViewEmail();

        EventEmitter::getInstance()->emit('Contacts', 'PopulateContactModel', $this);
    }


    /**
     * Returns array with contact data.
     * @return array
     */
    public function toResponseArray()
    {
        //		$this->calculateETag();

        $aRes = parent::toResponseArray();
        if (is_null($aRes['Notes'])) {
            $aRes['Notes'] = '';
        }
        $aRes['GroupUUIDs'] = array_filter($this->Groups->map(function ($oGroup) {
            return $oGroup->IdUser === $this->IdUser ? $oGroup->UUID : null;
        })->toArray());

        foreach ($this->ExtendedInformation as $sKey => $mValue) {
            $aRes[$sKey] = $mValue;
        }

        $aArgs = ['Contact' => $this];
        \Aurora\System\Api::GetModule('Core')->broadcastEvent(
            'Contacts::Contact::ToResponseArray',
            $aArgs,
            $aRes
        );

        return $aRes;
    }

    public function calculateETag()
    {
        $this->ETag = \md5(\json_encode($this));
    }

    public function Groups()
    {
        return $this->belongsToMany(Group::class, 'contacts_group_contact', 'ContactId', 'GroupId');
    }

    public function getStorageWithId()
    {
        if ($this->Storage === StorageType::AddressBook) {
            return $this->Storage . $this->AddressBookId;
        } else {
            return $this->Storage;
        }
    }
}
