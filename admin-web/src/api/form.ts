import http from '@/utils/http';

export type FormSchemaOrigin = 'designer' | 'import' | 'migration' | 'api' | string;
export type FormSchemaNodeKind = 'field' | 'layout';

export interface FormSchemaValidationCondition {
  field?: string;
  op: string;
  value?: unknown;
  conditions?: FormSchemaValidationCondition[];
  condition?: FormSchemaValidationCondition;
}

export interface FormSchemaValidationRule {
  type: string;
  value?: unknown;
  message?: string;
  trigger?: string[];
  validator?: { key: string; params?: Record<string, unknown>; debounce?: number; timeout?: number; cacheTtl?: number };
  when?: FormSchemaValidationCondition;
  severity?: 'error' | 'warning';
  bail?: boolean;
}

export interface FormSchemaNode {
  id: string;
  kind: FormSchemaNodeKind;
  type: string;
  field?: string | null;
  title: string;
  defaultValue?: unknown;
  valueType?: 'string' | 'number' | 'boolean' | 'array' | 'object';
  props?: Record<string, unknown>;
  attrs?: Record<string, unknown>;
  className?: string;
  style?: Record<string, string | number>;
  hidden?: boolean;
  disabled?: boolean;
  info?: string;
  slot?: string | null;
  children: FormSchemaNode[];
  validation?: FormSchemaValidationRule[];
  dataSource?: Record<string, unknown> | null;
  conditions?: unknown[];
  events?: Record<string, unknown[]>;
  access?: { read?: string[]; write?: string[]; include?: 'auto' | 'always' | 'never' };
  layout?: { span?: number; group?: string };
  database?: Record<string, unknown>;
  list?: Record<string, unknown>;
}

export interface FormSchemaDocument {
  schemaVersion: 2;
  key: string;
  title: string;
  nodes: FormSchemaNode[];
  form?: Record<string, unknown>;
  actions?: unknown[];
  submit?: Record<string, unknown>;
}

/** 表单字段定义（列/表单/列表/关联全量参数） */
export interface FormFieldDef {
  id?: number;
  form_id?: number;
  field_name: string;
  label: string;
  type: string;
  column_type: string;
  nullable: number;
  default_value: string;
  comment: string;
  unsigned: number;
  index_type: 'none' | 'unique' | 'index';
  placeholder: string;
  options_source?: Record<string, unknown> | null;
  control_props?: Record<string, unknown> | null;
  validate_rules?: Record<string, unknown> | null;
  link_rules?: Record<string, unknown> | null;
  relation_type: 'none' | 'belongs_to' | 'has_many';
  relation_table: string;
  relation_label_field: string;
  relation_value_field: string;
  relation_multiple: number;
  relation_on_delete: 'restrict' | 'cascade' | 'set_null';
  list_show: number;
  list_sort: number;
  list_filter: string;
  list_formatter: string;
  list_width: number;
  form_show: number;
  form_required: number;
  form_group: string;
  form_span: number;
  form_readonly: number;
  sort_order: number;
}

/** 表单定义 */
export type FormPublishStatus =
  | 'draft'
  | 'validating'
  | 'validation_failed'
  | 'ddl_pending'
  | 'publishing'
  | 'ddl_failed'
  | 'metadata_partial'
  | 'dynamic_published'
  | 'partial'
  | 'conflict'
  | 'failed'
  | 'published';

export interface FormPublishConfig {
  module: string;
  apiPrefix: string;
  routePath: string;
  menuEnabled: boolean;
  parentId: number | null;
  parentSourceName: string;
  menuName: string;
  icon: string;
  sortOrder: number;
  softDeletes: boolean;
  batchDelete: boolean;
  import: boolean;
  export: boolean;
  formMode: 'dialog' | 'drawer';
  dataScopeEnabled: boolean;
  dataScopeField: string;
}

export interface FormDefinition {
  id?: number;
  form_key: string;
  name: string;
  table_name: string;
  connection: string;
  source_type: 'created' | 'adopted';
  status: number;
  list_config?: Record<string, unknown> | null;
  form_config?: Record<string, unknown> | null;
  schema_version?: number;
  schema_document?: FormSchemaDocument | null;
  schema_hash?: string | null;
  schema_origin?: FormSchemaOrigin;
  publish_config?: Partial<FormPublishConfig> | null;
  publish_status?: FormPublishStatus;
  published_at?: string | null;
  crud_generation_id?: number | null;
  published_definition_hash?: string | null;
  remark: string;
  sort_order: number;
  updated_at?: string;
  fields: FormFieldDef[];
  fields_count?: number;
}

export interface MigrationPreview {
  mode: 'create' | 'additive' | 'none';
  sql: string;
  file?: string;
  message: string;
  applied?: boolean;
}

export interface FormPublishPreview {
  formSchemaHash: string;
  formDependencyHash: string;
  diagnostics: Array<{ path: string; code: string; message: string }>;
  ddl: MigrationPreview;
  publishStatus: 'ready';
}

export interface FormFullPublishPreview extends Omit<FormPublishPreview, 'publishStatus'> {
  formId: number;
  definitionHash: string;
  generationId?: number | null;
  plan: { files: Array<{ path: string; status: 'create' | 'update' | 'auto-merged' | 'keep-local' | 'conflict' | 'binary-conflict' | 'conflict-no-base'; diff?: string }> };
  sensitive?: { confirmToken: string } | null;
  conflicts: Array<{ path: string; status: 'conflict' | 'binary-conflict' | 'conflict-no-base'; diff?: string; contentKind?: 'text' | 'binary'; baseHash?: string | null; localHash?: string | null; remoteHash?: string | null; baseContent?: string | null; localContent?: string | null; remoteContent?: string | null }>;
  publishStatus: 'ready' | 'conflict';
}

export interface FormPublishResult {
  form: FormDefinition;
  businessModule: Record<string, unknown>;
  ddl: MigrationPreview;
  publishStatus: 'dynamic_published';
  routePath: string;
}

export interface FormFullPublishResult {
  form: FormDefinition;
  ddl: MigrationPreview;
  generation: { generationId: number; resourceApplyStatus?: string; resourceApplyError?: string | null };
  publishStatus: FormPublishStatus;
  routePath: string;
}

export interface FormSchemaCompileResult {
  document: FormSchemaDocument;
  hash: string;
  projection: FormFieldDef[];
}

export interface FormSchemaVersion {
  id: number;
  form_id: number;
  version: number;
  schema_version: number;
  schema_hash: string;
  schema_document: FormSchemaDocument;
  origin: FormSchemaOrigin;
  parent_version_id?: number | null;
  change_summary?: string;
  created_by?: string;
  created_at?: string;
}

export interface FormSchemaDiffChange {
  op: 'add' | 'remove' | 'replace';
  path: string;
  from?: unknown;
  value?: unknown;
}

export interface FormSchemaDiff {
  fromVersion: number;
  toVersion: number;
  fromHash: string;
  toHash: string;
  changes: FormSchemaDiffChange[];
}

export interface FormComponentCatalogItem {
  type: string;
  namespace: string;
  component: string;
  kind?: FormSchemaNodeKind;
  label?: string;
  group?: string;
  defaultColumnType?: string;
  valueType: string;
  defaultValue?: unknown;
  defaultProps: Record<string, unknown>;
  propertySchema: { type?: string; properties?: Record<string, unknown> };
  codec: string;
  allowedAttrs?: string[];
  allowedEvents: string[];
}

export interface FormComponentCatalog {
  schemaVersion: 2;
  components: FormComponentCatalogItem[];
}

const PREFIX = '/form/designer';

export const formDesignerApi = {
  list: (params: { page?: number; pageSize?: number; keyword?: string; status?: number | string }) =>
    http.get<{ list: FormDefinition[]; total: number }>(`${PREFIX}/index`, params),
  detail: (id: number) => http.get<{ form: FormDefinition; fields: FormFieldDef[] }>(`${PREFIX}/detail/${id}`),
  save: (definition: Record<string, unknown>) => http.post<{ form: FormDefinition; fields: FormFieldDef[] }>(`${PREFIX}/save`, { definition }),
  remove: (id: number) => http.post<{ removed: number }>(`${PREFIX}/remove`, { id }),
  status: (id: number, status: number) => http.post<{ status: number }>(`${PREFIX}/status`, { id, status }),
  validate: (definition: Record<string, unknown>) => http.post<{ valid: boolean }>(`${PREFIX}/validate`, { definition }),
  compile: (definition: FormSchemaDocument) => http.post<FormSchemaCompileResult>(`${PREFIX}/compile`, { definition }),
  importSchema: (document: string) => http.post<FormSchemaCompileResult>(`${PREFIX}/import`, { document }),
  exportSchema: (definition: FormSchemaDocument) => http.post<{ document: string }>(`${PREFIX}/export`, { definition }),
  versions: (id: number) => http.get<{ list: FormSchemaVersion[] }>(`${PREFIX}/versions/${id}`),
  version: (id: number, version: number) => http.get<FormSchemaVersion>(`${PREFIX}/version/${id}/${version}`),
  diff: (id: number, fromVersion: number, toVersion: number) => http.get<FormSchemaDiff>(`${PREFIX}/diff/${id}`, { fromVersion, toVersion }),
  rollback: (id: number, version: number, summary = '') => http.post<FormSchemaVersion>(`${PREFIX}/rollback/${id}/${version}`, { summary }),
  catalog: () => http.get<FormComponentCatalog>(`${PREFIX}/component-catalog`),
  infer: (connection: string, table: string) => http.post<{ fields: Partial<FormFieldDef>[] }>(`${PREFIX}/infer`, { connection, table }),
  preview: (definition: Record<string, unknown>) => http.post<MigrationPreview>(`${PREFIX}/preview`, { definition }),
  apply: (definition: Record<string, unknown>) => http.post<MigrationPreview>(`${PREFIX}/apply`, { definition }),
  previewPublish: (definition: Record<string, unknown>) => http.post<FormPublishPreview>(`${PREFIX}/preview-publish`, { definition }),
  publish: (definition: Record<string, unknown>, formDependencyHash = '') => http.post<FormPublishResult>(`${PREFIX}/publish`, { definition: { ...definition, formDependencyHash } }),
  publishStatus: (id: number) => http.get<{ publishStatus: FormPublishStatus; publishedAt?: string | null }>(`${PREFIX}/publish-status/${id}`)
};

const FULL_PUBLISH_PREFIX = '/form/full-publish';

export const formFullPublishApi = {
  preview: (formId: number, schemaHash = '') =>
    http.post<FormFullPublishPreview>(`${FULL_PUBLISH_PREFIX}/preview`, { formId, schemaHash }),
  publish: (formId: number, generationId: number, schemaHash: string, confirmToken: string, operationKey: string = crypto.randomUUID()) =>
    http.post<FormFullPublishResult>(`${FULL_PUBLISH_PREFIX}/publish`, { formId, generationId, schemaHash, confirmToken, operationKey }),
  status: (id: number) => http.get<{ publishStatus: FormPublishStatus; generationId?: number | null }>(`${FULL_PUBLISH_PREFIX}/status/${id}`),
  generation: (id: number) => http.get<Record<string, unknown>>(`${FULL_PUBLISH_PREFIX}/generation/${id}`),
  retryResources: (id: number) => http.post<{ publishStatus: FormPublishStatus; resourceApplyStatus: string }>(`${FULL_PUBLISH_PREFIX}/retry-resources/${id}`)
};
