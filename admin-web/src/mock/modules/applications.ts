import { ok, page, type MockRoute } from '../types';

const applications = [
  { id: 1, code: 'crm', name: '客户中心', description: '统一客户管理', runtime_type: 'internal', launch_url: '/applications/crm', logo_url: '/logo.png', brand_config: { color: '#409eff' }, database_mode: 'shared', status: 'published' },
  { id: 2, code: 'analytics', name: '数据分析', description: '独立分析应用', runtime_type: 'standalone', launch_url: 'https://analytics.example.com/launch', logo_url: '', brand_config: { color: '#7c3aed' }, database_mode: 'external', status: 'draft' }
];
let nextId = 3;
let nextClientId = 2;
let nextSecretId = 1;
let nextKeyId = 2;
const oauthClients: any[] = [{ id: 1, application_id: 1, client_id: 'mock-crm-client', name: 'CRM Web', client_type: 'confidential', token_endpoint_auth_method: 'client_secret_basic', require_pkce: 0, status: 'active', grants: ['authorization_code', 'refresh_token'], scopes: ['openid', 'profile'], redirect_uris: [{ id: 1, uri_type: 'authorization_callback', redirect_uri: 'https://crm.example.com/callback' }] }];
const clientSecrets = new Map<number, any[]>();
const signingKeys: any[] = [{ id: 1, kid: 'mock-active-key', algorithm: 'RS256', status: 'active', activated_at: '2026-09-01 00:00:00', publish_until: null }];
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
  { method: 'POST', url: /^\/identity\/applications\/(\d+)\/health$/, paramNames: ['id'], handler: () => ok({ status: 'healthy' }) },
  { method: 'GET', url: '/identity/oauth-clients', handler: ({ params }) => { const keyword = String(params.keyword || ''); const applicationId = Number(params.applicationId || 0); const list = oauthClients.filter((item) => (!keyword || item.name.includes(keyword) || item.client_id.includes(keyword)) && (!applicationId || item.application_id === applicationId)); return ok(page(list, list.length, Number(params.page || 1), Number(params.pageSize || 20))); } },
  { method: 'GET', url: /^\/identity\/oauth-clients\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => ok(oauthClients.find((item) => item.id === Number(pathParams.id))) },
  { method: 'POST', url: '/identity/oauth-clients', handler: ({ body }) => { const item = { id: nextClientId++, application_id: Number(body.applicationId), client_id: `mock-client-${nextClientId}`, name: body.name, client_type: body.clientType, token_endpoint_auth_method: body.clientType === 'public' ? 'none' : 'client_secret_basic', require_pkce: body.clientType === 'public' ? 1 : 0, status: 'active', grants: body.grants, scopes: body.scopes, redirect_uris: [] }; oauthClients.push(item); return ok(item); } },
  { method: 'PUT', url: /^\/identity\/oauth-clients\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams, body }) => { const item = oauthClients.find((row) => row.id === Number(pathParams.id))!; Object.assign(item, { name: body.name, client_type: body.clientType, grants: body.grants, scopes: body.scopes }); return ok(item); } },
  { method: 'DELETE', url: /^\/identity\/oauth-clients\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => { const index = oauthClients.findIndex((item) => item.id === Number(pathParams.id)); if (index >= 0) oauthClients.splice(index, 1); return ok(null); } },
  { method: 'POST', url: /^\/identity\/oauth-clients\/(\d+)\/disable$/, paramNames: ['id'], handler: ({ pathParams }) => { const item = oauthClients.find((row) => row.id === Number(pathParams.id))!; item.status = 'disabled'; return ok(item); } },
  { method: 'GET', url: /^\/identity\/oauth-clients\/(\d+)\/secrets$/, paramNames: ['id'], handler: ({ pathParams }) => ok(clientSecrets.get(Number(pathParams.id)) || []) },
  { method: 'POST', url: /^\/identity\/oauth-clients\/(\d+)\/secrets$/, paramNames: ['id'], handler: ({ pathParams }) => { const clientId = Number(pathParams.id); const metadata = { id: nextSecretId++, prefix: 'mock_secret_', secret: `mock_secret_once_${crypto.randomUUID()}`, revoked_at: null }; clientSecrets.set(clientId, [...(clientSecrets.get(clientId) || []), { ...metadata, secret: undefined }]); return ok(metadata); } },
  { method: 'DELETE', url: /^\/identity\/oauth-clients\/(\d+)\/secrets\/(\d+)$/, paramNames: ['id', 'secretId'], handler: ({ pathParams }) => { const rows = clientSecrets.get(Number(pathParams.id)) || []; const row = rows.find((item) => item.id === Number(pathParams.secretId)); if (row) row.revoked_at = new Date().toISOString(); return ok(null); } },
  { method: 'PUT', url: /^\/identity\/oauth-clients\/(\d+)\/redirect-uris$/, paramNames: ['id'], handler: ({ pathParams, body }) => { const item = oauthClients.find((row) => row.id === Number(pathParams.id))!; item.redirect_uris = body.redirectUris.map((uri: any, index: number) => ({ id: index + 1, uri_type: uri.type, redirect_uri: uri.uri })); return ok(item.redirect_uris); } },
  ...['scopes', 'grants'].map((section) => ({ method: 'PUT', url: new RegExp(`^/identity/oauth-clients/(\\d+)/${section}$`), paramNames: ['id'], handler: ({ pathParams, body }: any) => { const item = oauthClients.find((row) => row.id === Number(pathParams.id))!; item[section] = body[section]; return ok(item); } } as MockRoute)),
  { method: 'GET', url: '/identity/signing-keys', handler: () => ok(signingKeys) },
  { method: 'POST', url: '/identity/signing-keys/rotate', handler: () => { const now = new Date(); const active = signingKeys.find((item) => item.status === 'active'); if (active) { active.status = 'retiring'; active.publish_until = new Date(now.getTime() + 86400000).toISOString(); } const key = { id: nextKeyId++, kid: `mock-key-${nextKeyId}`, algorithm: 'RS256', status: 'active', activated_at: now.toISOString(), publish_until: null }; signingKeys.unshift(key); return ok(key); } }
];
