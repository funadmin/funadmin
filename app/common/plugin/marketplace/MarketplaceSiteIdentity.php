<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

use Ramsey\Uuid\Uuid;
use think\facade\Cache;
use think\facade\Db;

/** 站点匿名标识：用于插件市场按站点去重统计安装量，不包含域名或任何业务数据。 */
final class MarketplaceSiteIdentity
{
    private const GROUP = 'marketplace';
    private const CODE = 'marketplace_site_id';

    public static function id(): string
    {
        $existing = (string) (syscfg(self::GROUP, self::CODE) ?? '');
        if (self::valid($existing)) {
            return $existing;
        }
        $id = Uuid::uuid4()->toString();
        try {
            Db::name('config')->insert([
                'code' => self::CODE,
                'value' => $id,
                'remark' => '插件市场匿名站点标识',
                'type' => 'text',
                'group' => self::GROUP,
                'status' => 1,
                'is_system' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // 并发生成时唯一键冲突，以已写入的值为准。
        }
        Cache::delete('syscfg:' . hash('sha256', self::GROUP . "\0" . self::CODE));
        $stored = (string) (Db::name('config')->where('code', self::CODE)->value('value') ?? '');
        return self::valid($stored) ? $stored : $id;
    }

    private static function valid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id) === 1;
    }
}
