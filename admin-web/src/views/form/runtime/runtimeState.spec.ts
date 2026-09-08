import { describe, expect, it, vi } from 'vitest';
import { createRuntimeState } from './runtimeState';

const nodes = [{
  id: 'detail', kind: 'field' as const, type: 'input', field: 'detail', title: '详情', children: [],
  conditions: [{ when: { field: 'enabled', op: 'eq', value: true }, then: { action: 'show' } }],
  events: { change: [{ type: 'setValue', target: 'mirror', value: 'changed' }] }
}];

describe('FormSchema runtime state', () => {
  it('条件根据当前值控制节点可见状态', () => {
    const disabled = createRuntimeState(nodes, { enabled: false });
    expect(disabled.nodeState('detail').hidden).toBe(true);
    const enabled = createRuntimeState(nodes, { enabled: true });
    expect(enabled.nodeState('detail').hidden).toBe(false);
  });

  it('字段事件只通过白名单动作修改值', async () => {
    const values: Record<string, unknown> = { enabled: true };
    const runtime = createRuntimeState(nodes, values);
    await runtime.dispatch('detail', 'change', 'x');
    expect(values.mirror).toBe('changed');
  });

  it('request 只能调用注入的注册处理器', async () => {
    const request = vi.fn();
    const runtime = createRuntimeState([{ ...nodes[0], events: { change: [{ type: 'request', key: 'load.detail', concurrency: 'latest' }] } }], {}, { requestKeys: ['load.detail'], request });
    await runtime.dispatch('detail', 'change', 'x');
    expect(request).toHaveBeenCalledOnce();
  });
});
