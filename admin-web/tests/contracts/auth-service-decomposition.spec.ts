import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');
const servicePath = (name: string) => resolve(projectRoot, `app/backend/service/${name}.php`);

const authService = readProjectFile('app/backend/service/AuthService.php');
const roleGuardService = readProjectFile('app/backend/service/RoleGuardService.php');
const indexController = readProjectFile('app/backend/controller/Index.php');
const ajaxController = readProjectFile('app/backend/controller/Ajax.php');
const apiRoleMiddleware = readProjectFile('app/backend/middleware/CheckAdminApiRole.php');

const methodBody = (source: string, method: string) => {
  const signatureAt = source.search(new RegExp(`(?:public|protected|private)\\s+function\\s+${method}\\s*\\(`));
  expect(signatureAt, `缺少 ${method} 方法`).toBeGreaterThan(-1);

  const bodyStart = source.indexOf('{', signatureAt);
  let depth = 0;
  for (let index = bodyStart; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}') depth -= 1;
    if (depth === 0) return source.slice(bodyStart + 1, index);
  }
  throw new Error(`${method} 方法体未闭合`);
};

const expectFacadeDelegation = (method: string, service: string) => {
  const body = methodBody(authService, method);
  expect(body).toContain(service);
  expect(body).toMatch(/return\s+.*->\w+\s*\(/s);
};

describe('AuthService 职责拆分源码契约', () => {
  it('删除零引用方法、无用请求属性和遗留注释代码', () => {
    for (const method of ['treemenu', 'buildPermissionTree', 'flattenPermissionTree', 'getAdmin', 'descendantRoleIds']) {
      expect(authService).not.toMatch(new RegExp(`function\\s+${method}\\s*\\(`));
    }
    expect(authService).not.toMatch(/protected\s+\$(controller|action)\b/);
    expect(authService).not.toMatch(/\$this->(controller|action)\s*=/);
    expect(authService).not.toMatch(/^\s*\/\/\s*\$list\[\]/m);
  });

  it('旧菜单 HTML 只由 AdminLegacyMenuService 生成且控制器直接使用新服务', () => {
    expect(existsSync(servicePath('AdminLegacyMenuService'))).toBe(true);
    const legacyMenuService = readProjectFile('app/backend/service/AdminLegacyMenuService.php');
    for (const method of ['menuhtml', 'childmenuhtml', 'filterAuthorizedMenus']) {
      expect(legacyMenuService).toMatch(new RegExp(`function\\s+${method}\\s*\\(`));
      expect(authService).not.toMatch(new RegExp(`function\\s+${method}\\s*\\(`));
    }
    expect(authService).not.toContain('layui-nav');
    expect(indexController).toContain('AdminLegacyMenuService');
    expect(indexController).not.toMatch(/AuthService::instance\(\)->menuhtml/);
    expect(ajaxController).toContain('AdminLegacyMenuService');
    expect(ajaxController).not.toMatch(/AuthService::instance\(\)->menuhtml/);
  });

  it('登录和会话实现下沉且 AuthService 保留兼容门面', () => {
    expect(existsSync(servicePath('AdminSessionService'))).toBe(true);
    const sessionService = readProjectFile('app/backend/service/AdminSessionService.php');
    for (const method of ['checkLogin', 'isLogin', 'logout']) {
      expect(sessionService).toMatch(new RegExp(`function\\s+${method}\\s*\\(`));
      expectFacadeDelegation(method, 'AdminSessionService');
    }
    expect(methodBody(authService, 'checkLogin')).not.toContain('password_verify');
    expect(sessionService).not.toMatch(/throw\s+new\s+\\?Exception\s*\(\s*\$e->getMessage\(\)\s*\)/);
    expect(sessionService).toMatch(/catch\s*\(\s*\\?Throwable\s+\$e\s*\)[\s\S]*throw\s+\$e\s*;/);
  });

  it('请求授权实现下沉且已认证请求不会重复验证登录', () => {
    expect(existsSync(servicePath('AdminAuthorizationService'))).toBe(true);
    const authorizationService = readProjectFile('app/backend/service/AdminAuthorizationService.php');
    for (const method of ['roleAccess', 'nodeAccess']) {
      expect(authorizationService).toMatch(new RegExp(`function\\s+${method}\\s*\\(`));
      expectFacadeDelegation(method, 'AdminAuthorizationService');
    }
    expect(methodBody(authService, 'roleAccess')).not.toContain('enforceAdmin');
    expect(apiRoleMiddleware).toMatch(/roleAccess\s*\(\s*true\s*\)/);
    expect(methodBody(authorizationService, 'roleAccess')).toMatch(/bool\s+\$authenticated\s*=\s*false/);
  });

  it('角色范围实现有单一归属且 RoleGuardService 不再依赖 AuthService', () => {
    const hasRoleScopeService = existsSync(servicePath('RoleScopeService'));
    const scopeServiceName = hasRoleScopeService ? 'RoleScopeService' : 'RoleGuardService';
    const scopeService = hasRoleScopeService
      ? readProjectFile('app/backend/service/RoleScopeService.php')
      : roleGuardService;
    for (const method of [
      'isSuperAdmin',
      'currentRoleIds',
      'manageableRoleIds',
      'canManageRole',
      'canUseParentRole',
      'canAssignRoles',
      'canManageAdmin',
      'canAssignPermissions',
    ]) {
      expect(scopeService).toMatch(new RegExp(`function\\s+${method}\\s*\\(`));
      expectFacadeDelegation(method, scopeServiceName);
    }
    expect(roleGuardService).not.toContain('AuthService');
  });

  it('请求后缀精确移除且角色权限缓存键按排序后的角色 ID 构造', () => {
    const authorizationSource = existsSync(servicePath('AdminAuthorizationService'))
      ? readProjectFile('app/backend/service/AdminAuthorizationService.php')
      : authService;
    expect(authorizationSource).not.toMatch(/rtrim\s*\(\s*\$this->requesturl\s*,/);
    expect(authorizationSource).toMatch(/preg_replace|substr|str_ends_with/);

    const scopeSource = existsSync(servicePath('RoleScopeService'))
      ? readProjectFile('app/backend/service/RoleScopeService.php')
      : authService;
    const permissionBody = methodBody(scopeSource, 'permissionIdsForRoles');
    const sortAt = permissionBody.search(/\b(?:sort|asort)\s*\(\s*\$roleIds\s*\)/);
    const keyAt = permissionBody.indexOf("'role-permissions-'", sortAt);
    expect(sortAt).toBeGreaterThan(-1);
    expect(keyAt).toBeGreaterThan(sortAt);
  });

  it('拆分后的服务启用严格类型且兼容门面声明返回类型', () => {
    for (const service of ['AdminLegacyMenuService', 'AdminSessionService', 'AdminAuthorizationService']) {
      const path = servicePath(service);
      expect(existsSync(path), `缺少 ${service}`).toBe(true);
      if (existsSync(path)) {
        expect(readFileSync(path, 'utf8')).toContain('declare(strict_types=1);');
      }
    }
    for (const method of ['isLogin', 'checkLogin', 'logout', 'roleAccess', 'nodeAccess']) {
      const signature = authService.match(new RegExp(`public\\s+function\\s+${method}\\s*\\([^)]*\\)\\s*:\\s*[^\\s{]+`));
      expect(signature, `${method} 兼容门面缺少返回类型`).not.toBeNull();
    }
  });
});
