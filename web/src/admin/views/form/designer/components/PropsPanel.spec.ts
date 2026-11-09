import { defineComponent } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ElementPlus from 'element-plus';
import { createI18n } from 'vue-i18n';
import PropsPanel from './PropsPanel.vue';
import propsPanelSource from './PropsPanel.vue?raw';
import propsFoldsSource from './PropsFieldFolds.vue?raw';
import propsToggleSource from './PropsToggle.vue?raw';
import { businessDevelopmentApi } from '@/api/development/business';
import { CONTROL_REGISTRY, createField } from '../../registry';
import type { FormFieldDef } from '@/api/form';
import zhCN from '@/locales/zh-CN';

const dictTypeApi = vi.hoisted(() => ({ list: vi.fn() }));
vi.mock('@/api/system/dict', () => ({ dictTypeApi, dictApi: { options: vi.fn(), batch: vi.fn() }, dictItemApi: {} }));
vi.mock('@/api/development/business', () => ({ businessDevelopmentApi: { databaseTables: vi.fn(), databaseTableSchema: vi.fn() } }));

const selectStub = defineComponent({
  props: ['modelValue'],
  emits: ['update:modelValue', 'visible-change'],
  template: '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value); $emit(\'visible-change\', true)"><slot /></select>'
});
const optionStub = defineComponent({ props: ['label', 'value'], template: '<option :value="value">{{ label }}</option>' });

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const render = (field: FormFieldDef, extra: Record<string, unknown> = {}) => mount(PropsPanel, {
  props: { moduleId: 1, field, sourceType: 'created', controls: CONTROL_REGISTRY, ...extra },
  global: { plugins: [ElementPlus, i18n], stubs: { ElSelect: selectStub, ElOption: optionStub } }
});

describe('字典控件数据源配置', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({
      list: [
        { id: 1, code: 'order_status', name: '订单状态', status: 1 },
        { id: 2, code: 'pay_channel', name: '支付渠道', status: 1 }
      ],
      total: 2
    });
  });

  it('字典控件显示字典选择器并加载字典分类，选择后写入 kind 与 dictionary', async () => {
    const field = createField('dictionary', 1);
    expect(field.options_source).toMatchObject({ kind: 'dictionary', dictionary: '' });
    const wrapper = render(field);
    await flushPromises();
    expect(dictTypeApi.list).toHaveBeenCalled();
    const select = wrapper.get('[data-testid="dictionary-code-select"]');
    await select.setValue('order_status');
    const patch = wrapper.emitted('update')?.at(-1)?.[0] as Record<string, unknown>;
    const source = patch.options_source as Record<string, unknown>;
    expect(source).toMatchObject({ kind: 'dictionary', dictionary: 'order_status' });
    expect(source).not.toHaveProperty('mode');
    wrapper.unmount();
  });

  it('回显已保存的字典编码（kind 键）', async () => {
    const byKind = render({ ...createField('dictionary', 1), options_source: { kind: 'dictionary', dictionary: 'pay_channel', options: [] } });
    await flushPromises();
    expect(byKind.get('[data-testid="dictionary-code-select"]').element).toHaveProperty('value', 'pay_channel');
    byKind.unmount();
  });

  it('非字典控件不渲染字典选择器', async () => {
    const wrapper = render(createField('input', 1));
    await flushPromises();
    expect(wrapper.find('[data-testid="dictionary-code-select"]').exists()).toBe(false);
    expect(dictTypeApi.list).not.toHaveBeenCalled();
    wrapper.unmount();
  });
});

describe('属性面板布局', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({ list: [], total: 0 });
  });

  it('标题区：字段标题 + 控件类型标签 + 「字段名 · 列类型 · 位置」', async () => {
    const wrapper = render({ ...createField('input', 1), field_name: 'order_no', label: '订单号', column_type: 'varchar(32)' }, { groupTitle: '基本信息' });
    await flushPromises();
    expect(wrapper.get('[data-props-title]').text()).toBe('订单号');
    expect(wrapper.get('[data-props-chip]').text()).toBe('单行输入');
    expect(wrapper.get('[data-props-meta]').text()).toBe('order_no · varchar(32) · 位于「基本信息」');
    wrapper.unmount();
    const root = render({ ...createField('input', 1), field_name: 'remark', label: '备注', column_type: 'varchar(255)' });
    await flushPromises();
    expect(root.get('[data-props-meta]').text()).toBe('remark · varchar(255) · 位于根节点');
    root.unmount();
  });

  it('页签依次为 表单 / 列表 / 数据库列 / 属性，默认表单', async () => {
    const wrapper = render(createField('input', 1));
    await flushPromises();
    expect(wrapper.findAll('.el-tabs__item').map((item) => item.text())).toEqual(['表单', '列表', '数据库列', '属性']);
    expect(wrapper.get('.el-tabs__item.is-active').text()).toBe('表单');
    wrapper.unmount();
  });

  it('四个页签的表单都是标签在上、输入在下', async () => {
    const wrapper = render(createField('input', 1));
    await flushPromises();
    for (const tab of ['form', 'list', 'column', 'properties']) {
      const forms = wrapper.get(`[data-props-tab="${tab}"]`).findAll('form.el-form');
      expect(forms.length, tab).toBeGreaterThan(0);
      forms.forEach((form) => expect(form.classes(), tab).toContain('el-form--label-top'));
    }
    wrapper.unmount();
  });

  it('表单页签按 基础 / 布局 / 状态 分组，基础含 显示名称、控件类型、分组、占位提示', async () => {
    const wrapper = render(createField('input', 1));
    await flushPromises();
    const form = wrapper.get('[data-props-tab="form"]');
    expect(form.findAll('[data-props-section]').map((item) => item.attributes('data-props-section'))).toEqual(['basic', 'layout', 'state']);
    expect(form.findAll('[data-props-section-title]').map((item) => item.text())).toEqual(['基础', '布局', '状态']);
    const basic = form.get('[data-props-section="basic"]');
    expect(basic.findAll('.el-form-item__label').map((item) => item.text())).toEqual(['显示名称', '控件类型', '分组', '占位提示']);
    expect(form.get('[data-props-section="layout"]').text()).toContain('宽度');
    expect(form.get('[data-props-section="state"]').findAll('[data-toggle]').map((item) => item.text())).toEqual(['必填', '显示', '只读']);
    expect(form.text()).not.toContain('编辑禁改');
    wrapper.unmount();
  });

  it('布局节点只保留 表单 / 属性 页签与原始 JSON 折叠项，状态区只有「显示」', async () => {
    const wrapper = render({ ...createField('group', 1), field_name: '@node:node_group_1' });
    await flushPromises();
    expect(wrapper.findAll('.el-tabs__item').map((item) => item.text())).toEqual(['表单', '属性']);
    expect(wrapper.get('[data-props-chip]').text()).toBe('分组');
    expect(wrapper.get('[data-props-meta]').text()).toBe('node_group_1 · 位于根节点');
    expect(wrapper.findAll('[data-fold]').map((item) => item.attributes('data-fold'))).toEqual(['raw']);
    expect(wrapper.findAll('[data-toggle]').map((item) => item.attributes('data-toggle'))).toEqual(['form_show']);
    expect(wrapper.find('[data-prop="placeholder"]').exists()).toBe(false);
    expect(wrapper.find('[data-span-option="24"]').exists()).toBe(true);
    wrapper.unmount();
  });
});

describe('宽度分段 ↔ form_span', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({ list: [], total: 0 });
  });

  const activeOption = (wrapper: ReturnType<typeof render>) => wrapper.findAll('[data-span-option]').filter((item) => item.attributes('aria-checked') === 'true').map((item) => item.attributes('data-span-option'));

  it('分段依次为 1/4 · 1/3 · 1/2 · 整行 · 自定义，对应 6/8/12/24', async () => {
    const wrapper = render({ ...createField('input', 1), form_span: 24 });
    await flushPromises();
    expect(wrapper.findAll('[data-span-option]').map((item) => [item.attributes('data-span-option'), item.text()])).toEqual([
      ['6', '1/4'], ['8', '1/3'], ['12', '1/2'], ['24', '整行'], ['custom', '自定义']
    ]);
    expect(activeOption(wrapper)).toEqual(['24']);
    expect(wrapper.get('[data-span-hint]').text()).toBe('对应栅格 24 列中的 6 / 8 / 12 / 24');
    wrapper.unmount();
  });

  it.each([[6, '6'], [8, '8'], [12, '12'], [24, '24']])('form_span=%s 时高亮对应分段', async (span, option) => {
    const wrapper = render({ ...createField('input', 1), form_span: span });
    await flushPromises();
    expect(activeOption(wrapper)).toEqual([option]);
    wrapper.unmount();
  });

  it('点击分段写回 form_span 6/8/12/24', async () => {
    const wrapper = render({ ...createField('input', 1), form_span: 24 });
    await flushPromises();
    for (const span of [6, 8, 12, 24]) {
      await wrapper.get(`[data-span-option="${span}"]`).trigger('click');
      expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ form_span: span });
    }
    wrapper.unmount();
  });

  it('非 6/8/12/24 的值显示「自定义 N 列」，不被改写', async () => {
    const wrapper = render({ ...createField('input', 1), form_span: 10 });
    await flushPromises();
    expect(activeOption(wrapper)).toEqual(['custom']);
    expect(wrapper.get('[data-span-hint]').text()).toBe('自定义 10 列');
    expect(wrapper.emitted('update')).toBeUndefined();
    await wrapper.setProps({ field: { ...createField('input', 1), form_span: 18 } });
    expect(wrapper.get('[data-span-hint]').text()).toBe('自定义 18 列');
    expect(wrapper.emitted('update')).toBeUndefined();
    wrapper.unmount();
  });

  it('「自定义」展开精确输入，不立即改写；输入后写回 form_span', async () => {
    const wrapper = render({ ...createField('input', 1), form_span: 24 });
    await flushPromises();
    expect(wrapper.find('[data-span-custom-input]').exists()).toBe(false);
    await wrapper.get('[data-span-option="custom"]').trigger('click');
    expect(wrapper.emitted('update')).toBeUndefined();
    const input = wrapper.get('[data-span-custom-input] input');
    await input.setValue('10');
    await input.trigger('change');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ form_span: 10 });
    wrapper.unmount();
  });

  it('属性页签可精确输入栅格列数', async () => {
    const wrapper = render({ ...createField('input', 1), form_span: 10 });
    await flushPromises();
    const input = wrapper.get('[data-props-tab="properties"] [data-prop="form_span"] input');
    expect((input.element as HTMLInputElement).value).toBe('10');
    await input.setValue('20');
    await input.trigger('change');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ form_span: 20 });
    wrapper.unmount();
  });
});

describe('状态切换块', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({ list: [], total: 0 });
  });

  it('必填 / 显示 / 只读 三块同一行，开关写回 form_required / form_show / form_readonly', async () => {
    const wrapper = render({ ...createField('input', 1), form_required: 0, form_show: 1, form_readonly: 0 });
    await flushPromises();
    const row = wrapper.get('[data-props-toggles="form"]');
    expect(row.findAll('[data-toggle]').map((item) => item.attributes('data-toggle'))).toEqual(['form_required', 'form_show', 'form_readonly']);
    expect(row.get('[data-toggle="form_show"]').classes()).toContain('is-on');
    expect(row.get('[data-toggle="form_required"]').classes()).not.toContain('is-on');
    await row.get('[data-toggle="form_required"] .el-switch').trigger('click');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ form_required: 1 });
    await row.get('[data-toggle="form_show"] .el-switch').trigger('click');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ form_show: 0 });
    await row.get('[data-toggle="form_readonly"] .el-switch').trigger('click');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ form_readonly: 1 });
    expect(wrapper.emitted('update')).toHaveLength(3);
    wrapper.unmount();
  });

  it('点击切换块的文字区域同样切换，且只写一次', async () => {
    const wrapper = render({ ...createField('input', 1), form_readonly: 1 });
    await flushPromises();
    await wrapper.get('[data-toggle="form_readonly"] [data-toggle-label]').trigger('click');
    expect(wrapper.emitted('update')).toEqual([[{ form_readonly: 0 }]]);
    wrapper.unmount();
  });

  it('「只读」对应原「编辑禁改」字段 form_readonly', async () => {
    const wrapper = render({ ...createField('input', 1), form_readonly: 1 });
    await flushPromises();
    const block = wrapper.get('[data-toggle="form_readonly"]');
    expect(block.text()).toBe('只读');
    expect(block.classes()).toContain('is-on');
    wrapper.unmount();
  });
});

describe('折叠区摘要', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({ list: [], total: 0 });
  });

  const summary = (wrapper: ReturnType<typeof render>, fold: string) => wrapper.get(`[data-fold="${fold}"] [data-fold-summary]`).text();

  it('校验规则、联动条件、数据源、原始 JSON 为表单页签下的折叠项', async () => {
    const field = { ...createField('input', 1), form_required: 1, validate_rules: { max: 20 }, link_rules: { rules: [{ when: { field: 'status', op: 'eq', value: 1 }, then: { action: 'show' } }] } } as FormFieldDef;
    const wrapper = render(field);
    await flushPromises();
    const form = wrapper.get('[data-props-tab="form"]');
    expect(form.findAll('[data-fold]').map((item) => item.attributes('data-fold'))).toEqual(['validation', 'conditions', 'dataSource', 'raw']);
    expect(form.findAll('[data-fold-label]').map((item) => item.text())).toEqual(['校验规则', '联动条件', '数据源', '原始 JSON（高级）']);
    expect(form.get('[data-fold="validation"]').text()).toContain('required');
    expect(form.get('[data-fold="validation"]').text()).toContain('max');
    wrapper.unmount();
  });

  it('校验规则摘要直接写出规则：必填 · 最大 32 字符', async () => {
    const wrapper = render({ ...createField('input', 1), form_required: 1, validate_rules: { maxLength: 32 } } as FormFieldDef);
    await flushPromises();
    expect(summary(wrapper, 'validation')).toBe('必填 · 最大 32 字符');
    expect(wrapper.get('[data-fold="validation"]').findAll('[data-rule-summary]').map((item) => item.text())).toEqual(['必填', '最大 32 字符']);
    wrapper.unmount();
  });

  it.each([
    [{ min: 1, max: 99 }, '最小 1 · 最大 99'],
    [{ minlen: 2, maxlen: 10 }, '最少 2 字符 · 最大 10 字符'],
    [{ type: 'array', min: 1, max: 3 }, '最少 1 项 · 最多 3 项'],
    [{ pattern: '^\\d+$' }, '正则 ^\\d+$'],
    [{ email: true }, '邮箱格式'],
    [{ format: 'url' }, '网址格式'],
    [{ enum: ['a', 'b'] }, '可选值 a / b'],
    [{ precision: 2 }, '精度 2 位'],
    [{ custom_rule: { x: 1 } }, 'custom_rule {"x":1}']
  ])('校验规则 %j 的摘要为 %s', async (rules, text) => {
    const wrapper = render({ ...createField('input', 1), form_required: 0, validate_rules: rules } as FormFieldDef);
    await flushPromises();
    expect(summary(wrapper, 'validation')).toBe(text);
    wrapper.unmount();
  });

  it('没有规则或条件时摘要为「无」', async () => {
    const wrapper = render({ ...createField('input', 1), form_required: 0, validate_rules: null, link_rules: null });
    await flushPromises();
    expect(summary(wrapper, 'validation')).toBe('无');
    expect(summary(wrapper, 'conditions')).toBe('无');
    wrapper.unmount();
  });

  it('联动条件摘要写出条件与动作', async () => {
    const field = {
      ...createField('input', 1),
      link_rules: { rules: [
        { when: { field: 'status', op: 'eq', value: 1 }, then: { action: 'show' } },
        { when: { op: 'and', conditions: [{ field: 'type', op: 'neq', value: 'a' }, { field: 'remark', op: 'notEmpty' }] }, then: { action: 'disable' } },
        { when: {}, then: {} }
      ] }
    } as FormFieldDef;
    const wrapper = render(field);
    await flushPromises();
    expect(wrapper.get('[data-fold="conditions"]').findAll('[data-condition-summary]').map((item) => item.text())).toEqual([
      'status = 1 时显示',
      'type ≠ a 且 remark 不为空 时禁用',
      '未设置动作'
    ]);
    expect(summary(wrapper, 'conditions')).toBe('status = 1 时显示；type ≠ a 且 remark 不为空 时禁用；未设置动作');
    wrapper.unmount();
  });

  it('数据源折叠项承载选项来源 JSON，原始 JSON 折叠项承载 props JSON，修改仍写回字段', async () => {
    const wrapper = render(createField('select', 1));
    await flushPromises();
    const options = wrapper.get('[data-fold="dataSource"] textarea');
    (options.element as HTMLTextAreaElement).value = '{"kind":"static","options":[]}';
    await options.trigger('change');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ options_source: { kind: 'static', options: [] } });
    const raw = wrapper.get('[data-fold="raw"] textarea');
    (raw.element as HTMLTextAreaElement).value = '{"size":"large"}';
    await raw.trigger('change');
    expect(wrapper.emitted('update')?.at(-1)?.[0]).toEqual({ control_props: { size: 'large' } });
    expect(wrapper.find('[data-props-tab="properties"] textarea').exists()).toBe(false);
    wrapper.unmount();
  });

  it('字典控件的字典选择位于数据源折叠项并默认展开', async () => {
    const wrapper = render(createField('dictionary', 1));
    await flushPromises();
    expect(wrapper.get('[data-fold="dataSource"]').find('[data-testid="dictionary-code-select"]').exists()).toBe(true);
    expect(wrapper.get('[data-fold="dataSource"]').classes()).toContain('is-active');
    wrapper.unmount();
  });
});

describe('原有字段写回键不变', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dictTypeApi.list.mockResolvedValue({ list: [], total: 0 });
    vi.mocked(businessDevelopmentApi.databaseTableSchema).mockResolvedValue({ columns: [{ name: 'id', type: 'int', primary: true }, { name: 'name', type: 'varchar' }] } as never);
  });

  it('每个控件仍以原键与原取值方式写回字段', async () => {
    const field = { ...createField('input', 1), relation_type: 'belongs_to', relation_table: 'fa_user', list_show: 1, list_sort: 0, nullable: 1, unsigned: 0 } as FormFieldDef;
    const wrapper = render(field);
    await flushPromises();
    const last = () => wrapper.emitted('update')?.at(-1)?.[0];
    const input = async (prop: string, value: string) => { await wrapper.get(`[data-prop="${prop}"] input`).setValue(value); };
    const number = async (selector: string, value: string) => { const el = wrapper.get(`${selector} input`); await el.setValue(value); await el.trigger('change'); };
    const select = async (prop: string, value: string) => { await wrapper.get(`[data-prop="${prop}"] select`).setValue(value); };
    const toggle = async (key: string) => { await wrapper.get(`[data-toggle="${key}"] .el-switch`).trigger('click'); };

    await input('label', '名称'); expect(last()).toEqual({ label: '名称' });
    await input('placeholder', '请输入'); expect(last()).toEqual({ placeholder: '请输入' });
    await input('form_group', '基础'); expect(last()).toEqual({ form_group: '基础' });
    await wrapper.get('[data-span-option="12"]').trigger('click'); expect(last()).toEqual({ form_span: 12 });
    await toggle('form_required'); expect(last()).toEqual({ form_required: 1 });
    await toggle('form_show'); expect(last()).toEqual({ form_show: 0 });
    await toggle('form_readonly'); expect(last()).toEqual({ form_readonly: 1 });
    await toggle('list_show'); expect(last()).toEqual({ list_show: 0 });
    await toggle('list_sort'); expect(last()).toEqual({ list_sort: 1 });
    await select('list_filter', 'eq'); expect(last()).toEqual({ list_filter: 'eq' });
    await select('list_formatter', 'tag'); expect(last()).toEqual({ list_formatter: 'tag' });
    await number('[data-prop="list_width"]', '120'); expect(wrapper.emitted('update')).toContainEqual([{ list_width: 120 }]);
    await input('field_name', 'title'); expect(last()).toEqual({ field_name: 'title' });
    await select('column_type', 'int'); expect(last()).toEqual({ column_type: 'int' });
    await toggle('nullable'); expect(last()).toEqual({ nullable: 0 });
    await input('default_value', '0'); expect(last()).toEqual({ default_value: '0' });
    await toggle('unsigned'); expect(last()).toEqual({ unsigned: 1 });
    await select('index_type', 'unique'); expect(last()).toEqual({ index_type: 'unique' });
    await input('comment', '备注'); expect(last()).toEqual({ comment: '备注' });
    await select('relation_type', 'has_many'); expect(last()).toEqual({ relation_type: 'has_many' });
    await select('relation_on_delete', 'cascade'); expect(last()).toEqual({ relation_on_delete: 'cascade' });
    await wrapper.get('[data-prop="relation_label_field"] select').trigger('change');
    await flushPromises();
    await select('relation_label_field', 'name'); expect(last()).toEqual({ relation_label_field: 'name' });
    await select('relation_value_field', 'id'); expect(last()).toEqual({ relation_value_field: 'id' });
    await wrapper.get('[data-props-tab="properties"] [data-property="placeholder"] input').setValue('x');
    expect(last()).toEqual({ control_props: { ...(field.control_props ?? {}), placeholder: 'x' } });
    await select('type', 'textarea');
    expect(Object.keys(last() as object).sort()).toEqual(['column_type', 'control_props', 'form_required', 'list_show', 'options_source', 'type']);
    wrapper.unmount();
  });

  it('关联表选择仍写回 relation_table / relation_label_field / relation_value_field', async () => {
    const wrapper = render({ ...createField('input', 1), relation_type: 'belongs_to', relation_table: '' } as FormFieldDef);
    await flushPromises();
    await wrapper.get('[data-prop="relation_table"] select').setValue('');
    await flushPromises();
    const keys = new Set((wrapper.emitted('update') ?? []).flatMap(([patch]) => Object.keys(patch as object)));
    expect([...keys].sort()).toEqual(['relation_label_field', 'relation_table', 'relation_value_field']);
    wrapper.unmount();
  });

  it('源码中写回字段的键集合与改版前一致', () => {
    // 逐个找出 patch({ ... }) 的对象字面量（括号配平），只保留第一层文本后收集键（含简写 `type,`）。
    const keys = new Set<string>();
    for (const source of [propsPanelSource, propsFoldsSource]) {
      for (let at = source.indexOf('patch({'); at >= 0; at = source.indexOf('patch({', at + 1)) {
        let depth = 0;
        let top = '';
        for (const char of source.slice(at + 6)) {
          if ('})]'.includes(char)) depth -= 1;
          if (depth === 0 && top) break;
          if (depth === 1) top += char;
          if ('{(['.includes(char)) depth += 1;
        }
        for (const match of top.matchAll(/(?:^|,)\s*([a-z_]+)\s*(?=[:,]|$)/g)) keys.add(match[1]);
      }
    }
    for (const key of ['label', 'type', 'placeholder', 'form_group', 'form_span', 'form_required', 'form_show', 'form_readonly', 'list_show', 'list_sort', 'list_filter', 'list_formatter', 'list_width', 'field_name', 'column_type', 'nullable', 'default_value', 'unsigned', 'index_type', 'comment', 'relation_type', 'relation_table', 'relation_label_field', 'relation_value_field', 'relation_on_delete', 'control_props', 'options_source']) {
      expect(keys, key).toContain(key);
    }
  });

  it('样式只用主题变量，不写死颜色、不用夜间仍为浅色的 primary-light 变量，控件不溢出', () => {
    for (const source of [propsPanelSource, propsFoldsSource, propsToggleSource]) {
      const style = source.slice(source.indexOf('<style'));
      expect(style).not.toMatch(/--el-color-primary-light-\d/);
      expect(style.split('\n').filter((line) => /#[0-9a-f]{3,8}\b|rgba?\(|(?<![-\w])(?:white|black)(?![-\w])/i.test(line))).toEqual([]);
    }
    expect(propsPanelSource).toMatch(/min-width:\s*0/);
  });
});
