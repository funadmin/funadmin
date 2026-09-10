import { ok, page, type MockRoute } from '../types';

const now = '2026-09-09 12:00:00';
const modules = [
  { id: 1, code: 'customer_order', name: '客户订单', origin: 'visual', lifecycle_status: 'dynamic_published', generation_status: 'completed', table_name: 'fun_customer_order', connection_name: 'mysql', runtime_route: '/development/business/runtime/customer_order', form_id: 1, updated_at: now },
  { id: 2, code: 'inventory', name: '库存管理', origin: 'database', lifecycle_status: 'draft', generation_status: 'idle', table_name: 'fun_inventory', connection_name: 'mysql', runtime_route: '/development/business/runtime/inventory', form_id: 2, updated_at: now }
];

export const developmentBusinessMockHandlers: MockRoute[] = [
  { method: 'GET', url: '/development/business/field-capabilities', handler: () => ok({ registryVersion: 'mock-v1', schemaVersion: 2, registryHash: 'd'.repeat(64), capabilities: [], diagnostics: [] }) },
  { method: 'GET', url: '/development/business/modules', handler: ({ params }) => {
    const keyword = String(params.keyword || '').toLowerCase();
    const list = modules.filter((item) => (!keyword || item.name.includes(keyword) || item.code.includes(keyword)) && (!params.status || item.lifecycle_status === params.status) && (!params.origin || item.origin === params.origin));
    return ok(page(list, list.length, Number(params.page || 1), Number(params.pageSize || 20)));
  } },
  { method: 'GET', url: /^\/development\/business\/modules\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => {
    const module = modules.find((item) => item.id === Number(pathParams.id)) || modules[0];
    return ok({ module, form: { id: module.form_id, form_key: module.code, name: module.name, table_name: module.table_name, connection: 'mysql', source_type: module.origin === 'database' ? 'adopted' : 'created', status: 1, remark: '', sort_order: 0, schema_version: 2, schema_hash: 'a'.repeat(64), schema_document: { schemaVersion: 2, key: module.code, title: module.name, nodes: [] }, fields: [] }, fields: [] });
  } },
  { method: 'POST', url: '/development/business/modules/from-database/inspect', handler: ({ body }) => ok({ connection: body.connection, table: body.table, fields: [{ field_name: 'id', label: 'ID', column_type: 'bigint unsigned', type: 'number' }, { field_name: 'name', label: '名称', column_type: 'varchar(100)', type: 'input' }] }) },
  { method: 'POST', url: /^\/development\/business\/modules\/(\d+)\/formal-generation\/preview$/, paramNames: ['id'], handler: ({ pathParams }) => ok({ generationId: 100 + Number(pathParams.id), definitionHash: 'c'.repeat(64), schemaHash: 'a'.repeat(64), routePath: `/generated/module-${pathParams.id}`, plan: { blocked: false, files: [{ path: 'app/generated/Model.php', status: 'create', contentKind: 'text' }] }, conflicts: [], sensitive: { confirmToken: 'mock-confirm-token' } }) },
  { method: 'GET', url: '/development/business/generations', handler: ({ params }) => ok(page([{ id: 101, business_module_id: Number(params.moduleId || 1), status: 'completed', generation_mode: 'managed', recovery_status: 'none', plan_digest: 'b'.repeat(64), created_at: now }], 1, Number(params.page || 1), Number(params.pageSize || 20))) },
  { method: 'GET', url: /^\/development\/business\/generations\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => ok({ id: Number(pathParams.id), business_module_id: 1, status: 'completed', generation_mode: 'managed', recovery_status: 'none', plan_digest: 'b'.repeat(64), created_at: now }) }
];
