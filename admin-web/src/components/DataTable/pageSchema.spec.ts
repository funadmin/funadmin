import { describe, expect, it, vi } from 'vitest';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { parsePageSchema, actionState, executePageAction } from './pageSchema';

const action = () => ({ id: 'edit', label: '编辑', permission: 'demo:edit', action: { type: 'registered', key: 'edit', capabilityVersion: '1' } });
const page = () => ({ pageSchemaVersion: 1, key: 'demo', search: [], columns: [], toolbar: [action()], rowActions: [], pagination: { pageSize: 20, pageSizes: [20] } });
const context = () => ({ values: { recycled: false, selectionCount: 0 }, permissions: ['demo:edit'], handlers: { edit: { version: '1', permission: 'demo:edit', run: vi.fn() } } });
describe('公共页面声明', () => {
  it('CRUD 日期区间、主键、排序、树表及工具双端协议', () => {
    const value = { ...page(), primaryKey: 'record_id', search: [{ field: 'created_at', label: '日期', type: 'date' }, { field: 'price', label: '价格', type: 'range' }], columns: [{ key: 'price', prop: 'price', label: '价格', sortable: true }], pagination: { pageSize: 20, pageSizes: [20], enabled: false }, list: { tree: { enabled: true, parentField: 'parent_id' }, tools: { search: false, refresh: false } } };
    expect(parsePageSchema(value)).toEqual(value);
    const php = process.env.PHP_BINARY || '/opt/homebrew/opt/php@8.1/bin/php';
    expect(execFileSync(php, ['-r', "require 'vendor/autoload.php'; \\app\\common\\form\\builder\\Page::validate(json_decode($argv[1],true)); echo 'ok';", JSON.stringify(value)], { cwd: resolve(process.cwd(), '..'), encoding: 'utf8' })).toBe('ok');
  });
  it('Builder 显式主键及分页开关编译后保真', () => {
    const php = process.env.PHP_BINARY || '/opt/homebrew/opt/php@8.1/bin/php';
    const output = execFileSync(php, ['-r', "require 'vendor/autoload.php'; echo json_encode(\\app\\common\\form\\builder\\Page::make('demo')->primaryKey('record_id')->pagination(20, [20], false)->compile());"], { cwd: resolve(process.cwd(), '..'), encoding: 'utf8' });
    expect(parsePageSchema(JSON.parse(output))).toMatchObject({ primaryKey: 'record_id', pagination: { enabled: false } });
  });
  it.each([
    { primaryKey: '__proto__' },
    { columns: [{ key: 'price', label: '价格', sortable: 'custom' }] },
    { list: { tree: { enabled: true } } },
    { list: { tools: { refresh: 'yes' } } },
    { pagination: { pageSize: 20, pageSizes: [20], enabled: 0 } }
  ])('两端拒绝非法 CRUD 展示能力 %j', patch => {
    const value = { ...page(), ...patch };
    expect(() => parsePageSchema(value)).toThrow('PAGE_SCHEMA_INVALID');
    const php = process.env.PHP_BINARY || '/opt/homebrew/opt/php@8.1/bin/php';
    const output = execFileSync(php, ['-r', "require 'vendor/autoload.php'; try { \\app\\common\\form\\builder\\Page::validate(json_decode($argv[1], true)); echo 'accepted'; } catch (InvalidArgumentException $e) { echo $e->getMessage(); }", JSON.stringify(value)], { cwd: resolve(process.cwd(), '..'), encoding: 'utf8' });
    expect(output).toBe('PAGE_SCHEMA_INVALID');
  });
  it('接收 PHP 编译产物，空集合保持为空', () => {
    expect(parsePageSchema(page()).columns).toEqual([]);
    const root = resolve(process.cwd(), '..');
    const php = process.env.PHP_BINARY || '/opt/homebrew/opt/php@8.1/bin/php';
    const output = execFileSync(php, ['-r', "require 'vendor/autoload.php'; echo json_encode(app\\console\\service\\MemberPageDefinition::build(['groups'=>[], 'levels'=>[], 'tags'=>[]]));"], { cwd: root, encoding: 'utf8' });
    expect(parsePageSchema(JSON.parse(output)).columns).toHaveLength(12);
  });
  it.each([null, {}, { ...page(), toolbar: null }, { ...page(), search: [{ field: '__proto__', label: '坏字段', type: 'input' }] }, { ...page(), toolbar: [{ ...action(), url: '/delete' }] }, { ...page(), toolbar: [{ ...action(), action: { type: 'external', key: 'edit' } }] }])('拒绝损坏及可执行声明 %j', (value) => {
    expect(() => parsePageSchema(value)).toThrow();
  });
  it('PHP/TS 分类按钮协议矩阵保持标识、文本、整数、null、对象编码与左树一致', () => {
    const tree = { enabled: true, source: { type: 'current' }, mapping: { valueField: 'id', labelField: 'name', targetField: 'category_id' }, selection: { mode: 'single', includeDescendants: false }, actions: { create: false, addChild: false, edit: false, delete: false } };
    const button = { id: 'inspect', label: '检查', order: 1, action: { type: 'registered', key: 'inspect', capabilityVersion: '1' }, visibleWhen: { field: 'id', op: 'eq', value: null } };
    const value = { ...page(), list: { leftTree: tree, buttons: { categoryToolbar: [button], categoryNode: [] } } };
    expect(() => parsePageSchema(value)).not.toThrow();
    expect(parsePageSchema(value).list?.buttons?.categoryToolbar).toEqual([button]);
    for (const invalid of [
      { ...button, id: 'BadId' },
      { ...button, label: 1 },
      { ...button, visibleWhen: { field: 'status', op: 'eq', value: {} } },
      { ...button, order: 1.5 },
      { ...button, action: { type: 'registered', key: 'inspect', capabilityVersion: 1 } },
    ]) expect(() => parsePageSchema({ ...page(), list: { leftTree: tree, buttons: { categoryToolbar: [invalid] } } })).toThrow('PAGE_SCHEMA_INVALID');
  });
  it('同一 JSON 输入由 PHP 与 TS 共同执行正反例矩阵', () => {
    const cases: { name: string; valid: boolean; value: unknown }[] = [];
    const add = (name: string, valid: boolean, patch: object) => cases.push({ name, valid, value: { ...page(), ...patch } });
    const category = (patch: object, location = 'categoryToolbar') => ({ list: { buttons: { [location]: [{ ...action(), ...patch }] } } });
    add('页面基础', true, {});
    add('页面上下文条件不参与基础字段可读验证', true, { toolbar: [{ ...action(), visibleWhen: { field: 'recycled', op: 'eq', value: false }, activeWhen: { field: 'selectionCount', op: 'neq', value: 0 } }] });
    for (const key of ['order', 'size', 'tips', 'placement', 'disabledReason', 'interaction', 'params', 'success', 'selection']) add(`顶部闭合-${key}`, false, { toolbar: [{ ...action(), [key]: 1 }] });
    for (const location of ['categoryToolbar', 'categoryNode']) {
      for (const [name, valid, patch] of [
        ['order1', true, { order: 1 }], ['order小数', false, { order: 1.5 }], ['order越界', false, { order: 10001 }],
        ['id条件', true, { visibleWhen: { field: 'id', op: 'eq', value: null } }],
        ['status不可读', false, { visibleWhen: { field: 'status', op: 'eq', value: null } }],
        ['嵌套条件', true, { disabledWhen: { op: 'and', conditions: [{ op: 'not', condition: { op: 'in', field: 'id', value: [1, null] } }] } }],
        ['坏条件', false, { visibleWhen: { op: 'exec', field: 'id', value: 1 } }],
        ['大写ID', false, { id: 'BadId' }], ['点号ID', true, { id: 'inspect.node' }], ['危险ID', false, { id: 'a.constructor' }],
        ['中文长度', false, { label: '中'.repeat(34) }], ['控制字符', false, { label: 'bad\ntext' }], ['空文本', false, { label: ' ' }],
        ['icon', true, { icon: 'inspect' }], ['iconNull', false, { icon: null }], ['color', false, { color: 'unknown' }], ['selection', false, { selection: { min: 1 } }],
        ['注册版本', false, { action: { type: 'registered', key: 'edit', capabilityVersion: 1 } }],
        ['空版本', false, { action: { type: 'registered', key: 'edit', capabilityVersion: '' } }],
        ['刷新', true, { action: { type: 'refresh' } }], ['刷新key', false, { action: { type: 'refresh', key: 'edit' } }],
        ['交互输入', true, { interaction: { type: 'input', fields: [{ name: 'reason', label: '原因', type: 'input', maxLength: 20 }] }, params: { reason: { source: 'form', field: 'reason' } } }],
        ['交互缺字段', false, { interaction: { type: 'input' } }], ['交互null', false, { interaction: null }], ['交互fieldsNull', false, { interaction: { type: 'form', fields: null } }],
        ['参数id', true, { params: { target: { source: 'row', field: 'id' } } }], ['参数status', false, { params: { target: { source: 'row', field: 'status' } } }],
        ['常量null', true, { params: { target: { source: 'literal', value: null } } }], ['常量对象', false, { params: { target: { source: 'literal', value: {} } } }],
        ['空对象', true, { params: {}, success: {} }], ['PHP空对象编码', true, { params: [], success: [] }], ['参数null', false, { params: null }],
        ['成功效果', true, { success: { refresh: true, message: '完成' } }], ['坏成功效果', false, { success: { refresh: 1 } }], ['未知字段', false, { url: '/bad' }],
      ] as const) add(`${location}-${name}`, valid, category(patch, location));
    }
    const tree = { enabled: true, source: { type: 'current' }, mapping: { valueField: 'id', labelField: 'name', targetField: 'category_id' } };
    add('左树', true, { list: { leftTree: tree } });
    add('左树缺开关', false, { list: { leftTree: {} } });
    add('左树危险标识', false, { list: { leftTree: { ...tree, mapping: { ...tree.mapping, labelField: 'constructor' } } } });
    add('左树null', false, { list: { leftTree: { ...tree, selection: null } } });
    add('左树禁用', true, { list: { leftTree: { enabled: false } } });
    add('搜索整数', true, { search: [{ field: 'id', label: '编号', type: 'select', options: [{ label: '一', value: 1 }] }] });
    add('搜索小数', false, { search: [{ field: 'id', label: '编号', type: 'select', options: [{ label: '一', value: 1.5 }] }] });
    add('列null', false, { columns: [{ key: 'id', label: '编号', width: null }] });
    const json = JSON.stringify(cases);
    const phpResults = JSON.parse(execFileSync(process.env.PHP_BINARY || '/opt/homebrew/opt/php@8.1/bin/php', ['-r',
      "require 'vendor/autoload.php'; $results=[]; foreach(json_decode(stream_get_contents(STDIN),true) as $case) { try { \\app\\common\\form\\builder\\Page::validate($case['value']); $results[]=true; } catch (InvalidArgumentException $e) { $results[]=$e->getMessage(); } } echo json_encode($results);"
    ], { cwd: resolve(process.cwd(), '..'), input: json, encoding: 'utf8' }));
    JSON.parse(json).forEach((test: typeof cases[number], index: number) => {
      let result: true | string = true;
      try { parsePageSchema(test.value); } catch (error) { result = (error as Error).message; }
      const expected = test.valid ? true : 'PAGE_SCHEMA_INVALID';
      expect(phpResults[index], `PHP: ${test.name}`).toBe(expected);
      expect(result, `TS: ${test.name}`).toBe(expected);
    });
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
