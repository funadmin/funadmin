<?php

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;
use app\common\model\concern\TokenSoftDeletingScope;
use Illuminate\Database\Eloquent\Builder;

class SystemMigration extends BaseModel
{
    use LaravelSoftDelete;

    protected $hidden = ['deleted_token'];

    protected $name = 'system_migration';

    public $timestamps = false;
    protected $guarded = [];

    /**
     * 迁移登记查询。安装或升级执行核心 001–004 时，system_migration 还没有 deleted_at（由 005 补上），
     * 这时不能加软删除条件；补列之后照常排除已删除的登记。
     *
     * @return Builder<static>
     */
    public static function ledger(): Builder
    {
        $query = static::query();
        $model = $query->getModel();
        return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $model->getDeletedAtColumn())
            ? $query
            : $query->withoutGlobalScope(TokenSoftDeletingScope::class);
    }
}
