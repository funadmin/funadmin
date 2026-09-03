<template>
  <el-popover
    placement="bottom-start"
    :width="360"
    trigger="click"
    popper-class="icon-select-popper"
    :visible="visible"
    @hide="visible = false"
  >
    <template #reference>
      <div class="icon-select__trigger" @click="visible = !visible">
        <i v-if="modelValue" :class="modelValue" class="text-base" />
        <span v-else class="icon-select__placeholder">{{ placeholder }}</span>
        <i class="i-ep-arrow-down icon-select__arrow" />
      </div>
    </template>
    <div class="icon-select">
      <el-input
        v-model="keyword"
        placeholder="搜索图标"
        clearable
        size="small"
        class="mb-2"
      >
        <template #prefix>
          <i class="i-ep-search" />
        </template>
      </el-input>
      <div class="icon-select__scroll">
        <div class="icon-select__grid">
          <div
            v-for="name in filtered"
            :key="name"
            class="icon-select__item"
            :class="{ 'is-active': modelValue === `i-ep-${name}` }"
            :title="name"
            @click="select(name)"
          >
            <i :class="`i-ep-${name}`" class="icon-select__item-icon" />
          </div>
        </div>
        <div v-if="!filtered.length" class="icon-select__empty">无匹配图标</div>
      </div>
      <div class="icon-select__footer">共 {{ allIcons.length }} 个图标 · 显示 {{ filtered.length }}</div>
    </div>
  </el-popover>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

interface Props {
  modelValue?: string;
  placeholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  placeholder: '选择图标'
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
  change: [value: string];
}>();

const visible = ref(false);
const keyword = ref('');

const allIcons: string[] = [
  'aim',
  'arrow-down',
  'avatar',
  'back',
  'bell',
  'bottom',
  'calendar',
  'chat-dot-round',
  'chat-line-square',
  'check',
  'clock',
  'close',
  'collection',
  'connection',
  'd-arrow-left',
  'd-arrow-right',
  'data-line',
  'delete',
  'document',
  'document-copy',
  'download',
  'edit',
  'edit-pen',
  'expand',
  'files',
  'fold',
  'folder',
  'folder-opened',
  'full-screen',
  'grid',
  'headset',
  'histogram',
  'house',
  'iphone',
  'key',
  'link',
  'loading',
  'lock',
  'map-location',
  'menu',
  'message',
  'monitor',
  'office-building',
  'partly-cloudy',
  'phone',
  'picture',
  'picture-filled',
  'plus',
  'rank',
  'refresh',
  'refresh-right',
  'right',
  'search',
  'semi-select',
  'setting',
  'shopping-cart',
  'sort',
  'success-filled',
  'sunny',
  'switch-button',
  'tickets',
  'top',
  'upload',
  'upload-filled',
  'user',
  'user-filled',
  'video-camera',
  'view',
  'wallet',
  'warning',
  'zoom-in',
];

const filtered = computed(() => {
  const k = keyword.value.trim().toLowerCase();
  if (!k) return allIcons;
  return allIcons.filter((name) => name.toLowerCase().includes(k));
});

function select(name: string) {
  const v = `i-ep-${name}`;
  emit('update:modelValue', v);
  emit('change', v);
  visible.value = false;
}

void props;
</script>

<style scoped>
.icon-select__trigger {
  display: flex;
  align-items: center;
  height: 32px;
  padding: 0 10px;
  border: 1px solid var(--el-border-color);
  border-radius: var(--app-radius-sm);
  background: var(--app-card-bg);
  color: var(--app-text);
  cursor: pointer;
  font-size: 13px;
  transition: border-color 0.2s;
}
.icon-select__trigger:hover {
  border-color: var(--el-color-primary);
}
.icon-select__placeholder {
  color: var(--app-text-secondary);
  flex: 1;
}
.icon-select__arrow {
  margin-left: auto;
  color: var(--app-text-secondary);
  font-size: 12px;
}
.icon-select {
  padding: 4px 0;
}
.icon-select__scroll {
  max-height: 220px;
  overflow-x: hidden;
  overflow-y: auto;
  width: 100%;
  -webkit-overflow-scrolling: touch;
}
.icon-select__grid {
  display: grid;
  grid-template-columns: repeat(8, minmax(0, 1fr));
  gap: 6px;
  width: 100%;
  box-sizing: border-box;
}
.icon-select__item {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-width: 0;
  aspect-ratio: 1;
  min-height: 32px;
  border-radius: var(--app-radius-sm);
  cursor: pointer;
  color: var(--app-text);
  transition: all 0.15s;
}
.icon-select__item-icon {
  font-size: 16px;
  line-height: 1;
  flex-shrink: 0;
}
.icon-select__item:hover {
  background: var(--el-fill-color-light);
  color: var(--el-color-primary);
}
.icon-select__item.is-active {
  background: var(--el-color-primary);
  color: #fff;
}
.icon-select__empty {
  text-align: center;
  font-size: 12px;
  color: var(--app-text-secondary);
  padding: 24px 0;
}
.icon-select__footer {
  margin-top: 6px;
  font-size: 11px;
  color: var(--app-text-secondary);
  text-align: right;
}
</style>

<!-- 弹层 teleport 到 body 后不带当前 SFC 的 data-v-*，scoped 网格样式会失效，需按 popper-class 全局兜底 -->
<style>
.icon-select-popper.el-popover,
.icon-select-popper.el-popper {
  box-sizing: border-box;
  padding: 12px;
}

.icon-select-popper .icon-select__scroll {
  max-height: 220px;
  overflow-x: hidden;
  overflow-y: auto;
  width: 100%;
  box-sizing: border-box;
}

.icon-select-popper .icon-select__grid {
  display: grid !important;
  grid-template-columns: repeat(8, minmax(0, 1fr)) !important;
  grid-auto-flow: row;
  gap: 6px;
  width: 100%;
  box-sizing: border-box;
}

.icon-select-popper .icon-select__item {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-width: 0;
  aspect-ratio: 1;
  min-height: 32px;
  border-radius: var(--app-radius-sm, 6px);
  cursor: pointer;
  color: var(--app-text);
  transition:
    background-color 0.15s ease,
    color 0.15s ease;
}

.icon-select-popper .icon-select__item:hover {
  background: var(--el-fill-color-light);
  color: var(--el-color-primary);
}

.icon-select-popper .icon-select__item.is-active {
  background: var(--el-color-primary);
  color: #fff;
}

.icon-select-popper .icon-select__item-icon {
  font-size: 16px;
  line-height: 1;
  flex-shrink: 0;
}

.icon-select-popper .icon-select__empty {
  text-align: center;
  font-size: 12px;
  color: var(--app-text-secondary);
  padding: 24px 0;
}

.icon-select-popper .icon-select__footer {
  margin-top: 8px;
  font-size: 11px;
  color: var(--app-text-secondary);
  text-align: right;
}
</style>
