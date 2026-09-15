import { describe, expect, it, vi } from 'vitest';
import { formatFieldValue, resolveFieldOptions, resolveFieldOptionsAsync, clearFieldOptionsCache } from './fieldPresentation';

const dynamicRequest = async (_key: string, _field: string) => ({ options: [{ label: '动态标签', value: 7 }] });

describe('共享字段展示运行时', () => {
  it('列表和详情使用同一组选项解析与值格式化规则', () => {
    const node = { field: 'status', title: '状态', dataSource: { options: [{ label: '启用', value: 1 }, { label: '停用', value: 0 }] } } as any;
    const options = resolveFieldOptions(node, {});
    expect(formatFieldValue(1, options)).toBe('启用');
    expect(formatFieldValue(0, options)).toBe('停用');
    expect(formatFieldValue(null, options)).toBe('');
  });

  it('对象不与其他对象或字符串碰撞，数组逐项安全展示', () => {
    const options = [{ label: '错误标签', value: { id: 1 } }];
    expect(formatFieldValue({ id: 2 }, options)).toBe('{"id":2}');
    expect(formatFieldValue([{ id: 2 }, null, 0], options)).toBe('{"id":2}, , 0');
    expect(formatFieldValue([1, '0'], [{ label: '启用', value: 1 }, { label: '停用', value: 0 }])).toBe('启用, 停用');
  });

  it('同一 Schema 值在运行时、动态列表、已发布列表和生成详情中保持一致', () => {
    const cases: Array<[unknown, string | undefined]> = [
      [12.5, 'money'],
      [0, 'percent'],
      [1234567, 'number'],
      ['2026-01-02T03:04:05Z', 'datetime'],
      ['', 'date'],
      [null, undefined],
      [['a', 'b'], undefined]
    ];
    for (const [value, formatter] of cases) {
      const expected = formatFieldValue(value, [], formatter);
      expect(formatFieldValue(value, [], formatter, 'runtime')).toBe(expected);
      expect(formatFieldValue(value, [], formatter, 'data')).toBe(expected);
      expect(formatFieldValue(value, [], formatter, 'published')).toBe(expected);
    }
  });

  it('跨宿主解析动态 options 并缓存并发请求，失败不伪造静态标签', async () => {
    clearFieldOptionsCache();
    const request = vi.fn(dynamicRequest);
    const node = { field: 'owner', dataSource: { kind: 'remote' } } as any;
    const [first, second] = await Promise.all([
      resolveFieldOptionsAsync('orders', node, {}, request),
      resolveFieldOptionsAsync('orders', node, {}, request)
    ]);
    expect(first).toEqual([{ label: '动态标签', value: 7 }]);
    expect(second).toEqual(first);
    expect(request).toHaveBeenCalledTimes(1);
    await expect(resolveFieldOptionsAsync('orders', node, {}, async () => { throw new Error('forbidden'); })).rejects.toThrow('forbidden');
  });

  it('合并 dataSource.params 与 runtime context，并在失败时使用固定脱敏提示', async () => {
    clearFieldOptionsCache();
    const request = vi.fn(async (_key: string, _field: string, params: Record<string, unknown>) => {
      expect(params).toEqual({ tenant: 'data', status: 'active' });
      throw new Error('敏感后端异常');
    });
    const node = { field: 'owner', dataSource: { kind: 'remote', params: { tenant: 'data' }, options: [{ label: '原始', value: 1 }] } } as any;
    await expect(resolveFieldOptionsAsync('orders', node, { status: 'active' }, request)).rejects.toThrow('敏感后端异常');
    expect(request).toHaveBeenCalledWith('orders', 'owner', { tenant: 'data', status: 'active' }, undefined);
  });

  it('保留树形选项与扩展属性，不接受非法选项', () => {
    const node = { id: 'tree', dataSource: { options: [null, [], { label: '父', value: 1, disabled: true, children: [{ label: '子', value: 2 }] }] } };
    const options = resolveFieldOptions(node);
    expect(options).toEqual([{ label: '父', value: 1, disabled: true, children: [{ label: '子', value: 2 }] }]);
    expect(formatFieldValue(2, options)).toBe('子');
    expect(resolveFieldOptions(node, { tree: [] })).toEqual([]);
  });
});
