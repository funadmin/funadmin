export interface CrudConnection { name: string }
export interface CrudTable { name: string; comment: string }
export interface CrudOption { label: string; value: string | number }
export type CrudSearchOperator =
  | 'like' | 'eq' | 'ne' | 'not_like' | 'starts_with' | 'ends_with'
  | 'gt' | 'gte' | 'lt' | 'lte' | 'in' | 'not_in' | 'range' | 'date' | 'is_null' | 'not_null';
export type CrudListFormatter = '' | 'tag' | 'image' | 'images' | 'date' | 'datetime' | 'time' | 'money' | 'number' | 'percent' | 'switch' | 'boolean' | 'link' | 'email' | 'phone' | 'json';
export interface CrudField {
  name: string;
  label?: string;
  dbType: string;
  nullable: boolean;
  primary?: boolean;
  comment?: string;
  managed?: boolean;
  writable?: boolean;
  legacy?: boolean;
  list?: boolean;
  search?: boolean;
  searchOperator?: CrudSearchOperator;
  sortable?: boolean;
  form?: boolean;
  detail?: boolean;
  rules?: string[];
  component?: string;
  valueType?: string;
  cast?: string;
  options?: CrudOption[];
  optionsSource?: string;
  relation?: string;
  references?: string;
  required?: boolean;
  minLength?: number;
  maxLength?: number;
  min?: number;
  max?: number;
  enum?: Array<string | number>;
  format?: 'email' | 'url' | 'date' | 'datetime' | 'uuid' | 'ip';
  unique?: boolean;
  dictionary?: boolean;
  upload?: boolean;
  indexMissing?: boolean;
  listFormatter?: CrudListFormatter;
  listWidth?: number;
  placeholder?: string;
  controlProps?: Record<string, unknown>;
}
export interface CrudCapabilities {
  list: boolean; search: boolean; form: boolean; detail: boolean;
  create?: boolean; update?: boolean; delete?: boolean; import?: boolean; export?: boolean;
}
export interface CrudRelation { name: string; type: 'belongsTo' | 'hasOne' | 'hasMany' | 'belongsToMany'; field: string; target: string; targetField: string; pivotTable?: string; pivotLocalKey?: string; pivotTargetKey?: string; optionsSource?: string; with?: boolean }
export interface CrudOptionsSource { name: string; type: 'relation' | 'dictionary' | 'endpoint'; endpoint?: string; dictionary?: string; labelField: string; valueField: string }
export interface CrudFeatures { batchDelete: boolean; status: boolean; detail: boolean; import: boolean; export: boolean; upload: boolean; dictionary: boolean; referenceProtection: boolean; formMode: 'dialog' | 'drawer'; importLimit: number; exportLimit: number }
export interface CrudMenuConfig { enabled: boolean; parentId: number | null; parentSourceName: string; name: string; icon: string; sortOrder: number; hidden: boolean; keepAlive: boolean; affix: boolean; target: '_self' | '_blank' }
export interface CrudPermissionAction { action: string; codeSuffix: string; label: string }
export interface CrudPermissionConfig { enabled: boolean; groupName: string; actions: CrudPermissionAction[] }
export interface CrudParentMenu { id: number; sourceName: string; name: string; path: string; children?: CrudParentMenu[] }
export interface CrudResourceOptions { parentMenus: CrudParentMenu[]; icons: string[] }
export interface CrudArtifactMap {
  migration: string; model: string; validate: string; service: string; controller: string;
  permissionMigration: string; api: string; view: string; form: string; detail: string;
  phpTest: string; vitestTest: string;
}
export type CrudTarget =
  | { type: 'core' }
  | { type: 'plugin'; plugin: string; scope: 'application' | 'console' | 'both' };
interface CrudDefinitionBase {
  schemaVersion: '1.0'; connection: string; module: string; entity: string; table: string;
  title: string; description?: string; apiPrefix: string; routePath: string; primaryKey: string;
  timestamps: boolean; softDeletes: boolean;
  permissionPrefix: string; fields: CrudField[]; relations: CrudRelation[];
  optionsSource: CrudOptionsSource[]; templates: CrudArtifactMap;
  capabilities: CrudCapabilities; features: CrudFeatures;
  dataScope: { enabled: boolean; field: string; resolver?: 'adminDepartmentIds' };
  menu: CrudMenuConfig; permission: CrudPermissionConfig;
  layoutSchema?: Array<Record<string, unknown>>;
}
export type CrudDefinition = CrudDefinitionBase & (
  | { target: { type: 'core' }; generationTargets: CrudArtifactMap }
  | { target: { type: 'plugin'; plugin: string; scope: 'application' | 'console' | 'both' }; generationTargets?: never }
);
export type CrudPlanStatus = 'create' | 'unchanged' | 'conflict' | 'blocked';
export interface CrudPlanFile {
  path: string;
  status: CrudPlanStatus;
  hash?: string;
  previousHash?: string | null;
  content?: string;
  diff?: string;
}
export interface CrudPreview {
  generationId?: number;
  plan: { files: CrudPlanFile[]; definitionHash?: string; planDigest?: string; [key: string]: unknown };
  sensitive?: { confirmToken: string };
}
export interface CrudGenerationManifest {
  createdFiles?: string[];
  overwrittenFiles?: string[];
  files?: CrudPlanFile[];
  validationResult?: { valid?: boolean; [key: string]: unknown };
  status?: string;
  error?: { message?: string; [key: string]: unknown } | null;
  resourceApplyStatus?: 'not_requested' | 'pending' | 'applied' | 'failed';
  resourceApplyError?: string | null;
  resourceChecksum?: string | null;
  [key: string]: unknown;
}
export interface CrudGeneration {
  generationId: number;
  write?: { status: string; written?: number; rollback?: string[] };
  manifest?: CrudGenerationManifest;
  plan?: CrudPreview['plan'];
  resourceApplyStatus?: 'not_requested' | 'pending' | 'applied' | 'failed';
  resourceApplyError?: string | null;
  resourceChecksum?: string | null;
}
