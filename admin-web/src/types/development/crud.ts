export interface CrudConnection { name: string }
export interface CrudTable { name: string; comment: string }
export interface CrudOption { label: string; value: string | number }
export interface CrudField {
  name: string;
  dbType: string;
  nullable: boolean;
  primary?: boolean;
  managed?: boolean;
  writable?: boolean;
  legacy?: boolean;
  list?: boolean;
  search?: boolean;
  form?: boolean;
  detail?: boolean;
  rules?: string[];
  component?: string;
  options?: CrudOption[];
  relation?: string;
  references?: string;
}
export interface CrudCapabilities {
  list: boolean; search: boolean; form: boolean; detail: boolean;
  create?: boolean; update?: boolean; delete?: boolean; import?: boolean; export?: boolean;
}
export interface CrudDefinition {
  schemaVersion: '1.0'; name: string; table: string; title: string; description?: string;
  paths: Record<string, string>; apiPrefix: string; permissionPrefix: string;
  fields: CrudField[]; relations: Array<Record<string, string>>; templates: Record<string, string>;
  metadata: { connection: string }; capabilities: CrudCapabilities;
  dataScope: { enabled: boolean; field: string };
}
export interface CrudPlanFile { path: string; status: 'create' | 'unchanged' | 'conflict'; hash?: string; previousHash?: string | null }
export interface CrudPreview { generationId?: number; plan: { files: CrudPlanFile[]; [key: string]: unknown }; sensitive?: { confirmToken: string } }
export interface CrudGeneration { generationId: number; write?: { status: string }; manifest?: Record<string, unknown>; plan?: CrudPreview['plan'] }
