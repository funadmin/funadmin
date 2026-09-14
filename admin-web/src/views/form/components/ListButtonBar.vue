<template>
  <span v-if="items.length" class="inline-flex flex-wrap items-center gap-2" @click.stop>
    <template v-for="item in inline" :key="item.button.id">
      <span :title="item.state.reason || item.button.tips"><el-button :link="link" :type="item.button.color === 'default' ? undefined : item.button.color" :size="item.button.size" :disabled="busy || lock?.busy || item.state.disabled" @click="execute(item.button)"><el-icon v-if="icons[item.button.icon ?? '']"><component :is="icons[item.button.icon ?? '']" /></el-icon>{{ item.button.label }}</el-button></span>
    </template>
    <el-dropdown v-if="more.length" trigger="click" @command="button => execute(button)"><el-button :disabled="busy || lock?.busy">更多</el-button><template #dropdown><el-dropdown-menu><el-dropdown-item v-for="item in more" :key="item.button.id" :command="item.button" :disabled="busy || lock?.busy || item.state.disabled" :title="item.state.reason || item.button.tips">{{ item.button.label }}</el-dropdown-item></el-dropdown-menu></template></el-dropdown>
  </span>
  <el-button v-if="catalogError" link :disabled="busy || lock?.busy" @click="loadCatalog">重试动作目录</el-button>
  <ListButtonInteraction ref="interaction" />
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { type FormListActionCatalog } from '@/api/formData';
import { Plus, Edit, Delete, View, Refresh, Download, Upload, Search } from '@element-plus/icons-vue';
import type { Component } from 'vue';
import type { FormListButton } from '../schema/types';
import { useListButtonAdapter, listButtonAdapterAllowed, listActionKey, listButtonState, type ListButtonHandlers } from '../runtime/listButtonHost';
import { createListButtonExecutor, registeredListButtonAvailable, listButtonRequest, type ListButtonContext } from '../runtime/listButtonExecutor';
import ListButtonInteraction from './ListButtonInteraction.vue';
const props = defineProps<{ buttons: FormListButton[]; handlers: ListButtonHandlers; allowed: (button: FormListButton) => boolean; row?: Record<string, unknown>; values?: Record<string, unknown>; fields?: string[]; link?: boolean; lock?: { busy: boolean }; refresh?: () => Promise<void>; clearSelection?: () => void; close?: () => void; preview?: boolean; context?: ListButtonContext; contextVersion?: string | number; permissionCheck?: (code: string) => boolean }>();
const icons: Record<string, Component> = { plus: Plus, edit: Edit, delete: Delete, view: View, refresh: Refresh, download: Download, upload: Upload, search: Search };
const interaction = ref<InstanceType<typeof ListButtonInteraction>>();
const busy = ref(false);
const catalog = ref<FormListActionCatalog>();
const catalogError = ref('');
let active = true;
let sequence = 0;
let controller: AbortController | undefined;
const adapter = useListButtonAdapter();
const permission = (code: string) => props.permissionCheck?.(code) === true;
const registered = (button: FormListButton) => Boolean(props.context && listButtonAdapterAllowed(adapter, permission, props.context) && registeredListButtonAvailable(button, props.context, catalog.value, permission));
const contextToken = () => JSON.stringify([props.context, props.contextVersion, props.row, props.buttons, props.preview]);
async function loadCatalog() {
  const current = ++sequence;
  controller?.abort(); controller = new AbortController();
  catalog.value = undefined; catalogError.value = '';
  if (!props.context || props.preview || !props.buttons.some(button => button.action.type === 'registered') || !listButtonAdapterAllowed(adapter, permission, props.context)) return;
  try {
    const result = await adapter!.api.listActions(props.context.formKey, props.context.location, controller.signal);
    if (active && current === sequence) catalog.value = result;
  } catch { if (active && current === sequence) catalogError.value = '动作目录加载失败，请重试'; }
}
const state = (button: FormListButton) => {
  const result = listButtonState(button, { handlers: props.handlers, allowed: props.allowed, registered, values: props.values ?? props.row, fields: props.fields });
  if (button.action.type === 'registered' && result.disabled) result.reason = catalogError.value || '动作目录、权限、版本或选择不满足执行条件';
  return result;
};
const items = computed(() => [...props.buttons].sort((a, b) => (a.order ?? 0) - (b.order ?? 0)).map(button => ({ button, state: state(button) })).filter(item => item.state.visible));
const inline = computed(() => items.value.filter(item => item.button.placement !== 'more'));
const more = computed(() => items.value.filter(item => item.button.placement === 'more'));
const executor = createListButtonExecutor({
  context: () => active ? contextToken() : 'unmounted',
  check: async button => {
    if (button.action.type === 'registered') await loadCatalog();
    const declared = props.buttons.find(item => item.id === button.id);
    if (!active || !declared || JSON.stringify(declared) !== JSON.stringify(button)) return false;
    const current = state(button); return current.visible && !current.disabled;
  },
  interact: (button, previous) => button.action.type === 'registered' && button.interaction?.type === 'confirm' ? Promise.resolve(previous) : interaction.value!.open(button, previous),
  confirm: async button => {
    try { await ElMessageBox.confirm(button.interaction?.message || '确认执行此动作？', button.interaction?.title || button.label, { type: 'warning', closeOnClickModal: false }); return true; }
    catch (error) { if (error === 'cancel' || error === 'close') return false; throw error; }
  },
  invoke: async (button, input, key, confirmation) => {
    if (props.preview) { ElMessage.info('预览仅模拟，不执行动作'); return { status: 'success' }; }
    if (button.action.type === 'registered') {
      if (!props.context || !registered(button)) throw Error('FORM_LIST_BUTTON_FORBIDDEN');
      return adapter!.api.listAction(props.context.formKey, listButtonRequest(button, props.context, input, key, confirmation));
    }
    const handler = props.handlers[listActionKey(button)];
    if (!handler) throw Error('动作尚未接通');
    return { status: 'success', result: await handler(props.row, input) };
  },
  refresh: async () => { if (!props.preview) await props.refresh?.(); },
  clearSelection: () => { if (!props.preview) props.clearSelection?.(); },
  close: async () => { if (!props.preview) await props.close?.(); }
});
watch(() => [props.context?.formKey, props.context?.schemaHash, props.context?.sourceSchemaHash, props.context?.location, props.buttons, props.preview, listButtonAdapterAllowed(adapter, permission, props.context)], loadCatalog, { immediate: true, deep: true });
watch(contextToken, () => { executor.clear(); interaction.value?.cancel(); }, { flush: 'sync' });
onBeforeUnmount(() => { active = false; sequence++; controller?.abort(); executor.clear(); interaction.value?.cancel(); });
async function execute(button: FormListButton, submittedInput?: Record<string, unknown>) {
  const acquiredLock = props.lock;
  if (busy.value || acquiredLock?.busy) return;
  busy.value = true; if (acquiredLock) acquiredLock.busy = true;
  try {
    const result = await executor.execute(button, submittedInput);
    if (result.status === 'success' && result.effectError) ElMessage.warning('动作已成功，但刷新失败，请手动刷新，不要重复提交');
    else if (result.status === 'success' && button.success?.message) ElMessage.success(button.success.message);
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      const message = error instanceof Error ? error.message : '动作执行失败';
      if (active && !message.includes('CONTEXT_CHANGED') && ['input', 'form'].includes(button.interaction?.type ?? '')) {
        const failedContext = contextToken();
        interaction.value?.showError(button, executor.input(button.id), message, input => { if (failedContext === contextToken()) void execute(button, input); });
      }
      ElMessage.error(message);
    }
  } finally { busy.value = false; if (acquiredLock) acquiredLock.busy = false; }
}
</script>
