import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ElementPlus from 'element-plus';
import Editor from './ListButtonEditor.vue';
import type { FormListButton } from '../../schema/types';
const button: FormListButton = { id: 'edit', label: '编辑', action: { type: 'builtin', key: 'edit' } };
const render = () => mount(Editor, { props: { title: '行操作', location: 'row', modelValue: [button] }, global: { plugins: [ElementPlus] } });
describe('按钮配置编辑器', () => {
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
