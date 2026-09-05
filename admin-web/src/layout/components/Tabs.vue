<template>
  <div class="app-tabs">
    <el-scrollbar
      ref="scrollRef"
      class="app-tabs__scroll"
      :always="false"
      @wheel.passive="onWheel"
    >
      <div class="app-tabs__inner">
        <div
          v-for="tab in tabsStore.tabs"
          :key="tab.path"
          ref="tabRefs"
          class="app-tab"
          :data-path="tab.path"
          :class="{ 'is-active': tabsStore.activePath === tab.path }"
          @click="onClick(tab.path)"
          @contextmenu.prevent="openMenu(tab.path, $event)"
        >
          <span
            class="app-tab__dot"
            :class="{ 'is-active': tabsStore.activePath === tab.path }"
          />
          <span class="app-tab__title">{{ tabTitle(tab) }}</span>
          <i
            v-if="!tab.affix"
            class="i-ep-close app-tab__close"
            @click.stop="closeOne(tab.path)"
          />
        </div>
      </div>
    </el-scrollbar>

    <el-dropdown trigger="click" @command="onCmd">
      <button class="app-tabs__more" :title="t('tabs.more')">
        <i class="i-ep-arrow-down" />
      </button>
      <template #dropdown>
        <el-dropdown-menu>
          <el-dropdown-item command="refresh">
            <i class="i-ep-refresh-right mr-1" /> {{ t('tabs.refreshCurrent') }}
          </el-dropdown-item>
          <el-dropdown-item command="closeOthers">
            <i class="i-ep-close mr-1" /> {{ t('tabs.closeOthers') }}
          </el-dropdown-item>
          <el-dropdown-item command="closeLeft">
            <i class="i-ep-back mr-1" /> {{ t('tabs.closeLeft') }}
          </el-dropdown-item>
          <el-dropdown-item command="closeRight">
            <i class="i-ep-right mr-1" /> {{ t('tabs.closeRight') }}
          </el-dropdown-item>
          <el-dropdown-item divided command="closeAll">
            <i class="i-ep-delete mr-1" /> {{ t('tabs.closeAll') }}
          </el-dropdown-item>
        </el-dropdown-menu>
      </template>
    </el-dropdown>

    <!-- 右键浮层菜单 -->
    <Teleport to="body">
      <transition name="ctx-fade">
        <div
          v-if="ctxVisible"
          ref="ctxRef"
          class="app-tabs__ctx"
          :style="{ left: ctxPos.x + 'px', top: ctxPos.y + 'px' }"
          @click.stop
        >
          <div
            v-for="(item, idx) in ctxItems"
            :key="idx"
            class="app-tabs__ctx-item"
            :class="{ 'is-divided': item.divided, 'is-disabled': item.disabled }"
            @click="!item.disabled && onCmd(item.cmd)"
          >
            <i :class="item.icon" />
            <span>{{ item.label }}</span>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useTabsStore, type TabItem } from '@/store/modules/tabs';
import { useMenuTitle } from '@/composables/useMenuTitle';

const { t, locale } = useI18n();

// el-scrollbar 实例 / 单个 tab 元素的 ref
const scrollRef = ref<any>(null);

// 拿到 el-scrollbar 内部真正可滚的 wrap DOM
function getWrap(): HTMLElement | null {
  return scrollRef.value?.wrapRef ?? null;
}

// 鼠标滚轮：把竖向滚动转换成 tabs 的横向滚动，避免顶栏被一并卷起
function onWheel(e: WheelEvent) {
  const wrap = getWrap();
  if (!wrap) return;
  // 仅在内容确实溢出时才接管，避免无溢出时阻塞页面滚动
  if (wrap.scrollWidth <= wrap.clientWidth) return;
  // 优先用 deltaY（常见鼠标滚轮），触摸板横向滑动 deltaX 也兼容
  const delta = Math.abs(e.deltaY) >= Math.abs(e.deltaX) ? e.deltaY : e.deltaX;
  if (delta === 0) return;
  wrap.scrollLeft += delta;
}

// 激活 tab 自动滚动到可视区域（切菜单/新增 tab 时触发）
function scrollActiveIntoView() {
  const wrap = getWrap();
  if (!wrap) return;
  const path = tabsStore.activePath;
  const el = wrap.querySelector<HTMLElement>(`.app-tab[data-path="${CSS.escape(path)}"]`);
  if (!el) return;
  const wrapRect = wrap.getBoundingClientRect();
  const elRect = el.getBoundingClientRect();
  // 计算让 tab 居中需要的滚动偏移
  const offset =
    elRect.left - wrapRect.left - (wrap.clientWidth - el.offsetWidth) / 2;
  wrap.scrollTo({ left: wrap.scrollLeft + offset, behavior: 'smooth' });
}

const { menuTitle } = useMenuTitle();

function tabTitle(tab: TabItem) {
  return menuTitle({ name: tab.name, meta: { title: tab.title } });
}

interface CtxItem {
  cmd: string;
  label: string;
  icon: string;
  divided?: boolean;
  disabled?: boolean;
}

const tabsStore = useTabsStore();
const router = useRouter();

const ctxPath = ref<string>('');
const ctxVisible = ref(false);
const ctxPos = reactive({ x: 0, y: 0 });
const ctxRef = ref<HTMLDivElement>();

const ctxItems = computed<CtxItem[]>(() => {
  void locale.value;
  const tabs = tabsStore.tabs;
  const idx = tabs.findIndex((t) => t.path === ctxPath.value);
  const target = tabs[idx];
  // 是否当前路由（影响"刷新"）
  const isCurrent = ctxPath.value === tabsStore.activePath;
  // 左/右是否还有可关闭的非固定页签
  const hasLeftClosable = idx > 0 && tabs.slice(0, idx).some((t) => !t.affix);
  const hasRightClosable =
    idx < tabs.length - 1 && tabs.slice(idx + 1).some((t) => !t.affix);
  // 是否还有别的非固定页签
  const hasOtherClosable = tabs.some((t) => t.path !== ctxPath.value && !t.affix);
  // 是否还有任何非固定页签
  const hasAnyClosable = tabs.some((t) => !t.affix);

  return [
    {
      cmd: 'refresh',
      label: t('tabs.refresh'),
      icon: 'i-ep-refresh-right',
      disabled: !isCurrent
    },
    {
      cmd: 'close',
      label: t('tabs.close'),
      icon: 'i-ep-close',
      disabled: !target || target.affix
    },
    {
      cmd: 'closeOthers',
      label: t('tabs.closeOthers'),
      icon: 'i-ep-semi-select',
      divided: true,
      disabled: !hasOtherClosable
    },
    {
      cmd: 'closeLeft',
      label: t('tabs.closeLeft'),
      icon: 'i-ep-back',
      disabled: !hasLeftClosable
    },
    {
      cmd: 'closeRight',
      label: t('tabs.closeRight'),
      icon: 'i-ep-right',
      disabled: !hasRightClosable
    },
    {
      cmd: 'closeAll',
      label: t('tabs.closeAll'),
      icon: 'i-ep-delete',
      divided: true,
      disabled: !hasAnyClosable
    }
  ];
});

function onClick(path: string) {
  if (path === tabsStore.activePath) return;
  router.push(path);
}

function closeOne(path: string) {
  const next = tabsStore.closeTab(path);
  if (next) router.push(next.path);
}

function openMenu(path: string, e: MouseEvent) {
  ctxPath.value = path;
  ctxVisible.value = true;
  // 等下一帧拿到菜单尺寸再修正坐标，避免溢出视口
  nextTick(() => {
    const el = ctxRef.value;
    const w = el?.offsetWidth ?? 168;
    const h = el?.offsetHeight ?? 240;
    const margin = 8;
    let x = e.clientX;
    let y = e.clientY;
    if (x + w + margin > window.innerWidth) x = window.innerWidth - w - margin;
    if (y + h + margin > window.innerHeight) y = window.innerHeight - h - margin;
    ctxPos.x = x;
    ctxPos.y = y;
  });
}

function closeMenu() {
  if (ctxVisible.value) ctxVisible.value = false;
}

function onCmd(cmd: string) {
  const target = ctxPath.value || tabsStore.activePath;
  closeMenu();
  switch (cmd) {
    case 'refresh': {
      const fullPath = router.currentRoute.value.fullPath;
      router.replace(`/redirect${fullPath}`);
      break;
    }
    case 'close':
      closeOne(target);
      break;
    case 'closeOthers':
      tabsStore.closeOthers(target);
      if (target) router.push(target);
      break;
    case 'closeLeft':
      tabsStore.closeLeft(target);
      break;
    case 'closeRight':
      tabsStore.closeRight(target);
      break;
    case 'closeAll':
      tabsStore.closeAll();
      router.push(tabsStore.activePath || '/dashboard');
      break;
  }
}

// 全局：点击 / 滚动 / esc / 切窗 关闭右键菜单
function onDocClick() {
  closeMenu();
}
function onDocKey(e: KeyboardEvent) {
  if (e.key === 'Escape') closeMenu();
}
document.addEventListener('click', onDocClick);
document.addEventListener('contextmenu', onDocClick, true);
window.addEventListener('resize', closeMenu);
window.addEventListener('blur', closeMenu);
document.addEventListener('keydown', onDocKey);

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick);
  document.removeEventListener('contextmenu', onDocClick, true);
  window.removeEventListener('resize', closeMenu);
  window.removeEventListener('blur', closeMenu);
  document.removeEventListener('keydown', onDocKey);
});

// 切换菜单 / tabs 数量变化时，等 DOM 渲染后把激活 tab 滚到可视范围
watch(
  () => [tabsStore.activePath, tabsStore.tabs.length] as const,
  () => {
    nextTick(scrollActiveIntoView);
  },
  { immediate: true }
);
</script>

<style scoped>
.app-tabs {
  display: flex;
  align-items: center;
  height: 100%;
  padding: 0 18px;
  gap: 4px;
}
.app-tabs__scroll {
  flex: 1;
  /* 限制宽度，确保超出时能横向滚动而不是把容器撑开 */
  min-width: 0;
}
/* 彻底隐藏 el-scrollbar 的滑块/轨道，但保留 wrap 的滚动能力 */
.app-tabs__scroll :deep(.el-scrollbar__bar) {
  display: none !important;
}
/* 同时隐藏 wrap 上原生横向滚动条（Firefox / Webkit） */
.app-tabs__scroll :deep(.el-scrollbar__wrap) {
  scrollbar-width: none;
}
.app-tabs__scroll :deep(.el-scrollbar__wrap)::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}
.app-tabs__inner {
  display: flex;
  align-items: center;
  gap: 6px;
  /* 与 --app-tabs-h：上下略增留白，与顶栏视觉节奏对齐 */
  padding: 5px 0;
  white-space: nowrap;
}
.app-tab {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 28px;
  padding: 0 10px;
  /* saiadmin .tags-container 用 4px 直角小圆角，克制不浮夸 */
  border-radius: 4px;
  /* 小于顶栏横向菜单(13.5px)，形成主次层级 */
  font-size: 12px;
  cursor: pointer;
  /* 默认态：白底 + 灰边，对齐 saiadmin .tags-container 标签 */
  background: var(--app-card-bg);
  color: var(--app-text-secondary);
  border: 1px solid var(--app-border);
  transition:
    color 0.2s,
    background-color 0.2s,
    border-color 0.2s;
}
/* hover：仅描边/字色变深，不再直接跳到主色浅底（与 active 拉开层级） */
.app-tab:hover {
  color: var(--app-text);
  border-color: color-mix(in srgb, var(--el-color-primary) 35%, var(--app-border));
}
/* active：primary-1 浅底 + primary-6 主色字 + primary-3 边，三层结构对齐 saiadmin */
.app-tab.is-active {
  color: var(--el-color-primary);
  background: color-mix(in srgb, var(--el-color-primary) 10%, white);
  border-color: color-mix(in srgb, var(--el-color-primary) 35%, white);
  font-weight: 600;
}
.app-tab__title {
  color: inherit;
  line-height: 1;
}
.app-tab__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--el-text-color-disabled);
  transition: background 0.2s;
}
.app-tab__dot.is-active {
  background: var(--el-color-primary);
  /* 阴影圈与 active 底色融合，不再外溢成另一层光晕 */
  box-shadow: none;
}
.app-tab__close {
  font-size: 11px;
  border-radius: 50%;
  width: 15px;
  height: 15px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-left: 2px;
  flex-shrink: 0;
}
.app-tab__close:hover {
  background: var(--el-color-danger);
  color: #fff;
}
.app-tabs__more {
  flex-shrink: 0;
  width: 30px;
  height: 30px;
  border: 0;
  background: transparent;
  cursor: pointer;
  border-radius: var(--app-radius);
  color: var(--app-text-secondary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.app-tabs__more:hover {
  background: var(--app-divider);
  color: var(--el-color-primary);
}

/* 夜间模式：--el-color-primary-light-9 在 dark 下偏白，会让激活 tab 出现白底浅字。
   改用项目深色 token，文字保留主色以突出。 */
html.dark .app-tab:hover {
  background: rgba(255, 255, 255, 0.06);
  color: var(--el-color-primary);
}
html.dark .app-tab.is-active {
  background: rgba(255, 255, 255, 0.1);
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
}
</style>

<style>
/* 右键菜单（Teleport 到 body，必须用全局样式） */
.app-tabs__ctx {
  position: fixed;
  z-index: 3000;
  min-width: 168px;
  padding: 6px;
  background: var(--app-card-bg);
  border: 1px solid var(--app-border);
  border-radius: var(--app-radius);
  box-shadow:
    0 12px 28px -8px rgba(0, 0, 0, 0.18),
    0 0 0 1px rgba(0, 0, 0, 0.04);
  user-select: none;
}
.app-tabs__ctx-item {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 34px;
  padding: 0 12px;
  font-size: 13px;
  color: var(--app-text);
  border-radius: var(--app-radius-sm);
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    color 0.15s ease;
}
.app-tabs__ctx-item i {
  font-size: 14px;
  color: var(--app-text-secondary);
}
.app-tabs__ctx-item:hover:not(.is-disabled) {
  background: var(--el-color-primary-light-9);
  color: var(--el-color-primary);
}
.app-tabs__ctx-item:hover:not(.is-disabled) i {
  color: var(--el-color-primary);
}
.app-tabs__ctx-item.is-divided {
  position: relative;
  margin-top: 6px;
}
.app-tabs__ctx-item.is-divided::before {
  content: '';
  position: absolute;
  left: 6px;
  right: 6px;
  top: -3px;
  height: 1px;
  background: var(--app-border);
}
.app-tabs__ctx-item.is-disabled {
  cursor: not-allowed;
  opacity: 0.4;
}
/* 弹出/收起 微动画 */
.ctx-fade-enter-active,
.ctx-fade-leave-active {
  transition:
    opacity 0.12s ease,
    transform 0.12s ease;
  transform-origin: top left;
}
.ctx-fade-enter-from,
.ctx-fade-leave-to {
  opacity: 0;
  transform: scale(0.96);
}

/*
 * ============== 标签页风格变体（html[data-tab-style] 切换） ==============
 * 写在非 scoped 块中，并以 html[data-tab-style] 提升特异性，覆盖 scoped 默认样式。
 * - default: 卡片描边
 * - card：浏览器 Tab 风格，底部色条
 * - minimal：无描边无底色，激活态底部下划线
 * - pill：胶囊圆角
 */

/* —— card：浏览器卡片 Tab，激活态底部色条 —— */
html[data-tab-style='card'] .app-tabs__inner .app-tab {
  border-radius: var(--app-radius-sm) var(--app-radius-sm) 0 0;
  border-bottom: none;
  height: 30px;
  padding: 0 12px;
  background: transparent;
  border-color: transparent;
}
html[data-tab-style='card'] .app-tabs__inner .app-tab:hover {
  background: var(--app-card-bg);
}
html[data-tab-style='card'] .app-tabs__inner .app-tab.is-active {
  background: var(--app-card-bg);
  border: 1px solid var(--app-border);
  border-bottom: 2px solid var(--el-color-primary);
  color: var(--el-color-primary);
}
html[data-tab-style='card'] .app-tabs__inner .app-tab .app-tab__dot {
  display: none;
}

/* —— minimal：极简下划线，无边框无底色 —— */
html[data-tab-style='minimal'] .app-tabs__inner .app-tab {
  background: transparent !important;
  border: none !important;
  border-radius: 0;
  height: 30px;
  padding: 0 10px;
  position: relative;
}
html[data-tab-style='minimal'] .app-tabs__inner .app-tab:hover {
  color: var(--el-color-primary);
  background: transparent !important;
}
html[data-tab-style='minimal'] .app-tabs__inner .app-tab.is-active {
  color: var(--el-color-primary);
  background: transparent !important;
}
html[data-tab-style='minimal'] .app-tabs__inner .app-tab.is-active::after {
  content: '';
  position: absolute;
  left: 10px;
  right: 10px;
  bottom: -4px;
  height: 2px;
  background: var(--el-color-primary);
  border-radius: 1px;
}
html[data-tab-style='minimal'] .app-tabs__inner .app-tab .app-tab__dot {
  display: none;
}

/* —— pill：胶囊形状，圆角拉满 —— */
html[data-tab-style='pill'] .app-tabs__inner .app-tab {
  border-radius: 999px;
  padding: 0 14px;
}
html[data-tab-style='pill'] .app-tabs__inner .app-tab.is-active {
  background: var(--el-color-primary);
  color: #ffffff;
  border-color: var(--el-color-primary);
}
html[data-tab-style='pill'] .app-tabs__inner .app-tab.is-active .app-tab__dot {
  background: rgba(255, 255, 255, 0.85);
  box-shadow: none;
}
html[data-tab-style='pill'] .app-tabs__inner .app-tab.is-active:hover {
  background: var(--el-color-primary);
  color: #ffffff;
}

/* 夜间模式：card / minimal / pill 微调对比度 */
html.dark[data-tab-style='card'] .app-tabs__inner .app-tab.is-active {
  background: var(--app-card-bg);
  border-color: var(--app-border);
  border-bottom-color: var(--el-color-primary);
}
html.dark[data-tab-style='pill'] .app-tabs__inner .app-tab.is-active {
  background: var(--el-color-primary);
  color: #ffffff;
}
</style>
