<?php

declare(strict_types=1);

namespace Tests\Cms;

use app\cms\service\PermissionCatalog;

/**
 * CMS 站点操作的宿主上限改为按方法推导的接口权限码后，必须与原粗粒度上限等价：
 * 迁移把旧粗码展开为它覆盖的全部新码，因此"持有旧上限码"⇔"持有新上限的全部码"。
 */
final class SiteCeilingTest extends \PHPUnit\Framework\TestCase
{
    /** @return array<string, list<string>> 站点操作 → 迁移前的粗粒度上限码（冻结） */
    private const LEGACY_CEILINGS = [
        'cms:site:detail' => ['cms:site:list'], 'cms:site:edit' => ['cms:site:save'], 'cms:site:domain' => ['cms:site:save'], 'cms:site:locale' => ['cms:site:save'],
        'cms:category:list' => ['cms:category:list'], 'cms:category:create' => ['cms:category:save'], 'cms:category:edit' => ['cms:category:save'], 'cms:category:status' => ['cms:category:save'],
        'cms:content:list' => ['cms:content:list'], 'cms:content:create' => ['cms:content:save'], 'cms:content:edit' => ['cms:content:save'],
        'cms:content:preview' => ['cms:content:preview'], 'cms:content:publish' => ['cms:content:publish'], 'cms:content:offline' => ['cms:content:offline'],
        'cms:menu:list' => ['cms:menu:list'], 'cms:menu:create' => ['cms:menu:save'], 'cms:menu:edit' => ['cms:menu:save'], 'cms:menu:status' => ['cms:menu:save'], 'cms:menu:delete' => ['cms:menu:delete'],
        'cms:tag:list' => ['cms:tag:list'], 'cms:tag:create' => ['cms:tag:save'], 'cms:tag:edit' => ['cms:tag:save'], 'cms:tag:preview' => ['cms:tag:preview'], 'cms:tag:publish' => ['cms:tag:publish'],
        'cms:model:list' => ['cms:model:list'], 'cms:model:status' => ['cms:site:save'], 'cms:model:template' => ['cms:site:save'],
        'cms:submission:list' => ['cms:submission:list'], 'cms:submission:review' => ['cms:submission:review'],
        'cms:access:list' => ['cms:site:grant'], 'cms:access:members' => ['cms:site:grant'], 'cms:access:roles' => ['cms:site:grant'],
    ];

    public function test_every_site_operation_ceiling_is_the_per_method_expansion_of_its_legacy_ceiling(): void
    {
        $expansion = require dirname(__DIR__, 2) . '/database/permission-maps/plugin-per-method.php';
        self::assertSame(array_keys(self::LEGACY_CEILINGS), PermissionCatalog::operations());
        foreach (self::LEGACY_CEILINGS as $operation => $legacy) {
            $expected = [];
            foreach ($legacy as $code) $expected = array_merge($expected, $expansion[$code]);
            $expected = array_values(array_unique($expected));
            sort($expected);
            $actual = PermissionCatalog::ceilings($operation);
            sort($actual);
            self::assertSame($expected, $actual, $operation);
        }
    }

    public function test_ceilings_only_use_per_method_route_codes(): void
    {
        foreach (PermissionCatalog::operations() as $operation) {
            foreach (PermissionCatalog::ceilings($operation) as $code) {
                self::assertMatchesRegularExpression('/^cms:(access|categories|content|contributions|menus|models|sites|tags):[a-z-]+$/', $code, $operation);
            }
        }
    }

    /**
     * 宿主权限发现范围（admin.permission_codes 过滤）参与路由判权，必须与升级前等价：
     * 随机抽取"宿主持有的旧权限 × 站点角色分配的站点操作"，比较每个 CMS 路由迁移前后是否可访问。
     */
    public function test_site_scoped_route_access_is_unchanged_for_random_grant_and_site_role_combinations(): void
    {
        $expansion = require dirname(__DIR__, 2) . '/database/permission-maps/plugin-per-method.php';
        $before = json_decode((string) file_get_contents(dirname(__DIR__) . '/fixtures/auth-requirements-before.json'), true, flags: JSON_THROW_ON_ERROR);
        $routes = [];
        foreach ($before as $key => $requirement) {
            if (!str_contains($key, '=> app\\admin\\plugins\\cms\\')) continue;
            preg_match('/=> (.+)::(.+)$/', $key, $target);
            $routes[$key] = [(array) $requirement['permission'], \app\admin\authorization\service\PermissionCode::derive($target[1], $target[2])];
        }
        self::assertCount(48, $routes);
        $oldCodes = array_values(array_filter(array_keys($expansion), static fn (string $c): bool => str_starts_with($c, 'cms:')));
        $operations = PermissionCatalog::operations();
        mt_srand(20261106);
        for ($round = 0; $round < 3000; $round++) {
            $density = [0.15, 0.4, 0.8][$round % 3];
            $held = array_values(array_filter($oldCodes, static fn (): bool => mt_rand() / mt_getrandmax() < $density));
            $assigned = array_values(array_filter($operations, static fn (): bool => mt_rand() / mt_getrandmax() < $density));
            // 迁移：持有旧码 X 的角色获得 X 展开的全部新码；站点操作码本身作为能力权限保留。
            $heldNew = $held;
            foreach ($held as $code) $heldNew = array_merge($heldNew, $expansion[$code]);
            $heldNew = array_values(array_unique($heldNew));

            $effectiveOld = array_values(array_filter($assigned, static fn (string $op): bool => array_diff(self::LEGACY_CEILINGS[$op], $held) === []));
            $effectiveNew = array_values(array_filter($assigned, static fn (string $op): bool => array_diff(PermissionCatalog::ceilings($op), $heldNew) === []));
            self::assertSame($effectiveOld, $effectiveNew, 'round ' . $round);

            $visibleOld = self::legacyScope($held, $effectiveOld);
            $visibleNew = PermissionCatalog::scope($heldNew, $effectiveNew);
            foreach ($routes as $key => [$guards, $code]) {
                $old = array_intersect(array_filter($guards, 'is_string'), $visibleOld) !== [];
                self::assertSame($old, in_array($code, $visibleNew, true), "round $round: $key");
            }
        }
    }

    /** 升级前 hooks/permissions.php 的范围计算（冻结）。 */
    private static function legacyScope(array $codes, array $operations): array
    {
        $other = array_values(array_filter($codes, static fn ($c) => !str_starts_with($c, 'cms:')));
        $allowed = $operations;
        foreach ($operations as $op) $allowed = array_merge($allowed, self::LEGACY_CEILINGS[$op]);
        if (!in_array('cms:access:list', $operations, true)) $allowed = array_values(array_diff($allowed, ['cms:site:grant']));
        return array_values(array_unique(array_merge($other, array_intersect($codes, $allowed))));
    }

    public function test_holder_of_a_legacy_ceiling_satisfies_the_new_one_and_nobody_else_does(): void
    {
        $expansion = require dirname(__DIR__, 2) . '/database/permission-maps/plugin-per-method.php';
        foreach (array_keys($expansion) as $grantedOld) {
            if (!str_starts_with($grantedOld, 'cms:')) continue;
            $held = $expansion[$grantedOld];
            foreach (self::LEGACY_CEILINGS as $operation => $legacy) {
                $before = array_diff($legacy, [$grantedOld]) === [];
                $after = array_diff(PermissionCatalog::ceilings($operation), $held) === [];
                self::assertSame($before, $after, "持有 $grantedOld 时对站点操作 $operation 的上限判断发生变化");
            }
        }
    }
}
