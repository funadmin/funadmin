import http from '@/utils/http';
import type { FormComponentCatalogItem, FormDefinition, FormFieldDef, FormSchemaDiff, FormSchemaDocument, FormSchemaCompileResult, FormSchemaVersion } from '@/api/form';

const PREFIX = '/development/business';

export interface BusinessDatabaseTable {
  name: string;
  comment: string;
}

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

export type BusinessGenerationAction = 'recover' | 'retry-resources';

export interface BusinessApiErrorDetail {
  code: string;
  requestId: string;
  retryable: boolean;
  details: Record<string, unknown>;
}

export interface BusinessApiErrorResponse {
  code: number;
  msg: string;
  data: { error: BusinessApiErrorDetail };
  time?: number;
}

export interface BusinessGenerationRecovery {
  state?: string;
  transactionId?: string;
  [key: string]: unknown;
}

export interface BusinessGenerationResult {
  state?: string;
  routePath?: string;
  definitionHash?: string;
  schemaHash?: string;
  resourceApplyStatus?: string;
  resourceApplyError?: string | null;
  [key: string]: unknown;
}

export interface BusinessGeneration {
  id: number;
  businessModuleId?: number;
  business_module_id?: number;
  status: string;
  generationMode?: string;
  generation_mode?: string;
  recoveryStatus?: string;
  recovery_status?: string;
  planDigest?: string | null;
  plan_digest?: string | null;
  definitionHash?: string;
  schemaHash?: string;
  routePath?: string;
  plan?: BusinessGenerationPlan;
  recovery?: BusinessGenerationRecovery | null;
  result?: BusinessGenerationResult | null;
  error?: BusinessApiErrorDetail | null;
  availableActions?: BusinessGenerationAction[];
  createdAt?: string;
  updatedAt?: string;
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

export interface BusinessGenerationPlan {
  blocked: boolean;
  summary?: Partial<Record<BusinessGenerationFile['status'], number>>;
  files: BusinessGenerationFile[];
  definitionHash?: string;
  [key: string]: unknown;
}

export interface BusinessFormalGenerationPreview {
  generationId: number;
  definitionHash?: string;
  schemaHash?: string;
  routePath?: string;
  plan: BusinessGenerationPlan;
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

export interface BusinessDatabaseIndex {
  name: string;
  columns?: string[];
  unique?: boolean;
  [key: string]: unknown;
}

export interface BusinessFieldInspection {
  fields: Array<Record<string, unknown>>;
  connection: string;
  table: string;
  snapshotHash: string;
  observedAt: string;
  primaryKey: string[];
  indexes: BusinessDatabaseIndex[];
}

export function isBusinessApiError(value: unknown): value is BusinessApiErrorResponse {
  if (!value || typeof value !== 'object') return false;
  const response = value as Partial<BusinessApiErrorResponse>;
  const error = response.data?.error;
  return typeof response.code === 'number'
    && typeof response.msg === 'string'
    && typeof error?.code === 'string'
    && typeof error.requestId === 'string'
    && typeof error.retryable === 'boolean'
    && typeof error.details === 'object';
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
  compileSchema: (id: number, schema: FormSchemaDocument) =>
    http.post<FormSchemaCompileResult>(`${PREFIX}/modules/${id}/schema/compile`, { schema }),
  exportSchema: (id: number, schema: FormSchemaDocument) =>
    http.post<{ document: string }>(`${PREFIX}/modules/${id}/schema/export`, { schema }),
  schemaVersions: (id: number) => http.get<{ list: FormSchemaVersion[] }>(`${PREFIX}/modules/${id}/schema/versions`),
  schemaVersion: (id: number, version: number) => http.get<FormSchemaVersion>(`${PREFIX}/modules/${id}/schema/versions/${version}`),
  schemaDiff: (id: number, fromVersion: number, toVersion: number) =>
    http.get<FormSchemaDiff>(`${PREFIX}/modules/${id}/schema/diff`, { fromVersion, toVersion }),
  rollbackSchema: (id: number, version: number, expectedSchemaHash: string, summary = '') =>
    http.post<FormSchemaVersion>(`${PREFIX}/modules/${id}/schema/versions/${version}/rollback`, { expectedSchemaHash, summary }),
  databaseTables: (connection: string, signal?: AbortSignal) => signal
    ? http.get<BusinessDatabaseTable[]>(`${PREFIX}/database/tables`, { connection }, { signal })
    : http.get<BusinessDatabaseTable[]>(`${PREFIX}/database/tables`, { connection }),
  databaseTableSchema: (connection: string, table: string) =>
    http.get<Record<string, unknown>>(`${PREFIX}/database/tables/${table}/schema`, { connection }),
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
  recoverGeneration: (id: number, expectedRecoveryStatus: string) =>
    http.post<BusinessGenerationResult>(`${PREFIX}/generations/${id}/recover`, { expectedRecoveryStatus }),
  retryResources: (id: number) => http.post<Record<string, unknown>>(`${PREFIX}/generations/${id}/retry-resources`),
  fieldCapabilities: () => http.get<{ registryVersion: string; schemaVersion: 2; registryHash: string; capabilities: FormComponentCatalogItem[]; diagnostics: Array<Record<string, unknown>> }>(`${PREFIX}/field-capabilities`)
};
