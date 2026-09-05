import { reactive } from 'vue';
import type { CrudCapabilities, CrudDefinition, CrudField, CrudGeneration, CrudPreview } from '@/types/development/crud';

export const CRUD_STEPS = ['数据源', '模块', '字段', '能力', '预览', '确认', '结果'].map((title, index) => ({ index, title }));

export interface WorkbenchValidationContext {
  fields?: CrudField[];
  capabilities?: Partial<CrudCapabilities>;
  dataScope?: { enabled: boolean; field: string };
}

export function validateWorkbenchStep(step: number, context: WorkbenchValidationContext): string {
  if (step === 2) {
    if (context.fields?.some((field) => field.legacy)) return '发现 legacy 字段，迁移完成前禁止生成';
    if (context.fields?.some((field) => field.relation && !field.references)) return '关系字段必须填写完整 references';
  }
  if (step === 3 && context.dataScope?.enabled && !context.dataScope.field) return '启用数据范围后必须选择数据范围字段';
  return '';
}

export function createCrudWorkbench() {
  return reactive({
    steps: CRUD_STEPS,
    step: 0,
    definition: null as CrudDefinition | null,
    preview: null as CrudPreview | null,
    result: null as CrudGeneration | null,
    confirmToken: '',
    allowOverwrite: [] as string[],
    error: '',
    setPreview(value: CrudPreview) {
      this.preview = value;
      this.confirmToken = value.sensitive?.confirmToken || '';
      this.allowOverwrite.splice(0);
    },
    conflicts(): string[] {
      return this.preview?.plan.files.filter((file) => file.status === 'conflict').map((file) => file.path) || [];
    },
    canGenerate(canOverwrite: boolean): boolean {
      const conflicts = this.conflicts();
      return Boolean(this.confirmToken) && (conflicts.length === 0 || (canOverwrite && conflicts.every((path) => this.allowOverwrite.includes(path))));
    },
    clearSensitive() { this.confirmToken = ''; },
    persistable() { return { step: this.step, definition: this.definition }; },
    fail(message: string) { this.error = message; this.clearSensitive(); }
  });
}
