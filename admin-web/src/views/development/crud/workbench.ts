import { reactive } from 'vue';
import type { CrudCapabilities, CrudDefinition, CrudField, CrudGeneration, CrudPreview } from '@/types/development/crud';

export const CRUD_STEPS = ['数据源', '模块', '字段', '能力', '预览', '确认', '结果'].map((title, index) => ({ index, title }));

export interface WorkbenchValidationContext {
  fields?: CrudField[];
  capabilities?: Partial<CrudCapabilities>;
  dataScope?: { enabled: boolean; field: string; resolver?: 'adminDepartmentIds' };
}

export function validateWorkbenchStep(step: number, context: WorkbenchValidationContext): string {
  if (step === 2) {
    if (context.fields?.some((field) => field.legacy)) return '发现 legacy 字段，迁移完成前禁止生成';
    const incompleteRelation = context.fields?.find((field) => Boolean(field.relation?.trim()) !== Boolean(field.references?.trim()));
    if (incompleteRelation) return `字段 ${incompleteRelation.name} 的 relation 与 references 必须同时配置`;
  }
  if (step === 3 && context.dataScope?.enabled) {
    if (!context.dataScope.field.trim()) return '启用 dataScope 时必须选择范围字段';
    if (context.dataScope.resolver !== 'adminDepartmentIds') return '启用 dataScope 时必须选择范围解析器';
  }
  return '';
}

export function createCrudDefinition(connection: string, table: string, fields: CrudField[]): CrudDefinition {
  const name = table.replace(/^fun_/, '').replace(/_/g, '-');
  const className = name.split('-').map((part) => part[0]?.toUpperCase() + part.slice(1)).join('');
  const hasWritableStatus = fields.some((field) => field.name === 'status' && field.writable !== false);
  return {
    schemaVersion: '1.0', name, table, title: table,
    paths: { migration: `database/generated/${name}.sql`, model: `app/console/model/${className}.php`, validate: `app/console/validate/${className}Validate.php`, service: `app/console/service/${className}Service.php`, controller: `app/console/controller/generated/${className}Controller.php`, permissionMigration: `database/generated/${name}_permissions.sql`, api: `admin-web/src/api/generated/${name}.ts`, view: `admin-web/src/views/generated/${name}/index.vue`, form: `admin-web/src/views/generated/${name}/components/${className}Form.vue`, detail: `admin-web/src/views/generated/${name}/components/${className}Detail.vue` },
    apiPrefix: `/generated/${name}`, permissionPrefix: `generated:${name}`, fields, relations: [], optionsSource: [],
    templates: { migration: 'database/migration.sql.tpl', model: 'console/model.php.tpl', validate: 'console/validate.php.tpl', service: 'console/service.php.tpl', controller: 'console/controller.php.tpl', permissionMigration: 'database/permissions.sql.tpl', api: 'frontend/api.ts.tpl', view: 'frontend/index.vue.tpl', form: 'frontend/form.vue.tpl', detail: 'frontend/detail.vue.tpl' },
    metadata: { connection }, capabilities: { list: true, search: true, form: true, detail: true, create: true, update: true, delete: true, import: true, export: true },
    features: { softDelete: true, batchDelete: true, status: hasWritableStatus, detail: true, import: true, export: true, upload: true, dictionary: true, referenceProtection: true, formMode: 'dialog', importLimit: 10000, exportLimit: 10000 },
    dataScope: { enabled: false, field: '' }
  };
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
