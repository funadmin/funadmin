import { describe, expect, it } from 'vitest';
import { createCrudDefinition, createCrudWorkbench, validateWorkbenchStep } from '@/views/development/crud/workbench';
import type { CrudField, CrudPreview } from '@/types/development/crud';

describe('CRUD Workbench', () => {
  it('严格执行七步流程且 token 仅保存在非持久状态', () => {
    const workbench = createCrudWorkbench();
    expect(workbench.steps.map((step) => step.title)).toEqual(['数据源', '模块', '字段', '能力', '预览', '确认', '结果']);
    workbench.confirmToken = 'secret';
    expect(JSON.stringify(workbench.persistable())).not.toContain('secret');
    workbench.clearSensitive();
    expect(workbench.confirmToken).toBe('');
  });

  it('legacy 字段阻止进入生成确认', () => {
    const fields = [{ name: 'create_time', legacy: true }] as CrudField[];
    expect(validateWorkbenchStep(2, { fields })).toContain('legacy');
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
    expect(validateWorkbenchStep(2, { fields: validRelation })).toBe('');
    expect(validateWorkbenchStep(2, { fields: [{ name: 'user_id', relation: 'user' }] as CrudField[] }))
      .toContain('relation 与 references 必须同时配置');
    expect(validateWorkbenchStep(3, { capabilities: { list: true }, dataScope: { enabled: true, field: '', resolver: 'adminDepartmentIds' } }))
      .toBe('启用 dataScope 时必须选择范围字段');
    expect(validateWorkbenchStep(3, { capabilities: { list: true }, dataScope: { enabled: true, field: 'department_id', resolver: 'adminDepartmentIds' } }))
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

  it('错误会保留当前步骤并清除敏感 token', () => {
    const workbench = createCrudWorkbench();
    workbench.step = 5;
    workbench.confirmToken = 'secret';
    workbench.fail('生成失败');
    expect(workbench.step).toBe(5);
    expect(workbench.error).toBe('生成失败');
    expect(workbench.confirmToken).toBe('');
  });
});
