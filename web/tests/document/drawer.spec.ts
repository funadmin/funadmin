import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

const msg = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), confirm: vi.fn() }));
vi.mock('element-plus', () => ({ ElMessage: { success: msg.success, error: msg.error }, ElMessageBox: { confirm: msg.confirm } }));
vi.mock('../../../admin/plugins/document/web/src/admin/plugins/document/api', () => ({
  documentApi: {
    revisions: vi.fn().mockResolvedValue({ list: [{ revisionNo: 1, title: 't', changeSummary: '首版', createdBy: 1, createdAt: '2026-11-08 10:00:00' }], total: 1 }),
    revision: vi.fn().mockResolvedValue({ revisionNo: 1, title: 't', summary: '', changeSummary: '', createdAt: '', content: { type: 'doc', content: [] } })
  }
}));
vi.mock('../../../admin/plugins/document/web/src/admin/plugins/document/editor/DocumentPreview.vue', () => ({ default: { template: '<div />' } }));

import Drawer from '../../../admin/plugins/document/web/src/admin/plugins/document/components/DocumentVersionDrawer.vue';
import { nextMermaidId } from '../../../admin/plugins/document/web/src/admin/plugins/document/editor/mermaidId';

const stubs = {
  'el-drawer': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
  'el-button': { emits: ['click'], template: '<button class="restore" @click="$emit(\'click\')"><slot /></button>' },
  'el-empty': true
};

describe('版本历史抽屉', () => {
  it('确认恢复后只发出恢复请求，不自行提示成功或关闭（结果由页面根据真实返回决定）', async () => {
    msg.confirm.mockResolvedValue(true);
    const wrapper = mount(Drawer, { props: { documentId: 7, canRestore: true, modelValue: true }, global: { stubs, directives: { loading: () => {} } } });
    await flushPromises();
    await wrapper.find('button.restore').trigger('click');
    await flushPromises();
    expect(wrapper.emitted('restore')?.[0]).toEqual([1]);
    expect(msg.success).not.toHaveBeenCalled();
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
  });
});

describe('Mermaid 渲染编号', () => {
  it('跨组件实例全局唯一，主预览与抽屉预览不会冲突', () => {
    const ids = new Set(Array.from({ length: 50 }, () => nextMermaidId()));
    expect(ids.size).toBe(50);
  });
});
