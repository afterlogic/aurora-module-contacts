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
 * @subpackage GroupEvent
 */

use Aurora\System\Classes\Model;

/**
 * Aurora\Modules\Contacts\Models\GroupEvent
 *
 * @property string $GroupUUID
 * @property string $ContactUUID
 * @property integer $Id
 * @property string $CalendarUUID
 * @property string $EventUUID
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read mixed $entity_id
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\GroupEvent firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|GroupEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\GroupEvent where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|GroupEvent whereCalendarUUID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupEvent whereEventUUID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupEvent whereGroupUUID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\Contacts\Models\GroupEvent whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupEvent whereUpdatedAt($value)
 */
class GroupEvent extends Model
{
    public $table = 'contacts_group_events';
    protected $fillable = [
        'Id',
        'GroupUUID',
        'CalendarUUID',
        'EventUUID'
    ];
}
