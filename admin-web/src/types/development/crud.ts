export interface CrudConnection { name: string }
export interface CrudTable { name: string; comment: string }
export interface CrudOption { label: string; value: string | number }
export type CrudSearchOperator = 'like' | 'eq' | 'in' | 'range' | 'gte' | 'lte';
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
}
export interface CrudCapabilities {
  list: boolean; search: boolean; form: boolean; detail: boolean;
  create?: boolean; update?: boolean; delete?: boolean; import?: boolean; export?: boolean;
}
export interface CrudRelation { name: string; type: 'belongsTo' | 'hasOne' | 'hasMany' | 'belongsToMany'; field: string; target: string; targetField: string; pivotTable?: string; optionsSource?: string; with?: boolean }
export interface CrudOptionsSource { name: string; type: 'relation' | 'dictionary' | 'endpoint'; endpoint?: string; dictionary?: string; labelField: string; valueField: string }
export interface CrudFeatures { batchDelete: boolean; status: boolean; detail: boolean; import: boolean; export: boolean; upload: boolean; dictionary: boolean; referenceProtection: boolean; formMode: 'dialog' | 'drawer'; importLimit: number; exportLimit: number }
export interface CrudArtifactMap {
  migration: string; model: string; validate: string; service: string; controller: string;
  permissionMigration: string; api: string; view: string; form: string; detail: string;
  phpTest: string; vitestTest: string;
}
export interface CrudDefinition {
  schemaVersion: '1.0'; connection: string; module: string; entity: string; table: string;
  title: string; description?: string; apiPrefix: string; routePath: string; primaryKey: string;
  timestamps: boolean; softDeletes: boolean; generationTargets: CrudArtifactMap;
  permissionPrefix: string; fields: CrudField[]; relations: CrudRelation[];
  optionsSource: CrudOptionsSource[]; templates: CrudArtifactMap;
  capabilities: CrudCapabilities; features: CrudFeatures;
  dataScope: { enabled: boolean; field: string; resolver?: 'adminDepartmentIds' };
}
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
  [key: string]: unknown;
}
export interface CrudGeneration {
  generationId: number;
  write?: { status: string; written?: number; rollback?: string[] };
  manifest?: CrudGenerationManifest;
  plan?: CrudPreview['plan'];
}
