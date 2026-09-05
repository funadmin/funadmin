<?php

declare(strict_types=1);

namespace app\common\model\concern;

use think\Model;
use think\model\Collection;
use think\model\concern\SoftDelete as ThinkSoftDelete;

/**
 * 使用 Laravel 风格 deleted_at 字段实现软删除。
 */
trait LaravelSoftDelete
{
    use ThinkSoftDelete;

    public function delete(): bool
    {
        if ($this->isEmpty() || false === $this->trigger('BeforeDelete')) {
            return false;
        }

        $name = $this->getDeleteTimeField();
        if (!$name || $this->isForce()) {
            return parent::delete();
        }

        $relations = [];
        foreach ($this->getData() as $key => $value) {
            if ($value instanceof Model || $value instanceof Collection) {
                $relations[$key] = $value;
            }
        }

        $deletedAt = $this->getDateTime($name);
        $this->exists()->withEvent(false)->save([
            'deleted_at' => $deletedAt,
        ]);

        $this->withEvent(true);
        if ($relations) {
            $this->relationDelete($relations);
        }
        $this->trigger('AfterDelete');
        $this->exists(false);
        $this->clear();

        return true;
    }

    public function restore(array $where = []): bool
    {
        $name = $this->getDeleteTimeField();
        if (!$name || false === $this->trigger('BeforeRestore')) {
            return false;
        }

        $this->getDbWhere($where)
            ->useSoftDelete($name, $this->getWithTrashedExp())
            ->update([
                'deleted_at' => null,
            ]);
        $this->trigger('AfterRestore');

        return true;
    }
}
