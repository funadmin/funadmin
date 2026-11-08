import http from '@/utils/http';
import type { FormComponentCatalogItem, FormDefinition, FormFieldDef, FormPublishConfig, FormSchemaDiff, FormSchemaDocument, FormSchemaCompileResult, FormSchemaVersion } from '@/api/form';

import type { FormListActionMetadata, FormListButtonLocation } from '@/views/form/schema/types';
import type { ListResource } from '@/views/form/runtime/listResourceHost';
import type { DatabaseSyncDiff } from '@/views/development/business/codegen/syncMerge';

export interface BusinessDesignActionCatalog {
  moduleId: number;
  designOnly: true;
  executable: false;
  actions: Record<FormListButtonLocation, Record<string, FormListActionMetadata>>;
  resources: Record<string, ListResource>;
}

const PREFIX = '/development/business';

export interface BusinessDatabaseTable {
  name: string;
  comment: string;
}

export type BusinessModuleOrigin = 'visual' | 'database';

export type BusinessTargetSelection = { type: 'core' } | { type: 'plugin'; pluginCode: string };
export interface BusinessTarget {
  type: 'core' | 'plugin';
  pluginCode: string | null;
  scope: 'admin';
  tableStrategy?: 'owned' | 'external';
  locked?: boolean;
}
export interface BusinessTargetCandidates {
  list: Array<BusinessTarget & {
    name: string;
    available?: boolean;
    reason?: { code: string; message: string } | null;
  }>;
  defaultConnection: string;
  migrationPath: string;
}

export interface BusinessModule {
  id: number;
  code: string;
  name: string;
  origin: BusinessModuleOrigin;
  lifecycle_status: 'draft' | 'published' | 'dynamic_published' | 'disabled' | string;
  generation_status?: string;
  recovery_status?: string;
  metadata?: { target?: BusinessTarget; [key: string]: unknown };
  table_name?: string;
  connection_name?: string;
  runtime_route?: string;
  module_route?: string;
  form_id?: number | null;
  updated_at?: string;
  /** 列表投影：模块表单已保存 Schema 的 list.tree.enabled（树表 CRUD）。 */
  tree_enabled?: boolean;
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
  /** 核心目标生成成功后是否已请求后端平滑重载 worker（新注解路由需重载后生效）；插件目标恒为 false。 */
  reloadRequested?: boolean;
  /** 未请求重载的原因，如 plugin_target、deferred（批量生成延后重载）、pid_file_missing、posix_unavailable、master_not_running。 */
  reloadReason?: string | null;
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
  targets: () => http.get<BusinessTargetCandidates>(`${PREFIX}/targets`),
  modules: (params: { page?: number; pageSize?: number; keyword?: string; status?: string; origin?: string; target?: '' | 'core' | 'plugin' } = {}) =>
    http.get<BusinessPageResult<BusinessModule>>(`${PREFIX}/modules`, params),
  module: (id: number) => http.get<BusinessModuleDetail>(`${PREFIX}/modules/${id}`),
  /** 批量删除时传 showSuccessMsg/showErrorMsg: false，由调用方汇总一条提示。 */
  removeModule: (id: number, options: { showSuccessMsg?: boolean; showErrorMsg?: boolean } = {}) =>
    http.delete<{ removed: number }>(`${PREFIX}/modules/${id}`, undefined, {
      requestOptions: { showSuccessMsg: options.showSuccessMsg ?? true, showErrorMsg: options.showErrorMsg ?? true }
    }),
  createVisual: (payload: Record<string, unknown>) => http.post<BusinessModuleDetail>(`${PREFIX}/modules/visual`, payload),
  inspectDatabase: (connection: string, table: string) =>
    http.post<BusinessFieldInspection>(`${PREFIX}/modules/from-database/inspect`, { connection, table }),
  createFromDatabase: (payload: Record<string, unknown>) =>
    http.post<BusinessModuleDetail>(`${PREFIX}/modules/from-database`, payload),
  validateSchema: (id: number, schema: FormSchemaDocument) =>
    http.post<FormSchemaCompileResult>(`${PREFIX}/modules/${id}/schema/validate`, { schema }),
  saveSchema: (id: number, schema: FormSchemaDocument, expectedSchemaHash: string, summary = '') =>
    http.post<{ schemaHash: string; document: FormSchemaDocument }>(`${PREFIX}/modules/${id}/schema/save`, { schema, expectedSchemaHash, summary }, { requestOptions: { showErrorMsg: false } }),
  savePublishConfig: (id: number, publishConfig: Partial<FormPublishConfig>) =>
    http.post<{ publishConfig: Partial<FormPublishConfig> }>(`${PREFIX}/modules/${id}/publish-config`, { publishConfig }, { requestOptions: { showErrorMsg: false } }),
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
  databaseSync: (id: number) => http.post<DatabaseSyncDiff>(`${PREFIX}/modules/${id}/schema/database-sync`),
  databaseTables: (connection: string, signal?: AbortSignal) => signal
    ? http.get<BusinessDatabaseTable[]>(`${PREFIX}/database/tables`, { connection }, { signal })
    : http.get<BusinessDatabaseTable[]>(`${PREFIX}/database/tables`, { connection }),
  databaseTableSchema: (connection: string, table: string) =>
    http.get<Record<string, unknown>>(`${PREFIX}/database/tables/${table}/schema`, { connection }),
  previewPublish: (id: number, payload: Record<string, unknown>) =>
    http.post<Record<string, unknown>>(`${PREFIX}/modules/${id}/publish/preview`, payload),
  publish: (id: number, payload: Record<string, unknown>) =>
    http.post<Record<string, unknown>>(`${PREFIX}/modules/${id}/publish`, payload),
  designActionCatalog: (id: number) => http.get<BusinessDesignActionCatalog>(`${PREFIX}/modules/${id}/schema/design-action-catalog`),
  runtimeMeta: (id: number) => http.get<Record<string, unknown>>(`${PREFIX}/modules/${id}/runtime-meta`),
  previewFormalGeneration: (id: number, nonce = '') =>
    http.post<BusinessFormalGenerationPreview>(`${PREFIX}/modules/${id}/formal-generation/preview`, { nonce }),
  /** deferReload：批量生成中非最后一个模块传 true，后端跳过 worker 重载（返回 reloadReason=deferred），由最后一个模块统一重载。 */
  formalGeneration: (id: number, generationId: number, confirmToken: string, options: { deferReload?: boolean } = {}) =>
    http.post<BusinessFormalGenerationResult>(`${PREFIX}/modules/${id}/formal-generation`, { generationId, confirmToken, ...options }),
  /** POST（带 CSRF）下载 zip；传入当前预览的 nonce，服务端复用同一生成记录，不让已打开的预览失效。错误由调用方提示。 */
  formalGenerationArchive: (id: number, nonce: string) =>
    http.downloadPost(`${PREFIX}/modules/${id}/formal-generation/archive`, { nonce }, { requestOptions: { showErrorMsg: false } }),
  /** 采纳最近冲突计划中本地已改成生成内容（Local hash = Remote hash）的 conflict-no-base 文件为新 baseline。 */
  adoptResolvedBaseline: (id: number, input: { generationId: number; path: string; localHash: string; remoteHash: string }) =>
    http.post<Record<string, unknown>>(`${PREFIX}/modules/${id}/baselines/adopt-resolved`, input),
  generations: (params: { page?: number; pageSize?: number; moduleId?: number; status?: string } = {}) =>
    http.get<BusinessPageResult<BusinessGeneration>>(`${PREFIX}/generations`, params),
  generation: (id: number) => http.get<BusinessGeneration>(`${PREFIX}/generations/${id}`),
  recoverGeneration: (id: number, expectedRecoveryStatus: string) =>
    http.post<BusinessGenerationResult>(`${PREFIX}/generations/${id}/recover`, { expectedRecoveryStatus }),
  retryResources: (id: number) => http.post<Record<string, unknown>>(`${PREFIX}/generations/${id}/retry-resources`),
  fieldCapabilities: () => http.get<{ registryVersion: string; schemaVersion: 2; registryHash: string; capabilities: FormComponentCatalogItem[]; diagnostics: Array<Record<string, unknown>> }>(`${PREFIX}/field-capabilities`)
};
