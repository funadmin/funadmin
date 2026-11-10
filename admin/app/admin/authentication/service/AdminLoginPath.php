<?php
declare(strict_types=1);

namespace app\admin\authentication\service;

use app\common\model\Config;
use InvalidArgumentException;
use support\Cache;
use support\Db;
use app\common\model\ConfigGroup;

/** Dynamic admin login entry stored in the system configuration table. */
final class AdminLoginPath
{
    public const CODE = 'system.admin_login_path';
    public const DEFAULT = 'admin';

    public static function normalize(mixed $value): string
    {
        $value = strtolower(trim((string) $value));
        if (preg_match('/\A[a-z][a-z0-9-]{3,31}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('后台登录入口必须为 4～32 位小写字母、数字或短横线，且以字母开头');
        }
        if (in_array($value, ['auth', 'api', 'admin', 'install', 'system', 'login', 'health'], true)) {
            if ($value !== self::DEFAULT) throw new InvalidArgumentException('后台登录入口属于保留名称');
        }
        return $value;
    }

    public static function current(): string
    {
        try {
            self::ensure();
            $value = Config::where('code', self::CODE)->where('status', 1)->value('value');
            return self::normalize($value ?: self::DEFAULT);
        } catch (\Throwable) {
            return self::DEFAULT;
        }
    }

    public static function set(string $entry): string
    {
        $entry = self::normalize($entry);
        self::ensure();
        $config = Config::where('code', self::CODE)->first();
        $config->forceFill(['value' => $entry, 'status' => 1, 'updated_at' => date('Y-m-d H:i:s')])->save();
        Cache::clear();
        return $entry;
    }

    public static function ensure(): void
    {
        if (!Db::schema()->hasTable('config')) return;
        $group = Db::schema()->hasTable('config_group') && ConfigGroup::withTrashed()->where('name', 'site')->exists()
            ? 'site'
            : null;
        if ($group === null || Config::where('code', self::CODE)->exists()) return;
        Config::forceCreate([
            'code' => self::CODE,
            'group' => $group,
            'type' => 'text',
            'verify' => '',
            'value' => self::DEFAULT,
            'extra' => '',
            'remark' => '后台登录入口；仅超级管理员可修改',
            'status' => 1,
            'is_system' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
