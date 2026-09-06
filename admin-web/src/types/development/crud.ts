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
}
export interface CrudCapabilities {
  list: boolean; search: boolean; form: boolean; detail: boolean;
  create?: boolean; update?: boolean; delete?: boolean; import?: boolean; export?: boolean;
}
export interface CrudRelation { name: string; type: 'belongsTo' | 'hasOne' | 'hasMany' | 'belongsToMany'; field: string; target: string; targetField: string; pivotTable?: string; optionsSource?: string; with?: boolean }
export interface CrudOptionsSource { name: string; type: 'relation' | 'dictionary' | 'endpoint'; endpoint?: string; dictionary?: string; labelField: string; valueField: string }
export interface CrudFeatures { softDelete: boolean; batchDelete: boolean; status: boolean; detail: boolean; import: boolean; export: boolean; upload: boolean; dictionary: boolean; referenceProtection: boolean; formMode: 'dialog' | 'drawer'; importLimit: number; exportLimit: number }
export interface CrudArtifactMap {
  migration: string; model: string; validate: string; service: string; controller: string;
  permissionMigration: string; phpTest: string; api: string; view: string; form: string; detail: string; vueTest: string;
}
export interface CrudDefinition {
  schemaVersion: '1.0'; name: string; table: string; title: string; description?: string;
  paths: CrudArtifactMap; apiPrefix: string; permissionPrefix: string;
  fields: CrudField[]; relations: CrudRelation[]; optionsSource: CrudOptionsSource[]; templates: CrudArtifactMap;
  metadata: { connection: string }; capabilities: CrudCapabilities; features: CrudFeatures;
  dataScope: { enabled: boolean; field: string; resolver?: 'adminDepartmentIds' };
}
export interface CrudPlanFile { path: string; status: 'create' | 'unchanged' | 'conflict'; hash?: string; previousHash?: string | null }
export interface CrudPreview { generationId?: number; plan: { files: CrudPlanFile[]; [key: string]: unknown }; sensitive?: { confirmToken: string } }
export interface CrudGeneration { generationId: number; write?: { status: string }; manifest?: Record<string, unknown>; plan?: CrudPreview['plan'] }
