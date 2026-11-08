import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ElementPlus, { ElMessage, ElMessageBox } from 'element-plus';
import Workspace from './ButtonsWorkspace.vue';
import listPreviewSource from './ListPreview.vue?raw';
import listPreviewZoneSource from './ListPreviewZone.vue?raw';
import { defaultListButtons, listButtonKeys } from '../../runtime/listButtonHost';
import type { FormListActionMetadata, FormListButton, FormListButtonLocation, FormListConfiguration, FormListCustomAction } from '../../schema/types';

afterEach(() => { document.body.innerHTML = ''; });
const labels: Record<FormListButtonLocation, string> = { toolbar: '顶部操作', row: '行操作', categoryToolbar: '分类顶部操作', categoryNode: '分类节点操作' };
const locations = (Object.keys(labels) as FormListButtonLocation[]).map(key => ({ key, label: labels[key], fields: ['id', 'status', 'title'], builtinKeys: listButtonKeys[key], resourceEnabled: true }));
const tools = [{ key: 'refresh', label: '刷新' }, { key: 'search', label: '搜索' }, { key: 'columns', label: '列设置' }, { key: 'density', label: '密度' }, { key: 'fullscreen', label: '全屏' }] as const;
const columns = [{ field: 'title', label: '标题' }, { field: 'status', label: '状态', options: [{ label: '待审核', value: 'pending' }, { label: '已审核', value: 'approved' }, { label: '已完成', value: 'done' }] }];
const render = (modelValue: FormListConfiguration = {}) => mount(Workspace, { attachTo: document.body, props: { modelValue, locations, tools, filterFields: [], categoryFields: [], resources: {}, columns }, global: { plugins: [ElementPlus] } });
const location = (wrapper: ReturnType<typeof render>, key: FormListButtonLocation) => wrapper.get(`[data-location="${key}"]`);
const radio = (wrapper: ReturnType<typeof render>, key: FormListButtonLocation, text: string) => location(wrapper, key).findAll('[role="radio"]').find(item => item.text() === text)!;
const inspectorButton = (text: string) => [...document.querySelectorAll('.button-inspector button')].find(item => item.textContent?.trim() === text) as HTMLElement;
const lastButtons = (wrapper: ReturnType<typeof render>) => wrapper.emitted('updateButtons')!.at(-1)! as [FormListButtonLocation, FormListButton[] | undefined];

describe('按钮与工具工作区', () => {
  it('每个位置的继承默认 / 自定义 / 全部隐藏分别对应 undefined / 数组 / 空数组', async () => {
    const wrapper = render({ buttons: { row: [] } });
    expect(radio(wrapper, 'toolbar', '继承默认').attributes('aria-checked')).toBe('true');
    expect(radio(wrapper, 'row', '全部隐藏').attributes('aria-checked')).toBe('true');
    await radio(wrapper, 'toolbar', '全部隐藏').trigger('click');
    expect(lastButtons(wrapper)).toEqual(['toolbar', []]);
    await radio(wrapper, 'toolbar', '自定义').trigger('click');
    expect(lastButtons(wrapper)).toEqual(['toolbar', defaultListButtons('toolbar')]);
    await wrapper.setProps({ modelValue: { buttons: { toolbar: [{ id: 'a', label: 'A', action: { type: 'refresh' } }] } } });
    expect(radio(wrapper, 'toolbar', '自定义').attributes('aria-checked')).toBe('true');
    await radio(wrapper, 'toolbar', '继承默认').trigger('click');
    expect(lastButtons(wrapper)).toEqual(['toolbar', undefined]);
    wrapper.unmount();
  });
  it('点击预览中的按钮即选中并在右栏编辑，应用写回该位置', async () => {
    const wrapper = render();
    await wrapper.get('[data-preview-button="row:edit"]').trigger('click'); await flushPromises();
    expect(wrapper.get('[data-preview-button="row:edit"]').classes()).toContain('is-selected');
    expect(location(wrapper, 'row').get('[data-button-id="edit"]').classes()).toContain('is-selected');
    const inspector = document.querySelector('.button-inspector')!;
    expect(inspector.textContent).toContain('编辑');
    const name = inspector.querySelector('[aria-label="显示名称"]') as HTMLInputElement;
    name.value = '修改'; name.dispatchEvent(new Event('input', { bubbles: true })); await flushPromises();
    inspectorButton('应用').click(); await flushPromises();
    const [key, value] = lastButtons(wrapper);
    expect(key).toBe('row');
    expect(value!.find(item => item.id === 'edit')).toMatchObject({ label: '修改', action: { type: 'builtin', key: 'edit' } });
    wrapper.unmount();
  });
  it('右栏五个页签承载原抽屉全部字段，告警置顶，保留模拟预览', async () => {
    const wrapper = render({ buttons: { toolbar: [{ id: 'gone', label: '失效', action: { type: 'registered', key: 'missing', capabilityVersion: 'v1' } }] } });
    await wrapper.get('[data-preview-button="toolbar:gone"]').trigger('click'); await flushPromises();
    const inspector = document.querySelector('.button-inspector')!;
    expect([...inspector.querySelectorAll('.el-tabs__item')].map(item => item.textContent?.trim())).toEqual(['基本', '条件', '交互', '成功后', '高级']);
    const text = inspector.textContent ?? '';
    for (const label of ['动作', '显示名称', '图标', '颜色', '尺寸', '排序', '显示位置', '最少选择', '最多选择', '清除数量约束', '启用显示', '提示 tips', '显示条件', '禁用条件', '禁用', '禁用原因', '交互', '成功提示', '成功刷新', '清空选择', '关闭容器', '附加权限', '目录版本', '稳定 ID', '模拟预览'])
      expect(text, label).toContain(label);
    const alert = inspector.querySelector('.el-alert')!;
    expect(alert.textContent).toContain('未注册、无权限或目录不可用');
    expect(alert.compareDocumentPosition(inspector.querySelector('.el-tabs')!) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    wrapper.unmount();
  });
  it('左栏与预览拖拽改顺序并写回 order', async () => {
    const wrapper = render();
    const rows = location(wrapper, 'row');
    await rows.get('[data-button-id="delete"]').trigger('dragstart');
    await rows.get('[data-button-id="edit"]').trigger('drop');
    expect(lastButtons(wrapper)[1]!.slice(0, 3).map(item => [item.id, item.order])).toEqual([['delete', 0], ['edit', 1], ['detail', 2]]);
    await wrapper.get('[data-preview-button="toolbar:refresh"]').trigger('dragstart');
    await wrapper.get('[data-preview-button="toolbar:create"]').trigger('drop');
    const toolbar = lastButtons(wrapper);
    expect(toolbar[0]).toBe('toolbar');
    expect(toolbar[1]![0]).toMatchObject({ id: 'refresh', order: 0 });
    expect(toolbar[1]!.map(item => item.order)).toEqual(toolbar[1]!.map((_, index) => index));
    wrapper.unmount();
  });
  it('通用工具开关同步预览图标', async () => {
    const wrapper = render({ tools: { density: false } });
    expect(wrapper.get('[data-preview-tool="density"]').classes()).toContain('is-off');
    expect(wrapper.get('[data-preview-tool="refresh"]').classes()).not.toContain('is-off');
    await wrapper.get('[data-tool-switch="refresh"]').trigger('click');
    expect(wrapper.emitted('updateTools')!.at(-1)).toEqual([{ density: false, refresh: false }]);
    await wrapper.setProps({ modelValue: { tools: { density: false, refresh: false } } });
    expect(wrapper.get('[data-preview-tool="refresh"]').classes()).toContain('is-off');
    wrapper.unmount();
  });
  it('未选中行 / 选中 2 行切换按 selection.min/max 体现禁用', async () => {
    const wrapper = render({ buttons: { toolbar: [{ id: 'pair', label: '成对', action: { type: 'refresh' }, selection: { min: 2 } }, { id: 'single', label: '单条', action: { type: 'refresh' }, selection: { max: 1 } }] } });
    const disabled = (id: string) => wrapper.get(`[data-preview-button="toolbar:${id}"]`).classes().includes('is-disabled');
    expect([disabled('pair'), disabled('single')]).toEqual([true, false]);
    await wrapper.findAll('[data-selection-toggle] [role="radio"]').find(item => item.text() === '选中 2 行')!.trigger('click');
    expect([disabled('pair'), disabled('single')]).toEqual([false, true]);
    expect(wrapper.findAll('[data-preview-row] input[type="checkbox"]:checked')).toHaveLength(2);
    wrapper.unmount();
  });
  it('示例行套用禁用条件与显示条件', () => {
    const wrapper = render({ buttons: { row: [{ id: 'del', label: '删除', action: { type: 'builtin', key: 'delete' }, disabledWhen: { field: 'status', op: 'eq', value: 'done' } }, { id: 'audit', label: '审核', action: { type: 'builtin', key: 'edit' }, visibleWhen: { field: 'status', op: 'eq', value: 'pending' } }] } });
    const rows = wrapper.findAll('[data-preview-row]');
    expect(rows).toHaveLength(3);
    expect(rows.map(row => row.get('[data-preview-button="row:del"]').classes().includes('is-disabled'))).toEqual([false, false, true]);
    expect(rows.map(row => row.get('[data-preview-button="row:audit"]').classes().includes('is-hidden'))).toEqual([false, true, true]);
    expect(rows[2].text()).toContain('已完成');
    wrapper.unmount();
  });
  it('分类按钮区域仅在启用分类树后出现在左侧；未启用时不占左栏，只在工具栏上方显示一行提示', async () => {
    const wrapper = render();
    expect(wrapper.find('[data-preview-button^="categoryToolbar:"]').exists()).toBe(false);
    expect(wrapper.find('[data-preview-category]').exists()).toBe(false);
    expect(wrapper.find('.list-preview').classes()).toContain('list-preview--no-aside');
    const hint = wrapper.get('[data-preview-category-hint]');
    expect(hint.text()).toContain('分类管理');
    expect(hint.element.parentElement?.classList.contains('list-preview__main')).toBe(true);
    expect(hint.element.compareDocumentPosition(wrapper.get('[data-zone="toolbar"]').element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    await wrapper.setProps({ modelValue: { leftTree: { enabled: true, source: { type: 'current' }, mapping: { valueField: 'id', labelField: 'title', targetField: 'category_id' } } } });
    expect(wrapper.find('[data-preview-category] [data-preview-button="categoryToolbar:create"]').exists()).toBe(true);
    expect(wrapper.find('[data-preview-category-hint]').exists()).toBe(false);
    wrapper.unmount();
  });
  it('行操作按钮在操作列内单行排列，不纵向堆叠（表格区域可横向滚动）', () => {
    const wrapper = render();
    const zones = wrapper.findAll('[data-preview-row] [data-zone="row"]');
    expect(zones).toHaveLength(3);
    for (const zone of zones) expect(zone.classes()).toContain('is-inline');
    expect(wrapper.get('[data-zone="toolbar"]').classes()).not.toContain('is-inline');
    expect(listPreviewZoneSource).toMatch(/\.preview-zone\.is-inline\s*\{[^}]*flex-wrap:\s*nowrap/);
    expect(listPreviewSource).toMatch(/\.list-preview__table-wrap\s*\{[^}]*overflow-x:\s*auto/);
    wrapper.unmount();
  });
  it('页签行「刷新目录」触发目录重新加载并显示目录规模', async () => {
    const wrapper = mount(Workspace, { attachTo: document.body, props: { modelValue: {}, locations, tools, filterFields: [], categoryFields: [], resources: {}, columns, catalogCount: 3 }, global: { plugins: [ElementPlus] } });
    const catalog = wrapper.get('[data-action-catalog]');
    expect(catalog.text()).toContain('3');
    await catalog.findAll('button').find(item => item.text() === '刷新目录')!.trigger('click');
    expect(wrapper.emitted('reloadCatalog')).toHaveLength(1);
    wrapper.unmount();
  });
  it('添加按钮先在右栏起草，应用后追加并保持选中', async () => {
    const wrapper = render({ buttons: { toolbar: [] } });
    await location(wrapper, 'toolbar').findAll('button').find(item => item.text().includes('添加按钮'))!.trigger('click'); await flushPromises();
    expect(wrapper.emitted('updateButtons')).toBeUndefined();
    inspectorButton('应用').click(); await flushPromises();
    const [key, value] = lastButtons(wrapper);
    expect(key).toBe('toolbar');
    expect(value).toHaveLength(1);
    expect(value![0]).toMatchObject({ label: '新按钮', action: { type: 'refresh' } });
    await wrapper.setProps({ modelValue: { buttons: { toolbar: value } } }); await flushPromises();
    expect(location(wrapper, 'toolbar').get(`[data-button-id="${value![0].id}"]`).classes()).toContain('is-selected');
    wrapper.unmount();
  });
  it('切换到另一位置或再次添加都开启新草稿，不串用上一个未应用的草稿', async () => {
    const wrapper = render();
    await location(wrapper, 'toolbar').findAll('button').find(item => item.text().includes('添加按钮'))!.trigger('click'); await flushPromises();
    const name = () => document.querySelector('.button-inspector [aria-label="显示名称"]') as HTMLInputElement;
    name().value = '草稿'; name().dispatchEvent(new Event('input', { bubbles: true })); await flushPromises();
    await location(wrapper, 'row').findAll('button').find(item => item.text().includes('添加按钮'))!.trigger('click'); await flushPromises();
    expect(name().value).toBe('新按钮');
    expect(document.querySelector('.button-inspector')!.textContent).toContain('行操作');
    name().value = '草稿'; name().dispatchEvent(new Event('input', { bubbles: true })); await flushPromises();
    await location(wrapper, 'row').findAll('button').find(item => item.text().includes('添加按钮'))!.trigger('click'); await flushPromises();
    expect(name().value).toBe('新按钮');
    wrapper.unmount();
  });
  it('窄屏 <1100px：右栏改为选中按钮时弹出的抽屉', async () => {
    const original = window.matchMedia;
    window.matchMedia = ((query: string) => ({ matches: query.includes('1099'), media: query, addEventListener: () => undefined, removeEventListener: () => undefined, addListener: () => undefined, removeListener: () => undefined, onchange: null, dispatchEvent: () => false })) as never;
    try {
      const wrapper = render();
      expect(wrapper.find('.buttons-workspace__right').exists()).toBe(false);
      expect(document.querySelector('.button-inspector')).toBeNull();
      await wrapper.get('[data-preview-button="row:edit"]').trigger('click'); await flushPromises();
      const drawer = document.querySelector('.buttons-workspace__drawer')!;
      expect(drawer).toBeTruthy();
      expect(drawer.querySelector('.button-inspector')!.textContent).toContain('编辑');
      wrapper.unmount();
    } finally { window.matchMedia = original; }
  });
  it('有未应用修改时拖拽排序只同步 order，应用不会写回旧排序', async () => {
    const wrapper = render();
    await wrapper.get('[data-preview-button="row:edit"]').trigger('click'); await flushPromises();
    const name = document.querySelector('.button-inspector [aria-label="显示名称"]') as HTMLInputElement;
    name.value = '改名'; name.dispatchEvent(new Event('input', { bubbles: true })); await flushPromises();
    await wrapper.get('[data-preview-button="row:delete"]').trigger('dragstart');
    await wrapper.get('[data-preview-button="row:edit"]').trigger('drop');
    const reordered = lastButtons(wrapper)[1]!;
    await wrapper.setProps({ modelValue: { buttons: { row: reordered } } }); await flushPromises();
    expect((document.querySelector('.button-inspector [aria-label="显示名称"]') as HTMLInputElement).value).toBe('改名');
    inspectorButton('应用').click(); await flushPromises();
    const saved = lastButtons(wrapper)[1]!;
    expect(saved.find(item => item.id === 'edit')).toMatchObject({ label: '改名', order: reordered.find(item => item.id === 'edit')!.order });
    expect(saved.map(item => item.id)).toEqual(reordered.map(item => item.id));
    wrapper.unmount();
  });
});

describe('自定义动作', () => {
  afterEach(() => vi.restoreAllMocks());
  const approve: FormListCustomAction = { key: 'approve', label: '审批', locations: ['row'], batch: false, requiresConfirmation: true, effect: 'write', parameters: [{ name: 'reason', type: 'string' }] };
  const settle: FormListCustomAction = { key: 'settle', label: '结算', locations: ['toolbar'], batch: true, requiresConfirmation: false, effect: 'write', parameters: [] };
  const generated: FormListActionMetadata = { permission: 'generated:fun-order:action-settle', capabilityVersion: '1', parameters: [], parameterTypes: {}, locations: ['toolbar'], targets: ['selection'], batch: true, effect: 'write', resultContract: 'json', requiresConfirmation: false };
  const withCatalog = locations.map(item => item.key === 'toolbar' ? { ...item, actions: { 'generated.fun-order.settle': generated } } : item);
  const mountCustom = (modelValue: FormListConfiguration, extra: Record<string, unknown> = {}) => mount(Workspace, { attachTo: document.body, props: { modelValue, locations: withCatalog, tools, filterFields: [], categoryFields: [], resources: {}, columns, customActionEntity: 'fun-order', ...extra } as never, global: { plugins: [ElementPlus] } });
  const section = (wrapper: ReturnType<typeof mountCustom>) => wrapper.get('[data-custom-actions]');
  async function openSelect(label: string) {
    const input = document.querySelector(`.button-inspector [aria-label="${label}"]`) as HTMLElement;
    (input.closest('.el-select')!.querySelector('.el-select__wrapper') as HTMLElement).click(); await flushPromises();
    return document.getElementById(input.getAttribute('aria-controls')!)!;
  }
  const dialog = () => document.querySelector('.custom-action-dialog') as HTMLElement;
  async function typeIn(root: Element, label: string, value: string) { const el = root.querySelector(`[aria-label="${label}"]`) as HTMLInputElement; el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); await flushPromises(); }
  const dialogButton = (text: string) => [...dialog().querySelectorAll('button')].find(item => item.textContent?.trim() === text) as HTMLElement;

  it('左栏列出本模块自定义动作，未生成的标「待生成」；新建写入 list.customActions', async () => {
    const wrapper = mountCustom({ customActions: [approve, settle] });
    const items = section(wrapper).findAll('[data-custom-action]');
    expect(items.map(item => item.attributes('data-custom-action'))).toEqual(['approve', 'settle']);
    expect(items[0].text()).toContain('待生成');
    expect(items[1].text()).not.toContain('待生成');
    await section(wrapper).findAll('button').find(item => item.text().includes('新建自定义动作'))!.trigger('click'); await flushPromises();
    await typeIn(dialog(), '名称', 'Refund');
    dialogButton('保存').click(); await flushPromises();
    expect(wrapper.emitted('updateCustomActions')!.at(-1)).toEqual([[approve, settle, { key: 'refund', label: 'Refund', locations: ['row'], batch: false, requiresConfirmation: true, effect: 'write', parameters: [] }], undefined]);
    wrapper.unmount();
  });
  it('动作下拉含「自定义动作」分组（待生成标记、按位置过滤）；选中后绑定注册键并出现参数绑定', async () => {
    const wrapper = mountCustom({ customActions: [approve, settle] });
    await wrapper.get('[data-preview-button="row:edit"]').trigger('click'); await flushPromises();
    const list = await openSelect('动作');
    const group = [...list.querySelectorAll('.el-select-group__wrap')].find(item => item.textContent?.includes('自定义动作'))!;
    expect(group).toBeTruthy();
    const options = [...group.querySelectorAll('.el-select-dropdown__item')].map(item => item.textContent?.trim());
    expect(options).toEqual(['审批待生成', '＋ 新建自定义动作']);
    ([...group.querySelectorAll('.el-select-dropdown__item')][0] as HTMLElement).click(); await flushPromises();
    const inspector = document.querySelector('.button-inspector')!;
    expect(inspector.textContent).toContain('generated.fun-order.approve');
    expect(inspector.textContent).toContain('参数绑定');
    expect(inspector.textContent).toContain('reason');
    inspectorButton('应用').click(); await flushPromises();
    expect(lastButtons(wrapper)[1]!.find(item => item.id === 'edit')).toMatchObject({ action: { type: 'registered', key: 'generated.fun-order.approve', capabilityVersion: '1' }, permission: 'generated:fun-order:action-approve', params: { reason: { source: 'form', field: 'reason' } }, interaction: { type: 'form' } });
    wrapper.unmount();
  });
  it('顶部操作只列出含顶部位置的自定义动作，已生成的不标待生成', async () => {
    const wrapper = mountCustom({ customActions: [approve, settle] });
    await wrapper.get('[data-preview-button="toolbar:create"]').trigger('click'); await flushPromises();
    const list = await openSelect('动作');
    const group = [...list.querySelectorAll('.el-select-group__wrap')].find(item => item.textContent?.includes('自定义动作'))!;
    expect([...group.querySelectorAll('.el-select-dropdown__item')].map(item => item.textContent?.trim())).toEqual(['结算', '＋ 新建自定义动作']);
    expect(list.textContent).not.toContain('注册动作 · generated.fun-order.settle');
    wrapper.unmount();
  });
  it('从下拉「＋ 新建自定义动作」创建后自动绑定当前按钮', async () => {
    const wrapper = mountCustom({ customActions: [] });
    await wrapper.get('[data-preview-button="row:edit"]').trigger('click'); await flushPromises();
    const list = await openSelect('动作');
    ([...list.querySelectorAll('.el-select-dropdown__item')].find(item => item.textContent?.trim() === '＋ 新建自定义动作') as HTMLElement).click(); await flushPromises();
    expect(wrapper.emitted('updateButtons')).toBeUndefined();
    await typeIn(dialog(), '名称', 'Ship');
    dialogButton('保存').click(); await flushPromises();
    const [actions] = wrapper.emitted('updateCustomActions')!.at(-1)! as [FormListCustomAction[]];
    expect(actions.map(item => item.key)).toEqual(['ship']);
    await wrapper.setProps({ modelValue: { customActions: actions } }); await flushPromises();
    const draft = (wrapper.findComponent({ name: 'ButtonInspector' }).vm as any).draft;
    expect(draft.action).toEqual({ type: 'registered', key: 'generated.fun-order.ship', capabilityVersion: '1' });
    wrapper.unmount();
  });
  it('删除自定义动作需确认，并一并移除绑定它的按钮', async () => {
    const bound: FormListButton = { id: 'b1', label: '审批', action: { type: 'registered', key: 'generated.fun-order.approve', capabilityVersion: '1' }, permission: 'generated:fun-order:action-approve', params: { reason: { source: 'literal', value: '' } } };
    const keep: FormListButton = { id: 'k', label: '刷新', action: { type: 'refresh' } };
    const wrapper = mountCustom({ customActions: [approve], buttons: { row: [bound, keep] } });
    const confirm = vi.spyOn(ElMessageBox, 'confirm').mockRejectedValueOnce('cancel').mockResolvedValueOnce('confirm' as never);
    const remove = () => section(wrapper).get('[data-custom-action="approve"]').findAll('button').find(item => item.text().includes('删除'))!;
    await remove().trigger('click'); await flushPromises();
    expect(confirm).toHaveBeenCalledTimes(1);
    expect(String(confirm.mock.calls[0][0])).toContain('1');
    expect(wrapper.emitted('updateCustomActions')).toBeUndefined();
    await remove().trigger('click'); await flushPromises();
    expect(wrapper.emitted('updateCustomActions')!.at(-1)).toEqual([undefined, { row: [keep] }]);
    wrapper.unmount();
  });
  it('编辑动作参数后同步绑定按钮：新参数绑定默认常量并提示，删除的参数移除绑定', async () => {
    const bound: FormListButton = { id: 'b1', label: '审批', action: { type: 'registered', key: 'generated.fun-order.approve', capabilityVersion: '1' }, permission: 'generated:fun-order:action-approve', params: { reason: { source: 'literal', value: '同意' } } };
    const wrapper = mountCustom({ customActions: [approve], buttons: { row: [bound] } });
    const notice = vi.spyOn(ElMessage, 'warning').mockImplementation((() => undefined) as never);
    await section(wrapper).get('[data-custom-action="approve"]').findAll('button').find(item => item.text().includes('编辑'))!.trigger('click'); await flushPromises();
    const edited: FormListCustomAction = { ...approve, parameters: [{ name: 'level', type: 'integer' }] };
    wrapper.findComponent({ name: 'CustomActionForm' }).vm.$emit('submit', edited); await flushPromises();
    expect(wrapper.emitted('updateCustomActions')!.at(-1)).toEqual([[edited], { row: [{ ...bound, params: { level: { source: 'literal', value: 0 } } }] }]);
    expect(notice).toHaveBeenCalledTimes(1);
    expect(String((notice.mock.calls[0][0] as { message?: string })?.message ?? notice.mock.calls[0][0])).toContain('level');
    wrapper.unmount();
  });
  it('插件目标不提供新建入口并说明原因', async () => {
    const wrapper = mountCustom({}, { customActionsBlocked: true });
    expect(section(wrapper).text()).toContain('插件');
    expect(section(wrapper).findAll('button').some(item => item.text().includes('新建自定义动作'))).toBe(false);
    await wrapper.get('[data-preview-button="row:edit"]').trigger('click'); await flushPromises();
    const list = await openSelect('动作');
    expect(list.textContent).not.toContain('＋ 新建自定义动作');
    wrapper.unmount();
  });
});
