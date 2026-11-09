<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, reactive, ref } from 'vue';
import { cmsApi } from '../api';
import type { CmsForm, CmsFormField, CmsFormLocaleText, FormData } from '../types';
import { ACCEPT_CATEGORIES, FORM_CODE_PATTERN, FORM_FIELD_META, FORM_FIELD_TYPES, MAX_FIELD_OPTIONS, MAX_FILE_SIZE, MAX_FILES, MAX_FORM_FIELDS, TEXT_LIMITS, asMap, createField, fieldHas, serializeFields, validateFormFields, type FormFieldType } from './formFields';
import { useOperation } from './useOperation';
interface Row { key: number; field: CmsFormField }
interface FieldText { label: string; placeholder: string; help: string; options: Record<string, string> }
type LocaleText = { name: string; success_message: string; submit_text: string };
const { t } = useCmsI18n();
const props=defineProps<{siteId:string;form:CmsForm|null;languages:Array<{locale:string;name:string}>}>();
const emit=defineEmits<{saved:[form:CmsForm];close:[]}>();
const {busy,error,run}=useOperation();
const MB=1024*1024;
const section=ref('basic'),newType=ref<FormFieldType>('text'),selectedKey=ref<number>();
const settings=asMap<unknown>(props.form?.settings);
const draft=reactive({name:props.form?.name||'',code:props.form?.code||'',enabled:props.form?.enabled??true,success_message:String(settings.success_message??''),submit_text:String(settings.submit_text??''),captcha:settings.captcha===true,rate_limit:Number(settings.rate_limit)||5});
let nextKey=0;
const rows=ref<Row[]>((props.form?.fields||[]).map(field=>({key:++nextKey,field:JSON.parse(JSON.stringify(field)) as CmsFormField})));
const storedTexts=asMap<CmsFormLocaleText>(props.form?.texts);
/** 本页只编辑当前启用的站点语言；其他语言已有的文字原样保留。 */
const preserved=Object.fromEntries(Object.entries(storedTexts).filter(([locale])=>!props.languages.some(language=>language.locale===locale)));
const texts=ref<Record<string,LocaleText>>({}),fieldTexts=ref<Record<string,Record<number,FieldText>>>({});
for(const {locale} of props.languages){
  const entry=asMap<any>(storedTexts[locale]),fields=asMap<any>(entry.fields);
  texts.value[locale]={name:entry.name||'',success_message:entry.success_message||'',submit_text:entry.submit_text||''};
  fieldTexts.value[locale]={};
  for(const row of rows.value){const item=asMap<any>(fields[row.field.name]);fieldTexts.value[locale][row.key]={label:item.label||'',placeholder:item.placeholder||'',help:item.help||'',options:{...asMap<string>(item.options)}};}
}
const selected=computed(()=>rows.value.find(row=>row.key===selectedKey.value));
const typeLabel=(type:string)=>FORM_FIELD_META[type as FormFieldType]?t(FORM_FIELD_META[type as FormFieldType].label):type;
const rowTitle=(row:Row)=>row.field.label||row.field.name||typeLabel(row.field.type);
const rangeLabels=computed(()=>{const range=selected.value?FORM_FIELD_META[selected.value.field.type as FormFieldType]?.range:undefined;return range?[t(`cms.forms.range.${range}Min`),t(`cms.forms.range.${range}Max`)]:['',''];});
const rangeLimit=computed(()=>{const field=selected.value?.field;if(!field)return undefined;return field.type==='checkbox'||field.type==='select'?(field.options||[]).length:TEXT_LIMITS[field.type as FormFieldType];});
function addField(){
  if(rows.value.length>=MAX_FORM_FIELDS){error.value=t('cms.forms.validation.tooManyFields',{max:MAX_FORM_FIELDS});return;}
  const row={key:++nextKey,field:createField(newType.value,rows.value.map(item=>item.field.name))};
  rows.value.push(row);for(const {locale} of props.languages)fieldTexts.value[locale][row.key]={label:'',placeholder:'',help:'',options:{}};
  selectedKey.value=row.key;
}
function move(index:number,offset:number){const target=index+offset;if(target<0||target>=rows.value.length)return;const list=[...rows.value];[list[index],list[target]]=[list[target],list[index]];rows.value=list;}
function removeField(index:number){const [row]=rows.value.splice(index,1);if(row?.key===selectedKey.value)selectedKey.value=rows.value[Math.min(index,rows.value.length-1)]?.key;}
function addOption(field:CmsFormField){field.options=[...(field.options||[]),{value:'',label:''}];}
function removeOption(field:CmsFormField,index:number){field.options=(field.options||[]).filter((_,position)=>position!==index);}
const numeric=(value:unknown)=>value===null||value===undefined||value===''?undefined:Number(value);
function setSize(field:CmsFormField,value:number|null|undefined){field.max_size=Math.round(Number(value||0)*MB);}
/** 只提交非空译文；字段按客户端 key 对应，改名或重排后仍落到正确的字段标识上。 */
function serializeTexts(fields:CmsFormField[]){
  const out:Record<string,CmsFormLocaleText>={...preserved};
  for(const {locale} of props.languages){
    const base=texts.value[locale],entry:CmsFormLocaleText={},overrides:Record<string,any>={};
    for(const key of ['name','success_message','submit_text'] as const)if(base[key].trim())entry[key]=base[key].trim();
    rows.value.forEach((row,index)=>{
      const source=fieldTexts.value[locale][row.key],field=fields[index],item:Record<string,any>={};
      if(!source)return;
      if(source.label.trim())item.label=source.label.trim();
      for(const key of ['placeholder','help'] as const)if(fieldHas(field,key)&&source[key].trim())item[key]=source[key].trim();
      const options=Object.fromEntries((field.options||[]).filter(option=>source.options[option.value]?.trim()).map(option=>[option.value,source.options[option.value].trim()]));
      if(Object.keys(options).length)item.options=options;
      if(Object.keys(item).length)overrides[field.name]=item;
    });
    if(Object.keys(overrides).length)entry.fields=overrides;
    if(Object.keys(entry).length)out[locale]=entry;
  }
  return out;
}
async function save(){
  if(busy.value)return;
  error.value='';
  const name=draft.name.trim(),code=draft.code.trim();
  if(!name){error.value=t('cms.forms.validation.nameRequired');section.value='basic';return;}
  if(!FORM_CODE_PATTERN.test(code)){error.value=t('cms.forms.validation.code');section.value='basic';return;}
  const problem=validateFormFields(rows.value.map(row=>row.field));
  if(problem){error.value=problem;section.value='fields';return;}
  const fields=serializeFields(rows.value.map(row=>row.field));
  const payload:FormData={site_id:props.siteId,code,name,enabled:draft.enabled,fields,texts:serializeTexts(fields),settings:{success_message:draft.success_message.trim(),submit_text:draft.submit_text.trim(),captcha:draft.captcha,rate_limit:Math.min(60,Math.max(1,Math.round(draft.rate_limit||5)))}};
  if(props.form){payload.id=props.form.id;payload.lock_version=props.form.lock_version;}
  const saved=await run(()=>cmsApi.saveForm(payload));
  if(saved)emit('saved',saved);
}
</script>
<template>
  <el-dialog :model-value="true" :title="form?t('cms.forms.editForm'):t('cms.forms.newForm')" width="min(1000px,96vw)" :close-on-click-modal="false" :show-close="!busy" @close="emit('close')">
    <el-alert v-if="error" :title="error" type="error" :closable="false" class="editor-error" />
    <el-tabs v-model="section">
      <el-tab-pane name="basic" :label="t('cms.forms.basicAndSettings')">
        <el-form label-position="top" :disabled="busy" class="grid">
          <el-form-item :label="t('cms.forms.formName')"><el-input v-model="draft.name" maxlength="100" :aria-label="t('cms.forms.formName')" /></el-form-item>
          <el-form-item :label="t('cms.forms.code')"><el-input v-model="draft.code" maxlength="64" :aria-label="t('cms.forms.code')" :placeholder="t('cms.forms.codeHelp')" /></el-form-item>
          <el-form-item :label="t('cms.forms.successMessage')"><el-input v-model="draft.success_message" maxlength="500" :aria-label="t('cms.forms.successMessage')" /></el-form-item>
          <el-form-item :label="t('cms.forms.submitText')"><el-input v-model="draft.submit_text" maxlength="50" :aria-label="t('cms.forms.submitText')" /></el-form-item>
          <el-form-item :label="t('cms.forms.rateLimit')"><el-input-number v-model="draft.rate_limit" :min="1" :max="60" :precision="0" :aria-label="t('cms.forms.rateLimit')" /></el-form-item>
          <el-form-item :label="t('cms.forms.formOptions')"><el-switch v-model="draft.enabled" :active-text="t('cms.forms.enabled')" /><el-switch v-model="draft.captcha" :active-text="t('cms.forms.captcha')" class="spaced" /></el-form-item>
        </el-form>
      </el-tab-pane>
      <el-tab-pane name="fields" :label="t('cms.forms.fieldEditor')">
        <div class="add-field">
          <el-select v-model="newType" :aria-label="t('cms.forms.fieldType')" :disabled="busy"><el-option v-for="type in FORM_FIELD_TYPES" :key="type" :label="typeLabel(type)" :value="type" /></el-select>
          <el-button :disabled="busy||rows.length>=MAX_FORM_FIELDS" @click="addField">{{ t('cms.forms.addField') }}</el-button>
          <span class="muted">{{ t('cms.forms.fieldCount',{count:rows.length,max:MAX_FORM_FIELDS}) }}</span>
        </div>
        <div class="field-editor">
          <ol class="field-list">
            <li v-for="(row,index) in rows" :key="row.key" :class="{active:row.key===selectedKey}">
              <button type="button" class="field-pick" @click="selectedKey=row.key"><strong>{{ rowTitle(row) }}</strong><span class="muted">{{ typeLabel(row.field.type) }}<template v-if="row.field.name"> · {{ row.field.name }}</template></span></button>
              <el-button link :disabled="busy||index===0" @click="move(index,-1)">{{ t('cms.forms.moveUp') }}</el-button><el-button link :disabled="busy||index===rows.length-1" @click="move(index,1)">{{ t('cms.forms.moveDown') }}</el-button><el-button link type="danger" :disabled="busy" @click="removeField(index)">{{ t('cms.forms.remove') }}</el-button>
            </li>
            <li v-if="!rows.length" class="muted">{{ t('cms.forms.noFields') }}</li>
          </ol>
          <el-form v-if="selected" label-position="top" :disabled="busy" class="field-properties">
            <p class="muted">{{ typeLabel(selected.field.type) }}</p>
            <el-form-item v-if="!FORM_FIELD_META[selected.field.type as FormFieldType]?.display" :label="t('cms.forms.fieldName')"><el-input v-model="selected.field.name" maxlength="64" :aria-label="t('cms.forms.fieldName')" :placeholder="t('cms.forms.fieldNameHelp')" /></el-form-item>
            <el-form-item :label="t('cms.forms.fieldLabel')"><el-input v-model="selected.field.label" maxlength="100" :aria-label="t('cms.forms.fieldLabel')" /></el-form-item>
            <el-form-item :label="t('cms.forms.width')"><el-select v-model="selected.field.width" :aria-label="t('cms.forms.width')"><el-option value="full" :label="t('cms.forms.widthFull')" /><el-option value="half" :label="t('cms.forms.widthHalf')" /></el-select></el-form-item>
            <el-form-item v-if="fieldHas(selected.field,'placeholder')" :label="t('cms.forms.placeholder')"><el-input v-model="selected.field.placeholder" maxlength="255" :aria-label="t('cms.forms.placeholder')" /></el-form-item>
            <el-form-item v-if="fieldHas(selected.field,'help')" :label="t('cms.forms.help')"><el-input v-model="selected.field.help" maxlength="500" :aria-label="t('cms.forms.help')" /></el-form-item>
            <el-form-item v-if="fieldHas(selected.field,'required')"><el-switch v-model="selected.field.required" :active-text="t('cms.forms.required')" /></el-form-item>
            <el-form-item v-if="fieldHas(selected.field,'multiple')"><el-switch v-model="selected.field.multiple" :active-text="t('cms.forms.multiple')" /></el-form-item>
            <el-form-item v-if="fieldHas(selected.field,'options')" :label="t('cms.forms.options')">
              <div v-for="(option,index) in selected.field.options" :key="index" class="option-row"><el-input v-model="option.value" maxlength="255" :aria-label="t('cms.forms.optionValue')" :placeholder="t('cms.forms.optionValue')" /><el-input v-model="option.label" maxlength="255" :aria-label="t('cms.forms.optionLabel')" :placeholder="t('cms.forms.optionLabel')" /><el-button link type="danger" @click="removeOption(selected.field,index)">{{ t('cms.forms.removeOption') }}</el-button></div>
              <el-button :disabled="(selected.field.options||[]).length>=MAX_FIELD_OPTIONS" @click="addOption(selected.field)">{{ t('cms.forms.addOption') }}</el-button>
            </el-form-item>
            <el-form-item v-if="fieldHas(selected.field,'default')" :label="t('cms.forms.defaultValue')">
              <el-select v-if="fieldHas(selected.field,'options')" :model-value="selected.field.default as string|undefined" clearable @update:model-value="selected.field.default=$event" :aria-label="t('cms.forms.defaultValue')"><el-option v-for="option in (selected.field.options||[]).filter(item=>item.value)" :key="option.value" :label="option.label||option.value" :value="option.value" /></el-select>
              <el-input v-else :model-value="selected.field.default==null?'':String(selected.field.default)" :aria-label="t('cms.forms.defaultValue')" @update:model-value="selected.field.default=$event" />
            </el-form-item>
            <div v-if="fieldHas(selected.field,'range')" class="grid">
              <template v-if="FORM_FIELD_META[selected.field.type as FormFieldType].range==='date'">
                <el-form-item :label="rangeLabels[0]"><el-date-picker v-model="selected.field.min" type="date" value-format="YYYY-MM-DD" :aria-label="rangeLabels[0]" /></el-form-item>
                <el-form-item :label="rangeLabels[1]"><el-date-picker v-model="selected.field.max" type="date" value-format="YYYY-MM-DD" :aria-label="rangeLabels[1]" /></el-form-item>
              </template>
              <template v-else-if="FORM_FIELD_META[selected.field.type as FormFieldType].range==='number'">
                <el-form-item :label="rangeLabels[0]"><el-input-number :model-value="numeric(selected.field.min)" @update:model-value="selected.field.min=$event??null" :aria-label="rangeLabels[0]" /></el-form-item>
                <el-form-item :label="rangeLabels[1]"><el-input-number :model-value="numeric(selected.field.max)" @update:model-value="selected.field.max=$event??null" :aria-label="rangeLabels[1]" /></el-form-item>
              </template>
              <template v-else>
                <el-form-item :label="rangeLabels[0]"><el-input-number :model-value="numeric(selected.field.min)" @update:model-value="selected.field.min=$event??null" :min="0" :max="rangeLimit" :precision="0" :aria-label="rangeLabels[0]" /></el-form-item>
                <el-form-item :label="rangeLabels[1]"><el-input-number :model-value="numeric(selected.field.max)" @update:model-value="selected.field.max=$event??null" :min="0" :max="rangeLimit" :precision="0" :aria-label="rangeLabels[1]" /></el-form-item>
              </template>
            </div>
            <el-form-item v-if="fieldHas(selected.field,'integer')"><el-switch v-model="selected.field.integer" :active-text="t('cms.forms.integer')" /></el-form-item>
            <el-form-item v-if="fieldHas(selected.field,'html')" :label="t('cms.forms.html')"><el-input v-model="selected.field.html" type="textarea" :rows="4" :aria-label="t('cms.forms.html')" /></el-form-item>
            <el-form-item v-if="fieldHas(selected.field,'accept')" :label="t('cms.forms.accept')"><el-checkbox-group v-model="selected.field.accept"><el-checkbox v-for="category in ACCEPT_CATEGORIES" :key="category" :value="category">{{ t(`cms.forms.acceptCategories.${category}`) }}</el-checkbox></el-checkbox-group></el-form-item>
            <div v-if="fieldHas(selected.field,'max_size')" class="grid">
              <el-form-item :label="t('cms.forms.maxSize',{max:MAX_FILE_SIZE/MB})"><el-input-number :model-value="(selected.field.max_size||MAX_FILE_SIZE)/MB" :min="0.1" :max="MAX_FILE_SIZE/MB" :precision="1" :aria-label="t('cms.forms.maxSize',{max:MAX_FILE_SIZE/MB})" @update:model-value="setSize(selected.field,$event)" /></el-form-item>
              <el-form-item :label="t('cms.forms.maxFiles',{max:MAX_FILES})"><el-input-number v-model="selected.field.max_files" :min="1" :max="MAX_FILES" :precision="0" :aria-label="t('cms.forms.maxFiles',{max:MAX_FILES})" /></el-form-item>
            </div>
            <el-form-item v-if="fieldHas(selected.field,'value')" :label="t('cms.forms.hiddenValue')"><el-input v-model="selected.field.value" maxlength="500" :aria-label="t('cms.forms.hiddenValue')" /></el-form-item>
            <el-form-item v-if="fieldHas(selected.field,'from_query')"><el-switch v-model="selected.field.from_query" :active-text="t('cms.forms.fromQuery')" /></el-form-item>
          </el-form>
          <p v-else class="muted field-properties">{{ t('cms.forms.selectFieldToEdit') }}</p>
        </div>
      </el-tab-pane>
      <el-tab-pane name="texts" :label="t('cms.forms.localeTexts')">
        <p class="muted">{{ t('cms.forms.localeTextsHelp') }}</p>
        <el-tabs>
          <el-tab-pane v-for="language in languages" :key="language.locale" :name="language.locale" :label="`${language.name} (${language.locale})`">
            <el-form label-position="top" :disabled="busy" class="grid">
              <el-form-item :label="t('cms.forms.formName')"><el-input v-model="texts[language.locale].name" maxlength="100" :placeholder="draft.name" :aria-label="`${language.locale} ${t('cms.forms.formName')}`" /></el-form-item>
              <el-form-item :label="t('cms.forms.successMessage')"><el-input v-model="texts[language.locale].success_message" maxlength="500" :placeholder="draft.success_message" :aria-label="`${language.locale} ${t('cms.forms.successMessage')}`" /></el-form-item>
              <el-form-item :label="t('cms.forms.submitText')"><el-input v-model="texts[language.locale].submit_text" maxlength="50" :placeholder="draft.submit_text" :aria-label="`${language.locale} ${t('cms.forms.submitText')}`" /></el-form-item>
            </el-form>
            <section v-for="row in rows.filter(item=>item.field.type!=='divider')" :key="row.key" class="field-text">
              <h4>{{ rowTitle(row) }} <span class="muted">{{ typeLabel(row.field.type) }}</span></h4>
              <div class="grid">
                <el-input v-model="fieldTexts[language.locale][row.key].label" maxlength="100" :placeholder="row.field.label||t('cms.forms.fieldLabel')" :aria-label="`${language.locale} ${rowTitle(row)} ${t('cms.forms.fieldLabel')}`" />
                <el-input v-if="fieldHas(row.field,'placeholder')" v-model="fieldTexts[language.locale][row.key].placeholder" maxlength="255" :placeholder="row.field.placeholder||t('cms.forms.placeholder')" :aria-label="`${language.locale} ${rowTitle(row)} ${t('cms.forms.placeholder')}`" />
                <el-input v-if="fieldHas(row.field,'help')" v-model="fieldTexts[language.locale][row.key].help" maxlength="500" :placeholder="row.field.help||t('cms.forms.help')" :aria-label="`${language.locale} ${rowTitle(row)} ${t('cms.forms.help')}`" />
                <el-input v-for="option in (row.field.options||[]).filter(item=>item.value)" :key="option.value" v-model="fieldTexts[language.locale][row.key].options[option.value]" maxlength="255" :placeholder="option.label||option.value" :aria-label="`${language.locale} ${rowTitle(row)} ${option.value}`" />
              </div>
            </section>
          </el-tab-pane>
        </el-tabs>
      </el-tab-pane>
    </el-tabs>
    <template #footer><el-button :disabled="busy" @click="emit('close')">{{ t('cms.forms.cancel') }}</el-button><el-button type="primary" :loading="busy" @click="save">{{ t('cms.forms.saveForm') }}</el-button></template>
  </el-dialog>
</template>
<style scoped>
.editor-error{margin-bottom:12px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0 16px}.spaced{margin-left:16px}.muted{color:var(--el-text-color-secondary);font-size:12px}.add-field{display:flex;gap:12px;align-items:center;margin-bottom:12px}.add-field .el-select{width:180px}.field-editor{display:grid;grid-template-columns:minmax(240px,1fr) 2fr;gap:20px}.field-list{list-style:none;margin:0;padding:0;max-height:520px;overflow:auto}.field-list li{display:flex;align-items:center;gap:4px;padding:6px 8px;border-bottom:1px solid var(--el-border-color-lighter)}.field-list li.active{background:var(--el-fill-color-light)}.field-pick{flex:1;min-width:0;display:flex;flex-direction:column;align-items:flex-start;gap:2px;border:0;background:none;padding:0;cursor:pointer;text-align:left;color:inherit;font:inherit}.field-pick strong{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.option-row{display:flex;gap:8px;width:100%;margin-bottom:8px}.field-text{border-top:1px solid var(--el-border-color-lighter);padding-top:8px;margin-top:8px}.field-text h4{margin:4px 0 8px;font-size:13px}.field-text .el-input{margin-bottom:8px}@media(max-width:760px){.field-editor{grid-template-columns:1fr}}
</style>
