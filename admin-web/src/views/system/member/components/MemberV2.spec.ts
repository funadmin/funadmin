import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ElementPlus from 'element-plus';
import SchemaRenderer from '@/views/form/components/SchemaRenderer.vue';
import MemberFormDialog from './MemberFormDialog.vue';
import { memberApi } from '@/api/system/member';
import { flattenSchemaNodes } from '@/views/form/schema/types';
import { validateFormSchemaValues } from '@/views/form/validation/formSchemaDataValidator';

const mocks = vi.hoisted(() => ({ plugins: vi.fn(async () => {}), options: vi.fn() }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ permissions: ['system:member:add'] }) }));
vi.mock('@/views/form/schema/pluginComponentLoader', () => ({ loadPluginFormComponents: mocks.plugins }));
vi.mock('@/api/formData', () => ({ formDataApi: { options: mocks.options } }));
const php = process.env.PHP81_BINARY || '/opt/homebrew/opt/php@8.1/bin/php';
const definition = JSON.parse(execFileSync(php, ['-r', "require 'vendor/autoload.php'; echo json_encode(\\app\\admin\\service\\MemberFormDefinition::build(['groups'=>[['id'=>7,'name'=>'组']], 'levels'=>[['id'=>3,'name'=>'等级']], 'tags'=>[]]), JSON_THROW_ON_ERROR);"], { cwd: resolve(process.cwd(), '..'), encoding: 'utf8' }));

describe('PHP 真实会员 v2 文档', () => {
  it('会员弹窗直接消费原始 v2 布局并通过专用 API 映射提交', async () => {
    vi.spyOn(memberApi, 'options').mockResolvedValue(definition);
    const create = vi.spyOn(memberApi, 'create').mockResolvedValue({} as never);
    const wrapper = mount(MemberFormDialog, { props: { modelValue: true, options: definition }, global: { plugins: [ElementPlus] } });
    await flushPromises();
    const renderer = wrapper.findComponent(SchemaRenderer);
    expect(renderer.exists()).toBe(true);
    expect(renderer.props('schema')).toEqual(definition.schema);
    Object.assign(renderer.props('values'), { username: 'tester', mobile: '13800138000', email: 'tester@example.com' });
    const submit = wrapper.findAllComponents({ name: 'ElButton' }).find(button => button.text() === '确定')!;
    await submit.trigger('click'); await flushPromises();
    expect(create).toHaveBeenCalledWith({ username: 'tester', mobile: '13800138000', email: 'tester@example.com', groupIds: [7], levelId: 3, tagIds: [], sex: '0', status: 1, avatar: '' });
    wrapper.unmount(); vi.restoreAllMocks();
  });
  it('真实渲染九字段及布局，静态控件不访问动态接口或插件目录', async () => {
    const nodes = flattenSchemaNodes(definition.schema.nodes).map(({ node }) => node);
    const values = Object.fromEntries(nodes.filter(node => node.field).map(node => [node.field!, node.defaultValue]));
    Object.assign(values, { username: 'tester', mobile: '13800138000', email: 'tester@example.com' });
    const wrapper = mount(SchemaRenderer, { props: { schema: definition.schema, values }, global: { plugins: [ElementPlus] } });
    await flushPromises();
    expect(wrapper.findAllComponents({ name: 'ElFormItem' })).toHaveLength(9);
    expect(nodes.find(node => node.field === 'username')?.layout?.span).toBe(12);
    const rules = Object.fromEntries(nodes.map(node => [node.field!, node.validation ?? []]));
    expect(validateFormSchemaValues(values, rules)).toEqual([]);
    await expect(wrapper.vm.validate()).resolves.toBe(true);
    expect(values.group_ids).toEqual([7]);
    expect(values.level_id).toBe(3);
    expect(values.sex).toBe('0');
    expect(mocks.options).not.toHaveBeenCalled();
    expect(mocks.plugins).not.toHaveBeenCalled();
    wrapper.unmount();
  });
});
