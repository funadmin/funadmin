import { mount, flushPromises } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MemberFormDialog from './MemberFormDialog.vue';

const api = vi.hoisted(() => ({ options: vi.fn(), create: vi.fn(), update: vi.fn(), validate: vi.fn(async () => true) }));
vi.mock('@/api/system/member', () => ({ memberApi: api }));
vi.mock('@/views/form/components/SchemaRenderer.vue', () => ({ default: defineComponent({
  name: 'SchemaRenderer', props: ['schema', 'values', 'options'], setup(_, { expose }) { expose({ validate: api.validate }); }, template: '<div />'
}) }));
const definition = () => {
  const result = ({
  groups: [{ id: 7, name: '组' }], levels: [{ id: 3, name: '等级' }], tags: [], schema: { schemaVersion: 2, key: 'system_member', nodes: [] as any[] },
  fields: [
    ['username', 'input', ''], ['mobile', 'input', ''], ['email', 'input', ''], ['group_ids', 'select', [7]],
    ['level_id', 'select', 3], ['tag_ids', 'select', []], ['sex', 'radio', '0'], ['status', 'radio', 1], ['avatar', 'image', '']
  ].map(([field_name, type, default_value]) => ({ field_name, type, default_value, options_source: { kind: 'static', options: field_name === 'group_ids' ? [{ label: '组', value: 7 }] : field_name === 'level_id' ? [{ label: '等级', value: 3 }] : [] } }))
});
  result.schema.nodes = result.fields.map(field => ({ id: field.field_name, field: field.field_name, type: field.type, kind: 'field', title: field.field_name, defaultValue: field.default_value, children: [] }));
  return result;
};
const render = (row: any = null) => mount(MemberFormDialog, {
  props: { modelValue: true, row, options: definition() as any },
  global: { stubs: {
    ElDialog: { template: '<div><slot /><slot name="footer" /></div>' },
    ElButton: { template: '<button><slot /></button>' }, ElAlert: { props: ['title'], template: '<div>{{ title }}</div>' },
    ElSkeleton: true
  } }
});
const deferred = () => { let resolve!: (value: any) => void; const promise = new Promise<any>((r) => { resolve = r; }); return { promise, resolve }; };
beforeEach(() => { vi.clearAllMocks(); api.options.mockResolvedValue(definition()); api.create.mockResolvedValue({}); api.update.mockResolvedValue({}); });
describe('会员 Builder 弹窗', () => {
  it('页面动作持锁时禁止实际保存', async () => {
    const wrapper = render(); await wrapper.setProps({ lock: { busy: true } } as any); await flushPromises();
    await wrapper.findAll('button').find(b => b.text() === '确定')!.trigger('click'); await flushPromises();
    expect(api.create).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('加载九字段默认值，保留提示并以 camelCase 专用 payload 提交', async () => {
    const wrapper = render(); await flushPromises();
    const schema = wrapper.findComponent({ name: 'SchemaRenderer' });
    expect(schema.exists()).toBe(true);
    expect(schema.props('schema').nodes).toHaveLength(9);
    expect(wrapper.text()).toContain('不设置密码');
    Object.assign(schema.props('values'), { username: '会员', mobile: '123456', email: '' });
    await wrapper.findAll('button').find((b) => b.text() === '确定')!.trigger('click'); await flushPromises();
    expect(api.create).toHaveBeenCalledWith({ username: '会员', mobile: '123456', email: '', groupIds: [7], levelId: 3, tagIds: [], sex: '0', status: 1, avatar: '' });
    wrapper.unmount();
  });
  it('失败时不展示旧定义，提供重试', async () => {
    api.options.mockRejectedValueOnce(new Error('失败'));
    const wrapper = render(); await flushPromises();
    expect(wrapper.findComponent({ name: 'SchemaRenderer' }).exists()).toBe(false);
    await wrapper.findAll('button').find((b) => b.text() === '重试')!.trigger('click'); await flushPromises();
    expect(wrapper.findComponent({ name: 'SchemaRenderer' }).exists()).toBe(true);
    wrapper.unmount();
  });
  it('切换会员丢弃迟到响应，不覆盖新会员', async () => {
    const first = deferred(); api.options.mockReturnValueOnce(first.promise);
    const wrapper = render();
    await wrapper.setProps({ row: { id: 2, username: '第二位', mobile: '123456', email: '', groupIds: [99], groupNames: ['旧组'], tagIds: [], tagNames: [], levelId: 3, levelName: '等级', sex: '0', status: 1, avatar: '' } as any });
    await flushPromises(); first.resolve(definition()); await flushPromises();
    const schema = wrapper.findComponent({ name: 'SchemaRenderer' });
    expect(schema.props('values').username).toBe('第二位');
    expect(schema.props('values').group_ids).toEqual([99]);
    expect(JSON.stringify(schema.props('options'))).toContain('不可用');
    expect(wrapper.text()).toContain('不可用');
    wrapper.unmount();
  });
  it('编辑提交走原 update，关闭后的加载响应不恢复表单', async () => {
    const row = { id: 8, username: '编辑会员', mobile: '123456', email: '', groupIds: [7], groupNames: ['组'], tagIds: [], tagNames: [], levelId: 3, levelName: '等级', sex: '1', status: 0, avatar: '/existing.png' };
    const wrapper = render(row); await flushPromises();
    await wrapper.findAll('button').find((b) => b.text() === '确定')!.trigger('click'); await flushPromises();
    expect(api.update).toHaveBeenCalledWith(8, expect.objectContaining({ groupIds: [7], levelId: 3, sex: '1', status: 0, avatar: '/existing.png' }));
    expect(api.create).not.toHaveBeenCalled();
    const pending = deferred(); api.options.mockReturnValueOnce(pending.promise);
    await wrapper.setProps({ row: null }); await wrapper.setProps({ modelValue: false });
    pending.resolve(definition()); await flushPromises();
    expect(wrapper.findComponent({ name: 'SchemaRenderer' }).exists()).toBe(false);
    wrapper.unmount();
  });
  it('不可用关系阻止提交而不是静默过滤', async () => {
    const wrapper = render({ id: 9, username: '会员', groupIds: [99], groupNames: ['停用组'], tagIds: [88], tagNames: ['停用标签'], levelId: 66, levelName: '停用等级' });
    await flushPromises();
    await wrapper.findAll('button').find((b) => b.text() === '确定')!.trigger('click'); await flushPromises();
    expect(api.update).not.toHaveBeenCalled();
    expect(wrapper.findComponent({ name: 'SchemaRenderer' }).props('values')).toMatchObject({ group_ids: [99], tag_ids: [88], level_id: 66 });
    wrapper.unmount();
  });
  it('校验等待期间与请求等待期间均防重复提交', async () => {
    const validation = deferred(); api.validate.mockReturnValueOnce(validation.promise);
    const request = deferred(); api.create.mockReturnValueOnce(request.promise);
    const wrapper = render(); await flushPromises();
    const submit = wrapper.findAll('button').find((b) => b.text() === '确定')!;
    await submit.trigger('click'); await submit.trigger('click');
    expect(api.validate).toHaveBeenCalledTimes(1);
    validation.resolve(true); await flushPromises(); await submit.trigger('click');
    expect(api.create).toHaveBeenCalledTimes(1);
    request.resolve({}); await flushPromises(); wrapper.unmount();
  });
});
