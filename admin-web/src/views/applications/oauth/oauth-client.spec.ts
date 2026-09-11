import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { mapOAuthClient } from '@/api/identity/oauthClients';
import { getAdminMenuTreeSeed } from '@/mock/data/adminSeed';

const src = resolve(process.cwd(), 'src');
const read = (path: string) => readFileSync(resolve(src, path), 'utf8');

describe('OAuth Client 管理', () => {
  it('映射真实 API DTO 并接入应用中心菜单', () => {
    expect(mapOAuthClient({ id: 1, application_id: 2, client_id: 'cid', name: 'Web', client_type: 'public', status: 'active', require_pkce: 1, grants: ['authorization_code'], scopes: ['openid'], redirect_uris: [{ uri_type: 'authorization_callback', redirect_uri: 'https://example.com/cb' }] })).toMatchObject({ applicationId: 2, clientId: 'cid', requirePkce: true, redirectUris: [{ uriType: 'authorization_callback', redirectUri: 'https://example.com/cb' }] });
    const applications = getAdminMenuTreeSeed().find((item) => item.routeName === 'EnterpriseApplications');
    expect(applications?.children?.some((item) => item.routeName === 'OAuthClientManagement')).toBe(true);
  });

  it('一次性 secret 只保存在内存且关闭时清空', () => {
    const page = read('views/applications/oauth/index.vue');
    expect(page).toContain("const clearRevealedSecret=()=>{revealedSecret.value='';}");
    expect(page).toContain('@closed="clearRevealedSecret"');
    expect(page).not.toContain('localStorage');
    expect(page).not.toContain('sessionStorage');
    expect(page).not.toMatch(/query:\s*\{[^}]*secret/i);
  });

  it('提供 URI 校验、scope/grant、secret 与 key rotation', () => {
    const page = read('views/applications/oauth/index.vue');
    for (const marker of ['validateUris', 'authorization_code', 'client_credentials', 'replaceRedirectUris', 'createSecret', 'rotateKey', 'publishUntil']) expect(page).toContain(marker);
    expect(page).toContain("url.protocol==='https:'");
    expect(page).toContain("!url.hash");
  });

  it('应用设置 OAuth tab 不再是假占位', () => {
    const center = read('views/applications/index.vue');
    expect(center).toContain('openOAuthManagement');
    expect(center).not.toContain('OAuth 客户端配置将在后续阶段开放');
  });
});
