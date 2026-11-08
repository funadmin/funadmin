<template>
  <div class="list-preview" :class="{ 'list-preview--no-aside': categoryMode !== 'tree' }">
    <aside v-if="categoryMode === 'tree'" class="list-preview__aside" data-preview-category>
      <ListPreviewZone location="categoryToolbar" :label="labelOf('categoryToolbar')" v-bind="zoneBinding('categoryToolbar', {})" />
      <div v-for="(node, index) in nodes" :key="index" class="list-preview__node" :class="{ 'is-current': index === 0 }" :style="{ paddingLeft: `${8 + node.depth * 14}px` }">
        <span class="list-preview__node-label">{{ node.label }}</span>
        <ListPreviewZone v-if="index === 0" location="categoryNode" :label="labelOf('categoryNode')" v-bind="zoneBinding('categoryNode', node.values ?? {})" />
      </div>
    </aside>
    <div class="list-preview__main">
      <!-- 未启用分类树时不占左栏，只在工具栏上方给出一行说明，把宽度留给列表预览。 -->
      <p v-if="categoryMode !== 'tree' && categoryHint" class="list-preview__hint" data-preview-category-hint :title="categoryHintText">{{ categoryHintText }}</p>
      <div v-if="categoryMode === 'tabs' && categoryTabs?.length" class="list-preview__tabs" data-preview-tabs>
        <span v-for="(tab, index) in categoryTabs" :key="tab" :class="{ 'is-active': index === 0 }">{{ tab }}</span>
      </div>
      <ListPreviewZone location="toolbar" :label="labelOf('toolbar')" v-bind="zoneBinding('toolbar', {})">
        <span class="list-preview__tools">
          <span v-for="tool in toolIcons" :key="tool.key" class="list-preview__tool" :class="{ 'is-off': tools?.[tool.key] === false }" :data-preview-tool="tool.key" :title="toolLabels?.[tool.key] ?? tool.key"><i :class="tool.icon" /></span>
        </span>
      </ListPreviewZone>
      <div class="list-preview__table-wrap">
        <table class="list-preview__table">
          <thead><tr><th class="list-preview__check"><input type="checkbox" disabled :aria-label="t('formDesigner.buttonsWorkspace.selectAll', '全选')" /></th><th v-for="column in shownColumns" :key="column.field">{{ column.label }}</th><th>{{ t('common.operation', '操作') }}</th></tr></thead>
          <tbody>
            <tr v-for="(row, index) in rows" :key="index" :data-preview-row="index">
              <td class="list-preview__check"><input type="checkbox" disabled :checked="index < (selectionCount ?? 0)" :aria-label="t('formDesigner.buttonsWorkspace.selectRow', { n: index + 1 }, '选择第 {n} 行')" /></td>
              <td v-for="column in shownColumns" :key="column.field">{{ listPreviewCell(column, row[column.field]) }}</td>
              <td><ListPreviewZone location="row" inline :label="index === 0 ? labelOf('row') : undefined" v-bind="zoneBinding('row', row)" :addable="index === 0" :active="index === 0 && selected?.location === 'row'" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <slot name="footer" />
    </div>
  </div>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormListButton, FormListButtonLocation, FormListConfiguration } from '../../schema/types';
import { listButtonKeys, listButtonState } from '../../runtime/listButtonHost';
import { listButtonSelectionReason, type ListButtonContext } from '../../runtime/listButtonExecutor';
import ListPreviewZone, { type ListPreviewItem } from './ListPreviewZone.vue';
import { listPreviewCell, listPreviewNodes, type ListPreviewColumn } from './listPreviewSample';

const props = withDefaults(defineProps<{
  /** 各位置的生效按钮（继承时为宿主默认按钮）。 */
  buttons: Partial<Record<FormListButtonLocation, FormListButton[]>>;
  tools?: FormListConfiguration['tools'];
  toolLabels?: Partial<Record<string, string>>;
  columns: ListPreviewColumn[];
  rows: Record<string, unknown>[];
  /** 条件可读字段（与运行时一致：条件只能引用这些字段）。 */
  fields: string[];
  locationLabels?: Partial<Record<FormListButtonLocation, string>>;
  selectionCount?: number;
  selected?: { location: FormListButtonLocation; id?: string };
  categoryMode?: 'none' | 'tabs' | 'tree';
  categoryTabs?: string[];
  categoryNodes?: Array<{ label: string; depth: number; values?: Record<string, unknown> }>;
  categoryFields?: string[];
  /** 未启用分类树时是否在工具栏上方显示一行说明。 */
  categoryHint?: boolean;
  interactive?: boolean;
  maxColumns?: number;
}>(), { selectionCount: 0, categoryMode: 'none', categoryHint: true, interactive: true, maxColumns: 5 });
const emit = defineEmits<{ select: [location: FormListButtonLocation, id: string]; reorder: [location: FormListButtonLocation, fromId: string, toId: string]; add: [location: FormListButtonLocation] }>();
const { t } = useI18n();
const toolIcons = [{ key: 'refresh', icon: 'i-ep-refresh' }, { key: 'search', icon: 'i-ep-search' }, { key: 'columns', icon: 'i-ep-operation' }, { key: 'density', icon: 'i-ep-sort' }, { key: 'fullscreen', icon: 'i-ep-full-screen' }] as const;
const shownColumns = computed(() => props.columns.filter(column => column.field !== 'id').slice(0, props.maxColumns));
const nodes = computed(() => props.categoryNodes ?? listPreviewNodes(t));
const labelOf = (location: FormListButtonLocation) => props.locationLabels?.[location];
const categoryHintText = computed(() => props.categoryMode === 'tabs'
  ? t('formDesigner.buttonsWorkspace.categoryTabsHint', '顶部选项页签模式没有分类按钮；改用左侧分类树后，列表左侧会显示分类按钮的位置。')
  : t('formDesigner.buttonsWorkspace.categoryHint', '未启用分类：在「分类管理」中启用左侧分类树后，列表左侧会显示分类按钮的位置。'));
// 预览只体现配置自身的显示/禁用条件与数量约束：动作接通、权限与目录在设计期一律视为可用，避免整片按钮因宿主未接通而显示禁用。
const handlers = Object.fromEntries([...new Set([...Object.values(listButtonKeys).flat(), 'refresh'])].map(key => [key, () => undefined]));
function item(button: FormListButton, location: FormListButtonLocation, values: Record<string, unknown>): ListPreviewItem {
  const fields = location.startsWith('category') ? props.categoryFields ?? props.fields : props.fields;
  const state = listButtonState(button, { handlers, allowed: () => true, registered: () => true, resource: () => true, values, fields });
  const selection = location === 'toolbar' ? listButtonSelectionReason(button, { location, ids: Array.from({ length: props.selectionCount }, (_, index) => index + 1), formKey: 'preview', schemaHash: '' } as ListButtonContext) : '';
  return { button, hidden: !state.visible, disabled: state.disabled || Boolean(selection), reason: selection || state.reason || (state.visible ? '' : t('formDesigner.buttonsWorkspace.hiddenReason', '运行时不显示（已关闭显示或显示条件不满足）')) };
}
const sorted = (location: FormListButtonLocation) => [...props.buttons[location] ?? []].sort((left, right) => (left.order ?? 0) - (right.order ?? 0));
const dragging = ref<{ location: FormListButtonLocation; id: string }>();
function zoneBinding(location: FormListButtonLocation, values: Record<string, unknown>) {
  return {
    items: sorted(location).map(button => item(button, location, values)),
    selectedId: props.selected?.location === location ? props.selected.id : undefined,
    active: props.selected?.location === location,
    interactive: props.interactive,
    addable: true,
    onSelect: (id: string) => emit('select', location, id),
    onDragstart: (id: string) => { dragging.value = { location, id }; },
    onDrop: (id: string) => { const from = dragging.value; dragging.value = undefined; if (from && from.location === location && from.id !== id) emit('reorder', location, from.id, id); },
    onAdd: () => emit('add', location)
  };
}
</script>
<style scoped>
.list-preview { display: grid; grid-template-columns: auto minmax(0, 1fr); overflow: hidden; width: 100%; max-width: 980px; margin: 0 auto; font-size: 12px; border: 1px solid var(--el-border-color-light); border-radius: 12px; background: var(--el-bg-color); }
.list-preview--no-aside { grid-template-columns: minmax(0, 1fr); }
.list-preview__aside { display: grid; align-content: start; gap: 6px; width: 190px; padding: 12px 10px; border-right: 1px solid var(--el-border-color-light); background: var(--el-fill-color-lighter); }
.list-preview__hint { margin: 0; overflow: hidden; font-size: 12px; line-height: 18px; color: var(--el-text-color-secondary); text-overflow: ellipsis; white-space: nowrap; }
.list-preview__node { display: grid; gap: 4px; padding: 4px 8px; border-radius: 6px; color: var(--el-text-color-regular); }
.list-preview__node.is-current { background: color-mix(in srgb, var(--el-color-primary) 10%, var(--el-bg-color)); color: var(--el-color-primary); font-weight: 600; }
.list-preview__main { display: grid; align-content: start; gap: 10px; min-width: 0; padding: 14px 14px 12px; }
.list-preview__tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--el-border-color-light); }
.list-preview__tabs span { padding: 6px 10px; color: var(--el-text-color-regular); border-bottom: 2px solid transparent; }
.list-preview__tabs span.is-active { color: var(--el-color-primary); border-color: var(--el-color-primary); font-weight: 600; }
.list-preview__tools { display: inline-flex; gap: 4px; margin-left: auto; }
.list-preview__tool { display: inline-grid; place-items: center; width: 24px; height: 24px; border: 1px solid var(--el-border-color); border-radius: 6px; font-size: 13px; color: var(--el-text-color-regular); background: var(--el-bg-color); }
.list-preview__tool.is-off { opacity: 0.3; }
.list-preview__table-wrap { overflow-x: auto; }
.list-preview__table { width: 100%; border-collapse: collapse; font-size: 12px; }
.list-preview__table th, .list-preview__table td { padding: 6px 8px; border-bottom: 1px solid var(--el-border-color-lighter); text-align: left; white-space: nowrap; vertical-align: middle; }
.list-preview__table th { color: var(--el-text-color-secondary); font-weight: 500; background: var(--el-fill-color-light); }
.list-preview__check { width: 28px; }
@media (max-width: 760px) {
  .list-preview { grid-template-columns: minmax(0, 1fr); }
  .list-preview__aside { width: auto; border-right: 0; border-bottom: 1px solid var(--el-border-color-light); }
}
</style>
