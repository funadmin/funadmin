<?php

declare(strict_types=1);

namespace app\common\crud;

use app\common\service\database\SqlDialect;
use InvalidArgumentException;

/** PostgreSQL 资源制品使用标量子查询，不使用 MySQL 会话变量。 */
final class PostgresqlResourceSql
{
    public static function permissions(array $data): string
    {
        $d = new SqlDialect('pgsql');
        $source = $d->literal($data['entity']);
        $owned = 'source_type=\'generated\' AND source_name=' . $source;
        $group = '(SELECT id FROM "fun_permission" WHERE ' . $owned . " AND resource_type='group' ORDER BY id LIMIT 1)";
        $sql = [];
        if ($data['permission']['enabled']) {
            $label = $d->literal($data['permission']['groupName']);
            $sql[] = 'INSERT INTO "fun_permission" (pid,app_name,code,obj,act,name,resource_type,status,is_public,source_type,source_name,created_at,updated_at,sort_order) SELECT 0,\'admin\',NULL,\'\',\'\',' . $label . ',\'group\',TRUE,FALSE,\'generated\',' . $source . ',NOW(),NOW(),0 WHERE NOT EXISTS (SELECT 1 FROM "fun_permission" WHERE ' . $owned . " AND resource_type='group');";
            $sql[] = 'UPDATE "fun_permission" SET name=' . $label . ',status=TRUE,deleted_at=NULL,updated_at=NOW() WHERE id=' . $group . ';';
            $codes = [];
            $controller = $d->literal('admin/generated.' . strtolower(str_replace(' ', '', ucwords(str_replace('-', ' ', $data['entity'])))) . 'controller');
            foreach ($data['permission']['actions'] as $i => $action) {
                $code = $d->literal($data['permissionPrefix'] . ':' . $action['codeSuffix']);
                $codes[] = $code;
                $values = implode(',', [$group, "'admin'", $code, $controller, $d->literal($action['action']), $d->literal($action['label']), "'route'", 'TRUE', 'FALSE', "'generated'", $source, 'NOW()', 'NOW()', (string) (($i + 1) * 10)]);
                $sql[] = 'INSERT INTO "fun_permission" (pid,app_name,code,obj,act,name,resource_type,status,is_public,source_type,source_name,created_at,updated_at,sort_order) VALUES (' . $values . ') ON CONFLICT (code) DO UPDATE SET pid=EXCLUDED.pid,obj=EXCLUDED.obj,act=EXCLUDED.act,name=EXCLUDED.name,status=TRUE,sort_order=EXCLUDED.sort_order,deleted_at=NULL,updated_at=NOW() WHERE "fun_permission".source_type=\'generated\' AND "fun_permission".source_name=' . $source . ';';
            }
            $sql[] = 'UPDATE "fun_permission" SET status=FALSE,updated_at=NOW() WHERE ' . $owned . " AND resource_type='route'" . ($codes === [] ? '' : ' AND code NOT IN (' . implode(',', $codes) . ')') . ';';
        }
        if ($data['menu']['enabled']) {
            $code = null;
            foreach ($data['permission']['actions'] as $action) if ($action['action'] === 'index') $code = $data['permissionPrefix'] . ':' . $action['codeSuffix'];
            if ($code === null || !$data['permission']['enabled']) throw new InvalidArgumentException('启用菜单时必须同时启用列表权限');
            $permission = '(SELECT id FROM "fun_permission" WHERE code=' . $d->literal($code) . ' AND status=TRUE AND deleted_at IS NULL LIMIT 1)';
            $menu = $data['menu'];
            $parent = ($menu['parentSourceName'] ?? '') !== '' ? 'COALESCE((SELECT id FROM "fun_admin_menu" WHERE source_type IN (\'admin_web\',\'generated\',\'plugin\') AND source_name=' . $d->literal($menu['parentSourceName']) . ' ORDER BY id LIMIT 1),0)' : (string) ($menu['parentId'] ?? 0);
            $attributes = ['pid' => $parent, 'permission_id' => $permission, 'app_name' => "'admin'", 'name' => $d->literal($menu['name']), 'href' => $d->literal('/' . ltrim($data['routePath'], '/')), 'query' => $d->literal(ProductionTemplateContext::menuQuery($data)), 'target' => $d->literal($menu['target']), 'icon' => $d->literal($menu['icon']), 'status' => 'TRUE', 'source_type' => "'generated'", 'source_name' => $source, 'created_at' => 'NOW()', 'updated_at' => 'NOW()', 'sort_order' => (string) $menu['sortOrder']];
            $sql[] = 'INSERT INTO "fun_admin_menu" (' . implode(',', array_map($d->identifier(...), array_keys($attributes))) . ') SELECT ' . implode(',', $attributes) . ' WHERE ' . $permission . ' IS NOT NULL AND NOT EXISTS (SELECT 1 FROM "fun_admin_menu" WHERE ' . $owned . ');';
            unset($attributes['created_at']);
            $updates = [];
            foreach ($attributes as $name => $value) $updates[] = $d->identifier($name) . '=' . $value;
            $sql[] = 'UPDATE "fun_admin_menu" SET ' . implode(',', $updates) . ',deleted_at=NULL WHERE ' . $permission . ' IS NOT NULL AND ' . $owned . ';';
        }
        return '-- PostgreSQL generated resources' . "\n" . ($sql === [] ? 'SELECT 1;' : implode("\n", $sql)) . "\n";
    }

    public static function languages(array $packs): string
    {
        $d = new SqlDialect('pgsql');
        $sql = [];
        foreach ($packs as $locale => $pack) {
            $rows = [];
            foreach ($pack as $key => $value) $rows[] = '(' . $d->literal($locale) . ',' . $d->literal($key) . ',' . $d->literal($value) . ',NOW(),NOW())';
            if ($rows !== []) $sql[] = 'INSERT INTO "fun_language_line" ("locale","key","value","created_at","updated_at") VALUES ' . implode(',', $rows) . ' ON CONFLICT ("locale","key") DO NOTHING;';
        }
        return '-- PostgreSQL generated language pack' . "\n" . implode("\n", $sql) . "\n";
    }
}
