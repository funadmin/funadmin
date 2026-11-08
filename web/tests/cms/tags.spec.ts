import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { ElButton, ElTable, ElTableColumn, ElTag } from 'element-plus';
import SourceTagPanel from '../../../admin/plugins/cms/web/src/admin/modules/cms/components/TagPanel.vue';
import PublishedTagPanel from '../../src/admin/modules/cms/components/TagPanel.vue';

const api = vi.hoisted(() => ({ tags: vi.fn(), providers: vi.fn(), tagOptions: vi.fn(), saveTag: vi.fn(), publishTag: vi.fn(), previewTag: vi.fn() }));
vi.mock('../../../admin/plugins/cms/web/src/admin/modules/cms/api', () => ({ cmsApi: api }));
vi.mock('../../src/admin/modules/cms/api', () => ({ cmsApi: api }));
const builtin = { id: 'builtin:content', builtin: true, name: 'content', parameters: { limit: { type: 'integer', default: 12, min: 1, max: 100 } } };
const custom = { id: '12', builtin: false, name: 'company_news', locale: '', current_version: 1, draft_version: 2, draft: { provider: 'content', parameters: { limit: 3 }, template_fragment: '<p>{$item.title}</p>' } };
const dialog = defineComponent({
  props: { modelValue: Boolean, title: String },
  setup(props, { slots }) { return () => props.modelValue ? h('section', { role: 'dialog', 'aria-label': props.title }, [slots.default?.(), slots.footer?.()]) : null; }
});
// Native controls exercise selection and serialization without relying on popup positioning.
const select = defineComponent({
  props: ['modelValue'], emits: ['update:modelValue', 'change'],
  setup(props, { slots, emit }) { return () => h('select', { value: props.modelValue, onChange: (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;
    emit('update:modelValue', value);emit('change', value);
  } }, slots.default?.()); }
});
const option = defineComponent({ props: ['value', 'label'], setup(props) { return () => h('option', { value: props.value }, props.label); } });
const formItem = defineComponent({ props: ['label'], setup(props, { slots }) { return () => h('label', [props.label, slots.default?.()]); } });
const form = defineComponent({ setup(_props, { slots }) { return () => h('form', slots.default?.()); } });
const wrappers: ReturnType<typeof mount>[] = [];
beforeEach(() => {
  vi.clearAllMocks();
  api.tags.mockResolvedValue([builtin, custom]);
  api.providers.mockResolvedValue([{ name: 'content', parameters: builtin.parameters }]);
  api.tagOptions.mockResolvedValue({ models: [{ value: 'article', label: '文章' }], categories: [{ value: '42', label: '新闻栏目', path: '/news/' }] });
});
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); });

describe.each([['源插件', SourceTagPanel], ['运行副本', PublishedTagPanel]] as const)('%s 模板标签列表', (_name, panel) => {
  async function render(can = (_permission: string) => true) {
    const wrapper = mount(panel, {
      props: { scope: { site_id: '7', locale: 'zh-cn' }, can },
      global: {
        components: { ElButton, ElTable, ElTableColumn, ElTag },
        stubs: { ElDialog: dialog, ElAlert: true, ElForm: form, ElFormItem: formItem, ElInput: true, ElInputNumber: true, ElSelect: select, ElOption: option, ElSwitch: true }
      }
    });
    wrappers.push(wrapper);await flushPromises();await nextTick();
    return wrapper;
  }

  it('显示系统内置标识，内置行没有编辑、删除或发布操作，自定义行仍可编辑', async () => {
    const wrapper = await render();
    const row = wrapper.findAll('.el-table__row').find(item => item.text().includes('系统内置'));
    expect(row).toBeDefined();
    expect(row!.text()).toContain('content');
    expect(row!.text()).not.toMatch(/编辑|删除|发布草稿/);
    const customRow = wrapper.findAll('.el-table__row').find(item => item.text().includes('company_news'))!;
    expect(customRow.text()).toContain('编辑');
    await customRow.findAll('button').find(button => button.text() === '编辑')!.trigger('click');
    expect(wrapper.find('[role="dialog"]').attributes('aria-label')).toBe('自定义模板标签');
  });

  it('内置标签只能查看参数，直接调用编辑和发布入口也不发请求', async () => {
    const wrapper = await render();
    const state = (wrapper.vm as any).$.setupState;
    state.open(builtin);
    await state.publish(builtin);
    await state.showPreview(builtin);
    await nextTick();
    expect(wrapper.find('[aria-label="自定义模板标签"]').exists()).toBe(false);
    expect(api.publishTag).not.toHaveBeenCalled();
    expect(api.previewTag).not.toHaveBeenCalled();
    const row = wrapper.findAll('.el-table__row').find(item => item.text().includes('系统内置'))!;
    await row.findAll('button').find(button => button.text() === '查看')!.trigger('click');
    const details = wrapper.get('[aria-label="系统内置标签"]');
    expect(details.text()).toContain('limit');
    expect(details.text()).toContain('12');
    expect(details.text()).not.toContain('保存草稿');
  });

  it('说明列显示内置标签用途与自定义标签备注，查看内置标签时列出参数含义', async () => {
    api.tags.mockResolvedValue([builtin, { ...custom, remark: '首页新闻区块' }]);
    const wrapper = await render();
    const rows = wrapper.findAll('.el-table__row');
    expect(wrapper.findAll('th').map(cell => cell.text())).toContain('说明');
    expect(rows.find(item => item.text().includes('系统内置'))!.text()).toContain('按模型、栏目、关键词查询内容列表');
    expect(rows.find(item => item.text().includes('company_news'))!.text()).toContain('首页新闻区块');
    await rows.find(item => item.text().includes('系统内置'))!.findAll('button').find(button => button.text() === '查看')!.trigger('click');
    const details = wrapper.get('[aria-label="系统内置标签"]');
    expect(details.text()).toContain('按模型、栏目、关键词查询内容列表');
    expect(details.text()).toContain('每次返回的条数');
  });

  it('自定义标签可以填写备注并随草稿保存，编辑时带出原备注', async () => {
    contentProvider();
    api.tags.mockResolvedValue([{ ...custom, remark: '首页新闻区块' }]);
    const wrapper = await render();
    const state = (wrapper.vm as any).$.setupState;
    await wrapper.get('.el-table__row').findAll('button').find(button => button.text() === '编辑')!.trigger('click');await flushPromises();
    const editor = wrapper.get('[aria-label="自定义模板标签"]');
    expect(editor.text()).toContain('备注');
    expect(state.form.remark).toBe('首页新闻区块');
    state.form.remark = '页脚新闻区块';
    await editor.findAll('button').find(button => button.text() === '保存草稿')!.trigger('click');await flushPromises();
    expect(api.saveTag).toHaveBeenCalledWith(expect.objectContaining({ remark: '页脚新闻区块' }));
  });

  it('没有标签查看权限时不读取列表或内置目录', async () => {
    const wrapper = await render(() => false);
    expect(api.tags).not.toHaveBeenCalled();
    expect(api.providers).not.toHaveBeenCalled();
    expect(api.tagOptions).not.toHaveBeenCalled();
    expect(wrapper.findAll('.el-table__row')).toHaveLength(0);
  });

  function contentProvider() {
    api.providers.mockResolvedValue([{ name: 'content', parameters: {
      model: { type: 'string', default: '' }, category: { type: 'string', default: '' }, limit: { type: 'integer', default: 12 }
    } }]);
  }
  async function create(wrapper: ReturnType<typeof mount>) {
    await wrapper.findAll('button').find(button => button.text() === '自定义标签')!.trigger('click');
    await flushPromises();
    return wrapper.get('[aria-label="自定义模板标签"]');
  }
  it('用中文名称选择模型和栏目，保存模型代码与栏目 ID，支持全部选项', async () => {
    contentProvider();
    const wrapper = await render();const editor = await create(wrapper);
    expect(editor.text()).toContain('内容列表');
    const model = editor.get('select[aria-label="内容模型"]');
    const category = editor.get('select[aria-label="所属栏目"]');
    expect(model.text()).toContain('文章');expect(model.text()).toContain('全部模型');
    expect(category.text()).toContain('新闻栏目');expect(category.text()).toContain('全部栏目');
    await model.setValue('article');await category.setValue('42');
    await editor.findAll('button').find(button => button.text() === '保存草稿')!.trigger('click');await flushPromises();
    expect(api.saveTag).toHaveBeenLastCalledWith(expect.objectContaining({ parameters: { model: 'article', category: '42', limit: 12 }, site_id: '7', locale: 'zh-cn' }));
    const all = await create(wrapper);
    await all.get('select[aria-label="内容模型"]').setValue('');await all.get('select[aria-label="所属栏目"]').setValue('');
    await all.findAll('button').find(button => button.text() === '保存草稿')!.trigger('click');await flushPromises();
    expect(api.saveTag).toHaveBeenLastCalledWith(expect.objectContaining({ parameters: { model: '', category: '', limit: 12 } }));
  });
  it('编辑旧配置显示栏目名称并保留原来的路径值和其他参数', async () => {
    contentProvider();
    api.tags.mockResolvedValue([{ ...custom, draft: { ...custom.draft, parameters: { model: 'article', category: '/news/', limit: 3 } } }]);
    const wrapper = await render();
    await wrapper.get('.el-table__row').findAll('button').find(button => button.text() === '编辑')!.trigger('click');await flushPromises();
    const editor = wrapper.get('[aria-label="自定义模板标签"]');
    const category = editor.get('select[aria-label="所属栏目"]');
    expect(category.get('option:checked').text()).toContain('新闻栏目');
    await editor.findAll('button').find(button => button.text() === '保存草稿')!.trigger('click');await flushPromises();
    expect(api.saveTag).toHaveBeenCalledWith(expect.objectContaining({ parameters: { model: 'article', category: '/news/', limit: 3 } }));
  });
  it('选项读取失败时保留旧值并允许重试，切换站点后不沿用旧选项', async () => {
    contentProvider();api.tagOptions.mockRejectedValueOnce(new Error('offline'));
    api.tags.mockResolvedValue([{ ...custom, draft: { ...custom.draft, parameters: { model: 'legacy', category: '99', limit: 3 } } }]);
    const wrapper = await render();
    await wrapper.get('.el-table__row').findAll('button').find(button => button.text() === '编辑')!.trigger('click');await flushPromises();
    const editor = wrapper.get('[aria-label="自定义模板标签"]');
    expect((editor.get('select[aria-label="内容模型"]').element as HTMLSelectElement).value).toBe('legacy');
    expect((editor.get('select[aria-label="所属栏目"]').element as HTMLSelectElement).value).toBe('99');
    await editor.findAll('button').find(button => button.text() === '重新加载选项')!.trigger('click');await flushPromises();
    expect(editor.get('select[aria-label="内容模型"]').text()).toContain('文章');
    await wrapper.setProps({ scope: { site_id: '8', locale: 'en-us' } });await flushPromises();
    expect(wrapper.find('[aria-label="自定义模板标签"]').exists()).toBe(false);
    api.tagOptions.mockResolvedValue({ models: [], categories: [] });
    const empty = await create(wrapper);
    expect(empty.text()).toContain('当前站点暂无可选内容模型');
    expect(empty.get('select[aria-label="所属栏目"]').text()).not.toContain('新闻栏目');
  });
});
