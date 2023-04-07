<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts\Models;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 * @package Models
 * @subpackage AddressBook
 */

use Aurora\Modules\Core\Models\User;
use Aurora\System\Classes\Model;

/**
 * Aurora\Modules\Contacts\Models\AddressBook
 *
 * @property int $Id
 * @property string $UUID
 * @property int $UserId
 * @property string $Name
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read mixed $entity_id
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\AddressBook firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|AddressBook newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AddressBook newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AddressBook query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\AddressBook where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|AddressBook whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AddressBook whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\AddressBook whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|AddressBook whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AddressBook whereUUID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AddressBook whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AddressBook whereUserId($value)
 */
class AddressBook extends Model
{
    public $table = 'contacts_addressbooks';

    protected $foreignModel = User::class;
    protected $foreignModelIdColumn = 'UserId'; // Column that refers to an external table

    protected $fillable = [
        'Id',
        'UUID',
        'UserId',
        'Name'
    ];
}
