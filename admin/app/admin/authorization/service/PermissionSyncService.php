<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use app\admin\authorization\model\Permission;
use RuntimeException;
use support\Db;
use Webman\Route;

/**
 * 代码即权限：扫描后台路由，按 PermissionCode 推导的权限码登记到权限表。
 *
 * - 只新增或更新登记，不授予任何角色，因此不会改变任何人的访问能力。
 * - 已登记但不再对应任何方法的旧码（方法改名或删除）只报告为 stale，不自动删除，避免已授权角色无声失权。
 * - 插件方法的权限归属该插件（source_type=plugin, source_name=插件名），宿主方法归属 system。
 */
final class PermissionSyncService
{
    /** @var array<string, string> 推导码 → 方法 OpenAPI summary（未命名时作为默认名称） */
    private array $summaries = [];

    /**
     * @param string|null $plugin 只同步指定插件；null 同步全部宿主与插件路由
     * @param array<string, string> $names 推导码 → 中文名称（来自插件清单或宿主基线）
     * @param string|null $appPath 插件所在的 app 目录（默认当前项目 app/）
     * @param bool $enable 是否启用已存在的行：插件启用时为 true；迁移与日常同步保持管理员设定的启用状态
     * @return array{added: list<string>, updated: list<string>, stale: list<string>}
     */
    public function sync(?string $plugin = null, array $names = [], ?string $appPath = null, bool $enable = false): array
    {
        // 插件按控制器文件推导（安装/升级时新路由尚未载入路由表）；全量同步按已载入的路由表推导。
        $derived = $plugin !== null
            ? array_fill_keys($this->pluginCodes($plugin, $appPath), ['type' => 'plugin', 'name' => $plugin])
            : $this->derivedCodes();
        foreach (array_keys($names) as $code) {
            if (!isset($derived[$code])) {
                throw new RuntimeException('权限码没有对应的后台方法（请检查拼写或方法是否存在）：' . $code);
            }
        }

        $added = $updated = [];
        Db::transaction(function () use ($derived, $names, $enable, &$added, &$updated): void {
            foreach ($derived as $code => $owner) {
                ['obj' => $obj, 'act' => $act] = PermissionCode::resource($code);
                $permission = Permission::where('code', $code)->first();
                if ($permission !== null && !self::ownedBy($permission, $owner)) {
                    throw new RuntimeException('权限码归属冲突：' . $code);
                }
                $fill = ['obj' => $obj, 'act' => $act, 'resource_type' => Permission::TYPE_ROUTE];
                if ($permission === null || $enable) {
                    $fill['status'] = 1;
                }
                $name = $names[$code] ?? $this->summaries[$code] ?? $code;
                if ($permission === null) {
                    $permission = new Permission();
                    $fill += [
                        'pid' => 0, 'app_name' => 'admin', 'code' => $code,
                        'name' => $name,
                        'is_public' => 0, 'sort_order' => 999,
                        'source_type' => $owner['type'], 'source_name' => $owner['name'],
                    ];
                    $added[] = $code;
                } else {
                    // 显式名称覆盖；未命名（名称仍是权限码）的行补上默认名称。
                    if (isset($names[$code]) || (string) $permission->name === $code) $fill['name'] = $name;
                    if ($permission->obj !== $obj || $permission->act !== $act || ($enable && (int) $permission->status !== 1)
                        || (string) $permission->resource_type !== Permission::TYPE_ROUTE
                        || (isset($fill['name']) && $permission->name !== $fill['name'])) {
                        $updated[] = $code;
                    }
                }
                $permission->forceFill($fill)->save();
            }
        });

        $stale = $this->staleCodes($plugin, $derived);
        if ($added !== [] || $updated !== []) {
            CasbinService::instance()->reload();
        }
        sort($added);
        sort($updated);
        return ['added' => $added, 'updated' => $updated, 'stale' => $stale];
    }

    /** @return array<string, array{type: string, name: string}> 推导码 → 归属 */
    public function derivedCodes(?string $plugin = null): array
    {
        Route::load([config_path()]);
        $codes = [];
        foreach (Route::getRoutes() as $route) {
            $callback = $route->getCallback();
            if (!is_array($callback) || !is_string($callback[0])) continue;
            $code = PermissionCode::derive($callback[0], (string) $callback[1]);
            if ($code === null) continue;
            $owner = preg_match('/^app\\\\admin\\\\plugins\\\\([a-z][a-z0-9]*)\\\\/', $callback[0], $match) === 1
                ? ['type' => 'plugin', 'name' => $match[1]]
                : ['type' => 'system', 'name' => ''];
            if ($plugin !== null && $owner['name'] !== $plugin) continue;
            $codes[$code] = $owner;
            if (method_exists($callback[0], (string) $callback[1])) {
                $this->remember($code, new \ReflectionMethod($callback[0], (string) $callback[1]));
            }
        }
        ksort($codes);
        return $codes;
    }

    /**
     * 扫描插件控制器文件（app/admin/plugins/{插件}/controller），收集带路由注解的公开方法并推导权限码。
     * 与 Webman 注解路由的收集规则一致：只看本类声明的公开方法，路由路径为 null 的注解不注册路由。
     *
     * @return list<string>
     */
    public function pluginCodes(string $plugin, ?string $appPath = null): array
    {
        if (preg_match('/^[a-z][a-z0-9]*$/D', $plugin) !== 1) return [];
        $root = ($appPath ?? app_path()) . '/admin/plugins/' . $plugin . '/controller';
        if (!is_dir($root)) return [];
        $codes = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $class = 'app\\admin\\plugins\\' . $plugin . '\\controller\\' . str_replace('/', '\\', $relative);
            // 直接解析控制器源文件：Web 进程内升级插件时旧版本的类可能已被加载，反射会读到过期的方法列表。
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/^\s*(?:abstract\s+(?:readonly\s+)?class|interface|trait)\s/m', $source) === 1) continue;
            foreach (self::routedMethods($source) as $method => $doc) {
                $code = PermissionCode::derive($class, $method);
                if ($code !== null) {
                    $codes[$code] = true;
                    $this->rememberDoc($code, $doc);
                }
            }
        }
        $codes = array_keys($codes);
        sort($codes);
        return $codes;
    }

    /** 插件码必须归属同名插件；宿主码可沿用任何非插件来源的已有行（宿主基线、生成模块等）。 */
    private static function ownedBy(Permission $permission, array $owner): bool
    {
        if ($owner['type'] === 'plugin') {
            return (string) $permission->source_type === 'plugin' && (string) $permission->source_name === $owner['name'];
        }
        // 宿主能力权限与推导码同名时不能被静默转成接口权限（obj/act 相同会让持有能力的角色获得接口）。
        return (string) $permission->source_type !== 'plugin' && (string) $permission->resource_type === Permission::TYPE_ROUTE;
    }

    private const ROUTE_ATTRIBUTES = ['Get', 'Post', 'Put', 'Delete', 'Patch', 'Head', 'Options', 'Any', 'Route'];

    /**
     * 解析源文件中带路由注解的公开方法（与 Webman 注解路由规则一致：路径为 null 的注解不注册路由）。
     *
     * @return array<string, string> 方法名 → 文档注释
     */
    public static function routedMethods(string $source): array
    {
        $tokens = token_get_all($source);
        $methods = [];
        $routed = $public = false;
        $doc = '';
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $id = is_array($token) ? $token[0] : null;
            if ($id === T_ATTRIBUTE) {
                $text = '';
                for ($depth = 1, $i++; $i < $count && $depth > 0; $i++) {
                    $part = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                    if ($part === '[') $depth++;
                    if ($part === ']' && --$depth === 0) break;
                    $text .= $part;
                }
                $routed = $routed || self::isRouteAttribute($text);
                continue;
            }
            if ($id === T_DOC_COMMENT) { $doc = $token[1]; continue; }
            if ($id === T_PUBLIC) { $public = true; continue; }
            if ($id === T_PRIVATE || $id === T_PROTECTED) { $public = false; continue; }
            if ($id === T_FUNCTION) {
                for ($j = $i + 1; $j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE; $j++);
                $name = is_array($tokens[$j] ?? null) && $tokens[$j][0] === T_STRING ? $tokens[$j][1] : '';
                if ($routed && $public && $name !== '' && !in_array(strtolower($name), ['__construct', '__destruct'], true)) {
                    $methods[$name] = $doc;
                }
                $routed = $public = false;
                $doc = '';
                continue;
            }
            if (!is_array($token) && in_array($token, [';', '{', '}'], true)) {
                $routed = $public = false;
                $doc = '';
            }
        }
        return $methods;
    }

    /** 一个属性组里是否有注册路由的注解（Get('')、Route('/x') 等；无参数或路径为 null 时不注册）。 */
    private static function isRouteAttribute(string $group): bool
    {
        preg_match_all('/(?:^|,)\s*\\\\?(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*([A-Za-z_][A-Za-z0-9_]*)\s*(\((?:[^()]|(?2))*\))?/', $group, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], self::ROUTE_ATTRIBUTES, true)) continue;
            $arguments = trim(substr($match[2] ?? '', 1, -1));
            if ($arguments === '' || preg_match('/^(?:path\s*:\s*)?null\b/i', $arguments) === 1) continue;
            return true;
        }
        return false;
    }

    /** 推导码的默认名称（方法 OpenAPI summary）；须先调用 derivedCodes() 或 pluginCodes()。 */
    public function summary(string $code): ?string
    {
        return $this->summaries[$code] ?? null;
    }

    private function remember(string $code, \ReflectionMethod $method): void
    {
        $this->rememberDoc($code, (string) $method->getDocComment());
    }

    private function rememberDoc(string $code, string $doc): void
    {
        if (isset($this->summaries[$code])) return;
        if (preg_match('/@OA\\\\[A-Za-z]+\\([^)]*?summary="([^"]+)"/s', $doc, $match) === 1) {
            // 权限名称列为 varchar(100)：过长的说明截断。
            $this->summaries[$code] = mb_substr((string) preg_replace('/[。.]+$/u', '', trim($match[1])), 0, 100);
        }
    }

    /** @param array<string, mixed> $derived */
    private function staleCodes(?string $plugin, array $derived): array
    {
        $query = Permission::where('resource_type', Permission::TYPE_ROUTE)->where('status', 1)->whereNull('deleted_at')
            ->where('code', 'like', '%:%:%');
        $plugin === null ? $query->whereIn('source_type', ['system', 'plugin']) : $query->where('source_type', 'plugin')->where('source_name', $plugin);
        $stale = array_values(array_filter(
            $query->pluck('code')->all(),
            static fn (string $code): bool => !isset($derived[$code]) && preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/', $code) === 1
        ));
        sort($stale);
        return $stale;
    }
}
