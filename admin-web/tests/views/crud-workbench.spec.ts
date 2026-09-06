import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { createCrudDefinition, createCrudWorkbench, validateWorkbenchStep } from '@/views/development/crud/workbench';
import type { CrudField, CrudPreview } from '@/types/development/crud';

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
