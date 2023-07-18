<?php

namespace Aurora\Modules\Contacts\Models;

use Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Core\Models\Tenant;

/**
 * Aurora\Modules\Contacts\Models\CTag
 *
 * @property integer $Id
 * @property integer $UserId
 * @property string $Storage
 * @property integer $CTag
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read mixed $entity_id
 * @method static \Illuminate\Database\Eloquent\Builder|CTag firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|CTag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CTag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CTag query()
 * @method static \Illuminate\Database\Eloquent\Builder|CTag firstOrCreate(array $attributes = [], array $values = [])
 * @method static \Illuminate\Database\Eloquent\Builder|CTag where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|CTag whereCTag($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CTag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CTag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CTag whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|CTag whereStorage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CTag whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CTag whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CTag increment(string|\Illuminate\Database\Query\Expression $column, float|int $amount = 1, array $extra = [])
 */
class CTag extends Model
{
    protected $table = 'contacts_ctags';

    protected $foreignModel = User::class;
    protected $foreignModelSecond = Tenant::class;
    protected $foreignModelIdColumn = 'UserId'; // Column that refers to an external table

    protected $fillable = [
        'Id',
        'UserId',
        'Storage',
        'CTag'
    ];

    public function getOrphanIds()
    {
        if (!$this->foreignModel || !$this->foreignModelIdColumn) {
            return ['status' => -1, 'message' => 'Foreign field doesn\'t exist'];
        }
        $tableName = $this->getTable();
        $foreignObject = new $this->foreignModel();
        $foreignTable = $foreignObject->getTable();
        $foreignPK = $foreignObject->primaryKey;

        $orphanIds = self::where('Storage', '<>', 'team')->where('Storage', '<>', 'shared')->pluck($this->primaryKey)->diff(
            self::leftJoin($foreignTable, "$tableName.$this->foreignModelIdColumn", '=', "$foreignTable.$foreignPK")->whereNotNull("$foreignTable.$foreignPK")->where('Storage', '<>', 'team')->where('Storage', '<>', 'shared')->pluck("$tableName.$this->primaryKey")
        )->all();

        $foreignSecondObject = new $this->foreignModelSecond;
        $foreignSecondTable = $foreignSecondObject->getTable();
        $foreignSecondPK = $foreignSecondObject->primaryKey;
        $orphanSecondIds = self::where('Storage', 'team')->where('Storage', 'shared')->pluck($this->primaryKey)->diff(
            self::leftJoin($foreignSecondTable, "$tableName.$this->foreignModelIdColumn", '=', "$foreignSecondTable.$foreignSecondPK")->whereNotNull("$foreignSecondTable.$foreignSecondPK")->where('Storage', 'team')->where('Storage', 'shared')->pluck("$tableName.$this->primaryKey")
        )->all();

        $orphanIds = array_merge($orphanIds, $orphanSecondIds);

        $message = $orphanIds ? "$tableName table has orphans: " . count($orphanIds) . "." : "Orphans were not found.";
        $oResult = ['status' => $orphanIds ? 1 : 0, 'message' => $message, 'orphansIds' => $orphanIds];

        return $oResult;
    }
}
