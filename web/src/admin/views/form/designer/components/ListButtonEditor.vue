<template>
  <section class="button-location" :class="{ 'is-active': active }" :data-location="location">
    <div class="button-location__header" :title="t('formDesigner.buttonConfigHelp', { mode: modeHelp }, '{mode}；显示配置不会授予权限或开启业务能力。')">
      <strong>{{ title }}</strong>
      <small>{{ mode === 'inherit' ? t('formDesigner.buttonsWorkspace.inheritShort', '继承') : t('formDesigner.buttonsWorkspace.count', { n: modelValue?.length ?? 0 }, '{n} 个') }}</small>
    </div>
    <div class="button-location__mode" role="radiogroup" :aria-label="t('formDesigner.buttonsWorkspace.modeAria', { title }, '{title}按钮来源')">
      <button v-for="option in modeOptions" :key="option.value" type="button" role="radio" :aria-checked="mode === option.value" :class="{ 'is-on': mode === option.value }" @click="setMode(option.value)">{{ option.label }}</button>
    </div>
    <div class="button-location__list">
      <div
        v-for="(button, index) in buttons"
        :key="button.id"
        class="button-location__item"
        :class="{ 'is-selected': button.id === selectedId, 'is-drop-target': dropTarget === button.id }"
        :data-button-id="button.id"
        draggable="true"
        role="button"
        tabindex="0"
        @click="emit('select', button.id)"
        @keydown.enter.prevent="emit('select', button.id)"
        @dragstart="dragging = button.id"
        @dragover.prevent="dropTarget = button.id"
        @dragleave="dropTarget = ''"
        @drop.prevent="drop(button.id)"
        @dragend="dragging = ''; dropTarget = ''"
      >
        <span class="button-location__grip" aria-hidden="true">⠿</span>
        <span class="button-location__label" :title="`${button.label} · ${actionText(button)}`">{{ button.label }}</span>
        <small>{{ actionText(button) }}</small>
        <span class="button-location__ops" @click.stop>
          <button type="button" :aria-label="t('formDesigner.moveUp', '上移')" :title="t('formDesigner.moveUp', '上移')" :disabled="index === 0" @click="move(index, -1)"><i class="i-ep-top" /></button>
          <button type="button" :aria-label="t('formDesigner.moveDown', '下移')" :title="t('formDesigner.moveDown', '下移')" :disabled="index === buttons.length - 1" @click="move(index, 1)"><i class="i-ep-bottom" /></button>
          <button type="button" class="is-danger" :aria-label="t('formDesigner.remove', '移除')" :title="t('formDesigner.remove', '移除')" @click="remove(index)"><i class="i-ep-delete" /></button>
        </span>
      </div>
      <div v-if="!buttons.length" class="button-location__empty">{{ t('formDesigner.noButtons', '暂无按钮') }}</div>
    </div>
    <button type="button" class="button-location__add" @click="emit('add')">＋ {{ t('formDesigner.addButton', '添加按钮') }}</button>
  </section>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormListButton, FormListButtonLocation } from '../../schema/types';
import { defaultListButtons } from '../../runtime/listButtonHost';
/** 左栏单个按钮位置：来源切换（继承默认 / 自定义 / 全部隐藏）、可拖拽排序的按钮列表与添加入口；按钮字段在右栏 ButtonInspector 编辑。 */
const props = defineProps<{ title: string; location: FormListButtonLocation; modelValue?: FormListButton[]; selectedId?: string; active?: boolean }>();
const emit = defineEmits<{ update: [value: FormListButton[] | undefined]; select: [id: string]; add: [] }>();
const { t } = useI18n();
const buttons = computed(() => props.modelValue ?? defaultListButtons(props.location));
// 语义与 schema 一致：undefined = 继承宿主默认，非空数组 = 自定义，[] = 全部隐藏。
const mode = computed(() => props.modelValue === undefined ? 'inherit' : props.modelValue.length ? 'custom' : 'hidden');
const modeHelp = computed(() => mode.value === 'inherit' ? t('formDesigner.inheritDefault', '继承宿主默认按钮') : mode.value === 'custom' ? t('formDesigner.useCustom', '使用自定义按钮') : t('formDesigner.emptyHideAll', '空集合：全部隐藏'));
const modeOptions = computed(() => [{ value: 'inherit', label: t('formDesigner.buttonsWorkspace.modeInherit', '继承默认') }, { value: 'custom', label: t('formDesigner.buttonsWorkspace.modeCustom', '自定义') }, { value: 'hidden', label: t('formDesigner.hideAll', '全部隐藏') }] as const);
const clone = (value: FormListButton[]) => JSON.parse(JSON.stringify(value)) as FormListButton[];
function setMode(value: 'inherit' | 'custom' | 'hidden') {
  if (value === mode.value) return;
  if (value === 'inherit') emit('update', undefined);
  else if (value === 'hidden') emit('update', []);
  else emit('update', clone(defaultListButtons(props.location)));
}
const actionText = (button: FormListButton) => button.action.type === 'builtin' ? button.action.key : button.action.type;
function remove(index: number) { emit('update', clone(buttons.value).filter((_, i) => i !== index)); }
function move(index: number, offset: number) { const next = clone(buttons.value); const target = index + offset; [next[index], next[target]] = [next[target], next[index]]; next.forEach((button, i) => { button.order = i; }); emit('update', next); }
const dragging = ref(''); const dropTarget = ref('');
function drop(targetId: string) {
  const from = buttons.value.findIndex(button => button.id === dragging.value);
  const to = buttons.value.findIndex(button => button.id === targetId);
  dragging.value = ''; dropTarget.value = '';
  if (from < 0 || to < 0 || from === to) return;
  const next = clone(buttons.value); const [moved] = next.splice(from, 1); next.splice(to, 0, moved);
  next.forEach((button, i) => { button.order = i; }); emit('update', next);
}
</script>
<style scoped>
.button-location { display: grid; gap: 6px; padding: 8px 10px; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; background: var(--el-bg-color); }
.button-location.is-active { border-color: var(--el-color-primary-light-5); background: var(--el-color-primary-light-9); }
.button-location__header { display: flex; align-items: center; gap: 6px; font-size: 13px; }
.button-location__header small { margin-left: auto; font-size: 11px; color: var(--el-text-color-secondary); }
.button-location__mode { display: inline-flex; justify-self: start; gap: 1px; padding: 2px; border-radius: 7px; background: var(--el-fill-color); }
.button-location__mode button { padding: 1px 8px; border: 0; border-radius: 5px; font-size: 11px; line-height: 18px; color: var(--el-text-color-regular); background: transparent; cursor: pointer; }
.button-location__mode button.is-on { color: var(--el-color-primary); font-weight: 600; background: var(--el-bg-color); box-shadow: var(--el-box-shadow-lighter); }
.button-location__list { display: grid; gap: 2px; }
.button-location__item { display: flex; align-items: center; gap: 6px; min-width: 0; padding: 3px 6px; border: 1px solid transparent; border-radius: 6px; font-size: 12px; color: var(--el-text-color-regular); cursor: pointer; }
.button-location__item:hover { background: var(--el-fill-color-light); }
.button-location__item.is-selected { color: var(--el-color-primary); font-weight: 600; background: var(--el-color-primary-light-8); }
.button-location__item.is-drop-target { border-color: var(--el-color-primary); border-style: dashed; }
.button-location__grip { color: var(--el-text-color-placeholder); cursor: grab; }
.button-location__label { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.button-location__item small { margin-left: auto; font-size: 10px; font-weight: 400; color: var(--el-text-color-secondary); white-space: nowrap; }
.button-location__ops { display: none; gap: 2px; }
.button-location__item:hover .button-location__ops, .button-location__item:focus-within .button-location__ops, .button-location__item.is-selected .button-location__ops { display: inline-flex; }
.button-location__ops button { display: inline-grid; place-items: center; width: 20px; height: 20px; padding: 0; border: 0; border-radius: 4px; color: var(--el-text-color-secondary); background: transparent; cursor: pointer; }
.button-location__ops button:hover:not(:disabled) { color: var(--el-color-primary); background: var(--el-bg-color); }
.button-location__ops button.is-danger:hover { color: var(--el-color-danger); }
.button-location__ops button:disabled { opacity: 0.35; cursor: not-allowed; }
.button-location__empty { padding: 6px; border: 1px dashed var(--el-border-color); border-radius: 6px; font-size: 12px; text-align: center; color: var(--el-text-color-secondary); }
.button-location__add { justify-self: start; padding: 2px 10px; border: 1px dashed var(--el-color-primary-light-5); border-radius: 6px; font-size: 12px; color: var(--el-color-primary); background: transparent; cursor: pointer; }
.button-location__add:hover { background: var(--el-color-primary-light-9); }
</style>
