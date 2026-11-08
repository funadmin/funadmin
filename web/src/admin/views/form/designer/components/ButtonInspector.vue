<template>
  <div class="button-inspector">
    <template v-if="draft">
      <div class="button-inspector__head">
        <b>{{ draft.label || t('formDesigner.buttonsWorkspace.untitled', '未命名按钮') }}</b>
        <span>{{ title }} · {{ actionValue }} · {{ t('formDesigner.stableId', '稳定 ID') }} {{ draft.id }}</span>
      </div>
      <div v-if="unsupported || catalogError" class="button-inspector__alerts">
        <el-alert v-if="unsupported" :title="t('formDesigner.unsupportedAlert', '未注册、无权限或目录不可用：保留原配置，不代表可执行或可发布。')" type="warning" :closable="false" />
        <el-alert v-if="catalogError" :title="catalogError" type="warning" :closable="false" />
      </div>
      <el-form label-position="top" size="small" class="button-form">
        <el-tabs v-model="tab" class="button-inspector__tabs">
          <el-tab-pane :label="t('formDesigner.buttonsWorkspace.tabBasic', '基本')" name="basic">
            <el-form-item :label="t('formDesigner.action', '动作')"><el-select :aria-label="t('formDesigner.action', '动作')" :model-value="actionValue" @change="selectAction"><el-option v-for="option in actionOptions.filter(item => !item.custom)" :key="option.value" :value="option.value" :label="option.label" />
              <el-option-group v-if="customGroupVisible" :label="t('formDesigner.customAction.group', '自定义动作')">
                <el-option v-for="option in customOptions" :key="option.value" :value="option.value" :label="option.label"><span class="button-inspector__custom-option"><span>{{ option.label }}</span><el-tag v-if="option.pending" size="small" type="warning" effect="plain" disable-transitions>{{ t('formDesigner.customAction.pending', '待生成') }}</el-tag></span></el-option>
                <el-option v-if="customCreatable" :value="NEW_CUSTOM_ACTION" :label="t('formDesigner.customAction.create', '＋ 新建自定义动作')" />
                <el-option v-else :value="BLOCKED_CUSTOM_ACTION" :label="t('formDesigner.customAction.pluginBlocked', '插件目标的模块不支持自定义动作')" disabled />
              </el-option-group>
              <el-option v-if="unsupported && actionValue" :value="actionValue" :label="t('formDesigner.unavailableKept', { action: actionValue }, '{action}（不可用，已保留）')" disabled /></el-select>
              <div v-if="boundCustom" class="button-inspector__custom-hint" data-custom-bound>
                <span>{{ t('formDesigner.customAction.boundHint', '自定义动作') }} · {{ boundCustom.registryKey }}</span>
                <el-tag v-if="boundCustom.pending" size="small" type="warning" effect="plain" disable-transitions>{{ t('formDesigner.customAction.pending', '待生成') }}</el-tag>
                <span v-if="boundCustom.pending" class="button-inspector__custom-note">{{ t('formDesigner.customAction.pendingHint', '正式生成后才会生成处理方法与权限；动态发布页面上该按钮显示为禁用。') }}</span>
              </div></el-form-item>
            <el-form-item :label="t('formDesigner.displayName', '显示名称')"><el-input v-model="draft.label" :aria-label="t('formDesigner.displayName', '显示名称')" maxlength="100" /></el-form-item>
            <div class="button-form__two">
              <el-form-item :label="t('formDesigner.icon', '图标')"><el-select v-model="draft.icon" clearable><el-option v-for="icon in ['plus', 'edit', 'delete', 'view', 'refresh', 'download', 'upload', 'search']" :key="icon" :value="icon" :label="icon" /></el-select></el-form-item>
              <el-form-item :label="t('formDesigner.color', '颜色')"><el-select v-model="draft.color"><el-option v-for="color in ['default', 'primary', 'success', 'warning', 'danger', 'info']" :key="color" :value="color" :label="color" /></el-select></el-form-item>
              <el-form-item :label="t('formDesigner.size', '尺寸')"><el-select v-model="draft.size"><el-option v-for="size in ['small', 'default', 'large']" :key="size" :value="size" :label="size" /></el-select></el-form-item>
              <el-form-item :label="t('formDesigner.order', '排序')"><el-input-number v-model="draft.order" :min="-10000" :max="10000" :precision="0" controls-position="right" /></el-form-item>
            </div>
            <el-form-item :label="t('formDesigner.placement', '显示位置')"><el-radio-group v-model="draft.placement"><el-radio value="inline">{{ t('formDesigner.placementInline', '直接显示') }}</el-radio><el-radio value="more">{{ t('formDesigner.placementMore', '更多菜单') }}</el-radio></el-radio-group></el-form-item>
            <template v-if="location === 'toolbar'">
              <div class="button-form__two">
                <el-form-item :label="t('formDesigner.minSelection', '最少选择')"><el-input-number :aria-label="t('formDesigner.minSelectionCount', '最少选择数量')" :model-value="draft.selection?.min" :min="0" :max="200" :precision="0" controls-position="right" @update:model-value="value => setSelection('min', value)" /></el-form-item>
                <el-form-item :label="t('formDesigner.maxSelection', '最多选择')"><el-input-number :aria-label="t('formDesigner.maxSelectionCount', '最多选择数量')" :model-value="draft.selection?.max" :min="0" :max="200" :precision="0" controls-position="right" @update:model-value="value => setSelection('max', value)" /></el-form-item>
              </div>
              <el-form-item><el-button @click="delete draft.selection">{{ t('formDesigner.clearSelectionConstraint', '清除数量约束') }}</el-button></el-form-item>
            </template>
            <el-form-item :label="t('formDesigner.enableDisplay', '启用显示')"><el-switch :model-value="!draft.hidden" :aria-label="t('formDesigner.enableDisplay', '启用显示')" @change="value => draft!.hidden = !value" /></el-form-item>
            <el-form-item :label="t('formDesigner.tips', '提示 tips')"><el-input v-model="draft.tips" maxlength="500" /></el-form-item>
          </el-tab-pane>
          <el-tab-pane :label="t('formDesigner.buttonsWorkspace.tabCondition', '条件')" name="condition">
            <el-form-item v-for="condition in conditionKeys" :key="condition.key" :label="condition.label">
              <ListButtonConditionEditor :label="condition.label" :model-value="draft[condition.key]" :fields="fields ?? []" @update="value => { if (value) draft![condition.key] = value; else delete draft![condition.key]; }" />
            </el-form-item>
            <el-form-item :label="t('common.disable', '禁用')"><el-switch v-model="draft.disabled" /></el-form-item>
            <el-form-item :label="t('formDesigner.disabledReason', '禁用原因')"><el-input v-model="draft.disabledReason" maxlength="500" /></el-form-item>
          </el-tab-pane>
          <el-tab-pane :label="t('formDesigner.buttonsWorkspace.tabInteraction', '交互')" name="interaction">
            <el-alert v-if="metadata?.requiresConfirmation" class="mb-3" :title="t('formDesigner.requiresConfirmationAlert', '注册动作要求服务端二次确认，不能关闭；参数表单之后仍会确认。')" :closable="false" />
            <el-form-item :label="t('formDesigner.interaction', '交互')"><el-select :aria-label="t('formDesigner.interaction', '交互')" :model-value="draft.interaction?.type ?? 'none'" @change="changeInteraction"><el-option v-for="type in ['none', 'confirm', 'input', 'form']" :key="type" :value="type" :label="interactionTypeLabel(type)" /></el-select></el-form-item>
            <template v-if="draft.interaction && draft.interaction.type !== 'none'">
              <el-form-item :label="t('formDesigner.presentation', '容器')"><el-radio-group v-model="draft.interaction.presentation"><el-radio value="dialog">{{ t('formDesigner.presentationDialog', '弹窗') }}</el-radio><el-radio value="drawer">{{ t('formDesigner.presentationDrawer', '抽屉') }}</el-radio></el-radio-group></el-form-item>
              <el-form-item :label="t('formDesigner.interactionTitle', '标题')"><el-input v-model="draft.interaction.title" /></el-form-item><el-form-item :label="t('formDesigner.interactionMessage', '提示内容')"><el-input v-model="draft.interaction.message" /></el-form-item>
              <div v-for="(field, fieldIndex) in draft.interaction.fields ?? []" :key="fieldIndex" class="button-form__card">
                <el-form-item :label="t('formDesigner.paramName', '参数名')"><el-input :model-value="field.name" :aria-label="t('formDesigner.inputFieldName', { n: fieldIndex + 1 }, '输入字段 {n} 名称')" @update:model-value="value => renameField(fieldIndex, value)" /></el-form-item><el-form-item :label="t('formDesigner.fieldLabel', '标签')"><el-input v-model="field.label" /></el-form-item>
                <el-form-item :label="t('formDesigner.control', '控件')"><el-select v-model="field.type"><el-option v-for="type in ['input', 'textarea', 'number', 'select', 'switch', 'date']" :key="type" :value="type" :label="type" /></el-select></el-form-item>
                <el-form-item v-if="field.type === 'select'" :label="t('formDesigner.options', '选项')"><el-input type="textarea" :model-value="(field.options ?? []).map(option => `${option.label}=${option.value}`).join('\n')" :placeholder="t('formDesigner.optionsPlaceholder', '每行：标签=值')" @update:model-value="value => field.options = value.split('\n').filter(Boolean).map(line => { const [label, ...parts] = line.split('='); return { label, value: parts.join('=') || label }; })" /></el-form-item>
                <el-form-item v-if="field.type === 'number'" :label="t('formDesigner.minMax', '最小/最大')"><div class="button-form__two"><el-input-number v-model="field.min" controls-position="right" /><el-input-number v-model="field.max" controls-position="right" /></div></el-form-item>
                <div class="button-form__two">
                  <el-form-item :label="t('formDesigner.required', '必填')"><el-switch v-model="field.required" /></el-form-item>
                  <el-form-item :label="t('formDesigner.maxLength', '最大长度')"><el-input-number v-model="field.maxLength" :min="1" :max="10000" controls-position="right" /></el-form-item>
                </div>
                <el-button v-if="draft.interaction.type === 'form'" @click="removeField(fieldIndex)">{{ t('formDesigner.removeInputField', '移除输入字段') }}</el-button>
              </div>
              <el-button v-if="draft.interaction.type === 'form'" @click="addField">{{ t('formDesigner.addInputField', '添加输入字段') }}</el-button>
              <el-alert v-if="draft.interaction.fields?.length && draft.action.type === 'builtin'" class="mt-3" :title="t('formDesigner.builtinCrudAlert', '内置 CRUD 使用原业务表单；这里的输入不会被当作业务写入参数。')" :closable="false" />
            </template>
          </el-tab-pane>
          <el-tab-pane :label="t('formDesigner.buttonsWorkspace.tabSuccess', '成功后')" name="success">
            <el-form-item :label="t('formDesigner.successMessage', '成功提示')"><el-input :model-value="draft.success?.message" @update:model-value="value => draft!.success = { ...draft!.success, message: value }" /></el-form-item>
            <el-form-item v-for="effect in successEffects" :key="effect.key" :label="effect.label"><el-switch :model-value="draft.success?.[effect.key] ?? false" @change="value => draft!.success = { ...draft!.success, [effect.key]: Boolean(value) }" /></el-form-item>
          </el-tab-pane>
          <el-tab-pane :label="t('formDesigner.buttonsWorkspace.tabAdvanced', '高级')" name="advanced">
            <el-divider v-if="parameterNames.length">{{ t('formDesigner.paramBindingDivider', '参数绑定（数据库字段名）') }}</el-divider>
            <div v-for="name in parameterNames" :key="name" class="button-form__card">
              <strong>{{ name }} <small>{{ metadata?.parameterTypes[name] }}</small></strong>
              <el-select :aria-label="t('formDesigner.paramSource', { name }, '参数 {name} 来源')" :model-value="draft.params?.[name]?.source" :placeholder="t('formDesigner.selectSource', '选择来源')" @change="source => setBindingSource(name, source)">
                <el-option v-for="source in bindingSources" :key="source.value" :value="source.value" :label="source.label" :disabled="!sourceAllowed(source.value)" />
              </el-select>
              <template v-if="draft.params?.[name]?.source === 'literal'">
                <el-select :aria-label="t('formDesigner.paramLiteralType', { name }, '参数 {name} 常量类型')" :model-value="literalType(name)" @change="type => setLiteralType(name, type)"><el-option v-for="type in ['string', 'number', 'boolean', 'null']" :key="type" :value="type" :label="type" /></el-select>
                <el-input :aria-label="t('formDesigner.paramLiteralValue', { name }, '参数 {name} 常量值')" :model-value="String((draft.params[name] as { value: unknown }).value ?? '')" @update:model-value="value => setLiteral(name, value)" />
              </template>
              <el-select v-else :aria-label="t('formDesigner.paramField', { name }, '参数 {name} 字段')" :model-value="(draft.params?.[name] as { field?: string })?.field" filterable :placeholder="t('formDesigner.selectField', '选择字段')" @change="field => setBindingField(name, field)"><el-option v-for="field in bindingFields(draft.params?.[name]?.source)" :key="field" :value="field" :label="field" /></el-select>
              <el-button v-if="!requiredParameters.includes(name)" @click="delete draft.params![name]">{{ t('formDesigner.clearBinding', '清除绑定') }}</el-button>
            </div>
            <el-form-item :label="t('formDesigner.extraPermission', '附加权限')"><el-input v-model="draft.permission" :readonly="Boolean(metadata || resourceMetadata)" :placeholder="t('formDesigner.extraPermissionPlaceholder', '只收紧授权，不授予权限')" /></el-form-item>
            <el-form-item :label="t('formDesigner.catalogVersion', '目录版本')"><el-input :model-value="'capabilityVersion' in draft.action ? draft.action.capabilityVersion : ''" readonly :placeholder="t('formDesigner.buttonsWorkspace.noCatalogVersion', '仅注册动作与受控资源有目录版本')" /></el-form-item>
            <el-form-item :label="t('formDesigner.stableId', '稳定 ID')"><el-input :model-value="draft.id" disabled /></el-form-item>
          </el-tab-pane>
        </el-tabs>
      </el-form>
      <div class="button-inspector__footer">
        <el-button size="small" @click="preview"><i class="i-ep-video-play" />{{ t('formDesigner.simulatePreview', '模拟预览') }}</el-button>
        <el-button v-if="!creating" size="small" type="danger" plain @click="removeCurrent">{{ t('formDesigner.buttonsWorkspace.removeButton', '删除按钮') }}</el-button>
        <span class="button-inspector__spacer" />
        <el-button size="small" @click="init">{{ t('formDesigner.buttonsWorkspace.revert', '还原') }}</el-button>
        <el-button size="small" type="primary" :disabled="!draft.label.trim()" @click="save">{{ t('formDesigner.apply', '应用') }}</el-button>
      </div>
    </template>
    <div v-else class="button-inspector__empty">{{ t('formDesigner.buttonsWorkspace.inspectorEmpty', '点击左侧列表或预览中的按钮进行编辑') }}</div>
    <ListButtonInteraction ref="previewInteraction" />
  </div>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import ListButtonConditionEditor from './ListButtonConditionEditor.vue';
import ListButtonInteraction from '../../components/ListButtonInteraction.vue';
import { useButtonDraft, type ButtonDraftProps } from './useButtonDraft';
import type { FormListButton, FormListCustomAction } from '../../schema/types';
import { customActionAppliesTo, customActionRegistryKey } from './customActions';
/** 按钮与工具页签右栏：编辑选中按钮（原抽屉表单），草稿经「应用」校验后整组写回该位置。 */
const props = withDefaults(defineProps<ButtonDraftProps & { title: string; buttons: FormListButton[]; buttonId?: string; creating?: boolean; session?: number; customActions?: FormListCustomAction[]; customActionEntity?: string; customActionsBlocked?: boolean }>(), { resourceEnabled: true });
const emit = defineEmits<{ update: [value: FormListButton[]]; select: [id: string]; createCustomAction: [] }>();
const { t } = useI18n();
const tab = ref('basic');
const editor = useButtonDraft(props);
const { draft, actionValue, actionOptions, customOptions, metadata, resourceMetadata, unsupported, requiredParameters, parameterNames, bindingSources, conditionKeys, interactionTypeLabel, sourceAllowed, bindingFields, setBindingSource, setBindingField, literalType, setLiteralType, setLiteral, setCondition, changeAction, changeInteraction, addField, renameField, removeField, setSelection, validate } = editor;
// 「＋ 新建自定义动作」只是入口，不是可绑定的动作值；插件目标只显示不可选的说明。
const NEW_CUSTOM_ACTION = '__custom-action:new';
const BLOCKED_CUSTOM_ACTION = '__custom-action:blocked';
const customLocation = computed(() => props.location === 'row' || props.location === 'toolbar');
const customCreatable = computed(() => customLocation.value && !props.customActionsBlocked && Boolean(props.customActionEntity) && props.resourceEnabled !== false);
const customGroupVisible = computed(() => customLocation.value && (customOptions.value.length > 0 || customCreatable.value || Boolean(props.customActionsBlocked)));
const boundCustom = computed(() => {
  const action = draft.value?.action;
  if (action?.type !== 'registered' || !props.customActionEntity) return undefined;
  const item = (props.customActions ?? []).find(entry => customActionAppliesTo(entry, props.location) && customActionRegistryKey(props.customActionEntity!, entry.key) === action.key);
  return item ? { registryKey: action.key, pending: !props.actions?.[action.key] } : undefined;
});
function selectAction(value: string) {
  if (value === NEW_CUSTOM_ACTION) { emit('createCustomAction'); return; }
  if (value === BLOCKED_CUSTOM_ACTION) return;
  changeAction(value);
}
const successEffects = computed(() => [{ key: 'refresh', label: t('formDesigner.successRefresh', '成功刷新') }, { key: 'clearSelection', label: t('formDesigner.successClearSelection', '清空选择') }, { key: 'close', label: t('formDesigner.successClose', '关闭容器') }] as const);
const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
const index = computed(() => props.creating ? -1 : props.buttons.findIndex(button => button.id === props.buttonId));
const source = computed(() => index.value >= 0 ? JSON.stringify(props.buttons[index.value]) : '');
let snapshot = '';
function init() {
  editor.reset();
  if (props.creating) draft.value = { id: `button-${crypto.randomUUID()}`, label: t('formDesigner.newButton', '新按钮'), action: { type: 'refresh' } };
  else draft.value = index.value >= 0 ? clone(props.buttons[index.value]) : undefined;
  snapshot = draft.value ? JSON.stringify(draft.value) : '';
}
// 位置、选中按钮或一次新的「添加」都开启新草稿（session 区分连续两次添加）。
watch(() => `${props.location}|${props.creating ? `new:${props.session ?? 0}` : index.value >= 0 ? `id:${props.buttonId}` : ''}`, () => { tab.value = 'basic'; init(); }, { immediate: true });
// 外部改动（拖拽排序、撤销）同步到草稿：未改动的草稿直接重载；有未应用修改且外部只改了排序时只同步 order，避免应用时把旧排序写回。
watch(source, (next, previous) => {
  if (props.creating || !next || !draft.value) return;
  if (JSON.stringify(draft.value) === snapshot) { init(); return; }
  const before = JSON.parse(previous || '{}'); const after = JSON.parse(next);
  if (JSON.stringify({ ...before, order: undefined }) === JSON.stringify({ ...after, order: undefined })) { draft.value.order = after.order; snapshot = JSON.stringify({ ...JSON.parse(snapshot), order: after.order }); }
  else init();
});
const previewInteraction = ref<InstanceType<typeof ListButtonInteraction>>();
async function preview() {
  if (!draft.value) return;
  const button = clone(draft.value);
  button.interaction = { ...button.interaction, type: button.interaction?.type ?? 'none', title: t('formDesigner.simulateInteraction', { label: button.label }, '模拟交互：{label}') };
  const result = await previewInteraction.value?.open(button, {});
  if (result !== null) ElMessage.info(t('formDesigner.previewOnlyInfo', '预览仅模拟，不执行请求、写入、导航、复制或下载'));
}
function save() {
  const button = validate();
  if (!button) return;
  const next = clone(props.buttons); const position = props.creating ? -1 : next.findIndex(item => item.id === button.id);
  if (position < 0) next.push(button); else next[position] = button;
  snapshot = JSON.stringify(draft.value);
  emit('update', next);
  if (props.creating) emit('select', button.id);
}
function removeCurrent() { if (draft.value) emit('update', clone(props.buttons).filter(button => button.id !== draft.value!.id)); }
defineExpose({ draft, save, setCondition, changeAction, changeInteraction });
</script>
<style scoped>
.button-inspector { display: flex; flex-direction: column; height: 100%; min-height: 0; }
.button-inspector__head { display: grid; gap: 2px; padding: 12px 14px 6px; }
.button-inspector__head b { font-size: 14px; color: var(--el-text-color-primary); }
.button-inspector__head span { font-size: 11px; color: var(--el-text-color-secondary); word-break: break-all; }
.button-inspector__alerts { display: grid; gap: 6px; padding: 0 14px 8px; }
.button-form { flex: 1 1 auto; min-height: 0; overflow: auto; }
.button-inspector__footer, .button-inspector__head, .button-inspector__alerts { flex: none; }
.button-inspector__tabs :deep(.el-tabs__header) { margin: 0 0 10px; padding: 0 10px; }
.button-inspector__tabs :deep(.el-tabs__item) { padding: 0 8px; font-size: 12.5px; }
.button-inspector__tabs :deep(.el-tab-pane) { padding: 0 14px 12px; }
.button-form :deep(.el-form-item) { margin-bottom: 10px; }
.button-form :deep(.el-select), .button-form :deep(.el-input-number) { width: 100%; }
.button-form__two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 8px; width: 100%; }
.button-form__card { display: grid; gap: 6px; margin-bottom: 10px; padding: 10px; border: 1px solid var(--el-border-color-lighter); border-radius: 8px; }
.button-form__card small { font-weight: 400; color: var(--el-text-color-secondary); }
.button-inspector__footer { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 10px 14px; border-top: 1px solid var(--el-border-color-lighter); }
.button-inspector__footer :deep(.el-button + .el-button) { margin-left: 0; }
.button-inspector__spacer { flex: 1; }
.button-inspector__custom-option { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; }
.button-inspector__custom-hint { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; width: 100%; margin-top: 6px; font-size: 11.5px; color: var(--el-text-color-secondary); word-break: break-all; }
.button-inspector__custom-note { flex-basis: 100%; color: var(--el-text-color-placeholder); }
.button-inspector__empty { display: grid; flex: 1; place-items: center; min-height: 160px; padding: 24px; font-size: 12px; text-align: center; color: var(--el-text-color-secondary); }
</style>
