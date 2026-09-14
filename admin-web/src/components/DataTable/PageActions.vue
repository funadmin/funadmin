<template>
  <template v-for="action in actions" :key="action.id">
    <el-button v-if="actionState(action, context).visible" :type="color(action)" :plain="!row" :link="row"
      :disabled="busy || actionState(action, context).disabled" @click="execute(action)">
      <i v-if="action.icon" :class="action.icon" /> {{ action.label }}{{ action.selectionCount && context.values.selectionCount ? `(${context.values.selectionCount})` : '' }}
    </el-button>
  </template>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import { actionState, executePageAction, matchesPageCondition, type PageAction, type PageContext } from './pageSchema';
const props = defineProps<{ actions: PageAction[]; context: PageContext; row?: boolean }>();
const emit = defineEmits<{ error: [error: unknown] }>();
const busy = ref(false);
function color(action: PageAction) {
  const value = action.activeWhen && !matchesPageCondition(action.activeWhen, props.context.values) ? action.inactiveColor : action.color;
  return value === 'default' ? undefined : value;
}
async function execute(action: PageAction) {
  if (busy.value) return;
  busy.value = true;
  try { await executePageAction(action, props.context); } catch (error) { emit('error', error); }
  finally { busy.value = false; }
}
</script>
