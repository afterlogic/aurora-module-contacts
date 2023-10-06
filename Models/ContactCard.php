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
 * @property integer $CardId
 * @property integer|null $AddressBookId
 * @property string $FullName
 * @property string $ViewEmail
 * @property string $FirstName
 * @property string $LastName
 * @property integer $Frequency
 * @property array|null $Properties
 * @property-read mixed $age_score
 */
class ContactCard extends Model
{
    protected $table = 'contacts_cards';

    public $timestamps = false;

    protected $fillable = [
        'Id',
        'CardId',
        'AddressBookId',
        'PrimaryEmail',
        'ViewEmail',
        'PersonalEmail',
        'BusinessEmail',
        'OtherEmail',
        'BusinessCompany',
        'FullName',
        'FirstName',
        'LastName',
        'Frequency',
        'Properties'
    ];

    protected $casts = [
        'Properties' => 'array',
    ];

    protected $appends = [
        'UUID',
        'AgeScore',
        'UserId',
        'Storage',
        "DateModified",
        "ETag",
        'ViewEmail'
    ];

    public function getUUIDAttribute()
    {
        return $this->attributes['UUID'];
    }

    public function getAgeScoreAttribute()
    {
        return $this->attributes['AgeScore'];
    }

    public function getUserIdAttribute()
    {
        return $this->attributes['UserId'];
    }

    public function getStorageAttribute()
    {
        return $this->attributes['Storage'];
    }

    public function getDateModifiedAttribute()
    {
        return $this->attributes['DateModified'];
    }

    public function getETagAttribute()
    {
        return $this->attributes['ETag'];
    }

    /**
     * Returns value of email that is specified as primary.
     * @return string
     */
    protected function getViewEmailAttribute()
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
}
