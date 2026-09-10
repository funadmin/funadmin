import http from '@/utils/http';
import type { FormComponentCatalogItem, FormDefinition, FormFieldDef, FormSchemaDocument, FormSchemaCompileResult } from '@/api/form';

const PREFIX = '/development/business';

export interface BusinessModule {
  id: number;
  code: string;
  name: string;
  origin: 'visual' | 'database' | 'legacy_form' | string;
  lifecycle_status: 'draft' | 'published' | 'dynamic_published' | 'disabled' | string;
  generation_status?: string;
  table_name?: string;
  connection_name?: string;
  runtime_route?: string;
  module_route?: string;
  form_id?: number | null;
  updated_at?: string;
}

export interface BusinessGeneration {
  id: number;
  business_module_id?: number;
  status: string;
  generation_mode?: string;
  recovery_status?: string;
  plan_digest?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface BusinessGenerationFile {
  path: string;
  status: 'create' | 'update' | 'auto-merged' | 'keep-local' | 'delete' | 'conflict' | 'binary-conflict' | 'conflict-no-base';
  contentKind?: 'text' | 'binary';
  baseHash?: string | null;
  localHash?: string | null;
  remoteHash?: string | null;
  baseContent?: string | null;
  localContent?: string | null;
  remoteContent?: string | null;
}

export interface BusinessFormalGenerationPreview {
  generationId: number;
  definitionHash?: string;
  schemaHash?: string;
  routePath?: string;
  plan: { blocked: boolean; files: BusinessGenerationFile[] };
  conflicts: BusinessGenerationFile[];
  sensitive?: { confirmToken: string };
}

export interface BusinessFormalGenerationResult {
  generationId: number;
  state: string;
  resourceApplyStatus: string;
  resourceApplyError?: string | null;
  routePath?: string;
  definitionHash?: string;
  schemaHash?: string;
}

export interface BusinessModuleDetail {
  module: BusinessModule;
  form?: FormDefinition | null;
  fields: FormFieldDef[];
}

export interface BusinessPageResult<T> {
  list: T[];
  total: number;
  page: number;
  pageSize: number;
}

export interface BusinessFieldInspection {
  fields: Array<Record<string, unknown>>;
  connection: string;
  table: string;
}

export const businessDevelopmentApi = {
  modules: (params: { page?: number; pageSize?: number; keyword?: string; status?: string; origin?: string } = {}) =>
    http.get<BusinessPageResult<BusinessModule>>(`${PREFIX}/modules`, params),
  module: (id: number) => http.get<BusinessModuleDetail>(`${PREFIX}/modules/${id}`),
  createVisual: (payload: Record<string, unknown>) => http.post<BusinessModuleDetail>(`${PREFIX}/modules/visual`, payload),
  inspectDatabase: (connection: string, table: string) =>
    http.post<BusinessFieldInspection>(`${PREFIX}/modules/from-database/inspect`, { connection, table }),
  createFromDatabase: (payload: Record<string, unknown>) =>
    http.post<BusinessModuleDetail>(`${PREFIX}/modules/from-database`, payload),
  validateSchema: (id: number, schema: FormSchemaDocument) =>
    http.post<FormSchemaCompileResult>(`${PREFIX}/modules/${id}/schema/validate`, { schema }),
  saveSchema: (id: number, schema: FormSchemaDocument, expectedSchemaHash: string, summary = '') =>
    http.post<{ schemaHash: string; document: FormSchemaDocument }>(`${PREFIX}/modules/${id}/schema/save`, { schema, expectedSchemaHash, summary }),
  previewPublish: (id: number, payload: Record<string, unknown>) =>
    http.post<Record<string, unknown>>(`${PREFIX}/modules/${id}/publish/preview`, payload),
  publish: (id: number, payload: Record<string, unknown>) =>
    http.post<Record<string, unknown>>(`${PREFIX}/modules/${id}/publish`, payload),
  runtimeMeta: (id: number) => http.get<Record<string, unknown>>(`${PREFIX}/modules/${id}/runtime-meta`),
  previewFormalGeneration: (id: number, nonce = '') =>
    http.post<BusinessFormalGenerationPreview>(`${PREFIX}/modules/${id}/formal-generation/preview`, { nonce }),
  formalGeneration: (id: number, generationId: number, confirmToken: string) =>
    http.post<BusinessFormalGenerationResult>(`${PREFIX}/modules/${id}/formal-generation`, { generationId, confirmToken }),
  generations: (params: { page?: number; pageSize?: number; moduleId?: number; status?: string } = {}) =>
    http.get<BusinessPageResult<BusinessGeneration>>(`${PREFIX}/generations`, params),
  generation: (id: number) => http.get<BusinessGeneration>(`${PREFIX}/generations/${id}`),
  retryResources: (id: number) => http.post<Record<string, unknown>>(`${PREFIX}/generations/${id}/retry-resources`),
  fieldCapabilities: () => http.get<{ registryVersion: string; schemaVersion: 2; registryHash: string; capabilities: FormComponentCatalogItem[]; diagnostics: Array<Record<string, unknown>> }>(`${PREFIX}/field-capabilities`)
};
