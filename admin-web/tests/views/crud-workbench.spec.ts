import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import {
  createCrudDefinition,
  createCrudWorkbench,
  applyFieldDataConfiguration,
  createLatestRequestGate,
  snapshotCrudDefinition,
  validateWorkbenchStep
} from '@/views/development/crud/workbench';
import type { CrudDefinition, CrudField, CrudPreview } from '@/types/development/crud';

const read = (path: string) => readFileSync(resolve(process.cwd(), path), 'utf8');

describe('CRUD Workbench', () => {
  it('严格执行四步流程且 token 与覆盖授权不进入持久状态', () => {
    const workbench = createCrudWorkbench();
    expect(workbench.steps.map((step) => step.title)).toEqual(['数据与模块', '字段设计', '功能与预览', '确认与结果']);
    workbench.confirmToken = 'secret';
    workbench.allowOverwrite.push('app/Demo.php');
    expect(JSON.stringify(workbench.persistable())).not.toContain('secret');
    expect(JSON.stringify(workbench.persistable())).not.toContain('app/Demo.php');
    workbench.clearSensitive();
    expect(workbench.confirmToken).toBe('');
    expect(workbench.allowOverwrite).toEqual([]);
  });

  it('legacy 字段阻止进入生成确认', () => {
    const fields = [{ name: 'create_time', legacy: true }] as CrudField[];
    expect(validateWorkbenchStep(1, { fields })).toContain('legacy');
  });

  it('输出标准顶层字段及 PHP/Vitest 测试制品', () => {
    const definition = createCrudDefinition('mysql', 'fun_admin_log', [
      { name: 'id', dbType: 'bigint unsigned', nullable: false, primary: true },
      { name: 'created_at', dbType: 'datetime', nullable: false },
      { name: 'updated_at', dbType: 'datetime', nullable: false },
      { name: 'deleted_at', dbType: 'datetime', nullable: true }
    ] as CrudField[]);
    expect(definition).toMatchObject({
      connection: 'mysql', module: 'generated', entity: 'admin-log', apiPrefix: '/generated/admin-log', routePath: '/generated/admin-log',
      primaryKey: 'id', timestamps: true, softDeletes: true
    });
    expect(definition.generationTargets).toHaveProperty('phpTest');
    expect(definition.generationTargets).toHaveProperty('vitestTest');
    expect(definition.templates).toHaveProperty('phpTest');
    expect(definition.templates).toHaveProperty('vitestTest');
    expect(definition).not.toHaveProperty('name');
    expect(definition).not.toHaveProperty('paths');
    expect(definition).not.toHaveProperty('metadata');
  });

  it('仅在 schema 存在可写 status 时默认启用状态能力', () => {
    const baseFields = [
      { name: 'id', dbType: 'bigint unsigned', nullable: false, primary: true },
      { name: 'name', dbType: 'varchar(80)', nullable: false, writable: true }
    ] as CrudField[];
    expect(createCrudDefinition('mysql', 'fun_without_status', baseFields).features.status).toBe(false);
    expect(createCrudDefinition('mysql', 'fun_readonly_status', [
      ...baseFields,
      { name: 'status', dbType: 'tinyint(1)', nullable: false, writable: false }
    ]).features.status).toBe(false);
    expect(createCrudDefinition('mysql', 'fun_with_status', [
      ...baseFields,
      { name: 'status', dbType: 'tinyint(1)', nullable: false, writable: true }
    ]).features.status).toBe(true);
  });

  it('M5 支持关系与数据范围并严格校验必填配置', () => {
    const validRelation = [{ name: 'user_id', relation: 'user', references: 'User.id' }] as CrudField[];
    expect(validateWorkbenchStep(1, { fields: validRelation })).toBe('');
    expect(validateWorkbenchStep(1, { fields: [{ name: 'user_id', relation: 'user' }] as CrudField[] }))
      .toContain('relation 与 references 必须同时配置');
    expect(validateWorkbenchStep(2, { capabilities: { list: true }, dataScope: { enabled: true, field: '', resolver: 'adminDepartmentIds' } }))
      .toBe('启用 dataScope 时必须选择范围字段');
    expect(validateWorkbenchStep(2, { capabilities: { list: true }, dataScope: { enabled: true, field: 'department_id', resolver: 'adminDepartmentIds' } }))
      .toBe('');
  });

  it('冲突必须逐文件勾选且受 overwrite 权限控制', () => {
    const preview = { plan: { files: [{ path: 'app/Demo.php', status: 'conflict' }] }, sensitive: { confirmToken: 'memory-only' } } as CrudPreview;
    const workbench = createCrudWorkbench();
    workbench.setPreview(preview);
    expect(workbench.canGenerate(false)).toBe(false);
    workbench.allowOverwrite.push('app/Demo.php');
    expect(workbench.canGenerate(false)).toBe(false);
    expect(workbench.canGenerate(true)).toBe(true);
  });

  it('定义变化会使预览失效并清除 token 与覆盖授权', () => {
    const workbench = createCrudWorkbench();
    workbench.setPreview({
      plan: { files: [{ path: 'app/Demo.php', status: 'conflict' }] },
      sensitive: { confirmToken: 'secret' }
    } as CrudPreview);
    workbench.allowOverwrite.push('app/Demo.php');

    workbench.invalidatePreview();

    expect(workbench.preview).toBeNull();
    expect(workbench.confirmToken).toBe('');
    expect(workbench.allowOverwrite).toEqual([]);
    expect(workbench.previewInvalidated).toBe(true);
  });

  it('合并页面固定四步、深度监听定义变化且结果留在第四步', () => {
    const page = read('src/views/development/crud/index.vue');
    expect(page).toContain('<BasicsStep v-if="workbench.step === 0"');
    expect(page).toContain('<CapabilitiesPreviewStep v-else-if="workbench.step === 2');
    expect(page).toContain('<ConfirmResultStep v-else-if="workbench.step === 3');
    expect(page).toContain("{ deep: true, flush: 'sync' }");
    expect(page).toContain('workbench.invalidatePreview()');
    expect(page).toContain('workbench.step = 3');
    expect(page).toContain('返回修改');
    expect(page).toContain('重新开始');
  });

  it('旧 tables 与 preview 响应后返回时不能覆盖最新请求', () => {
    const tablesGate = createLatestRequestGate();
    const firstTables = tablesGate.begin();
    const secondTables = tablesGate.begin();
    expect(firstTables.signal.aborted).toBe(true);
    let tables = 'latest';
    expect(tablesGate.accept(firstTables.sequence, () => { tables = 'stale'; })).toBe(false);
    expect(tables).toBe('latest');
    expect(tablesGate.isLatest(secondTables.sequence)).toBe(true);

    const previewGate = createLatestRequestGate();
    const firstPreview = previewGate.begin();
    const secondPreview = previewGate.begin();
    expect(firstPreview.signal.aborted).toBe(true);
    let token = 'latest-token';
    expect(previewGate.accept(firstPreview.sequence, () => { token = 'stale-token'; })).toBe(false);
    expect(token).toBe('latest-token');
    expect(previewGate.isLatest(secondPreview.sequence)).toBe(true);
  });

  it('definition 变更会丢弃 preview token，且快照是深拷贝和稳定序列化', () => {
    const definition = createCrudDefinition('mysql', 'fun_demo', [
      { name: 'id', dbType: 'bigint unsigned', nullable: false, primary: true },
      { name: 'title', dbType: 'varchar(80)', nullable: false }
    ] as CrudField[]);
    const snapshot = snapshotCrudDefinition(definition);
    definition.fields[1].label = '标题';

    expect(snapshot.definition.fields[1].label).toBeUndefined();
    expect(snapshot.serialized).not.toBe(snapshotCrudDefinition(definition).serialized);
    expect(snapshot.serialized).toBe(snapshotCrudDefinition(JSON.parse(snapshot.serialized) as CrudDefinition).serialized);

    const workbench = createCrudWorkbench();
    workbench.setPreview({ plan: { files: [] }, sensitive: { confirmToken: 'stale-token' } } as CrudPreview, snapshot.serialized);
    workbench.invalidatePreview();
    expect(workbench.confirmToken).toBe('');
    expect(workbench.previewSnapshot).toBe('');
    expect(workbench.previewInvalidated).toBe(true);
  });

  it('组件卸载使 pending 请求失效', () => {
    const gate = createLatestRequestGate();
    const pending = gate.begin();
    gate.invalidate();
    expect(pending.signal.aborted).toBe(true);
    expect(gate.isLatest(pending.sequence)).toBe(false);
  });

  it('字段保存可创建、更新和改名完整关系与 model 数据源', () => {
    const definition = createCrudDefinition('mysql', 'fun_demo', [
      { name: 'id', dbType: 'bigint unsigned', nullable: false, primary: true },
      { name: 'owner_id', dbType: 'bigint unsigned', nullable: false, component: 'select' }
    ] as CrudField[]);
    applyFieldDataConfiguration(definition, 'owner_id', {
      kind: 'model', source: { name: 'owner_options', type: 'relation', labelField: 'username', valueField: 'id' },
      relation: { name: 'owner', type: 'belongsTo', target: 'Admin', targetField: 'id', with: true }
    });
    expect(definition.fields[1]).toMatchObject({ relation: 'owner', references: 'Admin.id', optionsSource: 'owner_options' });
    expect(definition.relations[0]).toMatchObject({ name: 'owner', type: 'belongsTo', field: 'owner_id', target: 'Admin', targetField: 'id', optionsSource: 'owner_options' });
    expect(definition.optionsSource[0]).toEqual({ name: 'owner_options', type: 'relation', labelField: 'username', valueField: 'id' });

    applyFieldDataConfiguration(definition, 'owner_id', {
      kind: 'model', source: { name: 'administrator_options', type: 'relation', labelField: 'nickname', valueField: 'id' },
      relation: { name: 'administrator', type: 'belongsToMany', target: 'Admin', targetField: 'id', pivotTable: 'fun_demo_admin', pivotLocalKey: 'demo_id', pivotTargetKey: 'admin_id' }
    });
    expect(definition.fields[1]).toMatchObject({ relation: 'administrator', optionsSource: 'administrator_options' });
    expect(definition.relations).toHaveLength(1);
    expect(definition.relations[0]).toMatchObject({ name: 'administrator', pivotTable: 'fun_demo_admin', pivotLocalKey: 'demo_id', pivotTargetKey: 'admin_id' });
    expect(definition.optionsSource).toEqual([{ name: 'administrator_options', type: 'relation', labelField: 'nickname', valueField: 'id' }]);
  });

  it('静态、dictionary、endpoint 保存完整字段并清理孤立引用', () => {
    const definition = createCrudDefinition('mysql', 'fun_demo', [
      { name: 'id', dbType: 'bigint unsigned', nullable: false, primary: true },
      { name: 'status', dbType: 'tinyint', nullable: false, component: 'select' }
    ] as CrudField[]);
    applyFieldDataConfiguration(definition, 'status', {
      kind: 'dictionary', source: { name: 'status_options', type: 'dictionary', dictionary: 'common.status', labelField: 'label', valueField: 'value' }
    });
    expect(definition.optionsSource[0]).toMatchObject({ type: 'dictionary', dictionary: 'common.status', labelField: 'label', valueField: 'value' });
    applyFieldDataConfiguration(definition, 'status', {
      kind: 'endpoint', source: { name: 'remote_status', type: 'endpoint', endpoint: '/common/status/options', labelField: 'name', valueField: 'id' }
    });
    expect(definition.optionsSource).toEqual([{ name: 'remote_status', type: 'endpoint', endpoint: '/common/status/options', labelField: 'name', valueField: 'id' }]);
    applyFieldDataConfiguration(definition, 'status', { kind: 'static', options: [{ label: '启用', value: 1 }] });
    expect(definition.fields[1].options).toEqual([{ label: '启用', value: 1 }]);
    expect(definition.fields[1].optionsSource).toBeUndefined();
    expect(definition.fields[1].relation).toBeUndefined();
    expect(definition.fields[1].references).toBeUndefined();
    expect(definition.optionsSource).toEqual([]);
    expect(definition.relations).toEqual([]);
  });

  it('仅清理孤立数据源，不删除其他字段仍引用的数据源', () => {
    const definition = createCrudDefinition('mysql', 'fun_demo', [
      { name: 'id', dbType: 'bigint unsigned', nullable: false, primary: true },
      { name: 'status', dbType: 'tinyint', nullable: false, optionsSource: 'status_options' },
      { name: 'state', dbType: 'tinyint', nullable: false, optionsSource: 'status_options' }
    ] as CrudField[]);
    definition.optionsSource = [{ name: 'status_options', type: 'dictionary', dictionary: 'common.status', labelField: 'label', valueField: 'value' }];
    applyFieldDataConfiguration(definition, 'status', {
      kind: 'endpoint', source: { name: 'remote_status', type: 'endpoint', endpoint: '/common/status/options', labelField: 'name', valueField: 'id' }
    });
    expect(definition.optionsSource.map((source) => source.name).sort()).toEqual(['remote_status', 'status_options']);
    expect(definition.fields[2].optionsSource).toBe('status_options');
  });

  it('多字段使用完全相同的 optionsSource 时复用同一资源', () => {
    const definition = createCrudDefinition('mysql', 'fun_demo', [
      { name: 'status', dbType: 'tinyint', nullable: false },
      { name: 'state', dbType: 'tinyint', nullable: false }
    ] as CrudField[]);
    const source = { name: 'status_options', type: 'dictionary' as const, dictionary: 'common.status', labelField: 'label', valueField: 'value' };

    applyFieldDataConfiguration(definition, 'status', { kind: 'dictionary', source });
    applyFieldDataConfiguration(definition, 'state', { kind: 'dictionary', source: { ...source } });

    expect(definition.fields.map((item) => item.optionsSource)).toEqual(['status_options', 'status_options']);
    expect(definition.optionsSource).toEqual([source]);
  });

  it('其他字段引用同名 optionsSource 且配置不同时拒绝并保持原定义不变', () => {
    const definition = createCrudDefinition('mysql', 'fun_demo', [
      { name: 'status', dbType: 'tinyint', nullable: false, optionsSource: 'status_options' },
      { name: 'state', dbType: 'tinyint', nullable: false }
    ] as CrudField[]);
    definition.optionsSource = [{ name: 'status_options', type: 'dictionary', dictionary: 'common.status', labelField: 'label', valueField: 'value' }];
    const before = snapshotCrudDefinition(definition).serialized;

    expect(() => applyFieldDataConfiguration(definition, 'state', {
      kind: 'endpoint', source: { name: 'status_options', type: 'endpoint', endpoint: '/status/options', labelField: 'name', valueField: 'id' }
    })).toThrow('数据源名称“status_options”已被其他字段使用且配置不同，请使用新名称');
    expect(snapshotCrudDefinition(definition).serialized).toBe(before);
  });

  it('共享 optionsSource 删除一方时保留，最后一个引用删除时清理', () => {
    const definition = createCrudDefinition('mysql', 'fun_demo', [
      { name: 'status', dbType: 'tinyint', nullable: false, optionsSource: 'status_options' },
      { name: 'state', dbType: 'tinyint', nullable: false, optionsSource: 'status_options' }
    ] as CrudField[]);
    definition.optionsSource = [{ name: 'status_options', type: 'dictionary', dictionary: 'common.status', labelField: 'label', valueField: 'value' }];

    applyFieldDataConfiguration(definition, 'status', { kind: 'none' });
    expect(definition.optionsSource).toHaveLength(1);
    expect(definition.fields[1].optionsSource).toBe('status_options');

    applyFieldDataConfiguration(definition, 'state', { kind: 'none' });
    expect(definition.optionsSource).toEqual([]);
  });

  it('共享资源改名只更新当前字段，不重命名或覆盖原资源', () => {
    const definition = createCrudDefinition('mysql', 'fun_demo', [
      { name: 'status', dbType: 'tinyint', nullable: false, optionsSource: 'status_options' },
      { name: 'state', dbType: 'tinyint', nullable: false, optionsSource: 'status_options' }
    ] as CrudField[]);
    definition.optionsSource = [{ name: 'status_options', type: 'dictionary', dictionary: 'common.status', labelField: 'label', valueField: 'value' }];

    applyFieldDataConfiguration(definition, 'status', {
      kind: 'endpoint', source: { name: 'remote_status', type: 'endpoint', endpoint: '/status/options', labelField: 'name', valueField: 'id' }
    });

    expect(definition.fields[0].optionsSource).toBe('remote_status');
    expect(definition.fields[1].optionsSource).toBe('status_options');
    expect(definition.optionsSource.map((item) => item.name).sort()).toEqual(['remote_status', 'status_options']);
  });

  it('关系名已绑定其他字段时拒绝且不修改任何资源', () => {
    const definition = createCrudDefinition('mysql', 'fun_demo', [
      { name: 'owner_id', dbType: 'bigint', nullable: false, relation: 'owner', references: 'Admin.id', optionsSource: 'owner_options' },
      { name: 'reviewer_id', dbType: 'bigint', nullable: false }
    ] as CrudField[]);
    definition.optionsSource = [{ name: 'owner_options', type: 'relation', labelField: 'username', valueField: 'id' }];
    definition.relations = [{ name: 'owner', type: 'belongsTo', field: 'owner_id', target: 'Admin', targetField: 'id', optionsSource: 'owner_options' }];
    const before = snapshotCrudDefinition(definition).serialized;

    expect(() => applyFieldDataConfiguration(definition, 'reviewer_id', {
      kind: 'model', source: { name: 'reviewer_options', type: 'relation', labelField: 'name', valueField: 'id' },
      relation: { name: 'owner', type: 'belongsTo', target: 'User', targetField: 'id' }
    })).toThrow('关系名称“owner”已绑定字段“owner_id”，请使用新名称');
    expect(snapshotCrudDefinition(definition).serialized).toBe(before);
  });

  it('字段编辑器接收完整 definition、同步顶层配置并展示保存冲突', () => {
    const drawer = read('src/views/development/crud/components/FieldEditorDrawer.vue');
    const fieldsStep = read('src/views/development/crud/components/FieldsStep.vue');
    expect(drawer).toContain('definition: CrudDefinition');
    expect(drawer).toContain('applyFieldDataConfiguration(props.definition');
    expect(drawer).toContain('ElMessage.error(message)');
    expect(fieldsStep).toContain(':definition="definition"');
    expect(fieldsStep).toContain(':field-name="activeFieldName"');
  });

  it('错误会保留确认与结果步骤并清除敏感状态', () => {
    const workbench = createCrudWorkbench();
    workbench.step = 3;
    workbench.confirmToken = 'secret';
    workbench.allowOverwrite.push('app/Demo.php');
    workbench.fail('生成失败');
    expect(workbench.step).toBe(3);
    expect(workbench.error).toBe('生成失败');
    expect(workbench.confirmToken).toBe('');
    expect(workbench.allowOverwrite).toEqual([]);
  });
});
