import { describe, it, expect, vi, beforeEach } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { mount } from '@vue/test-utils';

// 拦截 element-plus 的弹窗：单测里直接走「同意 / 自动通过」
vi.mock('element-plus', () => ({
  ElMessage: { warning: vi.fn(), success: vi.fn(), error: vi.fn() },
  ElMessageBox: { confirm: vi.fn(() => Promise.resolve()) }
}));

import { useCrud } from '@/composables/useCrud';
import { ElMessage, ElMessageBox } from 'element-plus';

interface Row {
  id: number;
  name: string;
}

interface Query {
  page: number;
  pageSize: number;
  keyword: string;
}

const initialQuery = (): Query => ({ page: 1, pageSize: 10, keyword: '' });

function makePagedApi(rows: Row[]) {
  return {
    list: vi.fn(async () => ({ list: rows, total: rows.length })),
    remove: vi.fn(async (_id: number) => undefined),
    removeMany: vi.fn(async (_ids: number[]) => undefined)
  };
}

function makeArrayApi(rows: Row[]) {
  return {
    list: vi.fn(async () => rows),
    remove: vi.fn(async (_id: number) => undefined)
  };
}

describe('composables/useCrud', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('loadData 兼容分页结构', async () => {
    const api = makePagedApi([{ id: 1, name: 'a' }]);
    const c = useCrud<Row, Query>({ api, initialQuery, pagination: true });
    await c.loadData();
    expect(api.list).toHaveBeenCalledWith(c.query);
    expect(c.list.value).toHaveLength(1);
    expect(c.total.value).toBe(1);
    expect(c.loading.value).toBe(false);
  });

  it('loadData 兼容数组返回', async () => {
    const api = makeArrayApi([{ id: 1, name: 'a' }, { id: 2, name: 'b' }]);
    const c = useCrud<Row, Query>({ api, initialQuery });
    await c.loadData();
    expect(c.list.value).toHaveLength(2);
    expect(c.total.value).toBe(2);
  });

  it('onSearch 在分页模式下重置 page 为 1', async () => {
    const api = makePagedApi([]);
    const c = useCrud<Row, Query>({ api, initialQuery, pagination: true });
    (c.query as Query).page = 5;
    c.onSearch();
    await nextTick();
    expect((c.query as Query).page).toBe(1);
    expect(api.list).toHaveBeenCalled();
  });

  it('onReset 还原查询条件并重新加载', async () => {
    const api = makePagedApi([]);
    const c = useCrud<Row, Query>({ api, initialQuery, pagination: true });
    (c.query as Query).keyword = 'xx';
    c.onReset();
    await nextTick();
    expect((c.query as Query).keyword).toBe('');
    expect(api.list).toHaveBeenCalled();
  });

  it('onAdd 打开弹窗并清空 current', () => {
    const api = makePagedApi([]);
    const c = useCrud<Row, Query>({ api, initialQuery });
    c.current.value = { id: 1, name: 'a' };
    c.onAdd();
    expect(c.dialogVisible.value).toBe(true);
    expect(c.current.value).toBeNull();
  });

  it('onEdit 打开弹窗并赋值 current', () => {
    const api = makePagedApi([]);
    const c = useCrud<Row, Query>({ api, initialQuery });
    const row = { id: 1, name: 'a' };
    c.onEdit(row);
    expect(c.dialogVisible.value).toBe(true);
    expect(c.current.value).toEqual(row);
  });

  it('onDelete 弹确认后调用 api.remove + 重新加载', async () => {
    const api = makePagedApi([{ id: 7, name: 'a' }]);
    const c = useCrud<Row, Query>({ api, initialQuery });
    await c.onDelete({ id: 7, name: 'a' });
    expect(ElMessageBox.confirm).toHaveBeenCalled();
    expect(api.remove).toHaveBeenCalledWith(7);
    expect(api.list).toHaveBeenCalled();
  });

  it('onBatchDelete 选择为空时只提示', async () => {
    const api = makePagedApi([]);
    const c = useCrud<Row, Query>({ api, initialQuery });
    await c.onBatchDelete();
    expect(ElMessage.warning).toHaveBeenCalledWith('请至少选择一项');
    expect(api.removeMany).not.toHaveBeenCalled();
    expect(api.remove).not.toHaveBeenCalled();
  });

  it('onBatchDelete 优先走 removeMany', async () => {
    const api = makePagedApi([]);
    const c = useCrud<Row, Query>({ api, initialQuery });
    c.onSelectionChange([
      { id: 1, name: 'a' },
      { id: 2, name: 'b' }
    ]);
    await c.onBatchDelete();
    expect(api.removeMany).toHaveBeenCalledWith([1, 2]);
    expect(api.remove).not.toHaveBeenCalled();
    expect(c.selection.value).toEqual([]);
  });

  it('onBatchDelete 无 removeMany 时退化到串行 remove', async () => {
    const api = makeArrayApi([]);
    const c = useCrud<Row, Query>({ api, initialQuery });
    c.onSelectionChange([
      { id: 11, name: 'a' },
      { id: 22, name: 'b' }
    ]);
    await c.onBatchDelete();
    expect(api.remove).toHaveBeenCalledTimes(2);
    expect(api.remove).toHaveBeenCalledWith(11);
    expect(api.remove).toHaveBeenCalledWith(22);
  });

  it('onSelectionChange 写入 selection', () => {
    const api = makePagedApi([]);
    const c = useCrud<Row, Query>({ api, initialQuery });
    c.onSelectionChange([{ id: 1, name: 'a' }]);
    expect(c.selection.value).toHaveLength(1);
  });

  it('loadData 异常仍会复位 loading', async () => {
    const api = {
      list: vi.fn(async () => {
        throw new Error('boom');
      })
    } as any;
    const c = useCrud<Row, Query>({ api, initialQuery });
    await expect(c.loadData()).rejects.toThrow('boom');
    expect(c.loading.value).toBe(false);
  });

  it('组件挂载时默认自动加载一次', async () => {
    const api = makePagedApi([{ id: 1, name: 'a' }]);
    const Comp = defineComponent({
      setup() {
        useCrud<Row, Query>({ api, initialQuery });
        return () => h('div');
      }
    });
    mount(Comp);
    await vi.waitFor(() => expect(api.list).toHaveBeenCalledTimes(1));
  });

  it('immediate 为 false 时挂载不自动加载', async () => {
    const api = makePagedApi([]);
    const Comp = defineComponent({
      setup() {
        useCrud<Row, Query>({ api, initialQuery, immediate: false });
        return () => h('div');
      }
    });
    mount(Comp);
    await nextTick();
    expect(api.list).not.toHaveBeenCalled();
  });
});
