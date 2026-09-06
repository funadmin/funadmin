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
      files: Object.entries(definition.generationTargets).map(([type, path], index) => ({
        path,
        status: index === 1 ? 'conflict' as const : index === 2 ? 'unchanged' as const : 'create' as const,
        previousHash: index === 1 ? 'mock-previous-hash' : null,
        diff: `--- current\n+++ generated\n+mock ${type}`
      }))
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
  { method: 'POST', url: '/development/crud/generate', handler: ({ body }) => {
    const definition = body.definition as CrudDefinition;
    const paths = Object.values(definition.generationTargets);
    return ok({
      generationId: 2,
      write: { status: 'written', written: paths.length - 1 },
      plan: preview(definition).plan,
      manifest: {
        status: 'written',
        createdFiles: paths.filter((_, index) => index !== 1 && index !== 2),
        overwrittenFiles: paths.slice(1, 2),
        validationResult: { valid: true }
      }
    });
  } },
  { method: 'GET', url: /^\/development\/crud\/generations\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => ok({ id: Number(pathParams.id), status: 'previewed' }) }
];