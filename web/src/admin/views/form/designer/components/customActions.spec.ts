import { describe, expect, it } from 'vitest';
import type { FormListButton, FormListCustomAction } from '../../schema/types';
import { syncBoundButtonParameters } from './customActions';

const action = (parameters: FormListCustomAction['parameters']): FormListCustomAction => ({ key: 'approve', label: '审核', locations: ['row', 'toolbar'], batch: true, requiresConfirmation: true, effect: 'write', parameters });
const bound = (id: string, params: FormListButton['params']): FormListButton => ({ id, label: id, action: { type: 'registered', key: 'business.fun-order.approve', capabilityVersion: '1' }, permission: 'business:fun-order:action-approve', params });
const other: FormListButton = { id: 'refresh', label: '刷新', action: { type: 'refresh' } };

describe('自定义动作参数变更同步到已绑定按钮', () => {
  it('新增参数按类型绑定常量默认值，删除的参数移除绑定，未绑定的按钮不动', () => {
    const before = action([{ name: 'id', type: 'integer' }, { name: 'note', type: 'string' }]);
    const after = action([{ name: 'id', type: 'integer' }, { name: 'reason', type: 'string' }, { name: 'count', type: 'integer' }, { name: 'ratio', type: 'number' }, { name: 'force', type: 'boolean' }]);
    const buttons = { row: [bound('b1', { id: { source: 'row', field: 'id' }, note: { source: 'literal', value: '同意' } }), other], toolbar: [bound('b2', undefined)] };
    const result = syncBoundButtonParameters(buttons, 'business.fun-order.approve', before, after);
    expect(result.count).toBe(2);
    expect(result.added).toEqual(['reason', 'count', 'ratio', 'force', 'id']);
    expect(result.patch!.row![0].params).toEqual({ id: { source: 'row', field: 'id' }, reason: { source: 'literal', value: '' }, count: { source: 'literal', value: 0 }, ratio: { source: 'literal', value: 0 }, force: { source: 'literal', value: false } });
    expect(result.patch!.row![1]).toBe(other);
    expect(result.patch!.toolbar![0].params).toEqual({ id: { source: 'literal', value: 0 }, reason: { source: 'literal', value: '' }, count: { source: 'literal', value: 0 }, ratio: { source: 'literal', value: 0 }, force: { source: 'literal', value: false } });
    expect(buttons.row[0].params).toHaveProperty('note');
  });
  it('参数未变化时不产生改动；类型改变时不匹配的常量重置为新类型默认值', () => {
    const before = action([{ name: 'note', type: 'string' }]);
    const buttons = { row: [bound('b1', { note: { source: 'literal', value: '同意' } })] };
    expect(syncBoundButtonParameters(buttons, 'business.fun-order.approve', before, before)).toEqual({ count: 0, added: [], patch: undefined });
    const retyped = syncBoundButtonParameters(buttons, 'business.fun-order.approve', before, action([{ name: 'note', type: 'integer' }]));
    expect(retyped.patch!.row![0].params).toEqual({ note: { source: 'literal', value: 0 } });
    expect(retyped.added).toEqual(['note']);
  });
});
