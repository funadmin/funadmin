<template>
  <section class="generation-file-diff" aria-label="只读文件差异">
    <p class="explanation">基线 Base：上次生成的版本；本地 Local：当前文件；待生成 Remote：本次生成候选。这里只比较内容，不代表最终合并结果，不会写入或选择覆盖。</p>
    <div role="tablist" aria-label="对比版本" class="diff-tabs">
      <button v-for="tab in tabs" :id="`${id}-${tab.key}`" :key="tab.key" type="button" role="tab"
        :data-comparison="tab.key" :aria-selected="active === tab.key" :aria-controls="`${id}-panel`"
        :tabindex="active === tab.key ? 0 : -1" @click="active = tab.key" @keydown="moveTab($event, tab.key)">{{ tab.label }}</button>
    </div>
    <div :id="`${id}-panel`" role="tabpanel" :aria-labelledby="`${id}-${active}`">
      <p data-diff-status role="status">{{ result.message }}</p>
      <template v-if="result.rows">
        <div class="diff-toolbar">
          <strong data-diff-stats>+{{ added }} / −{{ removed }} 行</strong>
          <span>− 删除　+ 新增（修改以删除＋新增表示）</span>
          <div class="navigation">
            <button type="button" data-prev-diff :disabled="!hunks.length || position === 0" @click="navigate(-1)">上一处差异</button>
            <span data-diff-position aria-live="polite">{{ hunks.length ? position + 1 : 0 }} / {{ hunks.length }}</span>
            <button type="button" data-next-diff :disabled="!hunks.length || position === hunks.length - 1" @click="navigate(1)">下一处差异</button>
          </div>
          <label><input v-model="wrapped" data-wrap-lines type="checkbox" />长行换行</label>
        </div>
        <div ref="scroll" data-diff-scroll class="diff-scroll" :class="{ 'is-wrapped': wrapped }" tabindex="0" role="region" aria-label="行级差异，可横向滚动">
          <table>
            <thead><tr><th scope="col">旧行</th><th scope="col">新行</th><th scope="col">±</th><th scope="col">{{ comparison.label }}</th></tr></thead>
            <tbody>
              <tr v-for="(row, index) in result.rows" :key="index" :data-line-kind="row.kind" :data-row="index" :class="[row.kind, { current: hunks[position] === index }]">
                <td data-old-line class="line-number">{{ row.oldLine }}</td><td data-new-line class="line-number">{{ row.newLine }}</td>
                <td class="sign">{{ row.kind === 'add' ? '+' : row.kind === 'delete' ? '−' : ' ' }}</td>
                <td class="source"><code>{{ row.text }}</code><span v-if="row.ending !== '\n'" class="ending">{{ row.ending === '\r\n' ? 'CRLF' : row.ending === '\r' ? 'CR' : '无末尾换行' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue';
import type { BusinessGenerationFile } from '@/api/development/business';

const props = defineProps<{ file: BusinessGenerationFile }>();
type Comparison = 'local' | 'remote' | 'direct';
type Line = { raw: string; text: string; ending: string };
type Row = Line & { kind: 'equal' | 'add' | 'delete'; oldLine?: number; newLine?: number };
const id = useId();
const active = ref<Comparison>('remote');
const wrapped = ref(false);
const position = ref(0);
const scroll = ref<HTMLElement>();
const missingBase = computed(() => typeof props.file.baseContent !== 'string');
const tabs = computed(() => [
  { key: 'local' as const, label: '本地修改（Base → Local）' },
  { key: 'remote' as const, label: '本次生成改动（Base → Remote）' },
  ...(missingBase.value ? [{ key: 'direct' as const, label: '本地与待生成（Local → Remote）' }] : [])
]);
watch(() => [props.file.path, missingBase.value], () => {
  active.value = missingBase.value && typeof props.file.localContent === 'string' && typeof props.file.remoteContent === 'string' ? 'direct' : 'remote';
}, { immediate: true });
const comparison = computed(() => ({
  before: active.value === 'direct' ? props.file.localContent : props.file.baseContent,
  after: active.value === 'local' ? props.file.localContent : props.file.remoteContent,
  label: tabs.value.find((tab) => tab.key === active.value)!.label
}));

// 保留每一行的实际结束符，空文件为零行，不吞掉 CRLF 或末尾换行差异。
function lines(content: string): Line[] {
  return (content.match(/[^\x0d\x0a]*(?:\x0d\x0a|\x0d|\x0a)|[^\x0d\x0a]+$/g) || []).map((raw) => {
    const ending = raw.match(/(?:\r\n|\r|\n)$/)?.[0] || '';
    return { raw, ending, text: ending ? raw.slice(0, -ending.length) : raw };
  });
}
const result = computed<{ message: string; rows?: Row[] }>(() => {
  if (props.file.contentKind === 'binary' || props.file.status === 'binary-conflict') return { message: '二进制文件：未计算文本差异，请使用专用工具核对。' };
  const { before, after } = comparison.value;
  if (typeof before !== 'string' || typeof after !== 'string') return { message: '服务端未提供所选版本内容，未计算差异；缺失内容不等于空文件。' };
  const limited = { message: '文本过大或差异计算超出预算，未计算行级差异；不表示内容相同。请在本地使用 diff 工具核对。' };
  if (before.length + after.length > 200000) return limited;
  const old = lines(before), fresh = lines(after);
  if (old.length + fresh.length > 5000) return limited;
  const rows: Row[] = [];
  if (before === after) return { message: '内容相同，无差异。', rows: old.map((line, i) => ({ ...line, kind: 'equal', oldLine: i + 1, newLine: i + 1 })) };
  // 有界 LCS 只服务于展示，不参与任何生成或合并决策；限制矩阵与 DOM 规模。
  if (old.length * fresh.length > 1000000) return limited;
  const width = fresh.length + 1;
  const lengths = new Uint16Array((old.length + 1) * width);
  for (let i = old.length - 1; i >= 0; i--) {
    for (let j = fresh.length - 1; j >= 0; j--) {
      lengths[i * width + j] = old[i].raw === fresh[j].raw
        ? lengths[(i + 1) * width + j + 1] + 1
        : Math.max(lengths[(i + 1) * width + j], lengths[i * width + j + 1]);
    }
  }
  let i = 0, j = 0;
  while (i < old.length || j < fresh.length) {
    if (i < old.length && j < fresh.length && old[i].raw === fresh[j].raw) {
      rows.push({ ...old[i], kind: 'equal', oldLine: ++i, newLine: ++j });
    } else if (i < old.length && (j === fresh.length || lengths[(i + 1) * width + j] >= lengths[i * width + j + 1])) {
      rows.push({ ...old[i], kind: 'delete', oldLine: ++i });
    } else {
      rows.push({ ...fresh[j], kind: 'add', newLine: ++j });
    }
  }
  return { message: '只读行级差异；旧行与新行对应同一组上下文。LF 为默认行尾，其他行尾单独标注。', rows };
});
const added = computed(() => result.value.rows?.filter((row) => row.kind === 'add').length || 0);
const removed = computed(() => result.value.rows?.filter((row) => row.kind === 'delete').length || 0);
const hunks = computed(() => (result.value.rows || []).flatMap((row, i, rows) => row.kind !== 'equal' && (i === 0 || rows[i - 1].kind === 'equal') ? [i] : []));
watch(result, async () => {
  position.value = 0;
  await nextTick();
  if (scroll.value) { scroll.value.scrollTop = 0; scroll.value.scrollLeft = 0; }
});
function navigate(direction: number) {
  position.value = Math.max(0, Math.min(hunks.value.length - 1, position.value + direction));
  const row = scroll.value?.querySelector<HTMLElement>(`[data-row="${hunks.value[position.value]}"]`);
  if (row && scroll.value) scroll.value.scrollTop += row.getBoundingClientRect().top - scroll.value.getBoundingClientRect().top - 36;
}
async function moveTab(event: KeyboardEvent, key: Comparison) {
  const index = tabs.value.findIndex((tab) => tab.key === key);
  const target = event.key === 'ArrowRight' ? (index + 1) % tabs.value.length : event.key === 'ArrowLeft' ? (index + tabs.value.length - 1) % tabs.value.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.value.length - 1 : -1;
  if (target < 0) return;
  event.preventDefault();
  active.value = tabs.value[target].key;
  await nextTick();
  document.getElementById(`${id}-${active.value}`)?.focus();
}
</script>

<style scoped>
.generation-file-diff { min-width: 0; max-width: 100%; margin-top: 12px; border: 1px solid var(--el-border-color); border-radius: 8px; padding: 12px; background: var(--el-bg-color); }
.explanation, [data-diff-status] { font-size: 13px; color: var(--el-text-color-secondary); margin: 0 0 10px; overflow-wrap: anywhere; }
.diff-tabs, .diff-toolbar, .navigation { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.diff-tabs { margin-bottom: 12px; border-bottom: 1px solid var(--el-border-color); padding-bottom: 8px; }
button { font: inherit; font-size: 13px; padding: 6px 10px; border: 1px solid var(--el-border-color); border-radius: 4px; background: var(--el-bg-color); color: var(--el-text-color-primary); cursor: pointer; }
button[aria-selected='true'] { color: var(--el-color-primary); border-color: var(--el-color-primary); background: var(--el-color-primary-light-9); }
button:disabled { opacity: .5; cursor: not-allowed; }
button:focus-visible, .diff-scroll:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 2px; }
.diff-toolbar { font-size: 12px; margin-bottom: 10px; }
.diff-scroll { overflow: auto; max-height: 480px; max-width: 100%; border: 1px solid var(--el-border-color); border-radius: 4px; }
table { border-collapse: collapse; width: 100%; font: 12px/1.7 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
th { position: sticky; top: 0; z-index: 1; background: var(--el-fill-color-light); text-align: left; white-space: nowrap; }
td, th { padding: 2px 8px; }
.line-number { width: 3em; min-width: 3em; text-align: right; color: var(--el-text-color-secondary); border-right: 1px solid var(--el-border-color-lighter); user-select: none; }
.sign { width: 1em; user-select: none; }
.source { white-space: pre; tab-size: 4; }
.source code { font: inherit; }
.add { background: var(--el-color-success-light-9, #edf8ed); color: var(--el-color-success-dark-2, #276327); }
.delete { background: var(--el-color-danger-light-9, #fff0f0); color: var(--el-color-danger-dark-2, #a32626); }
.current { box-shadow: inset 3px 0 var(--el-color-primary); }
.ending { margin-left: 12px; border: 1px solid currentColor; border-radius: 3px; padding: 0 3px; font-size: 10px; }
.is-wrapped table { table-layout: fixed; }
.is-wrapped th:nth-child(-n+2) { width: 3em; }
.is-wrapped th:nth-child(3) { width: 1em; }
.is-wrapped .source { white-space: pre-wrap; overflow-wrap: anywhere; }
@media (max-width: 600px) {
  .generation-file-diff { padding: 8px; }
  .diff-tabs button { flex: 1 1 100%; text-align: left; }
  td, th { padding: 2px 4px; }
  .navigation { width: 100%; }
}
</style>
