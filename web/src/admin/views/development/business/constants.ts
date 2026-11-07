import type { TagProps } from 'element-plus';

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
