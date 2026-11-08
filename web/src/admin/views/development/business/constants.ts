import type { FormInstance, TagProps } from 'element-plus';
import type { BusinessTargetSelection } from '@/api/development/business';

export type BusinessStatusTone = Exclude<TagProps['type'], undefined> | 'primary';

export interface BusinessStatusMeta {
  labelKey: string;
  tone: BusinessStatusTone;
  descriptionKey: string;
}

function statusMeta<T extends string>(group: string, tones: Record<T, BusinessStatusTone>): Record<T, BusinessStatusMeta> {
  return Object.fromEntries(Object.entries<BusinessStatusTone>(tones).map(([status, tone]) => [status, {
    labelKey: `business.status.${group}.${status}.label`,
    tone,
    descriptionKey: `business.status.${group}.${status}.description`
  }])) as Record<T, BusinessStatusMeta>;
}

export const LIFECYCLE_STATUS_META = statusMeta('lifecycle', {
  draft: 'info',
  published: 'success',
  dynamic_published: 'primary',
  disabled: 'warning'
});

export const GENERATION_STATUS_META = statusMeta('generation', {
  idle: 'info',
  planned: 'info',
  running: 'primary',
  completed: 'success',
  failed: 'danger',
  conflict: 'warning',
  superseded: 'info'
});

export const RECOVERY_STATUS_META = statusMeta('recovery', {
  none: 'info',
  recovering: 'primary',
  rolled_back: 'warning',
  recovered_completed: 'success',
  recovery_required: 'danger'
});

export const FILE_STATUS_META = statusMeta('file', {
  create: 'success',
  update: 'primary',
  'auto-merged': 'success',
  delete: 'danger',
  'keep-local': 'info',
  conflict: 'warning',
  'binary-conflict': 'danger',
  'conflict-no-base': 'danger'
});

export type LifecycleStatus = keyof typeof LIFECYCLE_STATUS_META;
export type GenerationStatus = keyof typeof GENERATION_STATUS_META;
export type RecoveryStatus = keyof typeof RECOVERY_STATUS_META;
export type GenerationFileStatus = keyof typeof FILE_STATUS_META;

export function normalizeGenerationStatus(status?: string | null): string {
  return status === 'generated' ? 'completed' : (status || 'idle');
}

/** 业务标识规范化：去空白、转小写，非法字符替换为下划线（与创建向导失焦时一致）。 */
export function normalizeBusinessCode(value: string): string {
  return value.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
}

/**
 * 采纳已有数据表时的业务默认值：标识 = 去掉 fun_ 前缀后的表名；名称 = 表描述，缺省时同标识。
 * 创建向导（visual.vue）与装载数据表弹窗共用。
 */
export function deriveAdoptedBusiness(table: string, comment = ''): { code: string; name: string } {
  const code = normalizeBusinessCode(table.replace(/^fun_/, ''));
  return { code, name: comment.trim() || code };
}

/** 业务标识：小写字母开头，只含小写字母、数字、下划线，最长 61。 */
export const BUSINESS_CODE_PATTERN = /^[a-z][a-z0-9_]{0,60}$/;
/** 数据库连接 / 数据表标识。 */
export const IDENTIFIER_PATTERN = /^[a-z_][a-z0-9_]*$/;

/**
 * 按业务名称建议业务标识：只能从名称中的英文、数字推导（没有拼音库），
 * 中文或没有字母的名称返回空串，由用户填写。
 */
export function suggestBusinessCode(name: string): string {
  return name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^[^a-z]+/, '').replace(/_+$/, '').slice(0, 61);
}

/**
 * 新建表的默认表名：插件目标补插件前缀（服务端要求 {连接前缀}{插件}_ 开头），核心目标为业务标识。
 * 连接配置的表前缀（如 fun_）由服务端按连接设置补齐，前端没有读取前缀的接口。
 */
export function defaultCreatedTable(code: string, pluginCode = ''): string {
  return code ? `${pluginCode ? `${pluginCode}_` : ''}${code}` : '';
}

export interface VisualCreateForm {
  name: string;
  code: string;
  table: string;
  connection: string;
  remark: string;
  treeEnabled: boolean;
  treeParentField: string;
  treeSelectionMode: 'single' | 'multiple';
}

/** createVisual（新建表）请求体：创建向导与新建表弹窗共用，新表不携带树形绑定（创建后在设计器配置）。 */
export function createVisualPayload(form: VisualCreateForm, target: BusinessTargetSelection): Record<string, unknown> {
  return { ...form, listConfig: {}, target };
}

/** 创建业务模块后进入表单设计器的路由。 */
export function designerLocation(module: { id: number; form_id?: number | null }) {
  return { path: '/admin/development/business/designer', query: { id: String(module.form_id), moduleId: String(module.id) } };
}

function errorRecord(reason: unknown): Record<string, unknown> {
  return reason && typeof reason === 'object' ? reason as Record<string, unknown> : {};
}

function fieldErrorMessage(value: unknown): string | undefined {
  if (typeof value === 'string') return value;
  if (Array.isArray(value)) return value.find((item): item is string => typeof item === 'string');
  if (value && typeof value === 'object' && 'message' in value && typeof value.message === 'string') return value.message;
  return undefined;
}

export interface CreationError {
  /** 字段 → 错误文案（来自 422 details.fieldErrors，支持对象或 [{path|field, message}] 数组）。 */
  fieldErrors: Record<string, string>;
  status: number | undefined;
  message: string | undefined;
}

/** 解析创建业务模块接口的失败响应（HTTP Error 或业务错误体）。 */
export function parseCreationError(reason: unknown): CreationError {
  const response = errorRecord(reason);
  const data = errorRecord(response.data);
  const error = errorRecord(data.error);
  const details = errorRecord(response.details ?? data.details ?? error.details);
  const source = details.fieldErrors ?? data.fieldErrors ?? response.fieldErrors;
  const fieldErrors = Array.isArray(source)
    ? Object.fromEntries(source.flatMap((item) => {
      const entry = errorRecord(item);
      const field = typeof entry.path === 'string' ? entry.path : typeof entry.field === 'string' ? entry.field : '';
      const message = fieldErrorMessage(entry.message);
      return field && message ? [[field, message]] : [];
    }))
    : Object.fromEntries(Object.entries(errorRecord(source)).flatMap(([field, value]) => {
      const message = fieldErrorMessage(value);
      return message ? [[field, message]] : [];
    }));
  const status = [response.status, response.code, data.status, data.code].find((value): value is number => typeof value === 'number');
  return { fieldErrors, status, message: typeof response.msg === 'string' ? response.msg : undefined };
}

/** 把服务端字段错误写到 Element Plus 表单项上，并聚焦第一个出错字段。 */
export function applyFieldErrors(form: FormInstance | undefined, errors: Record<string, string>) {
  form?.clearValidate();
  for (const [field, message] of Object.entries(errors)) {
    const context = form?.fields.find((item) => item.prop === field);
    if (!context) continue;
    context.validateState = 'error';
    context.validateMessage = message;
  }
  const first = Object.keys(errors)[0];
  if (!first) return;
  form?.scrollToField(first);
  form?.fields.find((item) => item.prop === first)?.$el?.querySelector<HTMLElement>('input, textarea, select, [tabindex]')?.focus();
}
