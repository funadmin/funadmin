import { describe, expect, it, vi, beforeEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

const api = vi.hoisted(() => ({
  tree: vi.fn(), list: vi.fn(), detail: vi.fn(), saveDraft: vi.fn(), publish: vi.fn(),
  archive: vi.fn(), restore: vi.fn(), remove: vi.fn(), create: vi.fn(), revisions: vi.fn(), revision: vi.fn(), updateMeta: vi.fn()
}));
const perms = vi.hoisted(() => ({ granted: ['document:document:index', 'document:document:detail', 'document:document:create', 'document:document:update-meta', 'document:document:save-draft', 'document:document:publish', 'document:document:archive', 'document:document:restore'] as string[] }));
vi.mock('../../../admin/plugins/document/web/src/admin/plugins/document/api', () => ({ documentApi: api }));
vi.mock('@/utils/permission', () => ({ hasPermission: (code: string | string[]) => (Array.isArray(code) ? code : [code]).some((c) => perms.granted.includes(c)) }));
// 编辑器/预览依赖真实 DOM 排版，在组件测试里用占位替代，单独由内容测试覆盖。
vi.mock('../../../admin/plugins/document/web/src/admin/plugins/document/editor/DocumentEditor.vue', () => ({ default: { name: 'DocumentEditor', props: ['modelValue', 'editable'], template: '<div class="stub-editor" />' } }));
vi.mock('../../../admin/plugins/document/web/src/admin/plugins/document/editor/DocumentPreview.vue', () => ({ default: { name: 'DocumentPreview', props: ['content'], template: '<div class="stub-preview" />' } }));

import Index from '../../../admin/plugins/document/web/src/admin/plugins/document/Index.vue';
import { useDocumentStore } from '../../../admin/plugins/document/web/src/admin/plugins/document/store';

const doc = { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: '正文' }] }] };
const row = (over: Record<string, unknown> = {}) => ({ id: 1, title: '插件开发快速开始', slug: 'quick', summary: '', scope: 'plugin_dev', pluginCode: '', type: 'guide', status: 'published', appliesTo: 'v1.0', lockVersion: 1, currentRevisionNo: 1, categoryIds: [], updatedAt: '2026-11-06 10:00:00', ...over });
const tree = [
  { id: 1, scope: 'framework', parentId: 0, name: '主框架', slug: 'framework', sortOrder: 10, children: [{ id: 2, scope: 'framework', parentId: 1, name: '架构设计', slug: 'arch', sortOrder: 10, children: [] }] },
  { id: 3, scope: 'plugin_dev', parentId: 0, name: '插件开发', slug: 'plugin-dev', sortOrder: 20, children: [] }
];

const stubs = {
  'el-tree': { props: ['data'], template: '<div class="tree"><slot v-for="n in data" :data="n" :node="{}" /></div>' },
  'el-input': { props: ['modelValue'], emits: ['update:modelValue'], template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
  'el-button': { emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' },
  'el-tag': { template: '<span class="tag"><slot /></span>' },
  'el-radio-group': { props: ['modelValue'], emits: ['update:modelValue'], template: '<div><slot /></div>' },
  'el-radio-button': { props: ['value'], template: '<button class="mode" :data-mode="value" @click="$parent.$emit(\'update:modelValue\', value)"><slot /></button>' },
  'el-empty': { props: ['description'], template: '<div class="empty">{{ description }}</div>' },
  'el-alert': { props: ['title'], template: '<div class="alert">{{ title }}<slot /></div>' },
  'el-select': { props: ['modelValue'], emits: ['update:modelValue', 'change'], template: '<select class="status-filter" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value); $emit(\'change\', $event.target.value)"><slot /></select>' },
  'el-option': { props: ['value', 'label'], template: '<option :value="value">{{ label }}</option>' },
  'el-pagination': true, 'el-drawer': true, 'el-dialog': true, 'el-form': true, 'el-form-item': true
};

const mountPage = async () => {
  const wrapper = mount(Index, { global: { stubs, directives: { loading: () => {} } } });
  await flushPromises();
  return wrapper;
};

describe('文档工作台', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    Object.values(api).forEach((fn) => fn.mockReset());
    api.tree.mockResolvedValue(tree);
    api.list.mockResolvedValue({ list: [row(), row({ id: 2, title: '权限模型', scope: 'framework', status: 'draft' })], total: 2, page: 1, pageSize: 20 });
    api.detail.mockResolvedValue({ ...row(), content: doc, plainText: '正文' });
    perms.granted = ['document:document:index', 'document:document:detail', 'document:document:create', 'document:document:update-meta', 'document:document:save-draft', 'document:document:publish', 'document:document:archive', 'document:document:restore'];
  });

  it('左栏显示四类范围和目录树，中栏显示文档列表', async () => {
    const wrapper = await mountPage();
    for (const label of ['FunAdmin 主框架', '插件开发', '官方插件', '第三方插件']) expect(wrapper.text()).toContain(label);
    expect(wrapper.text()).toContain('架构设计');
    expect(wrapper.text()).toContain('插件开发快速开始');
    expect(wrapper.text()).toContain('权限模型');
  });

  it('点击范围会按该范围重新加载列表', async () => {
    const wrapper = await mountPage();
    await wrapper.find('[data-scope="official_plugin"]').trigger('click');
    await flushPromises();
    expect(api.list).toHaveBeenLastCalledWith(expect.objectContaining({ scope: 'official_plugin', page: 1 }));
  });

  it('点击文档后加载正文并显示标题与元信息', async () => {
    const wrapper = await mountPage();
    await wrapper.find('[data-doc-id="1"]').trigger('click');
    await flushPromises();
    expect(api.detail).toHaveBeenCalledWith(1);
    expect(wrapper.find('.doc-title').text()).toContain('插件开发快速开始');
    expect(wrapper.text()).toContain('v1.0');
  });

  it('编辑/分栏/预览切换决定显示编辑器还是预览', async () => {
    const wrapper = await mountPage();
    await wrapper.find('[data-doc-id="1"]').trigger('click');
    await flushPromises();
    const store = useDocumentStore();
    store.editorMode = 'edit';
    await flushPromises();
    expect(wrapper.find('.stub-editor').exists()).toBe(true);
    expect(wrapper.find('.stub-preview').exists()).toBe(false);
    store.editorMode = 'preview';
    await flushPromises();
    expect(wrapper.find('.stub-editor').exists()).toBe(false);
    expect(wrapper.find('.stub-preview').exists()).toBe(true);
  });

  it('冲突时提示重新载入，而不是静默覆盖', async () => {
    const wrapper = await mountPage();
    await wrapper.find('[data-doc-id="1"]').trigger('click');
    await flushPromises();
    useDocumentStore().conflict = true;
    await flushPromises();
    expect(wrapper.find('.alert').text()).toContain('已被他人修改');
    expect(wrapper.text()).toContain('重新载入');
  });

  it('只有查看权限时不显示新建、保存和发布，并且编辑器只读', async () => {
    perms.granted = ['document:document:index', 'document:document:detail'];
    const wrapper = await mountPage();
    await wrapper.find('[data-doc-id="1"]').trigger('click');
    await flushPromises();
    const buttons = wrapper.findAll('button').map((b) => b.text());
    expect(buttons).not.toContain('新建文档');
    expect(buttons).not.toContain('保存');
    expect(buttons).not.toContain('发布');
    useDocumentStore().editorMode = 'edit';
    await flushPromises();
    expect(wrapper.findComponent({ name: 'DocumentEditor' }).props('editable')).toBe(false);
  });

  it('列表为空时显示空状态', async () => {
    api.list.mockResolvedValue({ list: [], total: 0, page: 1, pageSize: 20 });
    const wrapper = await mountPage();
    expect(wrapper.find('.empty').text()).toContain('暂无文档');
  });

  it('默认不含归档文档，可通过状态筛选查看已归档', async () => {
    const wrapper = await mountPage();
    await wrapper.find('select.status-filter').setValue('archived');
    await flushPromises();
    expect(api.list).toHaveBeenLastCalledWith(expect.objectContaining({ status: 'archived', page: 1 }));
  });

  it('有目录管理权限时显示管理目录入口', async () => {
    perms.granted = ['document:document:index', 'document:category:create'];
    const wrapper = await mountPage();
    expect(wrapper.find('[data-testid="manage-categories"]').exists()).toBe(true);
    perms.granted = ['document:document:index'];
    const viewer = await mountPage();
    expect(viewer.find('[data-testid="manage-categories"]').exists()).toBe(false);
  });
});
