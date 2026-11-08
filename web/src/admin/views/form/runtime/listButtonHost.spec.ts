import { describe, expect, it, vi } from 'vitest';
import * as host from './listButtonHost';
import type { FormListButton } from '../schema/types';
const button: FormListButton = { id: 'edit', label: '改名不改动作', action: { type: 'builtin', key: 'edit' } };
describe('列表宿主能力交集', () => {
  it('操作列判断：绑定本模块已声明自定义动作的按钮计为存在（待生成按钮禁用提示而非连列隐藏）', () => {
    const schema = { key: 'fun_order', list: { customActions: [{ key: 'mark_paid' }] } };
    const bound = (key: string): FormListButton => ({ id: key, label: key, action: { type: 'registered', key, capabilityVersion: '1' } });
    expect(host.declaredCustomActionButton(bound('generated.fun-order.mark_paid'), schema)).toBe(true);
    expect(host.declaredCustomActionButton(bound('generated.fun-order.other'), schema)).toBe(false);
    expect(host.declaredCustomActionButton(bound('generated.fun-order.mark_paid'), { key: 'fun_order' })).toBe(false);
    expect(host.declaredCustomActionButton(button, schema)).toBe(false);
  });
  it('默认、空集合、排序以及改名不改变分派', () => {
    expect(host.defaultListButtons('row').map(item => item.action)).toContainEqual(button.action);
    expect(host.listActionKey(button)).toBe('edit');
  });
  it('缺失适配明确禁用；权限撤销和条件隐藏不能执行', () => {
    const options = { handlers: { edit: vi.fn() }, allowed: () => true, values: { status: 1 }, fields: ['status'] };
    expect(host.listButtonState(button, options)).toEqual({ visible: true, disabled: false, reason: '' });
    expect(host.listButtonState(button, { ...options, allowed: () => false }).visible).toBe(false);
    expect(host.listButtonState({ ...button, action: { type: 'registered', key: 'refund', capabilityVersion: '1' } }, options).reason).toContain('尚未接通');
    expect(host.listButtonState({ ...button, visibleWhen: { field: 'status', op: 'eq', value: 2 } }, options).visible).toBe(false);
  });
  it('内置动作不能静默吞掉尚未适配的参数声明', () => {
    expect(host.listButtonState({ ...button, params: { reason: { source: 'literal', value: 'test' } } }, { handlers: { edit: vi.fn() }, allowed: () => true }).disabled).toBe(true);
  });
  it('拒绝原型、敏感未知字段和正则条件', () => {
    for (const condition of [{ field: 'constructor', op: 'eq', value: 1 }, { field: 'secret', op: 'empty' }, { field: 'status', op: 'matches', value: '.*' }]) {
      expect(host.listButtonState({ ...button, visibleWhen: condition }, { handlers: { edit: vi.fn() }, allowed: () => true, fields: ['status'], values: {} }).disabled).toBe(true);
    }
  });
});
