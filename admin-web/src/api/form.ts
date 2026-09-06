import http from '@/utils/http';

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
  remark: string;
  sort_order: number;
  updated_at?: string;
  fields: FormFieldDef[];
  fields_count?: number;
}

export interface MigrationPreview {
  mode: 'create' | 'additive' | 'none';
  sql: string;
  file: string;
  message: string;
  applied?: boolean;
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
  infer: (connection: string, table: string) => http.post<{ fields: Partial<FormFieldDef>[] }>(`${PREFIX}/infer`, { connection, table }),
  preview: (definition: Record<string, unknown>) => http.post<MigrationPreview>(`${PREFIX}/preview`, { definition }),
  apply: (definition: Record<string, unknown>) => http.post<MigrationPreview>(`${PREFIX}/apply`, { definition })
};
