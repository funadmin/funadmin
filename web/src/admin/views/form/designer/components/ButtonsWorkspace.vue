<template>
  <div class="buttons-workspace" :class="{ 'is-narrow': narrow }">
    <aside class="buttons-workspace__left">
      <div class="buttons-workspace__left-title">{{ t('formDesigner.buttonsWorkspace.locations', '按钮位置') }}</div>
      <div class="buttons-workspace__left-body">
        <ListButtonEditor
          v-for="location in locations"
          :key="location.key"
          :title="location.label"
          :location="location.key"
          :model-value="modelValue.buttons?.[location.key]"
          :selected-id="selected?.location === location.key ? selected.id : undefined"
          :active="selected?.location === location.key"
          @update="value => emit('updateButtons', location.key, value)"
          @select="id => select(location.key, id)"
          @add="add(location.key)"
        />
        <section class="buttons-workspace__custom" data-custom-actions>
          <strong>{{ t('formDesigner.customAction.group', '自定义动作') }}</strong>
          <p v-if="customActionsBlocked" class="buttons-workspace__custom-blocked" data-custom-actions-blocked>{{ t('formDesigner.customAction.pluginBlockedHint', '插件目标的模块不支持自定义动作：处理代码与权限只会生成到核心目录。') }}</p>
          <template v-else>
            <div v-for="action in customActions" :key="action.key" class="buttons-workspace__custom-item" :data-custom-action="action.key">
              <div class="buttons-workspace__custom-main">
                <span class="buttons-workspace__custom-label">{{ action.label }}</span>
                <el-tag v-if="customPending(action)" size="small" type="warning" effect="plain" disable-transitions>{{ t('formDesigner.customAction.pending', '待生成') }}</el-tag>
              </div>
              <span class="buttons-workspace__custom-key">{{ action.key }} · {{ action.locations.map(location => locationLabels[location]).join(' / ') }}</span>
              <span class="buttons-workspace__custom-ops">
                <el-button link type="primary" size="small" @click="openCustomAction(action)">{{ t('common.edit', '编辑') }}</el-button>
                <el-button link type="danger" size="small" @click="removeCustomAction(action)">{{ t('common.remove', '删除') }}</el-button>
              </span>
            </div>
            <el-button size="small" class="buttons-workspace__custom-add" :disabled="!customActionEntity" @click="openCustomAction()">{{ t('formDesigner.customAction.create', '＋ 新建自定义动作') }}</el-button>
          </template>
        </section>
        <section class="buttons-workspace__tools">
          <strong>{{ t('formDesigner.listPanel.commonTools', '通用工具') }}</strong>
          <label v-for="tool in tools" :key="tool.key" class="buttons-workspace__tool">
            <span>{{ tool.label }}</span>
            <el-switch size="small" :data-tool-switch="tool.key" :aria-label="tool.label" :model-value="modelValue.tools?.[tool.key] !== false" @change="value => emit('updateTools', { ...modelValue.tools, [tool.key]: Boolean(value) })" />
          </label>
        </section>
      </div>
    </aside>
    <main class="buttons-workspace__center">
      <div class="buttons-workspace__center-bar">
        <span>{{ t('formDesigner.buttonsWorkspace.previewHint', '列表页预览 · 点击按钮即可编辑，拖拽调整顺序') }}</span>
        <span class="buttons-workspace__spacer" />
        <Teleport :to="catalogTeleport || 'body'" :disabled="!catalogTeleport" defer>
          <span class="buttons-workspace__catalog" data-action-catalog>
            <span :class="{ 'is-failed': catalogFailed }">{{ catalogFailed ? t('formDesigner.buttonsWorkspace.catalogFailed', '动作目录不可用') : catalogCount === undefined ? t('formDesigner.buttonsWorkspace.catalog', '动作目录') : t('formDesigner.buttonsWorkspace.catalogSummary', { n: catalogCount }, '动作目录 · {n} 项') }}</span>
            <span aria-hidden="true">·</span>
            <el-button link type="primary" size="small" :loading="catalogLoading" @click="emit('reloadCatalog')">{{ t('formDesigner.buttonsWorkspace.reloadCatalog', '刷新目录') }}</el-button>
            <el-tooltip :content="t('formDesigner.listPanel.actionCatalogHint', '各位置可选的“注册动作”来自服务端设计目录（按路由权限过滤）；服务端新增或调整动作后点此刷新。')" placement="bottom-end"><i class="i-ep-info-filled buttons-workspace__hint" :aria-label="t('formDesigner.buttonsWorkspace.catalogHintAria', '动作目录说明')" /></el-tooltip>
          </span>
        </Teleport>
        <div class="buttons-workspace__seg" role="radiogroup" data-selection-toggle :aria-label="t('formDesigner.buttonsWorkspace.selectionAria', '模拟选中行数')">
          <button v-for="option in selectionOptions" :key="option.value" type="button" role="radio" :aria-checked="selectionCount === option.value" :class="{ 'is-on': selectionCount === option.value }" @click="selectionCount = option.value">{{ option.label }}</button>
        </div>
      </div>
      <ListPreview
        :buttons="effectiveButtons"
        :tools="modelValue.tools"
        :tool-labels="toolLabels"
        :columns="columns"
        :rows="rows"
        :fields="previewFields"
        :category-fields="categoryFields"
        :location-labels="locationLabels"
        :selection-count="selectionCount"
        :selected="selected"
        :category-mode="categoryMode"
        @select="select"
        @reorder="reorder"
        @add="add"
      >
        <template #footer><span class="buttons-workspace__muted">{{ t('formDesigner.buttonsWorkspace.sampleHint', '示例数据：按显示条件与禁用条件逐行计算；划线表示该行运行时不显示，半透明表示禁用（悬停查看原因）。') }}</span></template>
      </ListPreview>
    </main>
    <aside v-if="!narrow" class="buttons-workspace__right">
      <ButtonInspector v-if="selectedLocation" ref="inspector" v-bind="inspectorProps" @update="value => emit('updateButtons', selectedLocation!.key, value)" @select="id => select(selectedLocation!.key, id)" @create-custom-action="openCustomAction(undefined, true)" />
      <div v-else class="buttons-workspace__empty">{{ t('formDesigner.buttonsWorkspace.inspectorEmpty', '点击左侧列表或预览中的按钮进行编辑') }}</div>
    </aside>
    <el-drawer v-else v-model="drawerOpen" :title="selectedLocation?.label" size="min(420px, 96vw)" append-to-body class="buttons-workspace__drawer">
      <ButtonInspector v-if="selectedLocation" ref="inspector" v-bind="inspectorProps" @update="value => emit('updateButtons', selectedLocation!.key, value)" @select="id => select(selectedLocation!.key, id)" @create-custom-action="openCustomAction(undefined, true)" />
    </el-drawer>
    <el-dialog v-model="customDialog.open" :title="customDialog.action ? t('formDesigner.customAction.editTitle', '编辑自定义动作') : t('formDesigner.customAction.createTitle', '新建自定义动作')" width="min(520px, 96vw)" append-to-body class="custom-action-dialog" @closed="customDialog.bind = false">
      <CustomActionForm v-if="customDialog.open" :key="customDialog.session" :action="customDialog.action" :existing="customActions" :entity="customActionEntity ?? ''" :default-locations="customDialog.locations" @submit="submitCustomAction" @cancel="customDialog.open = false" />
    </el-dialog>
  </div>
</template>
<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useMediaQuery } from '@vueuse/core';
import type { FormListActionMetadata, FormListBuiltinAction, FormListButton, FormListButtonLocation, FormListConfiguration, FormListCustomAction, FormListCustomActionLocation } from '../../schema/types';
import type { ListResource } from '../../runtime/listResourceHost';
import { defaultListButtons } from '../../runtime/listButtonHost';
import ListButtonEditor from './ListButtonEditor.vue';
import ButtonInspector from './ButtonInspector.vue';
import ListPreview from './ListPreview.vue';
import CustomActionForm from './CustomActionForm.vue';
import { customActionRegistryKey, syncBoundButtonParameters, withoutBoundButtons } from './customActions';
import { effectiveListButtons, listPreviewRows, type ListPreviewColumn } from './listPreviewSample';

export interface ButtonsWorkspaceLocation { key: FormListButtonLocation; label: string; fields: string[]; builtinKeys: FormListBuiltinAction[]; resourceEnabled: boolean; actions?: Record<string, FormListActionMetadata>; catalogError?: string }
type ToolKey = keyof NonNullable<FormListConfiguration['tools']>;
const props = defineProps<{ modelValue: FormListConfiguration; locations: readonly ButtonsWorkspaceLocation[]; tools: ReadonlyArray<{ key: ToolKey; label: string }>; filterFields: string[]; categoryFields: string[]; resources: Record<string, ListResource>; columns: ListPreviewColumn[]; catalogLoading?: boolean; catalogCount?: number; catalogFailed?: boolean; catalogTeleport?: string; customActionEntity?: string; customActionsBlocked?: boolean }>();
/** updateCustomActions：新的 list.customActions（空时为 undefined，不写入空数组）与需要同时改写的按钮位置。 */
const emit = defineEmits<{ updateButtons: [location: FormListButtonLocation, value: FormListButton[] | undefined]; updateTools: [tools: NonNullable<FormListConfiguration['tools']>]; reloadCatalog: []; updateCustomActions: [actions: FormListCustomAction[] | undefined, buttons: Partial<Record<FormListButtonLocation, FormListButton[]>> | undefined] }>();
const { t } = useI18n();
const narrow = useMediaQuery('(max-width: 1099px)');
const drawerOpen = ref(false);
const selected = ref<{ location: FormListButtonLocation; id?: string; creating?: boolean; session?: number }>();
let session = 0;
const selectionCount = ref(0);
const selectionOptions = computed(() => [{ value: 0, label: t('formDesigner.buttonsWorkspace.noSelection', '未选中行') }, { value: 2, label: t('formDesigner.buttonsWorkspace.twoSelected', '选中 2 行') }]);
const selectedLocation = computed(() => props.locations.find(location => location.key === selected.value?.location));
const locationLabels = computed(() => Object.fromEntries(props.locations.map(location => [location.key, location.label])));
const toolLabels = computed(() => Object.fromEntries(props.tools.map(tool => [tool.key, tool.label])));
const previewFields = computed(() => props.locations.find(location => location.key === 'row')?.fields ?? []);
const rows = computed(() => listPreviewRows(props.columns, 3));
const categoryMode = computed(() => props.modelValue.leftTree?.enabled ? 'tree' : props.modelValue.category?.enabled ? 'tabs' : 'none');
const clone = (value: FormListButton[]) => JSON.parse(JSON.stringify(value)) as FormListButton[];
const baseButtons = (location: FormListButtonLocation) => props.modelValue.buttons?.[location] ?? defaultListButtons(location);
// 继承时预览宿主默认按钮，并按该位置实际可用的内置动作过滤（分类按钮取决于分类树的节点操作开关）。
const effectiveButtons = computed(() => effectiveListButtons(props.locations, props.modelValue.buttons));
const inspectorProps = computed(() => {
  const location = selectedLocation.value!;
  return { customActions: customActions.value, customActionEntity: props.customActionEntity, customActionsBlocked: props.customActionsBlocked, title: location.label, location: location.key, buttons: baseButtons(location.key), buttonId: selected.value?.id, creating: selected.value?.creating, session: selected.value?.session, fields: location.fields, filterFields: props.filterFields, categoryFields: props.categoryFields, resources: props.resources, actions: location.actions, catalogError: location.catalogError, builtinKeys: location.builtinKeys, resourceEnabled: location.resourceEnabled };
});
function select(location: FormListButtonLocation, id: string) { selected.value = { location, id }; if (narrow.value) drawerOpen.value = true; }
function add(location: FormListButtonLocation) { selected.value = { location, creating: true, session: ++session }; if (narrow.value) drawerOpen.value = true; }
/** 预览中按运行时排序（order）拖拽；写回时整组按新顺序重排 order。 */
function reorder(location: FormListButtonLocation, fromId: string, toId: string) {
  const next = clone(baseButtons(location)).map((button, index) => ({ button, index })).sort((left, right) => (left.button.order ?? 0) - (right.button.order ?? 0) || left.index - right.index).map(item => item.button);
  const from = next.findIndex(button => button.id === fromId); const to = next.findIndex(button => button.id === toId);
  if (from < 0 || to < 0) return;
  const [moved] = next.splice(from, 1); next.splice(to, 0, moved);
  next.forEach((button, index) => { button.order = index; });
  emit('updateButtons', location, next);
}
// 自定义动作（list.customActions）：左栏管理；按钮检查器下拉的「＋ 新建自定义动作」创建后自动绑定当前草稿。
const customActions = computed(() => props.modelValue.customActions ?? []);
const registryKey = (action: FormListCustomAction) => customActionRegistryKey(props.customActionEntity ?? '', action.key);
const customPending = (action: FormListCustomAction) => !props.locations.some(location => location.actions?.[registryKey(action)]);
const inspector = ref<{ changeAction: (key: string) => void }>();
const customDialog = reactive<{ open: boolean; action?: FormListCustomAction; bind: boolean; locations: FormListCustomActionLocation[]; session: number }>({ open: false, bind: false, locations: ['row'], session: 0 });
const pendingBind = ref<string>();
function openCustomAction(action?: FormListCustomAction, bind = false) {
  if (props.customActionsBlocked || !props.customActionEntity) return;
  const current = selected.value?.location;
  Object.assign(customDialog, { open: true, action, bind, locations: current === 'toolbar' ? ['toolbar'] : ['row'], session: customDialog.session + 1 });
}
const nextActions = (actions: FormListCustomAction[]) => actions.length ? actions : undefined;
async function submitCustomAction(action: FormListCustomAction) {
  const editing = customDialog.action;
  if (!editing) {
    if (customDialog.bind) pendingBind.value = `registered:${registryKey(action)}`;
    customDialog.open = false;
    emit('updateCustomActions', [...customActions.value, action], undefined);
    return;
  }
  // 编辑时去掉某个位置：该位置上绑定此动作的按钮将失效，确认后一并移除。
  const dropped = editing.locations.filter(location => !action.locations.includes(location));
  const { count, patch } = withoutBoundButtons(props.modelValue.buttons, registryKey(editing), dropped);
  if (count) {
    try { await ElMessageBox.confirm(t('formDesigner.customAction.dropLocationConfirm', { n: count }, '去掉的位置上有 {n} 个按钮绑定了此动作，保存后将一并移除这些按钮。'), t('formDesigner.customAction.editTitle', '编辑自定义动作'), { type: 'warning' }); }
    catch { return; }
  }
  // 参数变化：仍绑定此动作的按钮同步参数绑定，否则正式生成会因按钮缺少参数被拒绝。
  const synced = syncBoundButtonParameters({ ...props.modelValue.buttons, ...patch }, registryKey(editing), editing, action);
  customDialog.open = false;
  emit('updateCustomActions', customActions.value.map(item => item.key === editing.key ? action : item), patch || synced.patch ? { ...patch, ...synced.patch } : undefined);
  if (synced.added.length) ElMessage.warning({ message: t('formDesigner.customAction.parametersBoundDefaults', { n: synced.count, names: synced.added.join(', ') }, '已为绑定此动作的 {n} 个按钮将参数 {names} 绑定为默认常量，请在按钮的参数绑定中确认。'), duration: 6000 });
}
async function removeCustomAction(action: FormListCustomAction) {
  const { count, patch } = withoutBoundButtons(props.modelValue.buttons, registryKey(action));
  try {
    await ElMessageBox.confirm(count ? t('formDesigner.customAction.removeConfirmBound', { label: action.label, n: count }, '删除自定义动作「{label}」？绑定它的 {n} 个按钮将一并移除。') : t('formDesigner.customAction.removeConfirm', { label: action.label }, '删除自定义动作「{label}」？'), t('formDesigner.customAction.removeTitle', '删除自定义动作'), { type: 'warning' });
  } catch { return; }
  emit('updateCustomActions', nextActions(customActions.value.filter(item => item.key !== action.key)), patch);
}
// 新动作写回 Schema 后检查器才能列出它，再把当前草稿绑定过去。
watch(customActions, async () => {
  if (!pendingBind.value) return;
  const key = pendingBind.value;
  await nextTick();
  if (!customActions.value.some(action => `registered:${registryKey(action)}` === key)) return;
  pendingBind.value = undefined;
  inspector.value?.changeAction(key);
});
defineExpose({ select, add, selected });
</script>
<style scoped>
.buttons-workspace { display: grid; grid-template-columns: 248px minmax(0, 1fr) 320px; height: clamp(560px, calc(100vh - 210px), 1400px); overflow: hidden; border: 1px solid var(--el-border-color-lighter); border-radius: 12px; background: var(--el-bg-color); }
.buttons-workspace.is-narrow { grid-template-columns: 220px minmax(0, 1fr); }
.buttons-workspace__left { display: grid; grid-template-rows: auto minmax(0, 1fr); min-width: 0; border-right: 1px solid var(--el-border-color-lighter); }
.buttons-workspace__left-title { padding: 8px 0; border-bottom: 2px solid var(--el-color-primary); margin: 0 10px; font-size: 12.5px; font-weight: 600; text-align: center; color: var(--el-color-primary); }
.buttons-workspace__left-body { display: grid; align-content: start; gap: 8px; padding: 10px; overflow: auto; }
.buttons-workspace__custom { display: grid; gap: 6px; padding: 8px 10px; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; }
.buttons-workspace__custom strong { font-size: 12.5px; }
.buttons-workspace__custom-blocked { margin: 0; font-size: 12px; line-height: 1.5; color: var(--el-text-color-secondary); }
.buttons-workspace__custom-item { display: grid; gap: 2px; padding: 6px 8px; border-radius: 8px; background: var(--el-fill-color-light); }
.buttons-workspace__custom-main { display: flex; align-items: center; gap: 6px; min-width: 0; }
.buttons-workspace__custom-label { overflow: hidden; font-size: 12.5px; color: var(--el-text-color-primary); text-overflow: ellipsis; white-space: nowrap; }
.buttons-workspace__custom-key { font-size: 11px; color: var(--el-text-color-secondary); word-break: break-all; }
.buttons-workspace__custom-ops { display: flex; gap: 6px; }
.buttons-workspace__custom-ops :deep(.el-button + .el-button) { margin-left: 0; }
.buttons-workspace__custom-add { justify-self: start; }
.buttons-workspace__tools { display: grid; gap: 4px; padding: 8px 10px; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; }
.buttons-workspace__tools strong { font-size: 12.5px; }
.buttons-workspace__tool { display: flex; align-items: center; justify-content: space-between; padding: 0 6px; font-size: 12px; color: var(--el-text-color-regular); }
.buttons-workspace__center { display: grid; grid-template-rows: auto minmax(0, 1fr); gap: 10px; min-width: 0; padding: 14px; overflow: auto; background: var(--el-fill-color-light); }
.buttons-workspace__center > :deep(.list-preview) { align-self: start; }
.buttons-workspace__center-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 12px; color: var(--el-text-color-secondary); }
.buttons-workspace__spacer { flex: 1; }
.buttons-workspace__seg { display: inline-flex; gap: 2px; padding: 3px; border-radius: 9px; background: var(--el-fill-color); }
.buttons-workspace__seg button { padding: 2px 10px; border: 0; border-radius: 7px; font-size: 12px; color: var(--el-text-color-regular); background: transparent; cursor: pointer; }
.buttons-workspace__seg button.is-on { color: var(--el-color-primary); font-weight: 600; background: var(--el-bg-color); box-shadow: var(--el-box-shadow-lighter); }
.buttons-workspace__muted { font-size: 12px; color: var(--el-text-color-secondary); }
.buttons-workspace__right { min-width: 0; min-height: 0; overflow: hidden; border-left: 1px solid var(--el-border-color-lighter); }
.buttons-workspace__empty { display: grid; place-items: center; height: 100%; padding: 24px; font-size: 12px; text-align: center; color: var(--el-text-color-secondary); }
.buttons-workspace__catalog { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--el-text-color-secondary); white-space: nowrap; }
.buttons-workspace__catalog .is-failed { color: var(--el-color-warning); }
.buttons-workspace__hint { cursor: help; color: var(--el-text-color-placeholder); }
@media (max-width: 760px) {
  .buttons-workspace, .buttons-workspace.is-narrow { grid-template-columns: minmax(0, 1fr); height: auto; overflow: visible; }
  .buttons-workspace__left { border-right: 0; border-bottom: 1px solid var(--el-border-color-lighter); }
  .buttons-workspace__left-body { max-height: 50vh; }
}
</style>
<style>
.buttons-workspace__drawer .el-drawer__body { padding: 0; }
</style>
