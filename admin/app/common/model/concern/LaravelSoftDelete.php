<?php

declare(strict_types=1);

namespace app\common\model\concern;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;

/**
 * 使用 Laravel 风格 deleted_at 字段实现软删除，并维护 deleted_token。
 *
 * - 删除：deleted_at = now，deleted_token = 自身主键（唯一索引含 deleted_token，删除后可重建相同唯一值）。
 * - 恢复：deleted_at = null，deleted_token = 0；与未删除记录唯一值冲突时抛 RestoreConflictException。
 * - 表上没有 deleted_token 列时只写 deleted_at。
 * - 真实删除使用 forceDelete()。
 */
trait LaravelSoftDelete
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    /**
     * 替换 SoftDeletes 自带的作用域，使批量删除/恢复同时维护 deleted_token。
     */
    public static function bootSoftDeletes()
    {
        static::addGlobalScope(new TokenSoftDeletingScope());
    }

    /**
     * 当前表是否有 deleted_token 列（按连接与表缓存）。
     */
    public function hasDeletedToken(): bool
    {
        return TokenSoftDeletingScope::modelHasDeletedToken($this);
    }

    public function getDeletedTokenColumn(): string
    {
        return TokenSoftDeletingScope::TOKEN_COLUMN;
    }

    protected function runSoftDelete()
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->hasDeletedToken()) {
            $columns[$this->getDeletedTokenColumn()] = $this->getKey();
            $this->{$this->getDeletedTokenColumn()} = $this->getKey();
        }

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('trashed', false);
    }

    public function restore()
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $attributes = $this->getAttributes();
        $exists = $this->exists;

        $this->{$this->getDeletedAtColumn()} = null;
        if ($this->hasDeletedToken()) {
            $this->{$this->getDeletedTokenColumn()} = 0;
        }

        $this->exists = true;

        try {
            $result = $this->save();
        } catch (QueryException $error) {
            // 恢复失败时回滚内存中的属性，避免模型误以为已经恢复。
            $this->setRawAttributes($attributes);
            $this->exists = $exists;
            if (RestoreConflictException::isUniqueViolation($error)) {
                throw new RestoreConflictException($error);
            }
            throw $error;
        }

        if ($result) {
            $this->fireModelEvent('restored', false);
        }

        return $result;
    }
}
