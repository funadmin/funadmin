import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { mount, flushPromises } from '@vue/test-utils';
import ElementPlus from 'element-plus';
import { describe, expect, it, vi } from 'vitest';
import MemberFormDialog from './MemberFormDialog.vue';
import SchemaRenderer from '@/views/form/components/SchemaRenderer.vue';

const api = vi.hoisted(() => ({ options: vi.fn(), create: vi.fn(async () => ({})), update: vi.fn() }));
const remote = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('@/api/system/member', () => ({ memberApi: api }));
vi.mock('@/utils/http', () => ({ default: remote }));

const definition = JSON.parse(execFileSync(process.env.PHP_BINARY || '/opt/homebrew/opt/php@8.1/bin/php', ['-r', `require 'vendor/autoload.php'; echo json_encode(\\app\\console\\service\\MemberFormDefinition::build(['groups'=>[['id'=>7,'name'=>'会员组']], 'levels'=>[['id'=>3,'name'=>'会员等级']], 'tags'=>[['id'=>5,'name'=>'标签']]]));`], { cwd: resolve(process.cwd(), '..'), encoding: 'utf8' }));

describe('PHP 真实会员 v2 与真实渲染器', () => {
  it('九字段、真实校验、数值类型及专用提交映射，不访问动态目录', async () => {
    api.options.mockResolvedValue(definition);
    const wrapper = mount(MemberFormDialog, { props: { modelValue: true, options: definition }, global: { plugins: [ElementPlus], stubs: { ElDialog: { template: '<div><slot /><slot name="footer" /></div>' } } } });
    await flushPromises();
    const renderer = wrapper.findComponent(SchemaRenderer);
    expect(renderer.exists()).toBe(true);
    expect(wrapper.findAll('.el-form-item')).toHaveLength(9);
    expect(renderer.props('schema')).toEqual(definition.schema);
    expect(renderer.findComponent({ name: 'ElForm' }).props('rules').email).toHaveLength(2);
    expect((renderer.findComponent({ name: 'ElForm' }).vm as any).fields).toHaveLength(9);
    const values = renderer.props('values');
    Object.assign(values, { username: '真实会员', mobile: '13800138000', email: 'a..b@example.com', group_ids: [7], level_id: 3 });
    const submit = async () => { await flushPromises(); await wrapper.findAll('button').find(button => button.text() === '确定')!.trigger('click'); };
    await submit(); await flushPromises();
    expect(api.create).not.toHaveBeenCalled();
    values.email = 'user+tag@example.com'; values.group_ids = [];
    await submit(); await flushPromises();
    expect(api.create).not.toHaveBeenCalled();
    values.group_ids = [7]; values.tag_ids = [5];
    await submit(); await flushPromises();
    expect(api.create).toHaveBeenCalledWith({ username: '真实会员', mobile: '13800138000', email: 'user+tag@example.com', groupIds: [7], levelId: 3, tagIds: [5], sex: '0', status: 1, avatar: '' });
    expect(remote.get).not.toHaveBeenCalled();
    expect(remote.post).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('不设置密码');
    expect(definition.schema.nodes.find((node: any) => node.field === 'avatar').props.maxSize).toBe(2);
    wrapper.unmount();
  });
});