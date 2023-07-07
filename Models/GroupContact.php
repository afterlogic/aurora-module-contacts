<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\Contacts\Models;

/**
 * Aurora\Modules\Contacts\Models\GroupContact
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 * @package Models
 * @subpackage GroupEvent
 * @property integer $Id
 * @property integer $GroupId
 * @property integer $ContactId
 * @property-read mixed $entity_id
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\GroupContact firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|GroupContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupContact query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\GroupContact where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|GroupContact whereContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupContact whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupContact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\GroupContact whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 */

use Aurora\System\Classes\Model;
use Aurora\Modules\Contacts\Models\Group;

class GroupContact extends Model
{
    public $table = 'contacts_group_contact';
    protected $foreignModel = Group::class;
    protected $foreignModelIdColumn = 'GroupId'; // Column that refers to an external table

    protected $fillable = [
        'Id',
        'GroupId',
        'ContactId'
    ];
}
