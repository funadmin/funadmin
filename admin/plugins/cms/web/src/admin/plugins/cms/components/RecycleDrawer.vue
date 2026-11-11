<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import TablePagination from '@/components/TablePagination/index.vue';
import { useCmsI18n } from '@/composables/useCmsI18n';
import type { RecycleRow, RecycleSource } from './recycle';

/**
 * CMS 回收站抽屉：按页签列出已删除的记录，恢复与彻底删除各按自己的本站权限显示。
 * 恢复不需要确认；彻底删除弹出二次确认（不可恢复）。只采用最后一次请求的响应；当前页被清空时回到上一页。
 */
const props = defineProps<{ sources: RecycleSource<any>[] }>();
const emit = defineEmits<{ changed: [] }>();
const visible = defineModel<boolean>({ required: true });
const { t } = useCmsI18n();
const tt = (key: string, args?: Record<string, unknown>) => (args ? t(`cms.recycle.${key}`, args) : t(`cms.recycle.${key}`));

const available = computed(() => props.sources.filter((source) => source.canView));
const tab = ref('');
const source = computed(() => available.value.find((item) => item.key === tab.value));
const page = ref(1);
const rows = ref<RecycleRow[]>([]);
const total = ref(0);
const pageSize = ref(20);
const loading = ref(false);
const busy = ref(false);

const message = (e: unknown) => {
  const m = (e as { msg?: unknown } | null)?.msg;
  return typeof m === 'string' && m ? m : tt('failed');
};

let requestId = 0;
async function load() {
  const current = source.value;
  const id = ++requestId;
  if (!current) {
    rows.value = [];
    total.value = 0;
    return;
  }
  loading.value = true;
  try {
    const data = await current.load(page.value);
    if (id !== requestId) return;
    if (!data.rows.length && page.value > 1) {
      page.value -= 1;
      await load();
      return;
    }
    rows.value = data.rows;
    total.value = data.total;
    pageSize.value = data.pageSize;
  } catch (e) {
    if (id === requestId) ElMessage.error(message(e));
  } finally {
    if (id === requestId) loading.value = false;
  }
}

watch(visible, (open) => {
  if (!open) return;
  page.value = 1;
  const next = available.value[0]?.key ?? '';
  if (tab.value === next) void load();
  else tab.value = next;
});
watch(tab, () => {
  page.value = 1;
  rows.value = [];
  total.value = 0;
  if (visible.value) void load();
});

const canAct = (row: RecycleRow) => source.value?.actionable?.(row) ?? true;

async function run(action: () => Promise<{ count: number; skipped?: number }>, success: string) {
  if (busy.value) return;
  busy.value = true;
  try {
    const result = await action();
    const skipped = result?.skipped ?? 0;
    if (skipped > 0) ElMessage.warning(tt('restoredSkipped', { n: skipped }));
    else ElMessage.success(success);
    emit('changed');
  } catch (e) {
    ElMessage.error(message(e));
    return;
  } finally {
    busy.value = false;
  }
  await load();
}

function restore(row: RecycleRow) {
  const current = source.value;
  if (!current?.canRestore || !canAct(row)) return;
  void run(() => current.restore([row.id]), tt('restored'));
}

async function destroy(row: RecycleRow) {
  const current = source.value;
  if (!current?.canDestroy || !canAct(row)) return;
  const confirmed = await ElMessageBox.confirm(current.destroyBody(row), tt('destroyTitle'), {
    type: 'error',
    confirmButtonText: tt('destroy'),
    cancelButtonText: tt('cancel'),
    confirmButtonClass: 'el-button--danger'
  }).then(() => true, () => false);
  if (confirmed) await run(() => current.destroy([row.id]), tt('destroyed'));
}
</script>

<template>
  <el-drawer v-model="visible" :title="tt('title')" size="min(640px, 92vw)" append-to-body>
    <div v-loading="loading" class="rb">
      <p class="rb-hint">{{ tt('hint') }}</p>
      <el-radio-group v-if="available.length > 1" v-model="tab" size="small">
        <el-radio-button v-for="item in available" :key="item.key" :value="item.key">{{ item.label }}</el-radio-button>
      </el-radio-group>
      <el-empty v-if="!rows.length" :description="tt('empty')" :image-size="64" />
      <ul v-else class="rb-list">
        <li v-for="row in rows" :key="row.id" class="rb-item" data-testid="recycle-row">
          <div class="rb-main">
            <span class="rb-name">{{ source?.title(row) }}</span>
            <span class="rb-meta">{{ source?.meta(row) }} · {{ tt('deletedAt', { time: String(row.deleted_at).slice(0, 16) }) }}</span>
          </div>
          <div v-if="canAct(row)" class="rb-actions">
            <el-button v-if="source?.canRestore" size="small" :disabled="busy" data-testid="recycle-restore" @click="restore(row)">{{ tt('restore') }}</el-button>
            <el-button v-if="source?.canDestroy" size="small" type="danger" plain :disabled="busy" data-testid="recycle-destroy" @click="destroy(row)">{{ tt('destroy') }}</el-button>
          </div>
        </li>
      </ul>
      <TablePagination
        v-if="total > pageSize"
        small layout="prev, pager, next" :total="total" :page-size="pageSize" :current-page="page"
        @current-change="(p: number) => { page = p; void load(); }"
      />
    </div>
  </el-drawer>
</template>

<style scoped>
.rb { display: flex; flex-direction: column; gap: 12px; min-height: 200px; }
.rb-hint { margin: 0; font-size: 12px; color: var(--el-text-color-secondary); }
.rb-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.rb-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--el-border-color-lighter); }
.rb-main { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.rb-name { font-size: 14px; color: var(--el-text-color-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rb-meta { font-size: 12px; color: var(--el-text-color-placeholder); overflow-wrap: anywhere; }
.rb-actions { display: flex; gap: 6px; flex-shrink: 0; }
</style>
