import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { extname, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { backendJson, runBackendTests } from './backend-runtime';

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

const productionPhpFiles = ['admin/app', 'admin/extend', 'admin/config'].flatMap(phpFilesUnder);
const consoleRoute = readProjectFile('admin/config/route.php');
const funadminConfig = readProjectFile('admin/config/funadmin.php');
const crudConfig = readProjectFile('admin/config/crud.php');
const menuController = readProjectFile('admin/app/admin/authorization/controller/SystemMenu.php');
const apiRoleMiddleware = readProjectFile('admin/app/admin/middleware/CheckAdminApiRoleMiddleware.php');

const expectFileMissing = (relativePath: string) => {
  expect(existsSync(projectPath(relativePath)), `${relativePath} 应已删除`).toBe(false);
};

const readRequiredFile = (relativePath: string) => {
  expect(existsSync(projectPath(relativePath)), `缺少 ${relativePath}`).toBe(true);
  return readProjectFile(relativePath);
};

describe('管理员认证最终架构契约', () => {
  it('M8 删除旧菜单、控制器、中间件和认证兼容入口', () => {
    for (const file of [
      'admin/app/admin/service/AdminLegacyMenuService.php',
      'admin/app/admin/service/AuthService.php',
      'admin/app/admin/controller/Index.php',
      'admin/app/admin/controller/Login.php',
      'admin/app/admin/controller/Ajax.php',
      'admin/app/admin/controller/Error.php',
      'admin/app/admin/middleware/CheckRole.php',
      'admin/app/admin/middleware/ViewNode.php',
      'admin/app/common/controller/Backend.php',
    ]) {
      expectFileMissing(file);
    }

    const csrfPath = 'admin/app/admin/middleware/CheckCsrf.php';
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
    const adminAuth = readRequiredFile('admin/app/admin/authentication/controller/AdminAuth.php');
    const profile = readRequiredFile('admin/app/admin/authentication/controller/AdminProfile.php');
    const business = readRequiredFile('admin/app/admin/development/controller/Business.php');
    const roleGuard = readRequiredFile('admin/app/admin/authorization/service/RoleGuardService.php');
    const systemCallers = [
      'admin/app/admin/authorization/controller/SystemAdmin.php',
      'admin/app/admin/authorization/controller/SystemDepartment.php',
      'admin/app/admin/authorization/controller/SystemMenu.php',
      'admin/app/admin/authorization/controller/SystemPermission.php',
      'admin/app/admin/authorization/controller/SystemRole.php',
    ].map(readRequiredFile).join('\n');

    expect(adminAuth).toContain('AdminSessionService');
    expect(adminAuth).toContain('BrowserAuthService');
    expect(adminAuth).toContain('AuthorizationService');
    expect(adminAuth).not.toContain('AdminAuthorizationService');
    expect(profile).toContain('AdminSessionService');
    expect(business).toContain('AdminAuthorizationService');
    expect(roleGuard).toContain('RoleScopeService');
    expect(systemCallers).toContain('RoleScopeService');
    for (const source of [adminAuth, profile, business, roleGuard, systemCallers]) {
      expect(source).not.toMatch(/\bAuthService\b/);
    }
  });

  it('Webman 权限中间件拒绝匿名及越权访问且不泄漏请求身份', () => {
    expect(apiRoleMiddleware).toContain('BrowserAuthService');
    expect(apiRoleMiddleware).toContain('AuthorizationService');
    expect(apiRoleMiddleware).not.toMatch(/\bAuthService\b|->isLogin\s*\(|->roleAccess\s*\(/);
    expect(runBackendTests('MemberHttpTest.php')).toContain('OK (2 tests');
    expect(runBackendTests('AdminHttpFoundationTest.php', 'test_(anonymous_request_is_rejected_and_client_identity_is_not_trusted|authorized_request_does_not_authorize_a_later_anonymous_request)')).toContain('OK (2 tests');
  }, 60_000);

  it('旧白名单和 CRUD/Menu 源码不再保留旧入口名称', () => {
    expect(funadminConfig).not.toMatch(/['"](?:index|ajax)\//i);
    expect(funadminConfig).not.toMatch(/public_ajax_url|auth_super_only_routes[^;]*(?:index|ajax)\//is);
    for (const source of [crudConfig, menuController]) {
      expect(source).not.toMatch(/\b(?:Index|Login|Ajax)(?:::|\.php|\/)/);
      expect(source).not.toMatch(/app\\admin\\controller\\(?:Index|Login|Ajax)\b/);
    }
  });

  it('admin/app/functions node helper 若保留则直连 AdminAuthorizationService', () => {
    const common = readProjectFile('admin/app/functions.php');
    if (/function\s+node\s*\(/.test(common)) {
      expect(common).toContain('AdminAuthorizationService');
      expect(common).not.toMatch(/\bAuthService\b/);
    }
  });

  it('auth、upload、system API 通过原生 Webman Attribute 注册到真实控制器', () => {
    const contracts = [
      ['POST', '/admin/auth/login', 'app\\admin\\authentication\\controller\\AdminAuth', 'login'],
      ['GET', '/admin/auth/me', 'app\\admin\\authentication\\controller\\AdminAuth', 'me'],
      ['GET', '/admin/auth/menus', 'app\\admin\\authentication\\controller\\AdminAuth', 'menus'],
      ['POST', '/admin/auth/logout', 'app\\admin\\authentication\\controller\\AdminAuth', 'logout'],
      ['POST', '/admin/upload', 'app\\admin\\system\\controller\\AdminUpload', 'upload'],
      ['GET', '/admin/system/role', 'app\\admin\\authorization\\controller\\SystemRole', 'index'],
      ['GET', '/admin/system/user', 'app\\admin\\authorization\\controller\\SystemAdmin', 'index'],
      ['GET', '/admin/system/menu/tree', 'app\\admin\\authorization\\controller\\SystemMenu', 'tree']
    ];
    const routes = backendJson(String.raw`
      \Webman\Route::load([config_path()]);
      $result = [];
      foreach (\Webman\Route::getRoutes() as $route) {
          foreach ($route->getMethods() as $method) {
              $result[$method . ' ' . $route->getPath()] = $route->getCallback();
          }
      }
      echo json_encode($result, JSON_THROW_ON_ERROR);
    `) as Record<string, string[]>;
    for (const [method, path, controller, action] of contracts) {
      expect(routes[`${method} ${path}`], `${method} ${path}`).toEqual([controller, action]);
    }
    expect(consoleRoute).not.toContain('auth.AdminAuth/');
    expect(consoleRoute).not.toContain('system.AdminUpload/');
  });

  it('根入口返回 SPA，旧入口不可路由且 fallback 显式区分 404/405', () => {
    const result = backendJson<{ root: number; legacy: number[]; defaultRouteDisabled: boolean }>(String.raw`
      \Webman\Route::load([config_path()]);
      $paths = ['/index', '/login', '/ajax', '/admin/index', '/admin/login', '/admin/ajax'];
      echo json_encode([
          'root' => \Webman\Route::dispatch('GET', '/')[0],
          'legacy' => array_map(static fn ($path) => \Webman\Route::dispatch('GET', $path)[0], $paths),
          'defaultRouteDisabled' => \Webman\Route::isDefaultRouteDisabled(),
      ]);
    `);
    expect(result.root).toBe(1);
    expect(result.legacy).toEqual([0, 0, 0, 0, 0, 0]);
    expect(result.defaultRouteDisabled).toBe(true);
    expect(consoleRoute).toContain("response()->withFile(public_path('index.html'))");
    expect(consoleRoute).toMatch(/Route::fallback\s*\(/);
    expect(consoleRoute).toContain('METHOD_NOT_ALLOWED');
    expect(consoleRoute).toContain('->withStatus(404)');
    expect(consoleRoute).toContain('->withStatus(405)');
  });
});
