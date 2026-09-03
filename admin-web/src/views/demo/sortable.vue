<template>
  <PageWrapper title="拖拽排序" subtitle="SortableJS 演示 - 列表拖拽、看板拖拽、表格拖拽">
    <el-row :gutter="20">
      <!-- 案例1：基础列表拖拽 -->
      <el-col :xs="24" :sm="12" :lg="8" class="mb-4">
        <el-card shadow="hover">
          <template #header>
            <div class="flex items-center gap-2">
              <i class="i-ep-rank text-xl text-[var(--el-color-primary)]" />
              <span>基础列表拖拽</span>
            </div>
          </template>
          <p class="text-sm text-[var(--el-text-color-secondary)] mb-3">拖动列表项调整顺序</p>
          <ul ref="basicListRef" class="sortable-list">
            <li v-for="(item, index) in basicList" :key="item.id" class="sortable-item">
              <i class="i-ep-rank" />
              <span class="flex-1">{{ item.name }}</span>
              <el-tag size="small" type="info">#{{ index + 1 }}</el-tag>
            </li>
          </ul>
        </el-card>
      </el-col>

      <!-- 案例2：双列表互相拖拽 -->
      <el-col :xs="24" :sm="12" :lg="8" class="mb-4">
        <el-card shadow="hover">
          <template #header>
            <div class="flex items-center gap-2">
              <i class="i-ep-sort text-xl text-[var(--el-color-primary)]" />
              <span>分组互相拖拽</span>
            </div>
          </template>
          <p class="text-sm text-[var(--el-text-color-secondary)] mb-3">待办 ↔ 已完成</p>
          <div class="flex gap-3">
            <div class="flex-1">
              <h4 class="text-sm font-medium mb-2 text-center">📋 待办</h4>
              <ul ref="todoListRef" class="sortable-list sortable-list--compact">
                <li v-for="item in todoList" :key="item.id" class="sortable-item sortable-item--compact">
                  <i class="i-ep-rank" />
                  <span class="flex-1 truncate">{{ item.name }}</span>
                </li>
              </ul>
            </div>
            <div class="flex-1">
              <h4 class="text-sm font-medium mb-2 text-center">✅ 完成</h4>
              <ul ref="doneListRef" class="sortable-list sortable-list--compact">
                <li v-for="item in doneList" :key="item.id" class="sortable-item sortable-item--compact">
                  <i class="i-ep-rank" />
                  <span class="flex-1 truncate">{{ item.name }}</span>
                </li>
              </ul>
            </div>
          </div>
        </el-card>
      </el-col>

      <!-- 案例3：看板拖拽 -->
      <el-col :xs="24" :lg="16" class="mb-4">
        <el-card shadow="hover">
          <template #header>
            <div class="flex items-center gap-2">
              <i class="i-ep-connection text-xl text-[var(--el-color-primary)]" />
              <span>看板拖拽</span>
            </div>
          </template>
          <p class="text-sm text-[var(--el-text-color-secondary)] mb-3">拖动卡片切换状态列</p>
          <div class="kanban-board">
            <div v-for="col in kanbanColumns" :key="col.key" class="kanban-column"
                 :ref="(el: any) => { if (el) columnRefs[col.key] = el }">
              <div class="kanban-column-header" :style="{ background: col.color }">
                <span class="font-medium text-white">{{ col.label }}</span>
                <el-tag size="small" type="info" plain class="ml-auto">
                  {{ kanbanData[col.key].length }}
                </el-tag>
              </div>
              <ul class="sortable-list kanban-list" :data-column="col.key">
                <li v-for="item in kanbanData[col.key]" :key="item.id" class="sortable-item kanban-card">
                  <div class="flex items-center gap-2 w-full">
                    <i class="i-ep-rank" />
                    <div class="flex-1">
                      <div class="font-medium text-sm">{{ item.title }}</div>
                      <div class="text-xs text-[var(--el-text-color-secondary)]">{{ item.desc }}</div>
                    </div>
                  </div>
                  <div class="flex gap-1 mt-2">
                    <el-tag size="small" v-for="tag in item.tags" :key="tag" :type="tagColor(tag)">{{ tag }}</el-tag>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </el-card>
      </el-col>

      <!-- 案例4：表格行拖拽 -->
      <el-col :xs="24" class="mb-4">
        <el-card shadow="hover">
          <template #header>
            <div class="flex items-center gap-2">
              <i class="i-ep-menu text-xl text-[var(--el-color-primary)]" />
              <span>表格行拖拽</span>
            </div>
          </template>
          <p class="text-sm text-[var(--el-text-color-secondary)] mb-3">拖动左侧手柄调整行顺序</p>
          <el-table :data="tableData" border stripe>
            <el-table-column label="排序" width="60" align="center">
              <template #default="{ $index }">
                <span class="drag-handle cursor-move text-[var(--el-text-color-secondary)]">
                  <i class="i-ep-rank text-lg" />
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="id" label="ID" width="80" align="center" />
            <el-table-column prop="name" label="名称" min-width="150" />
            <el-table-column prop="role" label="角色" min-width="120" />
            <el-table-column prop="status" label="状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === '启用' ? 'success' : 'info'" size="small">
                  {{ row.status }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
          <div ref="tableRef" class="sr-only"></div>
        </el-card>
      </el-col>
    </el-row>
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref, reactive } from 'vue';
import Sortable from 'sortablejs';

defineOptions({ name: 'DemoSortable' });

// ==================== 案例1：基础列表 ====================
const basicListRef = ref<HTMLElement>();
const basicList = ref([
  { id: 1, name: 'Vue 3 技术选型' },
  { id: 2, name: 'Element Plus 组件库' },
  { id: 3, name: 'Vite 构建工具' },
  { id: 4, name: 'TypeScript 类型支持' },
  { id: 5, name: 'Pinia 状态管理' },
  { id: 6, name: 'Vue Router 路由管理' },
  { id: 7, name: 'Tailwind 原子化样式' },
  { id: 8, name: 'Axios 请求封装' }
]);

// ==================== 案例2：双列表互相拖拽 ====================
const todoListRef = ref<HTMLElement>();
const doneListRef = ref<HTMLElement>();
const todoList = ref([
  { id: 101, name: '需求评审' },
  { id: 102, name: '接口设计' },
  { id: 103, name: '数据库建模' },
  { id: 104, name: 'API 开发' }
]);
const doneList = ref([
  { id: 201, name: '项目初始化' },
  { id: 202, name: '路由配置' },
  { id: 203, name: '登录页开发' }
]);

// ==================== 案例3：看板拖拽 ====================
const columnRefs = reactive<Record<string, HTMLElement>>({});
const kanbanColumns = [
  { key: 'backlog', label: '待开发', color: '#6b7280' },
  { key: 'progress', label: '开发中', color: '#3b82f6' },
  { key: 'review', label: '测试中', color: '#f59e0b' },
  { key: 'done', label: '已完成', color: '#10b981' }
];
const kanbanData = reactive({
  backlog: [
    { id: 301, title: '用户管理', desc: 'CRUD + 权限', tags: ['后端', 'P1'] },
    { id: 302, title: '消息通知', desc: '站内消息 + 邮件', tags: ['全栈', 'P2'] }
  ],
  progress: [
    { id: 303, title: '角色管理', desc: '角色权限分配', tags: ['前端', 'P1'] },
    { id: 304, title: '菜单管理', desc: '动态菜单', tags: ['全栈', 'P0'] }
  ],
  review: [
    { id: 305, title: '部门管理', desc: '树形结构', tags: ['前端', 'P2'] }
  ],
  done: [
    { id: 306, title: '登录认证', desc: 'JWT Token', tags: ['后端', 'P0'] },
    { id: 307, title: '仪表盘', desc: '数据展示', tags: ['前端', 'P1'] }
  ]
});

// ==================== 案例4：表格行拖拽 ====================
const tableRef = ref<HTMLElement>();
const tableData = ref([
  { id: 1, name: '张三', role: '超级管理员', status: '启用' },
  { id: 2, name: '李四', role: '运营人员', status: '启用' },
  { id: 3, name: '王五', role: '访客', status: '禁用' },
  { id: 4, name: '赵六', role: '开发工程师', status: '启用' },
  { id: 5, name: '孙七', role: '测试工程师', status: '启用' }
]);

// ==================== 实例集合 ====================
let sortables: Sortable[] = [];

function tagColor(tag: string) {
  const map: Record<string, any> = { P0: 'danger', P1: 'warning', P2: 'info', 前端: 'success', 后端: 'primary', 全栈: '' };
  return map[tag] || 'info';
}

onMounted(() => {
  // 案例1：基础列表
  if (basicListRef.value) {
    sortables.push(Sortable.create(basicListRef.value, {
      animation: 200,
      ghostClass: 'sortable-ghost',
      chosenClass: 'sortable-chosen',
      dragClass: 'sortable-drag',
      handle: '.sortable-item',
      onEnd() {
        // 拖拽结束后同步数据顺序
        const items = Array.from(basicListRef.value!.children);
        basicList.value.sort((a, b) => {
          return items.findIndex(el => el.getAttribute('data-id') === String(a.id)) -
                 items.findIndex(el => el.getAttribute('data-id') === String(b.id));
        });
      }
    }));
  }

  // 案例2：双列表互相拖拽
  const sharedGroup = 'shared-todo-done';
  if (todoListRef.value) {
    sortables.push(Sortable.create(todoListRef.value, {
      group: sharedGroup,
      animation: 200,
      ghostClass: 'sortable-ghost',
      onEnd(evt) {
        const item = todoList.value.splice(evt.oldIndex!, 1)[0];
        (evt.to === doneListRef.value ? doneList : todoList).value.splice(evt.newIndex!, 0, item);
      }
    }));
  }
  if (doneListRef.value) {
    sortables.push(Sortable.create(doneListRef.value, {
      group: sharedGroup,
      animation: 200,
      ghostClass: 'sortable-ghost',
      onEnd(evt) {
        const item = doneList.value.splice(evt.oldIndex!, 1)[0];
        (evt.to === todoListRef.value ? todoList : doneList).value.splice(evt.newIndex!, 0, item);
      }
    }));
  }

  // 案例3：看板拖拽
  for (const col of kanbanColumns) {
    const list = col.key;
    const target = document.querySelector(`ul[data-column="${col.key}"]`) as HTMLElement;
    if (target) {
      sortables.push(Sortable.create(target, {
        group: 'kanban',
        animation: 200,
        ghostClass: 'sortable-ghost',
        onEnd(evt) {
          const fromList = kanbanData[evt.from.getAttribute('data-column')!];
          const toList = kanbanData[evt.to.getAttribute('data-column')!];
          if (!fromList || !toList) return;
          const [item] = fromList.splice(evt.oldIndex!, 1);
          toList.splice(evt.newIndex!, 0, item);
        }
      }));
    }
  }

  // 案例4：表格行拖拽
  const tableBody = document.querySelector('.el-table__body-wrapper tbody') as HTMLElement;
  if (tableBody) {
    sortables.push(Sortable.create(tableBody, {
      handle: '.drag-handle',
      animation: 200,
      ghostClass: 'sortable-ghost',
      onEnd(evt) {
        const item = tableData.value.splice(evt.oldIndex!, 1)[0];
        tableData.value.splice(evt.newIndex!, 0, item);
      }
    }));
  }
});

onUnmounted(() => {
  sortables.forEach(s => s.destroy());
  sortables = [];
});
</script>

<style scoped>
.sortable-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.sortable-list--compact .sortable-item {
  padding: 6px 10px;
  margin-bottom: 2px;
  font-size: 13px;
}
.sortable-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  margin-bottom: 4px;
  background: var(--app-card-bg);
  border: 1px solid var(--app-border);
  border-radius: 8px;
  cursor: grab;
  transition: box-shadow 0.2s, transform 0.15s;
  user-select: none;
}
.sortable-item:hover {
  box-shadow: var(--app-shadow);
  transform: translateY(-1px);
}
.sortable-item:active {
  cursor: grabbing;
}

/* SortableJS 拖拽状态 */
.sortable-ghost {
  opacity: 0.4;
  background: var(--app-app-bg);
}
.sortable-chosen {
  box-shadow: var(--app-shadow-lg);
  border-color: var(--el-color-primary);
}
.sortable-drag {
  opacity: 0.9;
  transform: scale(1.02);
  box-shadow: var(--app-shadow-lg);
}

/* 看板 */
.kanban-board {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 8px;
}
.kanban-column {
  flex: 1;
  min-width: 200px;
}
.kanban-column-header {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 8px 8px 0 0;
}
.kanban-list {
  min-height: 80px;
  padding: 4px;
  background: var(--app-app-bg);
  border-radius: 0 0 8px 8px;
}
.kanban-card {
  flex-direction: column;
  align-items: stretch;
}

/* 表格拖拽手柄 */
.drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
</style>
