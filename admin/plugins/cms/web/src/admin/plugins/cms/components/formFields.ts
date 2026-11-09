import { cmsTranslate } from '@/composables/useCmsI18n';
import type { CmsFormField } from '../types';

/** 与服务端 `FormFieldCatalog` 保持一致；这里只做提示与提交前检查，最终以服务端校验为准。 */
export const FORM_FIELD_TYPES = ['text', 'textarea', 'email', 'phone', 'number', 'radio', 'checkbox', 'select', 'date', 'datetime', 'time', 'consent', 'file', 'image', 'hidden', 'paragraph', 'divider'] as const;
export type FormFieldType = typeof FORM_FIELD_TYPES[number];
export type FormFieldProperty = 'placeholder' | 'help' | 'required' | 'default' | 'range' | 'integer' | 'options' | 'multiple' | 'html' | 'accept' | 'max_size' | 'max_files' | 'value' | 'from_query';
/** `range` 的含义：length 字数、number 数值、date 日期、count 可选数量。 */
export type FormFieldRange = 'length' | 'number' | 'date' | 'count';
export interface FormFieldMeta { label: string; display: boolean; properties: readonly FormFieldProperty[]; range?: FormFieldRange; defaults: Partial<CmsFormField> }

export const FIELD_NAME_PATTERN = /^[a-z][a-z0-9_]{0,63}$/;
export const RESERVED_FIELD_NAMES = ['captcha', 'captcha_id', 'hp', 'token', 'locale'];
export const MAX_FORM_FIELDS = 100;
export const MAX_FIELD_OPTIONS = 100;
export const MAX_FILE_SIZE = 20 * 1024 * 1024;
export const MAX_FILES = 10;
export const TEXT_LIMITS: Partial<Record<FormFieldType, number>> = { text: 2000, textarea: 20000, hidden: 500 };
export const ACCEPT_CATEGORIES = ['document', 'image', 'archive'] as const;
export const FORM_SUBMISSION_STATUSES = ['new', 'processing', 'done', 'invalid'] as const;
export const FORM_CODE_PATTERN = /^[a-z][a-z0-9_-]{0,63}$/;

const input = ['placeholder', 'help', 'required', 'default'] as const;
const meta = (type: FormFieldType, properties: readonly FormFieldProperty[], extra: Partial<FormFieldMeta> = {}): FormFieldMeta => ({ label: `cms.forms.types.${type}`, display: false, properties, defaults: {}, ...extra });
export const FORM_FIELD_META: Record<FormFieldType, FormFieldMeta> = {
  text: meta('text', [...input, 'range'], { range: 'length' }),
  textarea: meta('textarea', [...input, 'range'], { range: 'length' }),
  email: meta('email', input),
  phone: meta('phone', input),
  number: meta('number', [...input, 'range', 'integer'], { range: 'number', defaults: { integer: false } }),
  radio: meta('radio', ['help', 'required', 'default', 'options'], { defaults: { options: [] } }),
  checkbox: meta('checkbox', ['help', 'required', 'options', 'range'], { range: 'count', defaults: { options: [] } }),
  select: meta('select', [...input, 'options', 'multiple', 'range'], { range: 'count', defaults: { options: [], multiple: false } }),
  date: meta('date', [...input, 'range'], { range: 'date' }),
  datetime: meta('datetime', input),
  time: meta('time', input),
  consent: meta('consent', ['help', 'required', 'html'], { defaults: { html: '' } }),
  file: meta('file', ['help', 'required', 'accept', 'max_size', 'max_files'], { defaults: { accept: [...ACCEPT_CATEGORIES], max_size: MAX_FILE_SIZE, max_files: 1 } }),
  image: meta('image', ['help', 'required', 'max_size', 'max_files'], { defaults: { accept: ['image'], max_size: MAX_FILE_SIZE, max_files: 1 } }),
  hidden: meta('hidden', ['value', 'from_query'], { defaults: { value: '', from_query: false } }),
  paragraph: meta('paragraph', ['html'], { display: true, defaults: { html: '' } }),
  divider: meta('divider', [], { display: true })
};

export const isFieldType = (type: unknown): type is FormFieldType => FORM_FIELD_TYPES.includes(type as FormFieldType);
/** 多选下拉才有“可选数量”范围；单选下拉才有默认值。 */
export function fieldHas(field: CmsFormField, property: FormFieldProperty): boolean {
  if (!isFieldType(field.type) || !FORM_FIELD_META[field.type].properties.includes(property)) return false;
  if (field.type === 'select' && property === 'range') return !!field.multiple;
  if (field.type === 'select' && property === 'default') return !field.multiple;
  return true;
}

export function createField(type: FormFieldType, taken: string[]): CmsFormField {
  const definition = FORM_FIELD_META[type];
  let name = '';
  if (!definition.display) for (let index = 1; !name || taken.includes(name); index++) name = `${type}_${index}`;
  const base: CmsFormField = { name, type, label: '', width: 'full' };
  if (definition.properties.includes('required')) base.required = false;
  return { ...base, ...JSON.parse(JSON.stringify(definition.defaults)) };
}

const blank = (value: unknown) => value === undefined || value === null || value === '';
const labelOf = (field: CmsFormField) => field.label || field.name || cmsTranslate(FORM_FIELD_META[field.type as FormFieldType]?.label || 'cms.forms.field');

/** 返回第一条错误（已翻译），没有错误时返回空串。 */
export function validateFormFields(fields: CmsFormField[]): string {
  if (fields.length > MAX_FORM_FIELDS) return cmsTranslate('cms.forms.validation.tooManyFields', { max: MAX_FORM_FIELDS });
  const names = new Set<string>();
  for (const field of fields) {
    if (!isFieldType(field.type)) return cmsTranslate('cms.forms.validation.type');
    if (FORM_FIELD_META[field.type].display) continue;
    const name = field.name.trim();
    if (!FIELD_NAME_PATTERN.test(name)) return cmsTranslate('cms.forms.validation.fieldName', { name });
    if (RESERVED_FIELD_NAMES.includes(name)) return cmsTranslate('cms.forms.validation.reservedName', { name });
    if (names.has(name)) return cmsTranslate('cms.forms.validation.duplicateName', { name });
    names.add(name);
    const label = labelOf(field);
    if (fieldHas(field, 'options')) {
      const values = (field.options || []).map(option => option.value.trim());
      if (!values.length || values.length > MAX_FIELD_OPTIONS || values.some(value => !value) || new Set(values).size !== values.length) return cmsTranslate('cms.forms.validation.options', { label, max: MAX_FIELD_OPTIONS });
    }
    if (fieldHas(field, 'range') && !blank(field.min) && !blank(field.max)) {
      const range = FORM_FIELD_META[field.type].range;
      const [min, max] = range === 'date' ? [String(field.min), String(field.max)] : [Number(field.min), Number(field.max)];
      if (min > max) return cmsTranslate('cms.forms.validation.range', { label });
    }
    if (fieldHas(field, 'max_size') && (!Number.isInteger(field.max_size) || field.max_size! < 1 || field.max_size! > MAX_FILE_SIZE || !Number.isInteger(field.max_files) || field.max_files! < 1 || field.max_files! > MAX_FILES)) {
      return cmsTranslate('cms.forms.validation.files', { label, size: MAX_FILE_SIZE / 1024 / 1024, max: MAX_FILES });
    }
  }
  return '';
}

/** 按类型只提交可配置属性；展示类字段的标识由服务端按位置生成（`_p1`、`_d1`），这里同样计算以便多语言文字对上。 */
export function serializeFields(fields: CmsFormField[]): CmsFormField[] {
  const counters: Record<string, number> = { paragraph: 0, divider: 0 };
  return fields.map(field => {
    const type = field.type as FormFieldType;
    const definition = FORM_FIELD_META[type];
    const name = definition.display ? `_${type[0]}${++counters[type]}` : field.name.trim();
    const out: CmsFormField = { name, type, label: field.label.trim(), width: field.width === 'half' ? 'half' : 'full' };
    if (fieldHas(field, 'placeholder')) out.placeholder = field.placeholder || '';
    if (fieldHas(field, 'help')) out.help = field.help || '';
    if (fieldHas(field, 'required')) out.required = !!field.required;
    if (fieldHas(field, 'default') && !blank(field.default)) out.default = type === 'number' ? Number(field.default) : field.default;
    if (fieldHas(field, 'range')) for (const key of ['min', 'max'] as const) if (!blank(field[key])) out[key] = definition.range === 'date' ? String(field[key]) : Number(field[key]);
    if (fieldHas(field, 'integer')) out.integer = !!field.integer;
    if (fieldHas(field, 'options')) out.options = (field.options || []).map(option => ({ value: option.value.trim(), label: option.label.trim() }));
    if (type === 'select') out.multiple = !!field.multiple;
    if (fieldHas(field, 'html')) out.html = field.html || '';
    if (type === 'file' || type === 'image') { out.accept = type === 'image' && !field.accept?.length ? ['image'] : [...(field.accept || [])]; out.max_size = field.max_size; out.max_files = field.max_files; }
    if (fieldHas(field, 'value')) out.value = field.value || '';
    if (fieldHas(field, 'from_query')) out.from_query = !!field.from_query;
    return out;
  });
}

/** 空映射可能以 JSON 数组 `[]` 返回，读取前统一转成对象。 */
export function asMap<T>(value: unknown): Record<string, T> {
  return value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, T> : {};
}
