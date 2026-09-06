import { reactive } from 'vue';
import type { CrudCapabilities, CrudDefinition, CrudField, CrudGeneration, CrudOption, CrudOptionsSource, CrudPreview, CrudRelation } from '@/types/development/crud';

export const CRUD_STEPS = ['数据与模块', '字段设计', '功能与预览', '确认与结果'].map((title, index) => ({ index, title }));

function stableValue(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(stableValue);
  if (value && typeof value === 'object') {
    return Object.keys(value as Record<string, unknown>).sort().reduce<Record<string, unknown>>((result, key) => {
      result[key] = stableValue((value as Record<string, unknown>)[key]);
      return result;
    }, {});
  }
  return value;
}

export function snapshotCrudDefinition(definition: CrudDefinition): { definition: CrudDefinition; serialized: string } {
  const serialized = JSON.stringify(stableValue(definition));
  return { definition: JSON.parse(serialized) as CrudDefinition, serialized };
}

export function createLatestRequestGate() {
  let sequence = 0;
  let controller: AbortController | null = null;
  return {
    begin() {
      controller?.abort();
      controller = new AbortController();
      return { sequence: ++sequence, signal: controller.signal };
    },
    isLatest(value: number) { return value === sequence; },
    accept(value: number, apply: () => void): boolean {
      if (value !== sequence) return false;
      apply();
      return true;
    },
    invalidate() {
      sequence += 1;
      controller?.abort();
      controller = null;
    }
  };
}

type FieldDataConfiguration =
  | { kind: 'none' }
  | { kind: 'static'; options: CrudOption[] }
  | { kind: 'dictionary' | 'endpoint'; source: CrudOptionsSource }
  | { kind: 'model'; source: CrudOptionsSource; relation: Omit<CrudRelation, 'field' | 'optionsSource'> };

function configurationsEqual(left: unknown, right: unknown): boolean {
  return JSON.stringify(stableValue(left)) === JSON.stringify(stableValue(right));
}

export function applyFieldDataConfiguration(definition: CrudDefinition, fieldName: string, configuration: FieldDataConfiguration): void {
  const field = definition.fields.find((item) => item.name === fieldName);
  if (!field) throw new Error(`字段不存在：${fieldName}`);

  const source = configuration.kind === 'dictionary' || configuration.kind === 'endpoint' || configuration.kind === 'model'
    ? configuration.source
    : null;
  if (source) {
    const existingSource = definition.optionsSource.find((item) => item.name === source.name);
    const usedByOtherField = definition.fields.some((item) => item !== field && item.optionsSource === source.name)
      || definition.relations.some((relation) => relation.field !== fieldName && relation.optionsSource === source.name);
    if (existingSource && usedByOtherField && !configurationsEqual(existingSource, source)) {
      throw new Error(`数据源名称“${source.name}”已被其他字段使用且配置不同，请使用新名称`);
    }
  }

  if (configuration.kind === 'model') {
    const conflictingRelation = definition.relations.find((relation) => relation.name === configuration.relation.name && relation.field !== fieldName);
    if (conflictingRelation) {
      throw new Error(`关系名称“${configuration.relation.name}”已绑定字段“${conflictingRelation.field}”，请使用新名称`);
    }
  }

  delete field.options;
  delete field.optionsSource;
  delete field.relation;
  delete field.references;

  if (configuration.kind === 'static') field.options = configuration.options;
  if (source) {
    field.optionsSource = source.name;
    const sourceIndex = definition.optionsSource.findIndex((item) => item.name === source.name);
    if (sourceIndex === -1) definition.optionsSource.push(source);
    else if (!configurationsEqual(definition.optionsSource[sourceIndex], source)) definition.optionsSource[sourceIndex] = source;
  }
  if (configuration.kind === 'model') {
    field.relation = configuration.relation.name;
    field.references = `${configuration.relation.target}.${configuration.relation.targetField}`;
    definition.relations = definition.relations.filter((relation) => relation.field !== fieldName);
    definition.relations.push({ ...configuration.relation, field: fieldName, optionsSource: configuration.source.name });
  } else {
    definition.relations = definition.relations.filter((relation) => relation.field !== fieldName);
  }

  const relationNames = new Set(definition.fields.map((item) => item.relation).filter(Boolean));
  definition.relations = definition.relations.filter((relation) => relationNames.has(relation.name));
  const sourceNames = new Set([
    ...definition.fields.map((item) => item.optionsSource),
    ...definition.relations.map((relation) => relation.optionsSource)
  ].filter(Boolean));
  definition.optionsSource = definition.optionsSource.filter((item) => sourceNames.has(item.name));
}

export interface WorkbenchValidationContext {
  fields?: CrudField[];
  capabilities?: Partial<CrudCapabilities>;
  dataScope?: { enabled: boolean; field: string; resolver?: 'adminDepartmentIds' };
}

export function validateWorkbenchStep(step: number, context: WorkbenchValidationContext): string {
  if (step === 1) {
    if (context.fields?.some((field) => field.legacy)) return '发现 legacy 字段，迁移完成前禁止生成';
    const incompleteRelation = context.fields?.find((field) => Boolean(field.relation?.trim()) !== Boolean(field.references?.trim()));
    if (incompleteRelation) return `字段 ${incompleteRelation.name} 的 relation 与 references 必须同时配置`;
  }
  if (step === 2 && context.dataScope?.enabled) {
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
    schemaVersion: '1.0', connection, module: 'generated', entity: name, table, title: table,
    apiPrefix: `/generated/${name}`, routePath: `/generated/${name}`, primaryKey: fields.find((field) => field.primary)?.name || 'id',
    timestamps: fields.some((field) => field.name === 'created_at') && fields.some((field) => field.name === 'updated_at'),
    softDeletes: true,
    generationTargets: { migration: `database/generated/${name}.sql`, model: `app/console/model/${className}.php`, validate: `app/console/validate/${className}Validate.php`, service: `app/console/service/${className}Service.php`, controller: `app/console/controller/generated/${className}Controller.php`, permissionMigration: `database/generated/${name}_permissions.sql`, api: `admin-web/src/api/generated/${name}.ts`, view: `admin-web/src/views/generated/${name}/index.vue`, form: `admin-web/src/views/generated/${name}/components/${className}Form.vue`, detail: `admin-web/src/views/generated/${name}/components/${className}Detail.vue`, phpTest: `tests/generated/${className}GeneratedTest.php`, vitestTest: `admin-web/tests/generated/${name}.spec.ts` },
    permissionPrefix: `generated:${name}`, fields, relations: [], optionsSource: [],
    templates: { migration: 'database/migration.sql.tpl', model: 'console/model.php.tpl', validate: 'console/validate.php.tpl', service: 'console/service.php.tpl', controller: 'console/controller.php.tpl', permissionMigration: 'database/permissions.sql.tpl', api: 'frontend/api.ts.tpl', view: 'frontend/index.vue.tpl', form: 'frontend/form.vue.tpl', detail: 'frontend/detail.vue.tpl', phpTest: 'tests/php-test.php.tpl', vitestTest: 'tests/vitest-test.ts.tpl' },
    capabilities: { list: true, search: true, form: true, detail: true, create: true, update: true, delete: true, import: true, export: true },
    features: { batchDelete: true, status: hasWritableStatus, detail: true, import: true, export: true, upload: true, dictionary: true, referenceProtection: true, formMode: 'dialog', importLimit: 10000, exportLimit: 10000 },
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
    previewInvalidated: false,
    previewSnapshot: '',
    setPreview(value: CrudPreview, snapshot = '') {
      this.preview = value;
      this.previewSnapshot = snapshot;
      this.confirmToken = value.sensitive?.confirmToken || '';
      this.allowOverwrite.splice(0);
      this.previewInvalidated = false;
    },
    conflicts(): string[] {
      return this.preview?.plan.files.filter((file) => file.status === 'conflict').map((file) => file.path) || [];
    },
    canGenerate(canOverwrite: boolean): boolean {
      const conflicts = this.conflicts();
      return Boolean(this.confirmToken) && (conflicts.length === 0 || (canOverwrite && conflicts.every((path) => this.allowOverwrite.includes(path))));
    },
    clearSensitive() {
      this.confirmToken = '';
      this.allowOverwrite.splice(0);
    },
    invalidatePreview() {
      const hadPreview = this.preview !== null || this.confirmToken !== '';
      this.preview = null;
      this.previewSnapshot = '';
      this.clearSensitive();
      if (hadPreview) this.previewInvalidated = true;
    },
    persistable() { return { step: Math.min(this.step, 2), definition: this.definition }; },
    fail(message: string) { this.error = message; this.clearSensitive(); }
  });
}
