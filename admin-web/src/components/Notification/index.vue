<template>
  <el-popover
    placement="bottom-end"
    :width="360"
    trigger="click"
    popper-class="app-notification-popper"
  >
    <template #reference>
      <button class="app-btn-icon" title="消息">
        <el-badge
          :value="unread"
          :hidden="!unread"
          :max="99"
          class="app-notification__badge"
        >
          <i class="i-ep-bell" />
        </el-badge>
      </button>
    </template>

    <div class="app-notification">
      <div class="app-notification__header">
        <span class="font-medium">消息中心</span>
        <el-button v-if="unread" type="primary" link size="small" @click="markAllRead">
          全部已读
        </el-button>
      </div>

      <el-tabs v-model="active" class="app-notification__tabs">
        <el-tab-pane
          v-for="t in tabs"
          :key="t.key"
          :label="`${t.label}(${groupCount(t.key)})`"
          :name="t.key"
        >
          <el-scrollbar max-height="320px">
            <div v-if="!grouped[t.key]?.length" class="app-notification__empty">
              <el-empty :image-size="60" description="暂无消息" />
            </div>
            <div v-else class="app-notification__list">
              <div
                v-for="item in grouped[t.key]"
                :key="item.id"
                class="app-notification__item"
                :class="{ 'is-unread': !item.read }"
                @click="readOne(item.id)"
              >
                <el-avatar
                  :size="32"
                  :style="{ background: item.color }"
                  class="flex-shrink-0"
                >
                  <i :class="item.icon" />
                </el-avatar>
                <div class="app-notification__info">
                  <div class="app-notification__title">{{ item.title }}</div>
                  <div class="app-notification__desc">{{ item.desc }}</div>
                  <div class="app-notification__time">{{ item.time }}</div>
                </div>
              </div>
            </div>
          </el-scrollbar>
        </el-tab-pane>
      </el-tabs>

      <div class="app-notification__footer">
        <el-button text size="small">清空</el-button>
        <el-button text type="primary" size="small">查看全部</el-button>
      </div>
    </div>
  </el-popover>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { notificationApi, type NoticeItem, type NoticeType } from '@/api/notification';
import emitter from '@/utils/mitt';

const tabs: { key: NoticeType; label: string }[] = [
  { key: 'notice', label: '通知' },
  { key: 'message', label: '消息' },
  { key: 'todo', label: '待办' }
];

const active = ref<NoticeType>('notice');
const list = ref<NoticeItem[]>([]);

async function loadList() {
  try {
    list.value = await notificationApi.list();
  } catch {
    list.value = [];
  }
}

const grouped = computed(() => {
  const result: Record<NoticeType, NoticeItem[]> = {
    notice: [],
    message: [],
    todo: []
  };
  list.value.forEach((item) => result[item.type].push(item));
  return result;
});

const unread = computed(() => list.value.filter((i) => !i.read).length);

function groupCount(type: NoticeType) {
  return grouped.value[type]?.filter((i) => !i.read).length || 0;
}

async function readOne(id: number) {
  const item = list.value.find((i) => i.id === id);
  if (item && !item.read) {
    item.read = true;
    await notificationApi.read(id).catch(() => (item.read = false));
  }
}

async function markAllRead() {
  await notificationApi.readAll();
  list.value.forEach((i) => (i.read = true));
}

onMounted(loadList);
emitter.on('refresh:notification', loadList);
</script>

<style scoped>
.app-btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: var(--app-radius-sm);
  border: 0;
  background: transparent;
  color: var(--app-text-secondary);
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s;
}
.app-btn-icon:hover {
  background: var(--el-fill-color-light);
  color: var(--el-color-primary);
}
.app-notification__badge :deep(.el-badge__content) {
  border: 0;
}
.app-notification__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 0 10px;
  border-bottom: 1px solid var(--app-border);
}
/* 勿用负 margin 抵消 popper 内边距，否则标签/列表会贴左像「没留白」 */
.app-notification__tabs {
  margin: 0;
}
.app-notification__tabs :deep(.el-tabs__header) {
  margin: 0 0 4px;
}
.app-notification__tabs :deep(.el-tabs__nav-wrap) {
  padding: 0 2px;
}
.app-notification__tabs :deep(.el-tabs__nav-wrap)::after {
  display: none;
}
.app-notification__empty {
  padding: 16px 4px;
}
.app-notification__list {
  padding: 4px 2px 8px;
}
.app-notification__item {
  display: flex;
  gap: 10px;
  padding: 12px 10px;
  border-radius: var(--app-radius-sm);
  cursor: pointer;
  transition: background 0.18s;
}
.app-notification__item:hover {
  background: var(--el-fill-color-light);
}
.app-notification__item.is-unread .app-notification__title {
  color: var(--el-color-primary);
  font-weight: 600;
}
.app-notification__info {
  flex: 1;
  min-width: 0;
}
.app-notification__title {
  font-size: 13px;
  color: var(--app-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.app-notification__desc {
  margin-top: 2px;
  font-size: 12px;
  color: var(--app-text-secondary);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.app-notification__time {
  margin-top: 4px;
  font-size: 11px;
  color: var(--app-text-secondary);
}
.app-notification__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid var(--app-border);
  padding: 10px 2px 2px;
}
</style>

<style>
.app-notification-popper {
  --el-popover-padding: 14px 16px !important;
}
</style>
