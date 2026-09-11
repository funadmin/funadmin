import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { canLaunchApplication } from './applicationPolicy';
import {
  mapApplication,
  mapAssignment,
  mapDatabase,
  mapDomain,
  serializeDomainChange
} from '@/api/identity/applications';
import { getAdminMenuTreeSeed } from '@/mock/data/adminSeed';

const src = resolve(process.cwd(), 'src');
const read = (path: string) => readFileSync(resolve(src, path), 'utf8');

describe('企业应用中心', () => {
  it('只允许 published 应用进入且不拼接 token', () => {
    expect(canLaunchApplication({ status: 'published', launchUrl: 'https://app.example.com' })).toBe(true);
    expect(canLaunchApplication({ status: 'draft', launchUrl: '/draft' })).toBe(false);
    expect(canLaunchApplication({ status: 'disabled', launchUrl: '/disabled' })).toBe(false);
    expect(read('views/applications/index.vue')).not.toMatch(/[?&](?:token|access_token)=/);
  });

  it('提供卡片列表、统计搜索、应用管理和完整设置抽屉', () => {
    const page = read('views/applications/index.vue');
    for (const marker of ['viewMode', 'statistics', 'keyword', '新建应用', '发布', '停用', '删除', '进入应用', '基本信息', '运行与数据', '域名', '访问范围', '品牌', 'OAuth']) {
      expect(page).toContain(marker);
    }
    expect(page).toContain('applicationApi.domains');
    expect(page).toContain('applicationApi.assignments');
  });

  it('完整映射应用、database、domains、assignments 与 branding 的 snake_case DTO', () => {
    expect(mapApplication({
      id: 7,
      code: 'crm',
      name: 'CRM',
      description: '',
      runtime_type: 'standalone',
      launch_url: 'https://app.example.com',
      status: 'published',
      logo_url: '/logo.svg',
      brand_config: '{"color":"#112233"}',
      sort_order: 9,
      database_mode: 'external',
      base_url: 'https://app.example.com/base'
    })).toMatchObject({ runtimeType: 'standalone', launchUrl: 'https://app.example.com', logoUrl: '/logo.svg', brandConfig: { color: '#112233' }, sortOrder: 9, databaseMode: 'external', baseUrl: 'https://app.example.com/base' });
    expect(mapDatabase({ mode: 'external', credential_ref: 'vault://tenant/crm', health_path: '/health', credential_configured: true })).toEqual({ mode: 'external', credentialRef: 'vault://tenant/crm', healthPath: '/health', credentialConfigured: true });
    expect(mapAssignment({ id: 3, subject_type: 'department', subject_id: 11, effect: 'deny' })).toMatchObject({ id: 3, subjectType: 'department', subjectId: 11, effect: 'deny' });
    expect(mapDomain({ id: 5, domain_type: 'web', scheme: 'https', host: 'app.example.com', port: 443, identity_callback_path: '/identity', logout_callback_path: '/logout' })).toEqual({ id: 5, domainType: 'web', identityCallback: 'https://app.example.com/identity', logoutCallback: 'https://app.example.com/logout' });
  });

  it('以显式快照区分空域名的无变更与删除', () => {
    expect(serializeDomainChange({ identityCallback: '', logoutCallback: '', domainType: 'web' }, [], false)).toBeNull();
    expect(serializeDomainChange({ identityCallback: '', logoutCallback: '', domainType: 'web' }, [5], true)).toEqual({ domains: [], expectedDomainIds: [5] });
    expect(serializeDomainChange({ identityCallback: 'https://app.example.com/identity', logoutCallback: 'https://app.example.com/logout', domainType: 'web' }, [], true)).toEqual({ domains: [{ identityCallback: 'https://app.example.com/identity', logoutCallback: 'https://app.example.com/logout', domainType: 'web' }], expectedDomainIds: [] });
  });

  it('以独立一级菜单接入且保留统一业务开发结构', () => {
    const seed = getAdminMenuTreeSeed();
    expect(seed.find((item) => item.routeName === 'EnterpriseApplications')?.children?.[0].routeName).toBe('EnterpriseApplicationCenter');
    expect(seed.find((item) => item.routeName === 'Development')?.children?.find((item) => item.routeName === 'BusinessDevelopment')).toBeTruthy();
  });

  it('提供 API、mock 和中英文 i18n', () => {
    const api = read('api/identity/applications.ts');
    expect(api).toContain("const PREFIX = '/identity/applications'");
    expect(api).toContain('remove:');
    const mock = read('mock/modules/applications.ts');
    expect(mock).toContain('enterpriseApplicationMockHandlers');
    expect(mock).toContain("method: 'DELETE'");
    expect(read('locales/zh-CN.ts')).toContain('enterpriseApplications:');
    expect(read('locales/en-US.ts')).toContain('enterpriseApplications:');
  });
});
