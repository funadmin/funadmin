import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const adminRoot = resolve(projectRoot, 'admin/app/admin');
const controllerRoot = resolve(adminRoot, 'controller');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');

const authControllers = ['AdminAuth', 'AdminProfile'];
const systemControllers = [
  'AdminUpload',
  'SystemAttachment',
  'SystemAttachmentGroup',
  'SystemBlacklist',
  'SystemConfig',
  'SystemDict',
  'SystemLanguage',
  'SystemMember',
  'SystemMemberGroup',
  'SystemMemberLevel',
  'SystemOperationLog'
];
const authorizationControllers = ['SystemAdmin', 'SystemDepartment', 'SystemMenu', 'SystemPermission', 'SystemRole'];
const movedControllers = ['AdminApiController', ...authControllers, ...systemControllers, ...authorizationControllers];

const routeSource = readProjectFile('admin/config/route.php');
const authSource = readProjectFile('admin/app/admin/authentication/controller/AdminAuth.php');
const profileSource = readProjectFile('admin/app/admin/authentication/controller/AdminProfile.php');
const uploadSource = readProjectFile('admin/app/admin/system/controller/AdminUpload.php');
const roleSource = readProjectFile('admin/app/admin/authorization/controller/SystemRole.php');
const dictSource = readProjectFile('admin/app/admin/system/controller/SystemDict.php');

describe('后台控制器目录重组源码契约', () => {
  it('已移动控制器仅存在于对应子目录', () => {
    for (const controller of movedControllers) {
      expect(existsSync(resolve(controllerRoot, `${controller}.php`)), `${controller} 旧顶层文件仍存在`).toBe(false);
    }

    expect(existsSync(resolve(controllerRoot, 'base/AdminApiController.php'))).toBe(false);
    expect(existsSync(resolve(adminRoot, 'common/controller'))).toBe(false);
    expect(existsSync(resolve(projectRoot, 'admin/app/common/controller/AdminController.php'))).toBe(true);
    expect(existsSync(resolve(projectRoot, 'admin/app/common/controller/ApiController.php'))).toBe(true);
    for (const controller of authControllers) {
      expect(existsSync(resolve(adminRoot, `authentication/controller/${controller}.php`))).toBe(true);
    }
    for (const controller of systemControllers) {
      expect(existsSync(resolve(adminRoot, `system/controller/${controller}.php`))).toBe(true);
    }
    for (const controller of authorizationControllers) {
      expect(existsSync(resolve(adminRoot, `authorization/controller/${controller}.php`))).toBe(true);
    }
  });

  it('显式特例使用多级控制器目标，已迁移系统控制器使用 Attribute 路由', () => {
    expect(routeSource).not.toMatch(/['"](?:AdminAuth|AdminProfile|AdminUpload|System[A-Z][A-Za-z]*)\//);
    expect(routeSource).not.toContain("'auth.AdminAuth/login'");
    expect(routeSource).not.toContain("'auth.AdminProfile/index'");
    expect(authSource).toContain("#[Post('/login')]");
    expect(profileSource).toContain("#[Get('')]");
    expect(routeSource).not.toContain("'system.AdminUpload/upload'");
    expect(routeSource).not.toContain("'system.SystemRole/index'");
    expect(routeSource).not.toContain("'system.SystemDict/options'");
    expect(uploadSource).toContain("#[Post('/upload')]");
    expect(roleSource).toContain("#[RouteGroup('/admin/system/role')]");
    expect(dictSource).toContain("#[Get('/{code:[A-Za-z][A-Za-z0-9_]{0,59}}/options')]");
  });
});
