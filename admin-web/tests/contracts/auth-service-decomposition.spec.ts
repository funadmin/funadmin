import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { extname, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const projectPath = (relativePath: string) => resolve(projectRoot, relativePath);
const readProjectFile = (relativePath: string) => readFileSync(projectPath(relativePath), 'utf8');

const phpFilesUnder = (relativePath: string): string[] => {
  const directory = projectPath(relativePath);
  if (!existsSync(directory)) return [];

  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const child = `${relativePath}/${entry.name}`;
    if (entry.isDirectory()) return phpFilesUnder(child);
    return extname(entry.name) === '.php' ? [child] : [];
  });
};

const productionPhpFiles = ['app', 'extend', 'config'].flatMap(phpFilesUnder);
const productionPhp = productionPhpFiles.map((file) => `${file}\n${readProjectFile(file)}`).join('\n');
const backendRoute = readProjectFile('app/backend/route/app.php');
const adminAuthController = readProjectFile('app/backend/controller/auth/AdminAuth.php');
const uploadController = readProjectFile('app/backend/controller/system/AdminUpload.php');
const roleController = readProjectFile('app/backend/controller/system/SystemRole.php');
const adminController = readProjectFile('app/backend/controller/system/SystemAdmin.php');
const menuControllerRoutes = readProjectFile('app/backend/controller/system/SystemMenu.php');
const funadminConfig = readProjectFile('config/funadmin.php');
const crudConfig = readProjectFile('config/crud.php');
const menuController = readProjectFile('app/backend/controller/system/SystemMenu.php');
const apiRoleMiddleware = readProjectFile('app/backend/middleware/CheckAdminApiRole.php');

const expectFileMissing = (relativePath: string) => {
  expect(existsSync(projectPath(relativePath)), `${relativePath} 应已删除`).toBe(false);
};

const readRequiredFile = (relativePath: string) => {
  expect(existsSync(projectPath(relativePath)), `缺少 ${relativePath}`).toBe(true);
  return readProjectFile(relativePath);
};

const countMatches = (source: string, pattern: RegExp) => source.match(pattern)?.length ?? 0;

describe('管理员认证最终架构契约', () => {
  it('M8 删除旧菜单、控制器、中间件和认证兼容入口', () => {
    for (const file of [
      'app/backend/service/AdminLegacyMenuService.php',
      'app/backend/service/AuthService.php',
      'app/backend/controller/Index.php',
      'app/backend/controller/Login.php',
      'app/backend/controller/Ajax.php',
      'app/backend/controller/Error.php',
      'app/backend/middleware/CheckRole.php',
      'app/backend/middleware/ViewNode.php',
      'app/common/controller/Backend.php',
    ]) {
      expectFileMissing(file);
    }

    const csrfPath = 'app/backend/middleware/CheckCsrf.php';
    const csrfReferences = productionPhpFiles
      .filter((file) => file !== csrfPath)
      .filter((file) => /\bCheckCsrf\b/.test(readProjectFile(file)));
    expect(csrfReferences, '旧 CheckCsrf 不应再被生产代码引用').toEqual([]);
    if (csrfReferences.length === 0) expectFileMissing(csrfPath);
  });

  it('生产代码不再引用 AuthService', () => {
    const callers = productionPhpFiles.filter((file) => /\bAuthService\b/.test(readProjectFile(file)));
    expect(callers).toEqual([]);
  });

  it('调用方直接使用会话、授权和角色范围服务', () => {
    const adminAuth = readRequiredFile('app/backend/controller/auth/AdminAuth.php');
    const profile = readRequiredFile('app/backend/controller/auth/AdminProfile.php');
    const devCrud = readRequiredFile('app/backend/controller/development/DevCrud.php');
    const roleGuard = readRequiredFile('app/backend/service/RoleGuardService.php');
    const systemCallers = [
      'app/backend/controller/system/SystemAdmin.php',
      'app/backend/controller/system/SystemDepartment.php',
      'app/backend/controller/system/SystemMenu.php',
      'app/backend/controller/system/SystemPermission.php',
      'app/backend/controller/system/SystemRole.php',
    ].map(readRequiredFile).join('\n');

    expect(adminAuth).toContain('AdminSessionService');
    expect(adminAuth).toContain('RoleScopeService');
    expect(adminAuth).toContain('AdminAuthorizationService');
    expect(profile).toContain('AdminSessionService');
    expect(devCrud).toContain('AdminAuthorizationService');
    expect(roleGuard).toContain('RoleScopeService');
    expect(systemCallers).toContain('RoleScopeService');
    for (const source of [adminAuth, profile, devCrud, roleGuard, systemCallers]) {
      expect(source).not.toMatch(/\bAuthService\b/);
    }
  });

  it('CheckAdminApiRole 注入并直接调用两个服务且不重复登录', () => {
    expect(apiRoleMiddleware).toContain('AdminSessionService');
    expect(apiRoleMiddleware).toContain('AdminAuthorizationService');
    expect(apiRoleMiddleware).toMatch(/function\s+__construct\s*\([^)]*AdminSessionService[^)]*AdminAuthorizationService[^)]*\)/s);
    expect(countMatches(apiRoleMiddleware, /->isLogin\s*\(/g)).toBe(1);
    expect(countMatches(apiRoleMiddleware, /->roleAccess\s*\(/g)).toBe(1);
    expect(apiRoleMiddleware).toMatch(/->roleAccess\s*\(\s*true\s*\)/);
    expect(apiRoleMiddleware).not.toMatch(/\bAuthService\b|AdminSessionService\s*\(|AdminAuthorizationService\s*\(/);
  });

  it('旧白名单和 CRUD/Menu 源码不再保留旧入口名称', () => {
    expect(funadminConfig).not.toMatch(/['"](?:index|ajax)\//i);
    expect(funadminConfig).not.toMatch(/public_ajax_url|auth_super_only_routes[^;]*(?:index|ajax)\//is);
    for (const source of [crudConfig, menuController]) {
      expect(source).not.toMatch(/\b(?:Index|Login|Ajax)(?:::|\.php|\/)/);
      expect(source).not.toMatch(/app\\backend\\controller\\(?:Index|Login|Ajax)\b/);
    }
  });

  it('app/common node helper 若保留则直连 AdminAuthorizationService', () => {
    const common = readProjectFile('app/common.php');
    if (/function\s+node\s*\(/.test(common)) {
      expect(common).toContain('AdminAuthorizationService');
      expect(common).not.toMatch(/\bAuthService\b/);
    }
  });

  it('新 auth、upload、system API 文件与 Attribute 路由继续存在', () => {
    for (const file of [
      'app/backend/controller/auth/AdminAuth.php',
      'app/backend/controller/auth/AdminProfile.php',
      'app/backend/controller/system/AdminUpload.php',
      'app/backend/controller/system/SystemMenu.php',
      'app/backend/controller/system/SystemRole.php',
      'app/backend/controller/system/SystemAdmin.php',
    ]) {
      readRequiredFile(file);
    }

    expect(backendRoute).not.toContain('auth.AdminAuth/');
    expect(backendRoute).not.toContain('system.AdminUpload/');
    for (const [source, route] of [
      [adminAuthController, "#[Post('login')]"],
      [adminAuthController, "#[Get('me')]"],
      [adminAuthController, "#[Get('menus')]"],
      [adminAuthController, "#[Post('logout')]"],
      [uploadController, "#[Post('upload')]"],
      [roleController, "#[Group('system/role')]"],
      [adminController, "#[Group('system/user')]"],
      [menuControllerRoutes, "#[Get('tree')]"],
    ]) {
      expect(source).toContain(route);
    }
  });

  it('backend 根入口只跳转 admin-web 或返回 404，旧入口统一显式 404', () => {
    const hasRootRedirect = /Route::(?:get|redirect)\s*\(\s*['"]\/?['"][\s\S]{0,160}admin-web/.test(backendRoute);
    const hasRoot404 = /Route::get\s*\(\s*['"]\/?['"][\s\S]{0,160}\b404\b/.test(backendRoute);
    expect(hasRootRedirect || hasRoot404, 'backend 根入口必须跳转 /admin-web 或返回 404').toBe(true);
    expect(backendRoute).toMatch(/Route::miss\s*\([\s\S]*\b404\b/);
    expect(backendRoute).not.toMatch(/['"](?:index|login|ajax)(?:\/[^'"]*)?['"]\s*,\s*['"][^'"]*(?:Index|Login|Ajax)/i);
  });
});
