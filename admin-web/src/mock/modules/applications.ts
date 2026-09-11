import { ok, page, type MockRoute } from '../types';

const applications = [
  { id: 1, code: 'crm', name: '客户中心', description: '统一客户管理', runtime_type: 'internal', launch_url: '/applications/crm', logo_url: '/logo.png', brand_config: { color: '#409eff' }, database_mode: 'shared', status: 'published' },
  { id: 2, code: 'analytics', name: '数据分析', description: '独立分析应用', runtime_type: 'standalone', launch_url: 'https://analytics.example.com/launch', logo_url: '', brand_config: { color: '#7c3aed' }, database_mode: 'external', status: 'draft' }
];
let nextId = 3;
const settings = new Map<number, Record<string, any>>([
  [1, {
    database: { mode: 'shared', health_path: '/health', credential_configured: false },
    domains: [{ id: 11, domain_type: 'web', scheme: 'https', host: 'crm.example.com', port: 443, identity_callback_path: '/identity/callback', logout_callback_path: '/logout/callback' }],
    assignments: [{ id: 21, subject_type: 'all', subject_id: null, effect: 'allow' }]
  }],
  [2, {
    database: { mode: 'external', health_path: '/health', credential_configured: true },
    domains: [], assignments: [{ id: 22, subject_type: 'role', subject_id: 2, effect: 'allow' }]
  }]
]);

export const enterpriseApplicationMockHandlers: MockRoute[] = [
  { method: 'GET', url: '/identity/applications', handler: ({ params }) => { const keyword = String(params.keyword || ''); const list = applications.filter((item) => !keyword || item.name.includes(keyword) || item.code.includes(keyword)); return ok(page(list, list.length, Number(params.page || 1), Number(params.pageSize || 20))); } },
  { method: 'GET', url: /^\/identity\/applications\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => ok(applications.find((item) => item.id === Number(pathParams.id))) },
  { method: 'POST', url: '/identity/applications', handler: ({ body }) => { const app = { id: nextId++, code: body.code, name: body.name, description: body.description, runtime_type: body.runtimeType, launch_url: body.launchUrl, logo_url: body.logoUrl, brand_config: body.brandConfig, database_mode: body.databaseMode, status: 'draft' }; applications.push(app as any); return ok(app); } },
  { method: 'PUT', url: /^\/identity\/applications\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams, body }) => { const app = applications.find((item) => item.id === Number(pathParams.id))!; Object.assign(app, { code: body.code, name: body.name, description: body.description, runtime_type: body.runtimeType, launch_url: body.launchUrl, logo_url: body.logoUrl, brand_config: body.brandConfig, database_mode: body.databaseMode }); return ok(app); } },
  { method: 'DELETE', url: /^\/identity\/applications\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => { const index = applications.findIndex((item) => item.id === Number(pathParams.id)); if (index >= 0) applications.splice(index, 1); return ok(null); } },
  { method: 'POST', url: /^\/identity\/applications\/(\d+)\/(publish|disable)$/, paramNames: ['id', 'action'], handler: ({ pathParams }) => { const app = applications.find((item) => item.id === Number(pathParams.id))!; app.status = pathParams.action === 'publish' ? 'published' : 'disabled'; return ok(app); } },
  { method: 'GET', url: /^\/identity\/applications\/(\d+)\/launch$/, paramNames: ['id'], handler: ({ pathParams }) => ok({ launchUrl: applications.find((item) => item.id === Number(pathParams.id))?.launch_url }) },
  ...['assignments', 'domains', 'database'].flatMap((section) => ([
    { method: 'GET', url: new RegExp(`^/identity/applications/(\\d+)/${section}$`), paramNames: ['id'], handler: ({ pathParams }: any) => ok(settings.get(Number(pathParams.id))?.[section] || (section === 'database' ? { mode: 'shared', credential_configured: false } : [])) },
    { method: 'PUT', url: new RegExp(`^/identity/applications/(\\d+)/${section}$`), paramNames: ['id'], handler: ({ pathParams, body }: any) => {
      const id = Number(pathParams.id); const current = settings.get(id) || {};
      if (section === 'domains') {
        const currentIds = (current.domains || []).map((domain: any) => domain.id).sort();
        const expectedIds = [...(body.expectedDomainIds || [])].sort();
        if (JSON.stringify(currentIds) !== JSON.stringify(expectedIds)) throw new Error('域名设置已被其他请求修改，请刷新后重试');
      }
      settings.set(id, { ...current, [section]: body[section] || body }); return ok(body);
    } }
  ] as MockRoute[])),
  { method: 'POST', url: /^\/identity\/applications\/(\d+)\/health$/, paramNames: ['id'], handler: () => ok({ status: 'healthy' }) }
];
