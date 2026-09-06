import type { CrudDefinition, CrudField, CrudPreview } from '@/types/development/crud';
import { ok, type MockRoute } from '../types';

const fields: CrudField[] = [
  { name: 'id', label: 'ID', dbType: 'bigint unsigned', nullable: false, primary: true, managed: true, writable: false, list: true, detail: true },
  { name: 'name', label: '名称', dbType: 'varchar(100)', nullable: false, required: true, maxLength: 100, unique: true, search: true, searchOperator: 'like', sortable: true, list: true, form: true, detail: true },
  { name: 'status', label: '状态', dbType: 'tinyint(1)', nullable: false, writable: true, list: true, form: true, detail: true, options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }] },
  { name: 'created_at', label: '创建时间', dbType: 'datetime', nullable: true, managed: true, writable: false, list: true, detail: true },
  { name: 'updated_at', label: '更新时间', dbType: 'datetime', nullable: true, managed: true, writable: false, detail: true },
  { name: 'deleted_at', label: '删除时间', dbType: 'datetime', nullable: true, managed: true, writable: false, detail: true }
];

function preview(definition: CrudDefinition): CrudPreview {
  return {
    generationId: 1,
    plan: {
      definitionHash: 'mock-definition-hash',
      files: Object.values(definition.generationTargets).map((path) => ({ path, status: 'create' as const }))
    },
    sensitive: { confirmToken: 'mock-preview-token' }
  };
}

export const developmentCrudMockHandlers: MockRoute[] = [
  { method: 'GET', url: '/development/crud/connections', handler: () => ok([{ name: 'mysql' }]) },
  { method: 'GET', url: '/development/crud/tables', handler: () => ok([{ name: 'fun_demo', comment: 'Mock 验收数据表' }]) },
  { method: 'GET', url: /^\/development\/crud\/tables\/([a-z_][a-z0-9_]*)\/schema$/, paramNames: ['table'], handler: ({ pathParams }) => ok({ table: pathParams.table, fields }) },
  { method: 'POST', url: '/development/crud/infer', handler: ({ body }) => ok({ schema: { connection: body.connection, table: body.table }, fields }) },
  { method: 'POST', url: '/development/crud/definitions/validate', handler: () => ok({ valid: true, definitionHash: 'mock-definition-hash' }) },
  { method: 'POST', url: '/development/crud/preview', handler: ({ body }) => ok(preview(body.definition as CrudDefinition)) },
  { method: 'POST', url: '/development/crud/generate', handler: () => ok({ generationId: 1, write: { status: 'written' } }) },
  { method: 'GET', url: /^\/development\/crud\/generations\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => ok({ id: Number(pathParams.id), status: 'previewed' }) }
];