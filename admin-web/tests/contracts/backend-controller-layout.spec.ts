import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const controllerRoot = resolve(projectRoot, 'app/backend/controller');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');

const authControllers = ['AdminAuth', 'AdminProfile'];
const systemControllers = [
  'AdminUpload',
  'SystemAdmin',
  'SystemAttachment',
  'SystemAttachmentGroup',
  'SystemBlacklist',
  'SystemConfig',
  'SystemDepartment',
  'SystemDict',
  'SystemLanguage',
  'SystemMember',
  'SystemMemberGroup',
  'SystemMemberLevel',
  'SystemMenu',
  'SystemOperationLog',
  'SystemPermission',
  'SystemRole'
];
const movedControllers = ['AdminApiController', ...authControllers, ...systemControllers];

const routeSource = readProjectFile('app/backend/route/app.php');
const permissionSource = readProjectFile('app/backend/service/PermissionResource.php');

describe('后台控制器目录重组源码契约', () => {
  it('已移动控制器仅存在于对应子目录', () => {
    for (const controller of movedControllers) {
      expect(existsSync(resolve(controllerRoot, `${controller}.php`)), `${controller} 旧顶层文件仍存在`).toBe(false);
    }

    expect(existsSync(resolve(controllerRoot, 'base/AdminApiController.php'))).toBe(true);
    for (const controller of authControllers) {
      expect(existsSync(resolve(controllerRoot, `auth/${controller}.php`))).toBe(true);
    }
    for (const controller of systemControllers) {
      expect(existsSync(resolve(controllerRoot, `system/${controller}.php`))).toBe(true);
    }
  });

  it('显式路由全部使用多级控制器目标', () => {
    expect(routeSource).not.toMatch(/['"](?:AdminAuth|AdminProfile|AdminUpload|System[A-Z][A-Za-z]*)\//);
    expect(routeSource).toContain("'auth.AdminAuth/login'");
    expect(routeSource).toContain("'auth.AdminProfile/index'");
    expect(routeSource).toContain("'system.AdminUpload/upload'");
    expect(routeSource).toContain("'system.SystemRole/index'");
    expect(routeSource).toContain("'system.SystemDict/options'");
  });

  it('权限资源将新目录控制器稳定映射为既有控制器名', () => {
    expect(permissionSource).toMatch(/auth\.adminprofile[\s\S]*adminprofile/);
    expect(permissionSource).toMatch(/auth\.adminauth[\s\S]*adminauth/);
    expect(permissionSource).toMatch(/system\.adminupload[\s\S]*adminupload/);
    expect(permissionSource).toContain("preg_replace('/^system\\.(system[a-z0-9_]+)$/', '$1', $normalized)");
  });
});
