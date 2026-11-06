import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { ElButton, ElTable, ElTableColumn } from 'element-plus';
import SourcePanel from '../../../admin/plugins/cms/web/src/admin/modules/cms/components/MenuPanel.vue';
import RuntimePanel from '../../src/admin/modules/cms/components/MenuPanel.vue';
const api = vi.hoisted(() => ({ menus: vi.fn(), menuTargets: vi.fn(), removeMenu: vi.fn(), saveMenu: vi.fn(), menuStatus: vi.fn() }));
vi.mock('../../../admin/plugins/cms/web/src/admin/modules/cms/api', () => ({ cmsApi: api }));
vi.mock('../../src/admin/modules/cms/api', () => ({ cmsApi: api }));
const confirmation = defineComponent({
  props: ['title'], emits: ['confirm'], setup(props, { slots, emit }) {
    const shown = ref(false);
    return () => h('div', [h('span', { onClick: () => { shown.value = true; } }, slots.reference?.()), shown.value ? h('section', { role: 'alertdialog' }, [props.title, h('button', { onClick: () => emit('confirm') }, '确认删除')]) : null]);
  }
});
const removed = { id: '2', name: '旧导航', code: 'home', status: 1, items: [] };
const kept = { id: '3', name: '顶部导航', code: 'header', status: 1, items: [] };
const wrappers: ReturnType<typeof mount>[] = [];
beforeEach(() => {
  vi.clearAllMocks();
  api.menus.mockResolvedValue([removed, kept]);api.menuTargets.mockResolvedValue([]);api.removeMenu.mockResolvedValue(true);
});
afterEach(() => wrappers.splice(0).forEach(wrapper => wrapper.unmount()));
describe.each([['源插件', SourcePanel], ['运行副本', RuntimePanel]] as const)('%s 导航组删除', (_name, panel) => {
  async function render(can = (_permission: string) => true) {
    const wrapper = mount(panel, { props: { scope: { site_id: '7', locale: 'zh-cn' }, can }, global: {
      components: { ElButton, ElTable, ElTableColumn },
      stubs: { ElAlert: true, ElSwitch: true, ElDialog: true, ElInput: true, ElFormItem: true, ElForm: true, ElPopconfirm: confirmation }
    } });
    wrappers.push(wrapper);await flushPromises();return wrapper;
  }
  it('确认后删除指定组并刷新列表，不删除其他组', async () => {
    const wrapper = await render();
    const row = wrapper.findAll('.el-table__row').find(item => item.text().includes('旧导航'))!;
    await row.findAll('button').find(button => button.text() === '删除导航组')!.trigger('click');
    expect(api.removeMenu).not.toHaveBeenCalled();
    const prompt = row.get('[role="alertdialog"]');expect(prompt.text()).toContain('旧导航');
    api.menus.mockResolvedValue([kept]);
    await prompt.get('button').trigger('click');await flushPromises();
    expect(api.removeMenu).toHaveBeenCalledWith({ site_id: '7', locale: 'zh-cn', id: '2' });
    expect(wrapper.findAll('.el-table__row').some(item => item.text().includes('旧导航'))).toBe(false);
    expect(wrapper.findAll('.el-table__row').some(item => item.text().includes('顶部导航'))).toBe(true);
  });
  it('没有删除权限时隐藏按钮，直接调用入口也不提交', async () => {
    const wrapper = await render(permission => permission !== 'cms:menu:delete');
    expect(wrapper.findAll('button').some(button => button.text() === '删除导航组')).toBe(false);
    const remove = (wrapper.vm as any).$.setupState.remove;
    expect(typeof remove).toBe('function');await remove(removed);
    expect(api.removeMenu).not.toHaveBeenCalled();
  });
  it('删除失败时保留组并显示错误', async () => {
    api.removeMenu.mockRejectedValue({ msg: '没有本站删除权限' });
    const wrapper = await render();
    const row = wrapper.findAll('.el-table__row').find(item => item.text().includes('旧导航'))!;
    await row.findAll('button').find(button => button.text() === '删除导航组')!.trigger('click');
    await row.get('[role="alertdialog"] button').trigger('click');await flushPromises();
    expect(wrapper.findAll('.el-table__row').some(item => item.text().includes('旧导航'))).toBe(true);
    expect(wrapper.findAll('el-alert-stub').some(alert => alert.attributes('title') === '没有本站删除权限')).toBe(true);
  });
});
