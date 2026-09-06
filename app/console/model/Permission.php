<?php

namespace app\console\model;

class Permission extends BackendModel
{
    protected $name = 'permission';

    public const TYPE_GROUP = 'group';
    public const TYPE_ROUTE = 'route';

    public static function childIds(int $id): array
    {
        $result = [];
        $queue = [$id];
        while ($queue) {
            $children = self::whereIn('pid', $queue)->column('id');
            $queue = [];
            foreach ($children as $childId) {
                $childId = (int) $childId;
                if (!in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $result;
    }
}
