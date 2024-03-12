<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts\Models;

use Aurora\Modules\Contacts\Enums\StorageType;
use Aurora\System\Classes\Model;

/**
 * Aurora\Modules\Contacts\Models\ContactCard
 *
 * @property integer $Id
 * @property integer $CardId
 * @property integer|null $AddressBookId
 * @property string $PrimaryEmail
 * @property string $ViewEmail
 * @property string $PersonalEmail
 * @property string $BusinessEmail
 * @property string $OtherEmail
 * @property string $BusinessCompany
 * @property string $FullName
 * @property string $FirstName
 * @property string $LastName
 * @property integer $Frequency
 * @property bool $IsGroup
 * @property array|null $Properties
 *
 * @property bool $Auto
 * @property bool $Shared
 * @property bool $IsTeam
 *
 * @property string $UUID
 * @property string $ETag
 * @property string $Storage
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ContactCard select(mixed ...$args)
 * @method static \Illuminate\Database\Eloquent\Builder|ContactCard firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|ContactCard whereNotNull(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|ContactCard where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')

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
        'IsGroup',
        'Properties'
    ];

    protected $casts = [
        'Properties' => 'array',
        'Auto' => 'boolean',
        'Shared' => 'boolean',
        'IsTeam' => 'boolean',
    ];

    protected $appends = [
        'UUID',
        'AgeScore',
        'UserId',
        'Storage',
        "DateModified",
        "ETag",
        'ViewEmail',
        'Uri',
    ];

    public function getUUIDAttribute()
    {
        return $this->attributes['UUID'];
    }

    public function getAgeScoreAttribute()
    {
        return round($this->attributes['AgeScore']);
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

    public function getUriAttribute()
    {
        return $this->attributes['Uri'];
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
