import { describe, expect, it, vi } from 'vitest';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { parsePageSchema, actionState, executePageAction } from './pageSchema';

const action = () => ({ id: 'edit', label: '编辑', permission: 'demo:edit', action: { type: 'registered', key: 'edit', capabilityVersion: '1' } });
const page = () => ({ pageSchemaVersion: 1, key: 'demo', search: [], columns: [], toolbar: [action()], rowActions: [], pagination: { pageSize: 20, pageSizes: [20] } });
const context = () => ({ values: { recycled: false, selectionCount: 0 }, permissions: ['demo:edit'], handlers: { edit: { version: '1', permission: 'demo:edit', run: vi.fn() } } });
describe('公共页面声明', () => {
  it('接收 PHP 编译产物，空集合保持为空', () => {
    expect(parsePageSchema(page()).columns).toEqual([]);
    const root = resolve(process.cwd(), '..');
    const php = process.env.PHP_BINARY || 'php';
    const output = execFileSync(php, ['-r', "require 'vendor/autoload.php'; echo json_encode(app\\console\\service\\MemberPageDefinition::build(['groups'=>[], 'levels'=>[], 'tags'=>[]]));"], { cwd: root, encoding: 'utf8' });
    expect(parsePageSchema(JSON.parse(output)).columns).toHaveLength(12);
  });
  it.each([null, {}, { ...page(), toolbar: null }, { ...page(), search: [{ field: '__proto__', label: '坏字段', type: 'input' }] }, { ...page(), toolbar: [{ ...action(), url: '/delete' }] }, { ...page(), toolbar: [{ ...action(), action: { type: 'external', key: 'edit' } }] }])('拒绝损坏及可执行声明 %j', (value) => {
    expect(() => parsePageSchema(value)).toThrow();
  });
  it('分类协议拒绝缺失选项、未知来源、任意地址与非法操作开关', () => {
    const tree = { enabled: true, source: { type: 'module', module: 'categories' }, mapping: { valueField: 'id', labelField: 'name', targetField: 'category_id' } };
    expect(parsePageSchema({ ...page(), list: { leftTree: tree, buttons: { categoryNode: [] } } }).list?.buttons?.categoryNode).toEqual([]);
    for (const list of [{ category: { enabled: true, field: 'missing' } }, { url: '/delete' }, { leftTree: { ...tree, source: { type: 'sql' } } }, { leftTree: { ...tree, actions: { delete: 'yes' } } }, { leftTree: { ...tree, selection: { mode: 'arbitrary' } } }]) {
      expect(() => parsePageSchema({ ...page(), list })).toThrow();
    }
  });
  it('只调用自有已注册且版本匹配的 handler，重复执行仍检查权限', async () => {
    const c = context(); const a = parsePageSchema(page()).toolbar[0]!;
    await executePageAction(a, c); expect(c.handlers.edit.run).toHaveBeenCalledTimes(1);
    c.permissions = []; await executePageAction(a, c); expect(c.handlers.edit.run).toHaveBeenCalledTimes(1);
    c.permissions = ['*']; c.handlers.edit.version = '2'; expect(actionState(a, c).visible).toBe(false);
    expect(actionState({ ...a, action: { type: 'registered', key: 'constructor', capabilityVersion: '1' } }, c).visible).toBe(false);
  });
  it('损坏 disabled 条件不能意外启用动作', async () => {
    const c = context(); const a = parsePageSchema(page()).toolbar[0]!;
    a.disabledWhen = { field: 'selectionCount', op: 'execute', value: 0 } as any;
    expect(actionState(a, c).disabled).toBe(true);
    await executePageAction(a, c); expect(c.handlers.edit.run).not.toHaveBeenCalled();
  });
  it('声明不能移除宿主权限，visible/disabled 条件失败关闭', async () => {
    const c = context(); const a = parsePageSchema(page()).toolbar[0]!;
    c.permissions = []; delete a.permission;
    expect(actionState(a, c).visible).toBe(false);
    c.permissions = ['*:*:*']; a.disabledWhen = { field: 'selectionCount', op: 'eq', value: 0 };
    expect(actionState(a, c)).toMatchObject({ visible: true, disabled: true });
    await executePageAction(a, c); expect(c.handlers.edit.run).not.toHaveBeenCalled();
    a.visibleWhen = { field: 'recycled', op: 'eq', value: true };
    expect(actionState(a, c).visible).toBe(false);
    a.visibleWhen = { field: 'missing', op: 'neq', value: true };
    expect(actionState(a, c).visible).toBe(false);
  });
});
