import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { getAdminMenuTreeSeed } from '@/mock/data/adminSeed';

const project = resolve(process.cwd(), '..');
const src = resolve(process.cwd(), 'src');
const read = (path: string) => readFileSync(resolve(src, path), 'utf8');
const readProject = (path: string) => readFileSync(resolve(project, path), 'utf8');

describe('SSO Phase 8 管理控制台与应用门户', () => {
  it('应用中心是独立菜单且包含完整页面', () => {
    const root = getAdminMenuTreeSeed().find((item) => item.routeName === 'EnterpriseApplications');
    expect(root?.children?.map((item) => item.routeName)).toEqual(expect.arrayContaining(['ApplicationList', 'DomainManagement', 'SsoConfiguration', 'OAuthClientManagement', 'ScopeClaimManagement', 'IdentityUsers', 'IdentitySessions', 'SigningKeys', 'IdentityAudit', 'ApplicationPortal']));
    expect(getAdminMenuTreeSeed().find((item) => item.routeName === 'Development')?.children?.some((item) => item.routeName === 'BusinessDevelopment')).toBe(true);
  });

  it('SSO 页面提供三模式、固定保存与完整自检', () => {
    const page = read('views/applications/sso/index.vue');
    for (const marker of ['关闭 SSO', '身份提供方', '外部身份接入', 'sticky-save', 'issuer', 'https', 'redirect', 'pkce', 'scope', 'backchannel']) expect(page.toLowerCase()).toContain(marker.toLowerCase());
  });

  it('门户按 assignment 准入目录展示并通过 launch API 无 token 跳转', () => {
    const page = read('views/applications/portal.vue');
    const api = read('api/identity/applications.ts');
    expect(api).toContain("'/identity/applications/portal'");
    expect(page).toContain('applicationApi.portal');
    expect(page).toContain('applicationApi.launch');
    expect(page).toContain("searchParams.has('token')");
    expect(page).not.toContain('accessToken=');
    expect(page).toContain('availabilityReason');
  });

  it('Identity API 类型和 mock 覆盖全部管理端点', () => {
    const api = read('api/identity/management.ts');
    const mock = read('mock/modules/applications.ts');
    for (const endpoint of ['/identity/sso/config', '/identity/sso/check', '/identity/scopes', '/identity/users', '/identity/oidc-sessions', '/identity/audit']) {
      expect(api).toContain(endpoint);
      expect(mock).toContain(endpoint);
    }
    for (const endpoint of ['/links', '/sessions', '/authorizations']) {
      expect(api).toContain(endpoint);
      expect(mock).toContain(endpoint);
    }
    expect(mock).not.toMatch(/session_token_hash|transaction_hash|logout_token/);
  });

  it('migration 菜单复用既有权限并匹配真实控制器 action', () => {
    const migration = readProject('database/migrations/114_sso_phase8_admin_portal.sql');
    for (const routeName of ['ApplicationList', 'DomainManagement', 'SsoConfiguration', 'OAuthClientManagement', 'ScopeClaimManagement', 'IdentityUsers', 'IdentitySessions', 'SigningKeys', 'IdentityAudit']) expect(migration).toContain(`name=${routeName}`);
    expect(migration).toContain('console/identity.ssoconfiguration:config');
    expect(migration).toContain('console/identity.identityaudit:index');
    expect(migration).not.toContain("'console/identity.sso'");
    expect(migration).not.toContain("'console/identity.signingkey'");
  });
});
