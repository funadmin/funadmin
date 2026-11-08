import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const api = vi.hoisted(() => ({
  tree: vi.fn(),
  list: vi.fn(),
  detail: vi.fn(),
  create: vi.fn(),
  saveDraft: vi.fn(),
  publish: vi.fn(),
  archive: vi.fn(),
  restore: vi.fn(),
  remove: vi.fn()
}));
vi.mock('../../../admin/plugins/document/web/src/admin/plugins/document/api', () => ({ documentApi: api }));

import { useDocumentStore } from '../../../admin/plugins/document/web/src/admin/plugins/document/store';
import type { DocumentDetail } from '../../../admin/plugins/document/web/src/admin/plugins/document/types';

const doc = (text: string) => ({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text }] }] });
const detail = (over: Record<string, unknown> = {}): DocumentDetail => ({
  id: 7, title: '部署', slug: 'deploy', summary: '', scope: 'framework', pluginCode: '', type: 'guide',
  status: 'draft', appliesTo: '', lockVersion: 1, currentRevisionNo: 0, hasUnpublishedChanges: false, metaVersion: 1, categoryIds: [], updatedAt: '', plainText: '',
  content: doc('v1'), ...over
}) as DocumentDetail;

describe('document store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    Object.values(api).forEach((fn) => fn.mockReset());
  });

  it('打开文档后编辑会标记为未保存，保存成功后更新 lockVersion 并清除标记', async () => {
    api.detail.mockResolvedValue(detail());
    api.saveDraft.mockResolvedValue(detail({ lockVersion: 2, content: doc('v2') }));
    const store = useDocumentStore();
    await store.openDocument(7);
    expect(store.dirty).toBe(false);

    store.setContent(doc('v2'));
    expect(store.dirty).toBe(true);
    await store.saveDraft();

    expect(api.saveDraft).toHaveBeenCalledWith(7, doc('v2'), 1);
    expect(store.current?.lockVersion).toBe(2);
    expect(store.dirty).toBe(false);
    expect(store.conflict).toBe(false);
  });

  it('保存成功不回写编辑内容：服务端返回的正文即使键顺序不同也不替换本地草稿', async () => {
    api.detail.mockResolvedValue(detail());
    const reordered = { content: [{ content: [{ text: 'v2', type: 'text' }], type: 'paragraph' }], type: 'doc' };
    api.saveDraft.mockResolvedValue(detail({ lockVersion: 2, content: reordered }));
    const store = useDocumentStore();
    await store.openDocument(7);
    const mine = doc('v2');
    store.setContent(mine);
    const generation = store.contentGeneration;
    await store.saveDraft();
    // 草稿仍是本地编辑的内容（不是服务端键序不同的版本），且没有触发编辑器整体替换。
    expect(store.draft).toEqual(mine);
    expect(JSON.stringify(store.draft)).toBe(JSON.stringify(mine));
    expect(store.contentGeneration).toBe(generation);
  });

  it('保存请求进行中又输入的内容保持未保存', async () => {
    api.detail.mockResolvedValue(detail());
    let resolve!: (v: unknown) => void;
    api.saveDraft.mockImplementation(() => new Promise((r) => { resolve = r; }));
    const store = useDocumentStore();
    await store.openDocument(7);
    store.setContent(doc('v2'));
    const saving = store.saveDraft();
    store.setContent(doc('v3 保存途中输入'));
    resolve(detail({ lockVersion: 2, content: doc('v2') }));
    await saving;
    expect(store.draft).toEqual(doc('v3 保存途中输入'));
    expect(store.dirty).toBe(true);
    expect(store.current?.lockVersion).toBe(2);
  });

  it('没有修改时 saveDraft 不发请求', async () => {
    api.detail.mockResolvedValue(detail());
    const store = useDocumentStore();
    await store.openDocument(7);
    expect(await store.saveDraft()).toBe(true);
    expect(api.saveDraft).not.toHaveBeenCalled();
  });

  it('更新元数据只合并元数据，保留冲突、未保存状态和原 lockVersion', async () => {
    api.detail.mockResolvedValue(detail({ lockVersion: 3 }));
    api.saveDraft.mockRejectedValue({ code: 409, msg: '冲突' });
    const store = useDocumentStore();
    await store.openDocument(7);
    store.setContent(doc('我的修改'));
    await store.saveDraft();
    store.applyMeta(detail({ lockVersion: 9, metaVersion: 5, title: '新标题', content: doc('服务器正文') }));
    expect(store.current?.title).toBe('新标题');
    expect(store.current?.metaVersion).toBe(5);
    expect(store.current?.lockVersion).toBe(3);
    expect(store.conflict).toBe(true);
    expect(store.dirty).toBe(true);
    expect(store.draft).toEqual(doc('我的修改'));
  });

  it('保存返回 409 时保留本地修改并进入冲突状态，不覆盖编辑内容', async () => {
    api.detail.mockResolvedValue(detail());
    api.saveDraft.mockRejectedValue({ code: 409, msg: '文档已被他人修改' });
    const store = useDocumentStore();
    await store.openDocument(7);
    store.setContent(doc('我的修改'));

    await store.saveDraft();

    expect(store.conflict).toBe(true);
    expect(store.dirty).toBe(true);
    expect(store.draft).toEqual(doc('我的修改'));
  });

  it('重新载入会放弃本地修改并清除冲突', async () => {
    api.detail.mockResolvedValueOnce(detail()).mockResolvedValueOnce(detail({ lockVersion: 3, content: doc('他人版本') }));
    api.saveDraft.mockRejectedValue({ code: 409, msg: '冲突' });
    const store = useDocumentStore();
    await store.openDocument(7);
    store.setContent(doc('我的修改'));
    await store.saveDraft();

    await store.reload();
    expect(store.conflict).toBe(false);
    expect(store.dirty).toBe(false);
    expect(store.draft).toEqual(doc('他人版本'));
    expect(store.current?.lockVersion).toBe(3);
  });

  it('快速切换文档时，较早请求的迟到响应不会覆盖当前文档', async () => {
    let resolveFirst!: (value: unknown) => void;
    api.detail
      .mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve; }))
      .mockResolvedValueOnce(detail({ id: 8, title: '第二篇' }));
    const store = useDocumentStore();
    const first = store.openDocument(7);
    await store.openDocument(8);
    resolveFirst(detail({ id: 7, title: '第一篇' }));
    await first;
    expect(store.current?.id).toBe(8);
    expect(store.current?.title).toBe('第二篇');
  });

  it('恢复历史修订使用当前 lockVersion，并以恢复后的草稿替换编辑内容', async () => {
    api.detail.mockResolvedValue(detail({ lockVersion: 4, currentRevisionNo: 2 }));
    api.restore.mockResolvedValue(detail({ lockVersion: 5, content: doc('v1 恢复') }));
    const store = useDocumentStore();
    await store.openDocument(7);
    await store.restoreRevision(1);
    expect(api.restore).toHaveBeenCalledWith(7, 1, 4);
    expect(store.draft).toEqual(doc('v1 恢复'));
    expect(store.contentGeneration).toBeGreaterThan(0);
    expect(store.dirty).toBe(false);
  });

  it('有未保存修改时发布前先保存草稿', async () => {
    api.detail.mockResolvedValue(detail());
    api.saveDraft.mockResolvedValue(detail({ lockVersion: 2, content: doc('v2') }));
    api.publish.mockResolvedValue(detail({ lockVersion: 3, status: 'published', currentRevisionNo: 1 }));
    const store = useDocumentStore();
    await store.openDocument(7);
    store.setContent(doc('v2'));
    expect(await store.publish('首版')).toBe(true);
    expect(api.saveDraft).toHaveBeenCalledBefore(api.publish);
    expect(api.publish).toHaveBeenCalledWith(7, '首版', 2);
    expect(store.current?.status).toBe('published');
  });

  it('先保存失败时不发布，并返回失败', async () => {
    api.detail.mockResolvedValue(detail());
    api.saveDraft.mockRejectedValue({ code: 422, msg: '正文过大' });
    const store = useDocumentStore();
    await store.openDocument(7);
    store.setContent(doc('v2'));
    expect(await store.publish('首版')).toBe(false);
    expect(api.publish).not.toHaveBeenCalled();
    expect(store.error).toBe('正文过大');
  });

  it('归档前先保存未保存的修改，并带上 lockVersion', async () => {
    api.detail.mockResolvedValue(detail());
    api.saveDraft.mockResolvedValue(detail({ lockVersion: 2, content: doc('v2') }));
    api.archive.mockResolvedValue(detail({ lockVersion: 2, status: 'archived' }));
    const store = useDocumentStore();
    await store.openDocument(7);
    store.setContent(doc('v2'));
    expect(await store.archive()).toBe(true);
    expect(api.saveDraft).toHaveBeenCalledBefore(api.archive);
    expect(api.archive).toHaveBeenCalledWith(7, 2);
    expect(store.draft).toEqual(doc('v2'));
  });

  it('切换文档或发布会取消挂起的自动保存', async () => {
    vi.useFakeTimers();
    try {
      api.detail.mockResolvedValue(detail());
      api.publish.mockResolvedValue(detail({ status: 'published' }));
      const store = useDocumentStore();
      await store.openDocument(7);
      store.setContent(doc('v2'));
      store.scheduleAutosave(4000);
      api.saveDraft.mockResolvedValue(detail({ lockVersion: 2 }));
      await store.saveDraft();
      await store.publish('x');
      api.saveDraft.mockClear();
      await vi.advanceTimersByTimeAsync(5000);
      expect(api.saveDraft).not.toHaveBeenCalled();
    } finally {
      vi.useRealTimers();
    }
  });

  it('列表按当前范围和目录筛选，切换目录时回到第一页', async () => {
    api.list.mockResolvedValue({ list: [], total: 0, page: 1, pageSize: 20 });
    const store = useDocumentStore();
    store.query.page = 3;
    await store.selectCategory('official_plugin', 12);
    expect(api.list).toHaveBeenLastCalledWith(expect.objectContaining({ scope: 'official_plugin', categoryId: 12, page: 1 }));
  });
});
