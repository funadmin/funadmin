import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { backendJson } from './backend-runtime';

const projectRoot = resolve(process.cwd(), '..');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');

describe('015 title→name 改名迁移契约', () => {
  it('后端菜单 API 以 name 暴露名称并以 routeName 暴露路由名', () => {
    const authController = readProjectFile('admin/app/admin/authentication/controller/AdminAuth.php');
    const authorization = readProjectFile('admin/app/admin/authorization/service/AuthorizationService.php');
    const systemMenu = readProjectFile('admin/app/admin/authorization/controller/SystemMenu.php');
    expect(authController).toContain('AuthorizationService())->menus($admin)');
    expect(systemMenu).toContain('toManagementData(');
    const result = backendJson<{ management: Record<string, unknown>; web: Record<string, unknown>; login: Record<string, unknown>; fallback: Record<string, unknown> }>(String.raw`
      (new \app\common\service\database\MigrationService())->run();
      \support\Db::table('admin')->insert(['id' => 1, 'username' => 'contractroot', 'password' => 'hidden', 'status' => 1]);
      \support\Db::table('admin_menu')->delete();
      \support\Db::table('admin_menu')->insert([
          'id' => 42, 'pid' => 0, 'name' => '显示名称', 'app_name' => 'admin', 'status' => 1,
          'query' => 'name=ContractRoute&component=system/role/index', 'href' => '/system/role',
          'source_type' => 'admin_web', 'source_name' => 'core', 'permission_id' => 0,
      ]);
      $login = (new \app\admin\authorization\service\AuthorizationService())->menus(['id' => 1]);
      $menu = (new \app\admin\authorization\model\AdminMenu())->setRawAttributes([
          'id' => 42, 'pid' => 0, 'name' => '显示名称', 'title' => 'must-not-leak',
          'query' => 'name=ContractRoute&component=system/role/index', 'href' => '/system/role',
          'source_type' => 'admin_web', 'source_name' => 'core', 'permission_id' => 0,
      ]);
      $management = $menu->toManagementData();
      $web = $menu->toWebMenuData();
      $menu->query = '';
      echo json_encode(['management' => $management, 'web' => $web, 'login' => $login[0], 'fallback' => $menu->toWebMenuData()], JSON_THROW_ON_ERROR);
    `);
    for (const data of [result.management, result.web, result.login]) {
      expect(data).toMatchObject({ name: '显示名称', routeName: 'ContractRoute' });
      expect(data).not.toHaveProperty('title');
    }
    expect(result.fallback).toMatchObject({ name: '显示名称', routeName: 'Menu_42' });
    expect(JSON.stringify(result)).not.toContain('must-not-leak');
  });

  it('角色与权限 API 的真实序列化统一使用 name 而非旧 title 字段', () => {
    const role = readProjectFile('admin/app/admin/authorization/controller/SystemRole.php');
    const permission = readProjectFile('admin/app/admin/authorization/controller/SystemPermission.php');
    expect(role).toContain('$role->toRoleData()');
    expect(permission).toContain('$permission->toApiData()');
    const result = backendJson<{ role: Record<string, unknown>; permission: Record<string, unknown> }>(String.raw`
      (new \app\common\service\database\MigrationService())->run();
      $role = (new \app\admin\authorization\model\AuthGroup())->setRawAttributes(['id' => 0, 'name' => '角色名称', 'title' => 'old-role-title']);
      $permission = (new \app\admin\authorization\model\Permission())->setRawAttributes(['id' => 0, 'name' => '权限名称', 'title' => 'old-permission-title']);
      echo json_encode(['role' => $role->toRoleData(), 'permission' => $permission->toApiData()], JSON_THROW_ON_ERROR);
    `);
    expect(result.role.name).toBe('角色名称');
    expect(result.permission.name).toBe('权限名称');
    expect(result.role).not.toHaveProperty('title');
    expect(result.permission).not.toHaveProperty('title');
    expect(JSON.stringify(result)).not.toMatch(/old-(?:role|permission)-title/);
  }, 60_000);

  it('前端路由转换从 routeName 取路由名、从 name 取标题', () => {
    const dynamic = readProjectFile('web/src/admin/router/dynamic.ts');
    expect(dynamic).toContain('menu.routeName');
    expect(dynamic).toContain('title: menu.name,');
    expect(dynamic).not.toContain('menu.title');
  });
});
