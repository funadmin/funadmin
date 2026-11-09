import { defineComponent, h, inject, nextTick, provide } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import FormsPanel from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/FormsPanel.vue';
import FormSubmissionDrawer from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/FormSubmissionDrawer.vue';
import FormEditor from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/FormEditor.vue';
import { FORM_FIELD_META, FORM_FIELD_TYPES, validateFormFields } from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/formFields';

const apiPath = '../../../admin/plugins/cms/web/src/admin/plugins/cms/api';
const api = vi.hoisted(() => ({
  forms: vi.fn(), formSummary: vi.fn(), saveForm: vi.fn(), formStatus: vi.fn(), deleteForm: vi.fn(),
  formSubmissions: vi.fn(), formSubmission: vi.fn(), noteFormSubmission: vi.fn(), transitionFormSubmission: vi.fn(),
  invalidateFormSubmissions: vi.fn(), removeFormSubmissions: vi.fn(),
  formExportUrl: vi.fn((params: Record<string, unknown>) => `/export?${JSON.stringify(params)}`),
  formFileUrl: vi.fn((site: string, file: string, preview?: boolean) => `/file/${site}/${file}${preview ? '?preview=1' : ''}`)
}));
vi.mock('../../../admin/plugins/cms/web/src/admin/plugins/cms/api', () => ({ cmsApi: api }));

const pass = defineComponent({ setup(_, { slots }) { return () => h('div', [slots.default?.(), slots.footer?.()]); } });
const button = defineComponent({ props: ['disabled'], emits: ['click'], setup(p, { slots, emit }) { return () => h('button', { disabled: p.disabled, onClick: () => emit('click') }, slots.default?.()); } });
const input = defineComponent({ props: ['modelValue', 'type'], emits: ['update:modelValue'], setup(p, { emit, attrs }) { return () => h(p.type === 'textarea' ? 'textarea' : 'input', { ...attrs, value: p.modelValue ?? '', onInput: (e: any) => emit('update:modelValue', e.target.value) }); } });
const number = defineComponent({ props: ['modelValue'], emits: ['update:modelValue'], setup(p, { emit, attrs }) { return () => h('input', { ...attrs, type: 'number', value: p.modelValue ?? '', onInput: (e: any) => emit('update:modelValue', e.target.value === '' ? null : Number(e.target.value)) }); } });
const select = defineComponent({ props: ['modelValue'], emits: ['update:modelValue', 'change'], setup(p, { emit, slots, attrs }) { return () => h('select', { ...attrs, value: p.modelValue, onChange: (e: any) => { emit('update:modelValue', e.target.value); emit('change', e.target.value); } }, slots.default?.()); } });
const option = defineComponent({ props: ['value', 'label'], setup(p) { return () => h('option', { value: p.value }, p.label); } });
const overlay = defineComponent({ props: ['modelValue', 'title'], setup(p, { slots }) { return () => p.modelValue ? h('div', { class: 'overlay' }, [p.title, slots.default?.(), slots.footer?.()]) : null; } });
const labelled = defineComponent({ props: ['label', 'title'], setup(p, { slots }) { return () => h('div', [p.title, p.label, slots.default?.()]); } });
const tabPane = defineComponent({ props: ['label'], setup(p, { slots }) { return () => h('section', [h('h2', p.label), slots.default?.()]); } });
const RowScope = defineComponent({ props: ['row'], setup(p, { slots }) { provide('row', p.row); return () => h('div', { class: 'row' }, slots.default?.()); } });
const table = defineComponent({ props: ['data'], setup(p, { slots }) { return () => h('div', (p.data || []).map((row: any) => h(RowScope, { row }, () => slots.default?.()))); } });
const column = defineComponent({ props: ['prop', 'label', 'type'], setup(p, { slots }) { const row = inject<any>('row'); return () => p.type === 'selection' ? null : h('span', slots.default ? slots.default({ row, $index: 0 }) : String(row?.[p.prop] ?? '')); } });
const popconfirm = defineComponent({ emits: ['confirm'], setup(_, { slots, emit }) { return () => h('span', { onClick: () => emit('confirm') }, slots.reference?.()); } });
const stubs = {
  ...Object.fromEntries(['ElTabs', 'ElForm', 'ElTag', 'ElEmpty', 'ElSwitch', 'ElCheckbox', 'ElCheckboxGroup', 'ElDatePicker', 'ElTimeline', 'ElTimelineItem', 'ElDivider', 'ElPagination', 'TablePagination', 'ElLink'].map(n => [n, pass])),
  ElAlert: labelled, ElFormItem: labelled, ElButton: button, ElInput: input, ElInputNumber: number, ElSelect: select, ElOption: option,
  ElDialog: overlay, ElDrawer: overlay, ElTabPane: tabPane, ElTable: table, ElTableColumn: column, ElPopconfirm: popconfirm
};
const global = { stubs, directives: { loading: () => {} } };
const scope = { site_id: '7', locale: 'zh-cn' };
const languages = [{ id: '1', site_id: '7', locale: 'zh-cn', name: '中文' }, { id: '2', site_id: '7', locale: 'en', name: 'English' }];
const contact = { id: '3', site_id: '7', code: 'contact', name: '联系我们', enabled: true, fields: [], texts: [], settings: { success_message: '', submit_text: '', captcha: false, rate_limit: 5 }, lock_version: 1, created_at: '', updated_at: '', submission_count: 4, new_count: 3 };
const row = { id: '55', form_id: '3', locale: 'zh-cn', status: 'processing', source_url: '/contact', ip: '1.2.3.4', user_agent: '', member_id: null, lock_version: 2, handled_at: null, created_at: '2026-11-09 10:00:00', form_code: 'contact', form_name: '联系我们', summary: [{ label: '姓名', value: '张三' }] };
const created = { id: 'e1', type: 'created', admin_id: null, body: '', from_status: null, to_status: null, created_at: '2026-11-09 10:00:00' };
const detail = (events: any[] = [created], status = 'new') => ({
  ...row, status, summary: undefined,
  fields: [
    { name: 'name', type: 'text', label: '姓名', value: '张三', text: '张三' },
    { name: 'way', type: 'select', label: '联系方式', value: 'phone', text: '电话联系' },
    { name: 'agree', type: 'consent', label: '隐私条款', value: true, text: '' },
    { name: 'docs', type: 'file', label: '附件', value: null, text: 'a.png, b.pdf', files: [{ id: 'f1', field: 'docs', name: 'a.png', mime: 'image/png', size: 2048, created_at: '' }, { id: 'f2', field: 'docs', name: 'b.pdf', mime: 'application/pdf', size: 4096, created_at: '' }] }
  ],
  files: [{ id: 'f1', field: 'docs', name: 'a.png', mime: 'image/png', size: 2048, created_at: '' }, { id: 'f2', field: 'docs', name: 'b.pdf', mime: 'application/pdf', size: 4096, created_at: '' }],
  events
});
const buttons = (w: any, text: string) => w.findAll('button').filter((b: any) => b.text() === text);
const wrappers: Array<{ unmount: () => void }> = [];
function keep<T extends { unmount: () => void }>(w: T): T { wrappers.push(w); return w; }

beforeEach(() => {
  vi.clearAllMocks();
  api.forms.mockResolvedValue([contact]);
  api.formSummary.mockResolvedValue({ new_count: 3 });
  api.formSubmissions.mockResolvedValue({ list: [row], total: 1, page: 1, pageSize: 20 });
  api.formSubmission.mockResolvedValue(detail());
});
afterEach(() => { while (wrappers.length) wrappers.pop()!.unmount(); });

describe('CMS 后台表单页面', () => {
  it('默认只列出新提交和跟进中并显示新提交数量', async () => {
    const w = keep(mount(FormsPanel, { props: { scope, can: () => true, languages }, global })); await flushPromises();
    expect(api.formSubmissions).toHaveBeenCalledWith({ site_id: '7', status: ['new', 'processing'], page: 1 });
    expect(api.formSummary).toHaveBeenCalledWith('7');
    expect(w.text()).toContain('新提交 3');
    expect(w.text()).toContain('张三');
    expect(w.text()).toContain('跟进中');
    api.formSummary.mockResolvedValue({ new_count: 2 });
    api.invalidateFormSubmissions.mockResolvedValue({ count: 1 });
    (w.vm as any).$.setupState.selection = [row]; await nextTick();
    await buttons(w, '标记无效')[0].trigger('click'); await flushPromises();
    expect(api.invalidateFormSubmissions).toHaveBeenCalledWith({ site_id: '7', ids: ['55'] });
    expect(w.text()).toContain('新提交 2');
  });

  it('详情抽屉写备注并改状态后刷新时间线', async () => {
    const w = keep(mount(FormSubmissionDrawer, { props: { scope, can: () => true, id: '55' }, global })); await flushPromises();
    expect(api.formSubmission).toHaveBeenCalledWith('7', '55');
    expect(w.text()).toContain('电话联系');
    expect(w.text()).toContain('已同意');
    expect(w.find('img').attributes('src')).toBe('/file/7/f1?preview=1');
    expect(w.findAll('a').some(a => a.attributes('href') === '/file/7/f2' && a.text().includes('b.pdf'))).toBe(true);
    const note = { id: 'e2', type: 'note', admin_id: '1', body: '已电话回访', from_status: null, to_status: null, created_at: '2026-11-09 11:00:00' };
    api.noteFormSubmission.mockResolvedValue(detail([created, note]));
    await w.get('textarea[aria-label="备注"]').setValue('已电话回访');
    await buttons(w, '添加备注')[0].trigger('click'); await flushPromises();
    expect(api.noteFormSubmission).toHaveBeenCalledWith({ site_id: '7', id: '55', body: '已电话回访' });
    expect(w.text()).toContain('已电话回访');
    const moved = { id: 'e3', type: 'status', admin_id: '1', body: '', from_status: 'new', to_status: 'done', created_at: '2026-11-09 12:00:00' };
    api.transitionFormSubmission.mockResolvedValue({ ...detail([created, note, moved], 'done'), lock_version: 3 });
    await w.get('select[aria-label="新状态"]').setValue('done');
    await buttons(w, '更改状态')[0].trigger('click'); await flushPromises();
    expect(api.transitionFormSubmission).toHaveBeenCalledWith({ site_id: '7', id: '55', status: 'done', lock_version: 2 });
    expect(w.text()).toContain('新提交 → 已完成');
    expect(w.emitted('changed')).toHaveLength(2);
    api.transitionFormSubmission.mockRejectedValue({ code: 409, msg: '提交已被他人处理，请刷新后重试' });
    api.formSubmission.mockClear();
    await w.get('select[aria-label="新状态"]').setValue('invalid');
    await buttons(w, '更改状态')[0].trigger('click'); await flushPromises();
    expect(w.text()).toContain('提交已被他人处理，请刷新后重试');
    expect(api.formSubmission).toHaveBeenCalledWith('7', '55');
  });

  it('没有导出和删除权限时不显示对应按钮', async () => {
    const limited = keep(mount(FormsPanel, { props: { scope, can: (code: string) => !['cms:form:export', 'cms:form:delete'].includes(code), languages }, global })); await flushPromises();
    expect(buttons(limited, '导出 CSV')).toHaveLength(0);
    expect(buttons(limited, '删除所选')).toHaveLength(0);
    expect(buttons(limited, '标记无效')).toHaveLength(1);
    const reader = keep(mount(FormsPanel, { props: { scope, can: (code: string) => ['cms:form:list', 'cms:form:submissions'].includes(code), languages }, global })); await flushPromises();
    for (const text of ['标记无效', '导出 CSV', '删除所选', '新建表单', '编辑']) expect(buttons(reader, text)).toHaveLength(0);
    expect(buttons(reader, '嵌入代码')).toHaveLength(1);
    const open = vi.spyOn(window, 'open').mockReturnValue(null);
    const full = keep(mount(FormsPanel, { props: { scope, can: () => true, languages }, global })); await flushPromises();
    expect(buttons(full, '删除所选')).toHaveLength(1);
    await buttons(full, '导出 CSV')[0].trigger('click');
    expect(api.formExportUrl).toHaveBeenCalledWith({ site_id: '7', status: ['new', 'processing'], locale: 'zh-cn' });
    expect(open).toHaveBeenCalledWith(api.formExportUrl.mock.results[0].value, '_blank', 'noopener');
    open.mockRestore();
    const { cmsApi } = await vi.importActual<typeof import('../../../admin/plugins/cms/web/src/admin/plugins/cms/api')>(apiPath);
    expect(cmsApi.formExportUrl({ site_id: '7', status: ['new', 'processing'], keyword: 'a&b', locale: 'en-us' })).toBe('/admin/plugins/cms/forms/submissions/export?site_id=7&status[]=new&status[]=processing&keyword=a%26b&locale=en-us');
    expect(cmsApi.formFileUrl('7', '9', true)).toBe('/admin/plugins/cms/forms/files/download?site_id=7&id=9&preview=1');
  });

  it('字段编辑器按类型显示可配置属性并校验字段标识', async () => {
    expect(FORM_FIELD_TYPES).toHaveLength(17);
    expect(FORM_FIELD_META.paragraph.properties).toContain('html');
    expect(FORM_FIELD_META.consent.properties).toContain('html');
    expect(validateFormFields([{ name: 'token', type: 'text', label: '', width: 'full' }])).toContain('系统保留');
    api.saveForm.mockResolvedValue(contact);
    const w = keep(mount(FormEditor, { props: { siteId: '7', form: null, languages }, global })); await flushPromises();
    await w.get('input[aria-label="表单名称"]').setValue('联系我们');
    await w.get('input[aria-label="调用代码"]').setValue('contact');
    await w.get('select[aria-label="字段类型"]').setValue('file');
    await buttons(w, '添加字段')[0].trigger('click');
    expect(w.text()).toContain('允许的文件类型');
    expect(w.text()).toContain('文件数量上限');
    expect(buttons(w, '添加选项')).toHaveLength(0);
    await w.get('select[aria-label="字段类型"]').setValue('radio');
    await buttons(w, '添加字段')[0].trigger('click');
    expect(buttons(w, '添加选项')).toHaveLength(1);
    expect(w.text()).not.toContain('允许的文件类型');
    await w.get('input[aria-label="字段标识"]').setValue('Bad Name');
    await buttons(w, '保存表单')[0].trigger('click'); await flushPromises();
    expect(w.text()).toContain('字段标识「Bad Name」无效');
    await w.get('input[aria-label="字段标识"]').setValue('token');
    await buttons(w, '保存表单')[0].trigger('click'); await flushPromises();
    expect(w.text()).toContain('字段标识「token」为系统保留');
    await w.get('input[aria-label="字段标识"]').setValue('channel');
    await buttons(w, '保存表单')[0].trigger('click'); await flushPromises();
    expect(w.text()).toContain('需要 1 至 100 个选项');
    expect(api.saveForm).not.toHaveBeenCalled();
    await buttons(w, '添加选项')[0].trigger('click');
    await w.get('input[aria-label="选项值"]').setValue('ad');
    await w.get('input[aria-label="显示文字"]').setValue('广告');
    await buttons(w, '保存表单')[0].trigger('click'); await flushPromises();
    expect(api.saveForm).toHaveBeenCalledWith(expect.objectContaining({
      site_id: '7', code: 'contact', name: '联系我们', enabled: true,
      fields: [
        expect.objectContaining({ name: 'file_1', type: 'file', accept: ['document', 'image', 'archive'], max_size: 20 * 1024 * 1024, max_files: 1 }),
        expect.objectContaining({ name: 'channel', type: 'radio', options: [{ value: 'ad', label: '广告' }] })
      ],
      settings: { success_message: '', submit_text: '', captcha: false, rate_limit: 5 }
    }));
    expect(api.saveForm.mock.calls[0][0]).not.toHaveProperty('id');
    expect(w.emitted('saved')).toHaveLength(1);
  });

  it('导出与翻页只使用已查询的筛选，未选状态按全部状态查询', async () => {
    const open = vi.spyOn(window, 'open').mockReturnValue(null);
    const w = keep(mount(FormsPanel, { props: { scope, can: () => true, languages }, global })); await flushPromises();
    const state = (w.vm as any).$.setupState;
    state.filters.keyword = '李四'; state.filters.status = []; await nextTick();
    await buttons(w, '导出 CSV')[0].trigger('click');
    expect(api.formExportUrl).toHaveBeenLastCalledWith({ site_id: '7', status: ['new', 'processing'], locale: 'zh-cn' });
    state.page = 2; await flushPromises();
    expect(api.formSubmissions).toHaveBeenLastCalledWith({ site_id: '7', status: ['new', 'processing'], page: 2 });
    await buttons(w, '查询')[0].trigger('click'); await flushPromises();
    const all = ['new', 'processing', 'done', 'invalid'];
    expect(api.formSubmissions).toHaveBeenLastCalledWith({ site_id: '7', status: all, keyword: '李四', page: 1 });
    await buttons(w, '导出 CSV')[0].trigger('click');
    expect(api.formExportUrl).toHaveBeenLastCalledWith({ site_id: '7', status: all, keyword: '李四', locale: 'zh-cn' });
    open.mockRestore();
  });

  it('文件字段必须选择允许的类型，数字默认值必须是数字', async () => {
    expect(validateFormFields([{ name: 'cv', type: 'file', label: '简历', width: 'full', accept: [], max_size: 1024, max_files: 1 }])).toBe('「简历」至少选择一种允许的文件类型');
    expect(validateFormFields([{ name: 'photo', type: 'image', label: '照片', width: 'full', accept: [], max_size: 1024, max_files: 1 }])).toBe('');
    const w = keep(mount(FormEditor, { props: { siteId: '7', form: null, languages }, global })); await flushPromises();
    await w.get('input[aria-label="表单名称"]').setValue('报名');
    await w.get('input[aria-label="调用代码"]').setValue('signup');
    await w.get('select[aria-label="字段类型"]').setValue('number');
    await buttons(w, '添加字段')[0].trigger('click');
    await w.get('input[aria-label="标签"]').setValue('人数');
    await w.get('input[aria-label="默认值"]').setValue('abc');
    await buttons(w, '保存表单')[0].trigger('click'); await flushPromises();
    expect(w.text()).toContain('「人数」的默认值必须是数字');
    await w.get('input[aria-label="默认值"]').setValue('3');
    await w.get('select[aria-label="字段类型"]').setValue('file');
    await buttons(w, '添加字段')[0].trigger('click');
    (w.vm as any).$.setupState.selected.field.accept = [];
    await buttons(w, '保存表单')[0].trigger('click'); await flushPromises();
    expect(w.text()).toContain('至少选择一种允许的文件类型');
    expect(api.saveForm).not.toHaveBeenCalled();
    (w.vm as any).$.setupState.selected.field.accept = ['document'];
    api.saveForm.mockResolvedValue(contact);
    await buttons(w, '保存表单')[0].trigger('click'); await flushPromises();
    expect(api.saveForm.mock.calls[0][0].fields).toEqual([
      expect.objectContaining({ name: 'number_1', type: 'number', default: 3 }),
      expect.objectContaining({ name: 'file_1', type: 'file', accept: ['document'] })
    ]);
  });

  it('复制嵌入代码同时给出 Blade 与模板标签写法', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });
    const w = keep(mount(FormsPanel, { props: { scope, can: (code: string) => code === 'cms:form:list', languages }, global })); await flushPromises();
    expect(api.formSubmissions).not.toHaveBeenCalled();
    await buttons(w, '嵌入代码')[0].trigger('click');
    const blade = "{!! $cms->tag('form', ['name' => 'contact']) !!}", tag = '{cms:form name="contact" /}';
    expect(w.text()).toContain(blade);
    expect(w.text()).toContain(tag);
    await buttons(w, '复制')[0].trigger('click'); await flushPromises();
    expect(writeText).toHaveBeenCalledWith(blade);
    await buttons(w, '复制')[1].trigger('click'); await flushPromises();
    expect(writeText).toHaveBeenCalledWith(tag);
  });
});
