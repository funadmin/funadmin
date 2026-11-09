import { cmsTranslate } from '@/composables/useCmsI18n';
import type { ModelField, FormData, Category } from '../types';

export function fieldDefaults(fields: ModelField[], existing: FormData = {}): FormData {
  return Object.fromEntries(fields.map(field => {
    const fallback = field.type === 'boolean' ? false : field.type === 'gallery' ? [] : ['number','image','file'].includes(field.type) ? null : '';
    const value = Object.hasOwn(existing,field.name) ? existing[field.name] : Object.hasOwn(field,'default') ? field.default : fallback;
    return [field.name, value == null ? value : JSON.parse(JSON.stringify(value))];
  }));
}

export function compatibleCategories(categories: Category[], model: string): Category[] {
  return categories.filter(category => Number(category.status ?? 1) === 1 && (!category.model_id || String(category.model_id) === model));
}

export function validateContentForm(form: FormData, fields: ModelField[]): Record<string,string> {
  const errors: Record<string,string> = {};
  if (!String(form.title ?? '').trim()) errors.title=cmsTranslate('cms.labels.enterATitle2');
  const path=String(form.desired_path ?? '');
  if (!path.startsWith('/') || path.startsWith('//') || /[?#\\\x00-\x20]/.test(path) || path.split('/').some(part=>part==='.'||part==='..')) errors.desired_path=cmsTranslate('cms.labels.enterAValidPathStartingWithWithoutSpacesQuery');
  for (const field of fields) {
    const value=form.data?.[field.name], key=`data.${field.name}`;
    const empty=value==null || typeof value==='string'&&!value.trim() || Array.isArray(value)&&!value.length;
    if (empty) { if(field.required) errors[key]=cmsTranslate('cms.labels.enterValue', { value1: field.label });continue; }
    if (field.type==='boolean' && typeof value!=='boolean') errors[key]=cmsTranslate('cms.labels.selectOnOrOff');
    else if(field.type==='number') {
      if (typeof value==='boolean'||!['string','number'].includes(typeof value)||!Number.isFinite(Number(value))) errors[key]=cmsTranslate('cms.labels.enterAValidNumber');
      else if(field.min!=null&&Number(value)<field.min) errors[key]=cmsTranslate('cms.labels.mustBeAtLeastValue', { value1: field.min });
      else if(field.max!=null&&Number(value)>field.max) errors[key]=cmsTranslate('cms.labels.mustBeAtMostValue', { value1: field.max });
    } else if(field.type==='select'&&!field.options?.includes(value)) errors[key]=cmsTranslate('cms.labels.selectAValidOption');
    else if(['text','textarea'].includes(field.type)) {
      if(typeof value!=='string'&&typeof value!=='number') errors[key]=cmsTranslate('cms.labels.enterText');
      else if(Array.from(String(value).trim()).length>(field.max_length??(field.type==='textarea'?20000:2000))) errors[key]=cmsTranslate('cms.labels.enterUpToValueCharacters', { value1: field.max_length??(field.type==='textarea'?20000:2000) });
    }
  }
  return errors;
}

/** 编辑器里由作者修改、参与“是否有未保存修改”判断的字段。 */
export const CONTENT_EDITABLE_KEYS=['title','summary','body_html','desired_path','model_id','model_version_id','category_id','data','template','robots','seo_title','seo_description','author_name','cover_attach_id'];
function canonical(value: unknown): unknown {
  if(Array.isArray(value))return value.map(canonical);
  if(value&&typeof value==='object')return Object.fromEntries(Object.entries(value).sort(([a],[b])=>a.localeCompare(b)).map(([key,item])=>[key,canonical(item)]));
  return value;
}
export function contentFingerprint(form: FormData): string {
  return JSON.stringify(canonical(Object.fromEntries(CONTENT_EDITABLE_KEYS.map(key=>[key,form[key]??null]))));
}
