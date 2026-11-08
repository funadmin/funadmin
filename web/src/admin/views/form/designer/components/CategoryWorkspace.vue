<template>
  <div class="category-workspace">
    <aside class="category-workspace__left">
      <section class="category-workspace__sec">
        <b>{{ t('formDesigner.categoryWorkspace.mode', '分类方式') }}</b>
        <div class="category-workspace__choice" role="radiogroup" :aria-label="t('formDesigner.categoryWorkspace.mode', '分类方式')">
          <button v-for="card in cards" :key="card.mode" type="button" role="radio" class="category-card" :class="{ 'is-on': mode === card.mode }" :data-category-mode="card.mode" :aria-checked="mode === card.mode" @click="choose(card.mode)">
            <b>{{ card.title }}</b>
            <span class="category-card__thumb" :class="`is-${card.mode}`" aria-hidden="true">
              <span v-if="card.mode === 'tree'" class="category-card__side" />
              <span v-if="card.mode === 'tabs'" class="category-card__tabs"><i class="is-active" /><i /><i /></span>
              <i /><i /><i />
            </span>
            <span class="category-card__desc">{{ card.desc }}</span>
            <span v-if="card.mode === 'tree' && mode === 'tree' && missingMappings.length" class="category-card__pending" data-category-card-pending>{{ t('formDesigner.categoryWorkspace.pending', '待完成') }}</span>
          </button>
        </div>
      </section>

      <section v-if="mode === 'tabs'" class="category-workspace__sec" data-category-section="field">
        <b>{{ t('formDesigner.listPanel.categoryField', '分类绑定字段') }}</b>
        <el-select :model-value="modelValue.category?.field" filterable :placeholder="t('formDesigner.listPanel.categoryFieldPlaceholder', '选择已有静态选项或字典字段')" @change="(field: string) => emit('category', { field })">
          <el-option v-for="field in categoryFields" :key="field.field_name" :label="fieldLabel(field)" :value="field.field_name" />
        </el-select>
        <span class="category-workspace__muted">{{ categoryFields.length ? t('formDesigner.categoryWorkspace.tabsHint', '页签来自所选字段的静态选项或数据字典，列表顶部按该字段切换。') : t('formDesigner.categoryWorkspace.tabsEmpty', '暂无可用字段：请先在表单设计中为字段配置静态选项或数据字典。') }}</span>
      </section>

      <template v-if="mode === 'tree'">
        <div v-if="missingMappings.length" class="category-workspace__pending" data-category-pending role="note">
          <b>{{ t('formDesigner.categoryWorkspace.pending', '待完成') }}</b>
          <span>{{ t('formDesigner.categoryWorkspace.pendingHint', '还需选择以下字段对应，补全后分类树才会生效并保存：') }}</span>
          <span class="category-workspace__pending-list">
            <span v-for="item in missingMappings" :key="item.key" class="category-workspace__pending-item" :data-missing-mapping="item.key">{{ item.label }}</span>
          </span>
        </div>
        <section class="category-workspace__sec" data-category-section="source">
          <b>{{ t('formDesigner.categoryWorkspace.source', '分类数据从哪来') }}</b>
          <div class="category-workspace__seg" role="radiogroup" :aria-label="t('formDesigner.categoryWorkspace.source', '分类数据从哪来')">
            <button v-for="option in sourceOptions" :key="option.value" type="button" role="radio" :aria-checked="left.source.type === option.value" :class="{ 'is-on': left.source.type === option.value }" @click="left.source.type !== option.value && emit('changeSource', option.value)">{{ option.label }}</button>
          </div>
          <label v-if="left.source.type === 'module'" class="category-workspace__field" :data-label="t('formDesigner.listPanel.businessModule', '业务模块')">
            <span>{{ t('formDesigner.listPanel.businessModule', '业务模块') }}</span>
            <el-select :model-value="left.source.module" filterable @visible-change="(shown: boolean) => shown && emit('loadModules')" @change="(code: string) => emit('chooseModule', code)"><el-option v-for="item in modules" :key="item.moduleId" :label="item.moduleCode" :value="item.moduleCode" /></el-select>
          </label>
        </section>

        <section class="category-workspace__sec" data-category-section="mapping">
          <b>{{ t('formDesigner.categoryWorkspace.mapping', '字段对应') }}</b>
          <div class="category-workspace__map">
            <span class="category-workspace__hd">{{ sourceTitle }}</span><span /><span class="category-workspace__hd">{{ t('formDesigner.categoryWorkspace.meaning', '说明') }}</span>
            <template v-for="binding in sourceBindings" :key="binding.key">
              <div :data-mapping="binding.key">
                <el-select :model-value="left.mapping[binding.key]" clearable filterable :aria-label="binding.label" @change="(value: string) => setMapping(binding.key, value)">
                  <el-option v-if="binding.key === 'valueField' && showSystemPrimaryKey" :label="t('formDesigner.listPanel.systemPrimaryKey', '系统主键 (id)')" value="id" />
                  <el-option v-for="field in sourceFields" :key="field.field_name" :label="fieldLabel(field)" :value="field.field_name" />
                </el-select>
              </div>
              <span class="category-workspace__arr">·</span>
              <span class="category-workspace__muted">{{ binding.label }}<template v-if="binding.hint">{{ binding.hint }}</template></span>
            </template>
            <span class="category-workspace__hd category-workspace__hd--gap">{{ t('formDesigner.categoryWorkspace.sourceKey', '分类表主键') }}</span><span /><span class="category-workspace__hd category-workspace__hd--gap">{{ t('formDesigner.categoryWorkspace.currentTable', '本表') }}</span>
            <div class="category-workspace__ro" data-mapping-key-readonly>{{ left.mapping.valueField || '—' }}</div>
            <span class="category-workspace__arr">→</span>
            <div data-mapping="targetField">
              <el-select :model-value="left.mapping.targetField" clearable filterable :aria-label="t('formDesigner.listPanel.targetField', '右表关联字段')" :placeholder="t('formDesigner.listPanel.targetField', '右表关联字段')" @change="(value: string) => setMapping('targetField', value)">
                <el-option v-for="field in targetFields" :key="field.field_name" :label="fieldLabel(field)" :value="field.field_name" />
              </el-select>
            </div>
          </div>
          <span class="category-workspace__muted">{{ t('formDesigner.listPanel.targetField', '右表关联字段') }}：{{ t('formDesigner.categoryWorkspace.targetHint', '本表中保存分类主键的字段，用于按所选分类筛选列表。') }}</span>
        </section>

        <section class="category-workspace__sec" data-category-section="selection">
          <b>{{ t('formDesigner.categoryWorkspace.selection', '选择行为') }}</b>
          <div class="category-workspace__row">
            <span>{{ t('formDesigner.listPanel.selectionMode', '选择模式') }}</span>
            <span class="category-workspace__seg" role="radiogroup" :aria-label="t('formDesigner.listPanel.selectionMode', '选择模式')">
              <button v-for="option in selectionOptions" :key="option.value" type="button" role="radio" :aria-checked="selectionMode === option.value" :class="{ 'is-on': selectionMode === option.value }" @click="selectionMode !== option.value && emit('updateLeft', { selection: { ...left.selection, mode: option.value } })">{{ option.label }}</button>
            </span>
          </div>
          <div class="category-workspace__row" :data-label="t('formDesigner.categoryWorkspace.includeDescendants', '选中父级时包含后代')">
            <span>{{ t('formDesigner.categoryWorkspace.includeDescendants', '选中父级时包含后代') }}</span>
            <el-switch :disabled="!hasParent" :model-value="hasParent && (left.selection?.includeDescendants ?? false)" :aria-label="t('formDesigner.categoryWorkspace.includeDescendants', '选中父级时包含后代')" @change="(value: string | number | boolean) => emit('updateLeft', { selection: { ...left.selection, includeDescendants: Boolean(value) } })" />
          </div>
          <span v-if="!hasParent" class="category-workspace__muted">{{ t('formDesigner.categoryWorkspace.needParent', '选择父级字段后才能包含后代、新增子节点。') }}</span>
        </section>

        <section class="category-workspace__sec" data-category-section="actions">
          <b>{{ t('formDesigner.categoryWorkspace.actions', '分类节点可执行的操作') }}</b>
          <div class="category-workspace__two">
            <div v-for="action in actions" :key="action.key" class="category-workspace__row" :data-label="action.label">
              <span>{{ action.label }}</span>
              <el-switch :disabled="action.key === 'addChild' && !hasParent" :model-value="action.key === 'addChild' && !hasParent ? false : left.actions?.[action.key] ?? false" :aria-label="action.label" @change="(value: string | number | boolean) => emit('updateLeft', { actions: { ...left.actions, [action.key]: Boolean(value) } })" />
            </div>
          </div>
          <span class="category-workspace__muted">{{ t('formDesigner.categoryWorkspace.actionsHint', '这些按钮的文字、颜色、条件在「按钮与工具 › 分类顶部操作 / 分类节点操作」里调整。') }}</span>
        </section>
      </template>
    </aside>
    <main class="category-workspace__center">
      <div class="category-workspace__bar"><span>{{ t('formDesigner.categoryWorkspace.preview', '列表页预览') }}</span><span class="category-workspace__muted">{{ t('formDesigner.categoryWorkspace.previewHint', '示例数据，仅用于展示布局') }}</span></div>
      <ListPreview :buttons="previewButtons" :tools="modelValue.tools" :columns="columns" :rows="rows" :fields="rowFields" :category-fields="nodeFields" :category-mode="mode" :category-tabs="tabs" :category-nodes="nodes" :category-hint="false" :interactive="false" />
    </main>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormSourceField, FormSourceMeta } from '@/api/formData';
import type { FormLeftTreeConfiguration, FormListConfiguration } from '../../schema/types';
import type { ButtonsWorkspaceLocation } from './ButtonsWorkspace.vue';
import ListPreview from './ListPreview.vue';
import { effectiveListButtons, listPreviewNodes, listPreviewRows, type ListPreviewColumn } from './listPreviewSample';

type Mode = 'none' | 'tabs' | 'tree';
type MappingKey = keyof FormLeftTreeConfiguration['mapping'];
// treePending：已选择分类树但必填映射未补全，配置尚未写入开启（由面板维护的界面状态）。
const props = defineProps<{ modelValue: FormListConfiguration; left: FormLeftTreeConfiguration; categoryFields: FormSourceField[]; sourceFields: FormSourceField[]; targetFields: FormSourceField[]; modules: FormSourceMeta[]; columns: ListPreviewColumn[]; locations: readonly ButtonsWorkspaceLocation[]; treePending?: boolean }>();
const emit = defineEmits<{ category: [patch: Partial<NonNullable<FormListConfiguration['category']>>]; updateLeft: [patch: Partial<FormLeftTreeConfiguration>]; changeSource: [type: 'current' | 'module']; chooseModule: [code: string]; loadModules: []; clear: [] }>();
const { t } = useI18n();
// 与运行时、按钮工作区一致：分类树优先于选项页签。
const mode = computed<Mode>(() => props.left.enabled || props.treePending ? 'tree' : props.modelValue.category?.enabled ? 'tabs' : 'none');
const cards = computed(() => [
  { mode: 'none' as const, title: t('formDesigner.categoryWorkspace.none', '不分类'), desc: t('formDesigner.categoryWorkspace.noneDesc', '普通列表') },
  { mode: 'tabs' as const, title: t('formDesigner.categoryWorkspace.tabs', '顶部选项页签'), desc: t('formDesigner.categoryWorkspace.tabsDesc', '按一个选项字段切换') },
  { mode: 'tree' as const, title: t('formDesigner.categoryWorkspace.tree', '左侧分类树'), desc: t('formDesigner.categoryWorkspace.treeDesc', '可增删改的分类') }
]);
/** 选项页签与分类树互斥：沿用面板原有的 category()/updateLeft() 规则，开启一方即关闭另一方。 */
function choose(next: Mode) {
  if (next === mode.value) return;
  if (next === 'tabs') emit('category', { enabled: true });
  else if (next === 'tree') emit('updateLeft', { enabled: true });
  else emit('clear');
}
const fieldLabel = (field: FormSourceField): string => {
  const title = field.label?.trim();
  return title && title !== field.field_name ? `${title} (${field.field_name})` : field.field_name;
};
const sourceOptions = computed(() => [{ value: 'current' as const, label: t('formDesigner.categoryWorkspace.sourceCurrent', '当前业务（自关联）') }, { value: 'module' as const, label: t('formDesigner.categoryWorkspace.sourceModule', '其他已发布业务') }]);
const sourceTitle = computed(() => props.left.source.type === 'module' && props.left.source.module ? t('formDesigner.categoryWorkspace.sourceTableOf', { name: props.left.source.module }, '分类表 {name}') : t('formDesigner.categoryWorkspace.sourceTable', '分类表字段'));
const sourceBindings = computed(() => [
  { key: 'valueField' as const, label: t('formDesigner.listPanel.sourcePrimaryKey', '来源主键'), hint: '' },
  { key: 'labelField' as const, label: t('formDesigner.listPanel.labelField', '显示字段'), hint: '' },
  { key: 'parentField' as const, label: t('formDesigner.listPanel.parentField', '父级字段'), hint: t('formDesigner.categoryWorkspace.parentHint', '（不选则为平铺列表）') },
  { key: 'sortField' as const, label: t('formDesigner.listPanel.sortField', '排序字段'), hint: '' }
]);
// 分类树开启所必需的映射（与后端校验一致）；缺少时显示「待完成」。
const missingMappings = computed(() => [
  { key: 'valueField' as const, label: t('formDesigner.listPanel.sourcePrimaryKey', '来源主键') },
  { key: 'labelField' as const, label: t('formDesigner.listPanel.labelField', '显示字段') },
  { key: 'targetField' as const, label: t('formDesigner.listPanel.targetField', '右表关联字段') }
].filter(item => !props.left.mapping[item.key]));
const showSystemPrimaryKey = computed(() => props.left.source.type === 'current' && !props.sourceFields.some(field => field.field_name === 'id'));
const setMapping = (key: MappingKey, value: string) => emit('updateLeft', { mapping: { ...props.left.mapping, [key]: value } });
const hasParent = computed(() => Boolean(props.left.mapping.parentField));
const selectionMode = computed(() => props.left.selection?.mode ?? 'single');
const selectionOptions = computed(() => [{ value: 'single' as const, label: t('formDesigner.listPanel.single', '单选') }, { value: 'multiple' as const, label: t('formDesigner.categoryWorkspace.multiple', '多选') }]);
const actions = computed(() => [{ key: 'create' as const, label: t('formDesigner.listPanel.addRootNode', '新增根节点') }, { key: 'addChild' as const, label: t('formDesigner.listPanel.addChildNode', '新增子节点') }, { key: 'edit' as const, label: t('formDesigner.listPanel.editNode', '编辑节点') }, { key: 'delete' as const, label: t('formDesigner.listPanel.deleteNode', '删除节点') }]);

// 预览：与按钮工作区共用生效按钮规则，只读展示。
const previewButtons = computed(() => effectiveListButtons(props.locations, props.modelValue.buttons));
const rowFields = computed(() => props.locations.find(location => location.key === 'row')?.fields ?? []);
const nodeFields = computed(() => props.locations.find(location => location.key === 'categoryNode')?.fields ?? []);
const rows = computed(() => listPreviewRows(props.columns, 3));
const tabs = computed(() => {
  const options = props.columns.find(column => column.field === props.modelValue.category?.field)?.options;
  const labels = options?.length ? options.map(option => option.label) : [1, 2, 3].map(n => t('formDesigner.categoryWorkspace.sampleTab', { n }, '选项 {n}'));
  return [t('formDesigner.categoryWorkspace.all', '全部'), ...labels];
});
// 有父级字段时示例为层级树，否则为平铺列表。
const nodes = computed(() => listPreviewNodes(t, hasParent.value));
</script>
<style scoped>
.category-workspace { display: grid; grid-template-columns: minmax(0, 440px) minmax(0, 1fr); min-height: clamp(560px, calc(100vh - 210px), 1400px); overflow: hidden; border: 1px solid var(--el-border-color-lighter); border-radius: 12px; background: var(--el-bg-color); }
.category-workspace__left { display: grid; align-content: start; gap: 0; padding: 14px; min-width: 0; border-right: 1px solid var(--el-border-color-lighter); }
.category-workspace__sec { display: grid; gap: 8px; padding: 12px 0; border-top: 1px solid var(--el-border-color-lighter); }
.category-workspace__sec:first-child { padding-top: 0; border-top: 0; }
.category-workspace__sec > b { font-size: 12.5px; color: var(--el-text-color-primary); }
.category-workspace__choice { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.category-card { display: grid; gap: 8px; padding: 10px; border: 1.5px solid var(--el-border-color-light); border-radius: 12px; text-align: left; font: inherit; color: var(--el-text-color-primary); background: var(--el-bg-color); cursor: pointer; }
.category-card:hover { border-color: color-mix(in srgb, var(--el-color-primary) 50%, var(--el-bg-color)); }
/* 选中态背景与 --el-bg-color 混合：app 主题在 <html> 上把 --el-color-primary-light-* 固定为与白色混合，夜间仍是浅色。 */
.category-card.is-on { border-color: var(--el-color-primary); background: color-mix(in srgb, var(--el-color-primary) 10%, var(--el-bg-color)); box-shadow: 0 0 0 3px color-mix(in srgb, var(--el-color-primary) 20%, var(--el-bg-color)); }
.category-card:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 2px; }
.category-card > b { font-size: 13px; }
.category-card__desc { font-size: 11.5px; color: var(--el-text-color-secondary); }
.category-card__pending { justify-self: start; padding: 0 6px; border-radius: 4px; font-size: 11px; line-height: 18px; color: var(--el-color-warning); background: color-mix(in srgb, var(--el-color-warning) 14%, var(--el-bg-color)); }
.category-workspace__pending { display: grid; gap: 4px; margin-bottom: 12px; padding: 8px 10px; border: 1px solid color-mix(in srgb, var(--el-color-warning) 45%, var(--el-bg-color)); border-radius: 8px; font-size: 12px; color: var(--el-text-color-regular); background: color-mix(in srgb, var(--el-color-warning) 8%, var(--el-bg-color)); }
.category-workspace__pending > b { color: var(--el-color-warning); }
.category-workspace__pending-list { display: flex; flex-wrap: wrap; gap: 6px; }
.category-workspace__pending-item { padding: 0 6px; border-radius: 4px; line-height: 20px; color: var(--el-color-warning); background: color-mix(in srgb, var(--el-color-warning) 14%, var(--el-bg-color)); }
.category-card__thumb { display: grid; align-content: start; gap: 4px; height: 64px; padding: 6px; border: 1px solid var(--el-border-color-lighter); border-radius: 8px; background: var(--el-fill-color-light); }
.category-card__thumb i { display: block; height: 6px; border-radius: 3px; background: var(--el-border-color); }
.category-card__thumb.is-tree { grid-template-columns: 28px 1fr; }
.category-card__side { grid-row: span 4; border-radius: 4px; background: color-mix(in srgb, var(--el-color-primary) 20%, var(--el-bg-color)); }
.category-card__tabs { display: flex; gap: 3px; }
.category-card__tabs i { width: 22px; height: 8px; }
.category-card__tabs i.is-active { background: var(--el-color-primary); }
.category-workspace__seg { display: inline-flex; justify-self: start; gap: 2px; padding: 3px; border-radius: 9px; background: var(--el-fill-color); }
.category-workspace__seg button { padding: 3px 10px; border: 0; border-radius: 7px; font-size: 12px; white-space: nowrap; color: var(--el-text-color-regular); background: transparent; cursor: pointer; }
.category-workspace__seg button.is-on { color: var(--el-color-primary); font-weight: 600; background: var(--el-bg-color); box-shadow: var(--el-box-shadow-lighter); }
.category-workspace__field { display: grid; gap: 4px; font-size: 12px; color: var(--el-text-color-regular); }
.category-workspace__map { display: grid; grid-template-columns: minmax(0, 1fr) 22px minmax(0, 1fr); gap: 6px 8px; align-items: center; font-size: 12px; }
.category-workspace__hd { font-size: 11px; color: var(--el-text-color-secondary); }
.category-workspace__hd--gap { padding-top: 6px; }
.category-workspace__arr { text-align: center; color: var(--el-text-color-secondary); }
.category-workspace__ro { min-height: 30px; padding: 5px 10px; border: 1px solid var(--el-border-color-light); border-radius: 8px; color: var(--el-text-color-secondary); background: var(--el-fill-color-light); box-sizing: border-box; }
.category-workspace__row { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; color: var(--el-text-color-regular); }
.category-workspace__two { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
.category-workspace__muted { font-size: 12px; color: var(--el-text-color-secondary); }
.category-workspace__center { display: grid; align-content: start; gap: 10px; min-width: 0; padding: 14px; background: var(--el-fill-color-light); }
.category-workspace__bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; color: var(--el-text-color-secondary); }
@media (max-width: 900px) {
  .category-workspace { grid-template-columns: minmax(0, 1fr); min-height: 0; }
  .category-workspace__left { border-right: 0; border-bottom: 1px solid var(--el-border-color-lighter); }
  .category-workspace__choice, .category-workspace__two { grid-template-columns: minmax(0, 1fr); }
}
</style>
