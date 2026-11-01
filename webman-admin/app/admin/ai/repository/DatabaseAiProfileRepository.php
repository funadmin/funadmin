<?php

declare(strict_types=1);
namespace app\admin\ai\repository;

use app\admin\ai\model\AiConfigurationProfile;
use RuntimeException;
use think\facade\Db;

/** 所有查询均带管理员边界；写操作锁定档案，默认唯一性另由数据库约束兜底。 */
final class DatabaseAiProfileRepository
{
    private function query(int $adminId): \think\db\Query
    {
        if ($adminId <= 0) throw new RuntimeException('未登录', 401);
        return AiConfigurationProfile::where('admin_id', $adminId);
    }

    public function transaction(callable $operation): mixed { return Db::transaction($operation); }

    public function find(int $adminId, int $id, bool $lock = false): AiConfigurationProfile
    {
        $row = $this->query($adminId)->where('id', $id)->lock($lock)->find();
        if (!$row) throw new RuntimeException('档案不存在', 404);
        return $row;
    }

    public function all(int $adminId): array { return $this->query($adminId)->order('id')->select()->all(); }

    public function create(int $adminId, array $data): AiConfigurationProfile
    {
        $this->query($adminId);
        return AiConfigurationProfile::create($data + ['admin_id'=>$adminId, 'is_default'=>false]);
    }

    public function makeDefault(int $adminId, int $id): AiConfigurationProfile
    {
        return $this->transaction(function () use ($adminId, $id) {
            // 按固定顺序锁定管理员所有档案，避免切换默认时的反向加锁。
            $this->query($adminId)->order('id')->lock(true)->select();
            $row = $this->find($adminId, $id, true);
            $this->query($adminId)->where('is_default', 1)->update(['is_default'=>0, 'updated_at'=>date('Y-m-d H:i:s')]);
            $row->save(['is_default'=>true]);
            return $row;
        });
    }
}
