import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ElementPlus from 'element-plus';
import SchemaForm from './SchemaForm.vue';
import SchemaRenderer from './SchemaRenderer.vue';
import type { FormFieldDef } from '@/api/form';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

import ValidatorModule from 'async-validator';
// Node CJS 入口包装与生产 ESM 的导出差异，仅用于验证实际生成的规则。
const Validator = (ValidatorModule as any).default ?? ValidatorModule;
const mocks = vi.hoisted(() => ({ options: vi.fn(async () => ({ options: [] })), plugins: vi.fn(async () => {}) }));
vi.mock('@/api/formData', () => ({ formDataApi: { options: mocks.options } }));
vi.mock('../schema/pluginComponentLoader', () => ({ loadPluginFormComponents: mocks.plugins }));
const field = (overrides: Record<string, unknown>) => ({ field_name: 'value', label: '字段', type: 'input', ...overrides }) as FormFieldDef;
const render = (fields: FormFieldDef[], values: Record<string, unknown>) => mount(SchemaForm, {
  props: { formKey: 'system_member', fields, values },
  global: { plugins: [ElementPlus], stubs: { RegisteredControlRenderer: true } }
});

beforeEach(() => vi.clearAllMocks());
describe('PHP 生成 schema 的 renderer 能力', () => {
  it.each([false, true])('字典与上传开关 %s 保留关联、只读、日期能力', async (enabled) => {
    const output = JSON.parse(execFileSync(process.env.PHP_BINARY || '/opt/homebrew/opt/php@8.1/bin/php', ['-r', `
require 'vendor/autoload.php';
$nodes = [];
foreach (['dictionary_value'=>'dictionary', 'owner'=>'select', 'attachment'=>'file', 'event_date'=>'date', 'read_value'=>'readonly'] as $field=>$type) {
    $nodes[] = ['id'=>$field,'kind'=>'field','field'=>$field,'type'=>$type,'title'=>$field,'database'=>['columnType'=>'varchar'], 'props'=>$type==='date'?['valueFormat'=>'YYYY-MM-DD']:[], 'dataSource'=>$type==='dictionary'?['kind'=>'dictionary','dictionary'=>'status']:['kind'=>'static','options'=>[]]];
}
$c=(new app\\common\\form\\schema\\FormSchemaCompiler(new app\\common\\form\\schema\\FormSchemaValidator()))->compile(['schemaVersion'=>2,'key'=>'capability','title'=>'能力','nodes'=>$nodes]);
$d=(new app\\console\\development\\service\\FormCrudDefinitionFactory())->createFromSchema($c,['table_name'=>'fun_capability'])->toArray();
$d['features']['dictionary']=${enabled ? 'true' : 'false'}; $d['features']['upload']=${enabled ? 'true' : 'false'};
$d['optionsSource'][]=['name'=>'owner_options','type'=>'endpoint','endpoint'=>'/owners','labelField'=>'label','valueField'=>'value'];
foreach($d['fields'] as &$field) if($field['name']==='owner') $field['optionsSource']='owner_options'; unset($field);
echo json_encode(app\\common\\crud\\ProductionTemplateContext::build(app\\common\\crud\\CrudDefinition::fromArray($d)));
`], { cwd: resolve(process.cwd(), '..'), encoding: 'utf8' }));
    const schema = JSON.parse(output.formContent.match(/const formSchema=(.*?) as unknown as FormSchemaDocument;/)[1]);
    const request = vi.fn(async () => ({ options: [{ label: '关联值', value: 7 }] }));
    const wrapper = mount(SchemaRenderer, {
      props: { schema, formKey: 'capability', values: { owner: 7, read_value: '只读值' }, optionsRequest: request },
      global: { plugins: [ElementPlus], stubs: { RegisteredControlRenderer: true } }
    });
    await vi.waitFor(() => expect(request).toHaveBeenCalledTimes(enabled ? 2 : 1));
    expect(request.mock.calls.map(call => (call as unknown[])[1]).sort()).toEqual(enabled ? ['dictionary_value', 'owner'] : ['owner']);
    const controls = wrapper.findAllComponents({ name: 'RegisteredControlRenderer' });
    const control = (field: string) => controls.find(item => item.props('node').field === field)!;
    expect(control('dictionary_value').props('node').type).toBe(enabled ? 'dictionary' : 'input');
    expect(control('attachment').props('node').type).toBe(enabled ? 'file' : 'input');
    expect(control('event_date').props('node').props.valueFormat).toBe('YYYY-MM-DD');
    expect(control('read_value').props('disabled')).toBe(true);
    expect(control('read_value').props('modelValue')).toBe('只读值');
    await flushPromises();
    expect(control('owner').props('options')).toEqual([{ label: '关联值', value: 7 }]);
    wrapper.unmount();
  });
});

describe('轻量 SchemaForm 的 Builder 兼容', () => {
  it('旧投影仅转换后委托唯一 SchemaRenderer', async () => {
    const wrapper = render([field({})], { value: '有效' });
    await flushPromises();
    expect(wrapper.findComponent(SchemaRenderer).exists()).toBe(true);
    wrapper.unmount();
  });
  it('kind static 与旧 mode static 均不请求通用 options', async () => {
    const wrapper = render([
      field({ field_name: 'a', type: 'select', options_source: { kind: 'static', options: [{ label: '组', value: 7 }] } }),
      field({ field_name: 'b', type: 'select', options_source: { mode: 'static', options: [] } })
    ], { a: 7, b: '' });
    await flushPromises();
    expect(mocks.options).not.toHaveBeenCalled();
    expect(wrapper.findAllComponents({ name: 'RegisteredControlRenderer' })[0]?.props('options')).toEqual([{ label: '组', value: 7 }]);
    wrapper.unmount();
  });
  it('内置控件验证无需请求插件目录', async () => {
    const wrapper = render([field({})], { value: '有效' });
    await wrapper.vm.validate();
    expect(mocks.plugins).not.toHaveBeenCalled();
    wrapper.unmount();
  });
  it.each([
    [{ email: true }, 'not-email'],
    [{ format: 'email' }, 'not-email'],
    [{ maxLength: 3 }, 'abcd'],
    [{ minLength: 2 }, 'a'],
    [{ type: 'array', min: 1, max: 32 }, []],
    [{ type: 'array', max: 32 }, Array.from({ length: 33 }, (_, i) => i)],
    [{ type: 'number', min: 1 }, 0]
  ])('保留投影校验 %j', async (validate_rules, value) => {
    const wrapper = render([field({ validate_rules })], { value });
    await flushPromises();
    expect(wrapper.findComponent({ name: 'ElForm' }).props('rules')).toHaveProperty('value');
    expect(wrapper.findAllComponents({ name: 'ElFormItem' })).toHaveLength(1);
    expect((wrapper.findComponent({ name: 'ElForm' }).vm as any).fields).toHaveLength(1);
    const rules = wrapper.findComponent({ name: 'ElForm' }).props('rules');
    await expect(new Validator(rules).validate({ value })).rejects.toBeTruthy();
    wrapper.unmount();
  });
  it('继续支持旧远程 mode', async () => {
    const wrapper = render([field({ type: 'select', options_source: { mode: 'relation' } })], { value: '' });
    await vi.waitFor(() => expect(mocks.options).toHaveBeenCalledWith('system_member', 'value'));
    wrapper.unmount();
  });
});
