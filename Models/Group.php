<?php

namespace Aurora\Modules\Contacts\Models;

use Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\User;

/**
 * Aurora\Modules\Contacts\Models\Group
 *
 * @property integer $Id
 * @property string $UUID
 * @property integer $IdUser
 * @property string $Name
 * @property boolean $IsOrganization
 * @property string $Email
 * @property string $Company
 * @property string $Street
 * @property string $City
 * @property string $State
 * @property string $Zip
 * @property string $Country
 * @property string $Phone
 * @property string $Fax
 * @property string $Web
 * @property string $Events
 * @property array|null $Properties
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Contact> $Contacts
 * @property-read int|null $contacts_count
 * @property-read mixed $entity_id
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\Group firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Group newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Group newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Group query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\Group where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereCompany($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereEvents($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereFax($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\Group whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereIsOrganization($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereStreet($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereUUID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereWeb($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Group whereZip($value)
 */
class Group extends Model
{
    protected $table = 'contacts_groups';
    protected $foreignModel = User::class;
    protected $foreignModelIdColumn = 'IdUser'; // Column that refers to an external table

    public $Events = array();

    public $GroupContacts = array();

    protected $fillable = [
        'Id',
        'IdUser',
        'UUID',
        'Name',
        'IsOrganization',
        'Email',
        'Company',
        'Street',
        'City',
        'State',
        'Zip',
        'Country',
        'Phone',
        'Fax',
        'Web',
        'Events'
    ];

    protected $casts = [
        'Properties' => 'array',

        'IsOrganization' => 'boolean'
    ];

    public function populate($aGroup)
    {
        parent::populate($aGroup);

        if (!empty($aGroup['UUID'])) {
            $this->UUID = $aGroup['UUID'];
        } elseif (empty($this->UUID)) {
            $this->UUID = \Sabre\DAV\UUIDUtil::getUUID();
        }
    }

    public function Contacts()
    {
        return $this->belongsToMany(Contact::class, 'contacts_group_contact', 'GroupId', 'ContactId')
            ->where('IdUser', $this->IdUser);
    }
}
