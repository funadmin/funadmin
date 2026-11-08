import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import ElementPlus from 'element-plus';
import Form from './CustomActionForm.vue';
import type { FormListCustomAction } from '../../schema/types';

afterEach(() => { document.body.innerHTML = ''; });
const existing: FormListCustomAction[] = [{ key: 'approve', label: '审批', locations: ['row'], batch: false, requiresConfirmation: true, effect: 'write', parameters: [] }];
const render = (props: Record<string, unknown> = {}) => mount(Form, { attachTo: document.body, props: { existing, entity: 'fun-order', ...props } as never, global: { plugins: [ElementPlus] } });
type Wrapper = ReturnType<typeof render>;
const input = (wrapper: Wrapper, label: string) => wrapper.get(`[aria-label="${label}"]`).element as HTMLInputElement;
async function type(wrapper: Wrapper, label: string, value: string) { const el = input(wrapper, label); el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); await flushPromises(); }
const button = (wrapper: Wrapper, text: string) => wrapper.findAll('button').find(item => item.text().trim() === text)!;
const checkbox = (wrapper: Wrapper, location: string) => wrapper.get(`[data-location-option="${location}"] input`);
const errors = (wrapper: Wrapper) => wrapper.findAll('.custom-action-form__error').map(item => item.text());
const submitted = (wrapper: Wrapper) => wrapper.emitted('submit')?.at(-1)?.[0] as FormListCustomAction | undefined;

describe('自定义动作表单', () => {
  it('标识按名称建议，手动修改后不再跟随；注册键与权限码按模块推导', async () => {
    const wrapper = render();
    await type(wrapper, '名称', 'Mark Paid');
    expect(input(wrapper, '标识').value).toBe('mark_paid');
    expect(wrapper.text()).toContain('generated.fun-order.mark_paid');
    expect(wrapper.text()).toContain('generated:fun-order:action-mark-paid');
    await type(wrapper, '标识', 'settle');
    await type(wrapper, '名称', 'Mark Paid Later');
    expect(input(wrapper, '标识').value).toBe('settle');
    wrapper.unmount();
  });
  it('中文名称无法转写时建议不重复的 action_n', async () => {
    const wrapper = render({ existing: [...existing, { ...existing[0], key: 'action_1' }] });
    await type(wrapper, '名称', '标记已付');
    expect(input(wrapper, '标识').value).toBe('action_2');
    wrapper.unmount();
  });
  it('镜像后端规则：名称、标识格式与唯一、位置非空、参数名合法且唯一，不通过时不提交', async () => {
    const wrapper = render();
    await button(wrapper, '保存').trigger('click'); await flushPromises();
    expect(errors(wrapper).join('|')).toContain('请填写动作名称');
    expect(submitted(wrapper)).toBeUndefined();
    await type(wrapper, '名称', '审批');
    await type(wrapper, '标识', '1approve');
    await button(wrapper, '保存').trigger('click'); await flushPromises();
    expect(errors(wrapper).join('|')).toContain('小写字母开头');
    await type(wrapper, '标识', 'approve');
    await button(wrapper, '保存').trigger('click'); await flushPromises();
    expect(errors(wrapper).join('|')).toContain('标识已存在');
    await type(wrapper, '标识', 'reject');
    await checkbox(wrapper, 'row').setValue(false); await flushPromises();
    await button(wrapper, '保存').trigger('click'); await flushPromises();
    expect(errors(wrapper).join('|')).toContain('至少选择一个位置');
    await checkbox(wrapper, 'row').setValue(true); await flushPromises();
    await button(wrapper, '添加参数').trigger('click'); await button(wrapper, '添加参数').trigger('click'); await flushPromises();
    await type(wrapper, '参数 1 名称', 'reason'); await type(wrapper, '参数 2 名称', 'reason');
    await button(wrapper, '保存').trigger('click'); await flushPromises();
    expect(errors(wrapper).join('|')).toContain('参数名重复');
    await type(wrapper, '参数 2 名称', 'Bad-Name');
    await button(wrapper, '保存').trigger('click'); await flushPromises();
    expect(errors(wrapper).join('|')).toContain('参数名须以小写字母开头');
    expect(submitted(wrapper)).toBeUndefined();
    await type(wrapper, '名称', 'x'.repeat(51));
    await button(wrapper, '保存').trigger('click'); await flushPromises();
    expect(errors(wrapper).join('|')).toContain('1–50');
    wrapper.unmount();
  });
  it('最多 5 个参数', async () => {
    const wrapper = render();
    for (let index = 0; index < 5; index++) await button(wrapper, '添加参数').trigger('click');
    await flushPromises();
    expect(wrapper.findAll('[data-custom-parameter]')).toHaveLength(5);
    expect(button(wrapper, '添加参数').attributes('disabled')).toBeDefined();
    wrapper.unmount();
  });
  it('选择顶部操作时批量固定开启；仅行操作时不批量；提交结果与后端结构一致', async () => {
    const wrapper = render({ defaultLocations: ['row'] });
    const batch = () => wrapper.get('[data-custom-batch]');
    expect(batch().classes()).not.toContain('is-checked');
    expect(batch().classes()).toContain('is-disabled');
    await checkbox(wrapper, 'toolbar').setValue(true); await flushPromises();
    expect(batch().classes()).toContain('is-checked');
    expect(wrapper.text()).toContain('顶部操作总是批量');
    await type(wrapper, '名称', '  批量结算  ');
    await type(wrapper, '标识', 'settle');
    await wrapper.findAll('.el-radio').find(item => item.text().includes('只读'))!.trigger('click');
    await wrapper.get('[data-custom-confirm]').trigger('click');
    await button(wrapper, '添加参数').trigger('click'); await flushPromises();
    await type(wrapper, '参数 1 名称', 'amount');
    const select = wrapper.get('[aria-label="参数 1 类型"]');
    (select.element.closest('.el-select')!.querySelector('.el-select__wrapper') as HTMLElement).click(); await flushPromises();
    const id = select.attributes('aria-controls');
    ([...document.getElementById(id!)!.querySelectorAll('.el-select-dropdown__item')].find(item => item.textContent?.trim() === 'number') as HTMLElement).click(); await flushPromises();
    await button(wrapper, '保存').trigger('click'); await flushPromises();
    expect(submitted(wrapper)).toEqual({ key: 'settle', label: '批量结算', locations: ['row', 'toolbar'], batch: true, requiresConfirmation: false, effect: 'read', parameters: [{ name: 'amount', type: 'number' }] });
    wrapper.unmount();
  });
  it('编辑时标识只读，不与自身判重', async () => {
    const wrapper = render({ action: existing[0] });
    expect(input(wrapper, '标识').readOnly || input(wrapper, '标识').disabled).toBe(true);
    await type(wrapper, '名称', '审批通过');
    await button(wrapper, '保存').trigger('click'); await flushPromises();
    expect(submitted(wrapper)).toMatchObject({ key: 'approve', label: '审批通过', locations: ['row'], batch: false });
    await button(wrapper, '取消').trigger('click');
    expect(wrapper.emitted('cancel')).toHaveLength(1);
    wrapper.unmount();
  });
});
