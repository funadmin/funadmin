<template>
  <ListButtonBar :buttons="buttons" :handlers="{}" :allowed="() => true" :local-host="host" :lock="lock" :link="row" @error="emit('error', $event)" />
</template>
<script setup lang="ts">
import { computed } from 'vue';
import ListButtonBar from '@/views/form/components/ListButtonBar.vue';
import type { FormListButton } from '@/views/form/schema/types';
import { actionState, matchesPageCondition, type PageAction, type PageContext } from './pageSchema';
const props = defineProps<{ actions: PageAction[]; context: PageContext; row?: boolean; lock?: { busy: boolean } }>();
const emit = defineEmits<{ error: [error: unknown] }>();
// 模式切换（正常列表 / 回收站）的非当前项曾用灰色 info；统一改为主题色浅色按钮，避免一排按钮里出现突兀的灰块。
const inactive = (color: PageAction['inactiveColor']) => (color === 'info' ? 'primary' : color);
const original = (button: FormListButton) => props.actions.find(action => action.id === button.id);
const buttons = computed<FormListButton[]>(() => props.actions.map(action => ({
  id: action.id, label: action.label + (action.selectionCount && props.context.values.selectionCount ? `(${props.context.values.selectionCount})` : ''),
  action: action.action, icon: action.icon, hidden: action.hidden, disabled: action.disabled,
  color: action.activeWhen && !matchesPageCondition(action.activeWhen, props.context.values) ? inactive(action.inactiveColor) : action.color,
  interaction: props.context.handlers[action.action.key]?.interaction
})));
const host = {
  token: () => JSON.stringify([props.context.values, props.context.permissions, props.context.row, props.context.version, Object.entries(props.context.handlers).map(([key, handler]) => [key, handler.version])]),
  state: (button: FormListButton) => { const action = original(button); return action ? actionState(action, props.context) : { visible: false, disabled: true }; },
  invoke: (button: FormListButton) => {
    const action = original(button);
    if (!action) throw Error('PAGE_ACTION_FORBIDDEN');
    const state = actionState(action, props.context);
    if (!state.visible || state.disabled) throw Error('PAGE_ACTION_FORBIDDEN');
    return props.context.handlers[action.action.key]!.run(props.context.row);
  }
};
</script>
