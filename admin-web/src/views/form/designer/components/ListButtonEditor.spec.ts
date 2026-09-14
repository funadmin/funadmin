import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
afterEach(() => { document.body.innerHTML = ''; });
import ElementPlus from 'element-plus';
import Editor from './ListButtonEditor.vue';
import type { FormListButton } from '../../schema/types';
const button: FormListButton = { id: 'edit', label: '编辑', action: { type: 'builtin', key: 'edit' } };
const render = () => mount(Editor, { props: { title: '行操作', location: 'row', modelValue: [button] }, global: { plugins: [ElementPlus] } });
const resources = { customer: { type: 'navigate', permission: 'customer:view', capabilityVersion: 'v2', route: 'customer', params: ['code'], query: ['keyword'] } };
async function clickText(wrapper: ReturnType<typeof mount>, text: string) {
  await wrapper.findAll('button').find(item => item.text() === text)!.trigger('click');
  await flushPromises();
}
async function choose(label: string, option: string) {
  const input = document.querySelector(`[aria-label="${label}"]`) as HTMLElement;
  expect(input, label).toBeTruthy(); (input.closest('.el-select')?.querySelector('.el-select__wrapper') as HTMLElement ?? input).click(); await flushPromises();
  const listId = input.getAttribute('aria-controls');
  const scope = listId ? document.getElementById(listId)! : document;
  const item = [...scope.querySelectorAll('.el-select-dropdown__item')].find(item => item.textContent?.trim() === option) as HTMLElement;
  expect(item, `${option}: ${document.body.textContent}`).toBeTruthy(); item.click(); await flushPromises();
}
async function drawerClick(text: string) {
  const button = [...document.querySelectorAll('.el-drawer button')].find(item => item.textContent?.trim() === text) as HTMLElement;
  expect(button, text).toBeTruthy(); button.click(); await flushPromises();
}
describe('按钮配置编辑器', () => {
  it('顶部数量可配置并重载，倒置范围拒绝，清除恢复实际能力限制', async () => {
    const wrapper = mount(Editor, { attachTo: document.body, props: { title: '顶部操作', location: 'toolbar', modelValue: [{ ...button, action: { type: 'refresh' } }] }, global: { plugins: [ElementPlus] } });
    await clickText(wrapper, '配置');
    for (const [label, value] of [['最少选择数量', '2'], ['最多选择数量', '3']]) {
      const input = document.querySelector(`[aria-label="${label}"]`) as HTMLInputElement;
      expect(input).toBeTruthy(); input.value = value; input.dispatchEvent(new Event('input', { bubbles: true })); input.dispatchEvent(new Event('change', { bubbles: true })); await flushPromises();
    }
    await drawerClick('应用'); const saved = wrapper.emitted('update')!.at(-1)![0] as FormListButton[];
    expect(saved[0].selection).toEqual({ min: 2, max: 3 });
    await wrapper.setProps({ modelValue: saved }); await clickText(wrapper, '配置');
    const state = (wrapper.vm as any).$.setupState; state.draft.selection = { min: 4, max: 3 }; state.save(); expect(wrapper.emitted('update')).toHaveLength(1);
    await drawerClick('清除数量约束'); await drawerClick('应用'); expect((wrapper.emitted('update')!.at(-1)![0] as FormListButton[])[0].selection).toBeUndefined(); wrapper.unmount();
  });
  it('注册元数据自动生成表单，重命名输入同步绑定，抽屉模拟无副作用', async () => {
    const actions = { approve: { permission: 'approve', capabilityVersion: 'v1', parameters: ['reason'], parameterTypes: { reason: 'string' }, locations: ['row'], targets: ['record'], batch: false, effect: 'write', resultContract: 'json', requiresConfirmation: true } };
    const wrapper = mount(Editor, { attachTo: document.body, props: { title: '行操作', location: 'row', modelValue: [button], actions } as never, global: { plugins: [ElementPlus] } });
    const open = vi.spyOn(window, 'open');
    await clickText(wrapper, '配置'); await choose('动作', '注册动作 · approve');
    const input = document.querySelector('[aria-label="输入字段 1 名称"]') as HTMLInputElement;
    input.value = 'memo'; input.dispatchEvent(new Event('input', { bubbles: true })); await flushPromises();
    const radio = [...document.querySelectorAll('.el-radio')].find(item => item.textContent?.trim() === '抽屉')! as HTMLElement;
    radio.click(); await flushPromises();
    await drawerClick('模拟预览');
    expect(document.body.textContent).toContain('模拟交互：编辑');
    expect(open).not.toHaveBeenCalled();
    const dialogs = [...document.querySelectorAll('.el-drawer')];
    (dialogs.at(-1)!.querySelector('.el-drawer__close-btn') as HTMLElement).click(); await flushPromises();
    await drawerClick('应用');
    expect((wrapper.emitted('update')!.at(-1)![0] as FormListButton[])[0]).toMatchObject({ interaction: { type: 'form', presentation: 'drawer', fields: [{ name: 'memo', type: 'input' }] }, params: { reason: { source: 'form', field: 'memo' } }, permission: 'approve' });
    wrapper.unmount(); open.mockRestore();
  });
  it('拒绝空条件组；切换交互后返回保留原输入字段', async () => {
    const initial = { ...button, interaction: { type: 'form', presentation: 'drawer', fields: [{ name: 'memo', label: '备注', type: 'textarea' }] } } as FormListButton;
    const wrapper = mount(Editor, { attachTo: document.body, props: { title: '行操作', location: 'row', modelValue: [initial], fields: ['status'] }, global: { plugins: [ElementPlus] } });
    await clickText(wrapper, '配置'); await choose('交互', '确认'); await choose('交互', '参数表单');
    (wrapper.vm as any).$.setupState.draft.visibleWhen = { op: 'and', conditions: [] };
    (wrapper.vm as any).$.setupState.save(); expect(wrapper.emitted('update')).toBeUndefined();
    (wrapper.vm as any).$.setupState.draft.visibleWhen = undefined; await drawerClick('应用');
    expect((wrapper.emitted('update')!.at(-1)![0] as FormListButton[])[0].interaction).toEqual(initial.interaction); wrapper.unmount();
  });
  it('显示条件可视编辑组合规则并保存重载', async () => {
    const wrapper = mount(Editor, { attachTo: document.body, props: { title: '行操作', location: 'row', modelValue: [button], fields: ['status'] }, global: { plugins: [ElementPlus] } });
    await clickText(wrapper, '配置'); await choose('显示条件规则', '全部满足');
    await drawerClick('添加条件'); await choose('条件字段', 'status');
    await drawerClick('应用');
    const saved = wrapper.emitted('update')!.at(-1)![0] as FormListButton[];
    expect(saved[0].visibleWhen).toEqual({ op: 'and', conditions: [{ field: 'status', op: 'eq', value: '' }] });
    await wrapper.setProps({ modelValue: JSON.parse(JSON.stringify(saved)) }); await clickText(wrapper, '配置'); await drawerClick('应用');
    expect(wrapper.emitted('update')!.at(-1)![0]).toEqual(saved); wrapper.unmount();
  });
  it('真实抽屉选择导航和字段，应用后重载保留版本绑定，切换再返回不丢草稿', async () => {
    const wrapper = mount(Editor, { attachTo: document.body, props: { title: '行操作', location: 'row', modelValue: [button], fields: ['code', 'title'], resources } as never, global: { plugins: [ElementPlus] } });
    await clickText(wrapper, '配置');
    await choose('动作', '站内导航 · customer');
    await choose('参数 code 来源', '当前记录');
    await choose('参数 code 字段', 'code');
    await choose('动作', '刷新');
    await choose('动作', '站内导航 · customer');
    await drawerClick('应用');
    const saved = wrapper.emitted('update')!.at(-1)![0] as FormListButton[];
    expect(saved[0]).toMatchObject({ id: 'edit', action: { type: 'navigate', key: 'customer', capabilityVersion: 'v2' }, permission: 'customer:view', params: { code: { source: 'row', field: 'code' } } });
    await wrapper.setProps({ modelValue: JSON.parse(JSON.stringify(saved)) });
    await clickText(wrapper, '配置'); await drawerClick('应用');
    expect(wrapper.emitted('update')!.at(-1)![0]).toEqual(saved);
    wrapper.unmount();
  });
  it.each(['row', 'categoryNode', 'toolbar'] as const)('%s 仅提供真实上下文来源，复制与导出可保存', async location => {
    const wrapper = mount(Editor, { attachTo: document.body, props: { title: '按钮', location, modelValue: [], fields: ['title'], categoryFields: ['title'] }, global: { plugins: [ElementPlus] } });
    await clickText(wrapper, '添加按钮'); await choose('动作', location === 'toolbar' ? '授权导出下载' : '复制字段文本');
    if (location !== 'toolbar') await choose('参数 text 字段', 'title');
    await drawerClick('应用');
    expect((wrapper.emitted('update')!.at(-1)![0] as FormListButton[])[0]).toMatchObject(location === 'toolbar' ? { action: { type: 'download', key: 'export' } } : { action: { type: 'copy' }, params: { text: { source: location === 'row' ? 'row' : 'category', field: 'title' } } });
    wrapper.unmount();
  });
  it('未知目录元数据有诊断但应用不静默丢弃参数', async () => {
    const unknown = { ...button, action: { type: 'registered', key: 'missing', capabilityVersion: 'old' }, params: { code: { source: 'row', field: 'code' } } } as FormListButton;
    const wrapper = mount(Editor, { attachTo: document.body, props: { title: '行操作', location: 'row', modelValue: [unknown], fields: ['code'] }, global: { plugins: [ElementPlus] } });
    await clickText(wrapper, '配置');
    expect(document.body.textContent).toContain('未注册、无权限或目录不可用');
    await drawerClick('应用'); expect(wrapper.emitted('update')!.at(-1)![0]).toEqual([unknown]);
    wrapper.unmount();
  });
  it('显示配置编辑不改变稳定动作，空集合与恢复默认分别发出', async () => {
    const wrapper = render(); const state = (wrapper.vm as any).$.setupState;
    state.edit(0); state.draft.label = '修改'; state.draft.color = 'warning'; state.draft.icon = 'edit'; state.draft.tips = '提示'; state.save();
    expect(wrapper.emitted('update')?.[0]?.[0]).toEqual([{ ...button, label: '修改', color: 'warning', icon: 'edit', tips: '提示' }]);
    const controls = wrapper.findAll('button'); await controls.find(item => item.text() === '全部隐藏')!.trigger('click'); expect(wrapper.emitted('update')?.at(-1)).toEqual([[]]);
    await controls.find(item => item.text() === '恢复默认')!.trigger('click'); expect(wrapper.emitted('update')?.at(-1)).toEqual([undefined]); wrapper.unmount();
  });
  it('可编辑有限字段条件与成功效果，拒绝无效输入字段名', async () => {
    const wrapper = render(); const state = (wrapper.vm as any).$.setupState;
    state.edit(0); state.setCondition('visibleWhen', 'status');
    expect(state.draft.visibleWhen).toEqual({ field: 'status', op: 'eq', value: '' });
    state.changeInteraction('input'); state.draft.interaction.fields[0].name = 'constructor'; state.save();
    expect(wrapper.emitted('update')).toBeUndefined(); wrapper.unmount();
  });
});
