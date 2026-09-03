<template>
  <el-drawer
    v-model="visible"
    title="布局设置"
    :size="340"
    direction="rtl"
    :with-header="true"
  >
    <div class="setting">
      <!-- 主题方案（一键预设） -->
      <div class="setting__section">
        <div class="setting__title">主题方案</div>
        <div class="setting__preset-grid">
          <div
            v-for="p in presets"
            :key="p.value"
            class="setting__preset-card"
            :class="{ 'is-active': appStore.preset === p.value }"
            @click="appStore.setPreset(p.value)"
          >
            <div class="setting__preset-preview">
              <div
                class="setting__preset-side"
                :style="{ background: previewSide(p) }"
              />
              <div class="setting__preset-body">
                <div class="setting__preset-bar" :style="{ background: p.primaryColor }" />
                <div class="setting__preset-line" />
                <div class="setting__preset-line short" />
              </div>
            </div>
            <div class="setting__preset-meta">
              <span class="setting__preset-name">{{ p.label }}</span>
              <span class="setting__preset-desc">{{ p.desc }}</span>
            </div>
            <i v-if="appStore.preset === p.value" class="i-ep-check setting__preset-check" />
          </div>
        </div>
      </div>

      <!-- 布局模式 -->
      <div class="setting__section">
        <div class="setting__title">布局模式</div>
        <div class="setting__layout-grid">
          <div
            v-for="m in layoutModes"
            :key="m.value"
            class="setting__layout-card"
            :class="[`layout-${m.value}`, { 'is-active': appStore.layoutMode === m.value }]"
            :title="m.label"
            @click="appStore.setLayoutMode(m.value as LayoutMode)"
          >
            <div class="setting__layout-preview">
              <!-- vertical -->
              <template v-if="m.value === 'vertical'">
                <div class="layout-side" />
                <div class="layout-body">
                  <div class="layout-top" />
                  <div class="layout-content" />
                </div>
              </template>
              <!-- horizontal -->
              <template v-else-if="m.value === 'horizontal'">
                <div class="layout-body">
                  <div class="layout-top filled" />
                  <div class="layout-content" />
                </div>
              </template>
              <!-- mix -->
              <template v-else-if="m.value === 'mix'">
                <div class="layout-body">
                  <div class="layout-top filled" />
                  <div class="layout-mix-row">
                    <div class="layout-side" />
                    <div class="layout-content" />
                  </div>
                </div>
              </template>
              <!-- columns -->
              <template v-else-if="m.value === 'columns'">
                <div class="layout-rail" />
                <div class="layout-side narrow" />
                <div class="layout-body">
                  <div class="layout-top" />
                  <div class="layout-content" />
                </div>
              </template>
            </div>
            <span class="setting__layout-label">{{ m.label }}</span>
            <i v-if="appStore.layoutMode === m.value" class="i-ep-check setting__layout-check" />
          </div>
        </div>
      </div>

      <!-- 主题模式 -->
      <div class="setting__section">
        <div class="setting__title">主题模式</div>
        <div class="setting__theme-grid">
          <div
            v-for="m in themeModes"
            :key="m.value"
            class="setting__theme-card"
            :class="{ 'is-active': appStore.themeMode === m.value }"
            @click="appStore.setTheme(m.value as ThemeMode)"
          >
            <i :class="m.icon" class="text-base mb-1" />
            <span>{{ m.label }}</span>
          </div>
        </div>
      </div>

      <!-- 主题色 -->
      <div class="setting__section">
        <div class="setting__title">主题色</div>
        <div class="setting__colors">
          <div
            v-for="c in colors"
            :key="c"
            class="setting__color"
            :style="{ background: c }"
            :class="{ 'is-active': appStore.primaryColor === c }"
            @click="appStore.setPrimaryColor(c)"
          >
            <i v-if="appStore.primaryColor === c" class="i-ep-check text-white" />
          </div>
          <el-color-picker
            :model-value="appStore.primaryColor"
            size="small"
            class="setting__color-picker"
            @change="(v: string | null) => appStore.setPrimaryColor(v || '#6366f1')"
          />
        </div>
      </div>

      <!-- 侧栏主题 -->
      <div class="setting__section">
        <div class="setting__title">侧栏风格</div>
        <div class="setting__menu-grid">
          <div
            v-for="m in menuThemes"
            :key="m.value"
            class="setting__menu-card"
            :class="[`menu-${m.value}`, { 'is-active': appStore.menuTheme === m.value }]"
            :title="m.label"
            @click="appStore.setMenuTheme(m.value as MenuTheme)"
          >
            <div class="setting__menu-preview">
              <div class="setting__menu-side">
                <span class="setting__menu-logo" />
                <span class="setting__menu-item is-on" />
                <span class="setting__menu-item" />
                <span class="setting__menu-item" />
              </div>
              <div class="setting__menu-body">
                <span class="setting__menu-bar" />
                <span class="setting__menu-line" />
                <span class="setting__menu-line short" />
              </div>
            </div>
            <span class="setting__menu-label">{{ m.label }}</span>
            <i v-if="appStore.menuTheme === m.value" class="i-ep-check setting__menu-check" />
          </div>
        </div>
      </div>

      <!-- 界面 -->
      <div class="setting__section">
        <div class="setting__title">界面显示</div>
        <div class="setting__row">
          <span>多页签</span>
          <el-switch v-model="appStore.showTabs" />
        </div>
        <div class="setting__row">
          <span>面包屑</span>
          <el-switch v-model="appStore.showBreadcrumb" />
        </div>
        <div class="setting__row">
          <span>固定头部</span>
          <el-switch v-model="appStore.fixedHeader" />
        </div>
        <div class="setting__row">
          <span>显示 Logo</span>
          <el-switch v-model="appStore.showLogo" />
        </div>
        <div class="setting__row">
          <span>显示页脚</span>
          <el-switch v-model="appStore.showFooter" />
        </div>
      </div>

      <!-- 界面定制 -->
      <div class="setting__section">
        <div class="setting__title">界面定制</div>
        <div class="setting__row">
          <span>全局水印</span>
          <el-switch
            :model-value="appStore.watermark"
            @change="(v: any) => appStore.setWatermark(!!v)"
          />
        </div>
        <div class="setting__row">
          <span>菜单宽度</span>
          <el-input-number
            :model-value="appStore.sidebarWidth"
            :min="160"
            :max="320"
            :step="10"
            controls-position="right"
            class="setting__input-number"
            @change="(v: any) => appStore.setSidebarWidth(Number(v) || 220)"
          />
        </div>
        <div class="setting__row">
          <span>标签页风格</span>
          <el-select
            :model-value="appStore.tabStyle"
            class="setting__select"
            @change="(v: any) => appStore.setTabStyle(v)"
          >
            <el-option
              v-for="t in tabStyles"
              :key="t.value"
              :label="t.label"
              :value="t.value"
            />
          </el-select>
        </div>
        <div class="setting__row">
          <span>页面切换动画</span>
          <el-select
            :model-value="appStore.pageTransition"
            class="setting__select"
            @change="(v: any) => appStore.setPageTransition(v)"
          >
            <el-option
              v-for="t in pageTransitions"
              :key="t.value"
              :label="t.label"
              :value="t.value"
            />
          </el-select>
        </div>
        <div class="setting__row">
          <span>自定义圆角</span>
          <el-select
            :model-value="appStore.radiusScale"
            class="setting__select"
            @change="(v: any) => appStore.setRadiusScale(Number(v))"
          >
            <el-option
              v-for="r in radiusScales"
              :key="r.value"
              :label="r.label"
              :value="r.value"
            />
          </el-select>
        </div>
        <div v-if="appStore.watermark" class="setting__row">
          <span>水印文字</span>
          <el-input
            :model-value="appStore.watermarkText"
            class="setting__select"
            placeholder="水印文字"
            @change="(v: any) => appStore.setWatermarkText(v)"
          />
        </div>
      </div>

      <!-- 特殊模式 -->
      <div class="setting__section">
        <div class="setting__title">特殊模式</div>
        <div class="setting__row">
          <span>灰色模式</span>
          <el-switch
            :model-value="appStore.grayMode"
            @change="(v: any) => appStore.setGrayMode(!!v)"
          />
        </div>
        <div class="setting__row">
          <span>色弱模式</span>
          <el-switch
            :model-value="appStore.weakMode"
            @change="(v: any) => appStore.setWeakMode(!!v)"
          />
        </div>
      </div>

      <el-divider />

      <div class="setting__actions">
        <el-button @click="copyConfig">
          <i class="i-ep-document-copy mr-1" />
          复制配置
        </el-button>
        <el-button type="danger" plain @click="onReset">
          <i class="i-ep-refresh mr-1" />
          重置默认
        </el-button>
      </div>
    </div>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { useAppStore, PRESET_THEMES } from '@/store/modules/app';
import type { ThemeMode, MenuTheme, PresetThemeConfig, LayoutMode } from '@/store/modules/app';
import mitt from '@/utils/mitt';

const { t, locale } = useI18n();

const tabStyles = computed(() => {
  void locale.value;
  return [
  { value: 'default', label: t('setting.tabStyleDefault') },
  { value: 'card', label: t('setting.tabStyleCard') },
  { value: 'minimal', label: t('setting.tabStyleMinimal') },
  { value: 'pill', label: t('setting.tabStylePill') }
  ];
});

const pageTransitions = computed(() => {
  void locale.value;
  return [
  { value: 'fade', label: t('setting.transitionFade') },
  { value: 'fade-slide', label: t('setting.transitionFadeSlide') },
  { value: 'slide-left', label: t('setting.transitionSlideLeft') },
  { value: 'fade-scale', label: t('setting.transitionFadeScale') },
  { value: 'zoom-in', label: t('setting.transitionZoomIn') },
  { value: 'none', label: t('setting.transitionNone') }
  ];
});

const radiusScales = computed(() => {
  void locale.value;
  return [
  { value: 0, label: t('setting.radius0') },
  { value: 0.25, label: t('setting.radius025') },
  { value: 0.5, label: t('setting.radius05') },
  { value: 0.75, label: t('setting.radius075') },
  { value: 1, label: t('setting.radius1') },
  { value: 1.25, label: t('setting.radius125') },
  { value: 1.5, label: t('setting.radius15') },
  { value: 2, label: t('setting.radius2') }
  ];
});

const appStore = useAppStore();
const visible = ref(false);

const presets = computed(() => {
  void locale.value;
  return PRESET_THEMES.map((p) => ({
    ...p,
    label: t(`setting.preset.${p.value}.label`),
    desc: t(`setting.preset.${p.value}.desc`)
  }));
});

/** 侧栏预览色：根据 menuTheme 决定 */
function previewSide(p: PresetThemeConfig): string {
  if (p.menuTheme === 'dark') return '#001529';
  if (p.menuTheme === 'fresh') {
    /* loading 同款：多 radial-gradient 浅彩底 */
    return [
      'radial-gradient(ellipse at 18% 18%, #e0f2fe 0%, transparent 55%)',
      'radial-gradient(ellipse at 85% 12%, #fce7f3 0%, transparent 50%)',
      'radial-gradient(ellipse at 78% 88%, #d1fae5 0%, transparent 55%)',
      'linear-gradient(180deg, #fdfcfb 0%, #f5f7fa 100%)'
    ].join(',');
  }
  return p.themeMode === 'dark' ? '#131a2b' : '#f7fafc';
}

const themeModes = computed(() => {
  void locale.value;
  return [
    { value: 'light', label: t('setting.themeLight'), icon: 'i-ep-sunny' },
    { value: 'dark', label: t('setting.themeDark'), icon: 'i-ep-partly-cloudy' },
    { value: 'auto', label: t('setting.themeAuto'), icon: 'i-ep-monitor' }
  ];
});

const layoutModes = computed(() => {
  void locale.value;
  return [
    { value: 'vertical', label: t('setting.layoutVertical') },
    { value: 'horizontal', label: t('setting.layoutHorizontal') },
    { value: 'mix', label: t('setting.layoutMix') },
    { value: 'columns', label: t('setting.layoutColumns') }
  ];
});

const menuThemes = computed(() => {
  void locale.value;
  return [
    { value: 'dark', label: t('setting.menuThemeDark') },
    { value: 'light', label: t('setting.menuThemeLight') },
    { value: 'fresh', label: t('setting.menuThemeFresh') }
  ];
});

/** 系统主题色（柔和清新风格） */
const colors = [
  '#5D87FF', // 雅致 清新蓝（默认）
  '#a78bfa', // 淡紫
  '#2563eb', // 宝蓝
  '#10b981', // 翠绿
  '#38bdf8', // 天青
  '#f59e0b', // 暖橙
  '#f472b6'  // 樱粉
];

function open() {
  visible.value = true;
}

function copyConfig() {
  const config = {
    preset: appStore.preset,
    themeMode: appStore.themeMode,
    primaryColor: appStore.primaryColor,
    menuTheme: appStore.menuTheme,
    showTabs: appStore.showTabs,
    showBreadcrumb: appStore.showBreadcrumb,
    fixedHeader: appStore.fixedHeader,
    showLogo: appStore.showLogo,
    showFooter: appStore.showFooter,
    grayMode: appStore.grayMode,
    weakMode: appStore.weakMode
  };
  navigator.clipboard
    .writeText(JSON.stringify(config, null, 2))
    .then(() => ElMessage.success(t('setting.copySuccess')))
    .catch(() => ElMessage.error(t('setting.copyFail')));
}

function onReset() {
  appStore.resetSettings();
  ElMessage.success(t('setting.resetSuccess'));
}

onMounted(() => mitt.on('open:setting', open));
onUnmounted(() => mitt.off('open:setting', open));
</script>

<style scoped>
.setting {
  padding: 4px 0 16px;
}
.setting__section {
  margin-bottom: 18px;
}
.setting__title {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-text);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
}
.setting__title::before {
  content: '';
  width: 3px;
  height: 12px;
  background: var(--el-color-primary);
  margin-right: 6px;
  border-radius: 2px;
}

.setting__theme-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.setting__theme-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 56px;
  border: 1px solid var(--app-border);
  border-radius: var(--app-radius);
  font-size: 12px;
  cursor: pointer;
  color: var(--app-text-secondary);
  transition: all 0.18s;
}
.setting__theme-card:hover {
  border-color: var(--el-color-primary);
}
.setting__theme-card.is-active {
  background: var(--el-color-primary-light-9);
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
  font-weight: 600;
}

.setting__colors {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  justify-content: flex-start;
  padding: 4px 0;
}
.setting__color {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: #fff;
  transition: all 0.2s;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}
.setting__color:hover {
  transform: translateY(-2px) scale(1.08);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}
.setting__color.is-active {
  box-shadow: 0 0 0 2px var(--app-card-bg), 0 0 0 4px currentColor;
}
.setting__color-picker :deep(.el-color-picker__trigger) {
  width: 28px;
  height: 28px;
  padding: 0;
  border-radius: 50%;
  border: 1px dashed var(--app-border);
}

/* —— 侧栏风格（与布局模式保持视觉一致：mini 卡 + 下方标签 + 选中描边） —— */
.setting__menu-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}
.setting__menu-card {
  position: relative;
  border: 1px solid var(--app-border);
  border-radius: var(--app-radius);
  padding: 6px;
  cursor: pointer;
  background: var(--app-card-bg);
  transition: all 0.2s;
}
.setting__menu-card:hover {
  border-color: var(--el-color-primary);
  transform: translateY(-1px);
  box-shadow: var(--app-shadow-sm);
}
.setting__menu-card.is-active {
  border-color: var(--el-color-primary);
  box-shadow: 0 0 0 2px var(--el-color-primary-light-7);
}
.setting__menu-preview {
  display: flex;
  height: 56px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--app-divider);
  /* 内容区底色：浅色用纯白，深色用稍亮的灰，避免抽屉里大白块刺眼 */
  background: var(--app-card-bg);
}
.setting__menu-side {
  width: 34%;
  padding: 5px 4px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: #001529;
  position: relative;
}
.setting__menu-logo {
  display: block;
  height: 4px;
  width: 60%;
  border-radius: 2px;
  background: rgba(255, 255, 255, 0.55);
  margin-bottom: 2px;
}
.setting__menu-item {
  display: block;
  height: 4px;
  border-radius: 2px;
  background: rgba(255, 255, 255, 0.18);
}
.setting__menu-item.is-on {
  background: var(--el-color-primary);
  box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.15);
}
.setting__menu-body {
  flex: 1;
  padding: 5px 6px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: var(--app-card-bg);
}
.setting__menu-bar {
  display: block;
  height: 5px;
  border-radius: 2px;
  background: var(--el-color-primary);
  opacity: 0.85;
  width: 70%;
}
.setting__menu-line {
  display: block;
  height: 4px;
  border-radius: 2px;
  background: var(--app-divider);
}
.setting__menu-line.short {
  width: 55%;
}

/* 浅色侧栏 */
.setting__menu-card.menu-light .setting__menu-side {
  background: var(--app-app-bg);
  border-right: 1px solid var(--app-divider);
}
.setting__menu-card.menu-light .setting__menu-logo {
  background: color-mix(in srgb, var(--app-text) 32%, transparent);
}
.setting__menu-card.menu-light .setting__menu-item {
  background: color-mix(in srgb, var(--app-text) 14%, transparent);
}
.setting__menu-card.menu-light .setting__menu-item.is-on {
  background: var(--el-color-primary-light-8);
  box-shadow: inset 2px 0 0 var(--el-color-primary);
}

/* 清新侧栏（loading 同款：浅彩 radial-gradient + 白色卡片激活态） */
.setting__menu-card.menu-fresh .setting__menu-side {
  background:
    radial-gradient(ellipse at 18% 18%, #e0f2fe 0%, transparent 55%),
    radial-gradient(ellipse at 85% 12%, #fce7f3 0%, transparent 50%),
    radial-gradient(ellipse at 78% 88%, #d1fae5 0%, transparent 55%),
    linear-gradient(180deg, #fdfcfb 0%, #f5f7fa 100%);
  border-right: 1px solid rgba(91, 141, 239, 0.08);
}
.setting__menu-card.menu-fresh .setting__menu-logo {
  background: rgba(91, 141, 239, 0.35);
}
.setting__menu-card.menu-fresh .setting__menu-item {
  background: rgba(100, 116, 139, 0.18);
}
.setting__menu-card.menu-fresh .setting__menu-item.is-on {
  background: #ffffff;
  box-shadow:
    0 2px 8px -2px rgba(91, 141, 239, 0.25),
    0 0 0 1px rgba(91, 141, 239, 0.08);
  position: relative;
}
.setting__menu-card.menu-fresh .setting__menu-item.is-on::after {
  content: '';
  position: absolute;
  inset: 1px;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--el-color-primary) 0%, transparent 70%);
  opacity: 0.5;
}

.setting__menu-label {
  display: block;
  margin-top: 6px;
  text-align: center;
  font-size: 12px;
  color: var(--app-text);
  font-weight: 500;
}
.setting__menu-card.is-active .setting__menu-label {
  color: var(--el-color-primary);
  font-weight: 600;
}
.setting__menu-check {
  position: absolute;
  top: 4px;
  right: 4px;
  font-size: 14px;
  color: var(--el-color-primary);
}

.setting__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  font-size: 13px;
  color: var(--app-text);
  min-height: 44px;
}
.setting__select {
  width: 170px;
}
.setting__input-number {
  width: 150px;
}
.setting__input-number :deep(.el-input__wrapper) {
  padding-right: 28px;
}

.setting__actions {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  gap: 10px;
  width: 100%;
  box-sizing: border-box;
}
.setting__actions :deep(.el-button) {
  flex: 1;
  min-width: 0;
  width: auto;
  margin: 0;
  box-sizing: border-box;
}

/* 预设主题方案 */
.setting__preset-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
.setting__preset-card {
  position: relative;
  border: 1px solid var(--app-border);
  border-radius: var(--app-radius);
  padding: 6px;
  cursor: pointer;
  transition: all 0.18s;
  background: var(--app-card-bg);
  overflow: hidden;
}
.setting__preset-card:hover {
  border-color: var(--el-color-primary);
  transform: translateY(-1px);
  box-shadow: var(--app-shadow-sm);
}
.setting__preset-card.is-active {
  border-color: var(--el-color-primary);
  box-shadow: 0 0 0 2px var(--el-color-primary-light-7);
}
.setting__preset-preview {
  display: flex;
  height: 48px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--app-divider);
}
.setting__preset-side {
  width: 28%;
  height: 100%;
}
.setting__preset-body {
  flex: 1;
  background: var(--app-card-bg);
  padding: 5px 6px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
/* 顶部主色条：参考 saiadmin tag.active 的 primary-6 主色 + primary-3 边感，纯色不透明 */
.setting__preset-bar {
  height: 4px;
  border-radius: 2px;
  width: 70%;
  /* 内联 style 直接喂 p.primaryColor，这里保持饱和度，不再 opacity 衰减 */
}
.setting__preset-line {
  height: 4px;
  background: #e5e6eb; /* Arco border-2，骨架占位灰 */
  border-radius: 2px;
}
.setting__preset-line.short {
  width: 55%;
}
.setting__preset-meta {
  margin-top: 6px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
}
.setting__preset-name {
  font-weight: 600;
  color: var(--app-text);
}
.setting__preset-desc {
  font-size: 11px;
  color: var(--app-text-secondary);
}
.setting__preset-check {
  position: absolute;
  top: 4px;
  right: 4px;
  font-size: 14px;
  color: var(--el-color-primary);
}

/* 布局模式预览 */
.setting__layout-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}
.setting__layout-card {
  position: relative;
  border: 1px solid var(--app-border);
  border-radius: var(--app-radius);
  padding: 6px;
  cursor: pointer;
  transition: all 0.18s;
  background: var(--app-card-bg);
}
.setting__layout-card:hover {
  border-color: var(--el-color-primary);
  transform: translateY(-1px);
  box-shadow: var(--app-shadow-sm);
}
.setting__layout-card.is-active {
  border-color: var(--el-color-primary);
  box-shadow: 0 0 0 2px var(--el-color-primary-light-7);
}
.setting__layout-preview {
  display: flex;
  height: 50px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--app-divider);
  background: var(--app-card-bg);
}

/*
 * 布局缩略图配色（参考 saiadmin-vue mine 皮肤）：
 * - 灰阶严格按 Arco bg-2 / bg-3 / border-2 三档拉开层级，不用 slate 冷调
 * - 主侧栏：bg-3 (#f2f3f5)  次侧栏：bg-2 (#fafbfc)  内容区：bg-2 (#fafbfc)
 * - 顶栏 filled：primary-1 (主色淡化为 12% 浅蓝底) + 顶部 2px primary-6 主色实线
 *   —— 模拟 saiadmin 的 .tag.active "浅蓝底 + 主色边" 双层观感
 * - 暗色：用 Arco classics 皮肤的 #1c1e23 / #2e3033 / #232324 三档
 */
.layout-side {
  width: 22%;
  background: #f2f3f5; /* Arco bg-3 */
}
.layout-side.narrow {
  width: 18%;
  background: #fafbfc; /* Arco bg-2 */
}
.layout-rail {
  width: 12%;
  background: #f2f3f5;
}
.layout-body {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.layout-top {
  height: 28%;
  background: #ffffff;
  border-bottom: 1px solid #e5e6eb; /* Arco border-2 */
}
/* filled 顶栏：浅主色底 + 顶部一条主色细线，对应 saiadmin .tag.active 的双层结构 */
.layout-top.filled {
  background: color-mix(in srgb, var(--el-color-primary) 12%, white);
  border-bottom-color: transparent;
  position: relative;
}
.layout-top.filled::before {
  content: '';
  position: absolute;
  inset: 0 0 auto 0;
  height: 2px;
  background: var(--el-color-primary);
}
.layout-content {
  flex: 1;
  background: #fafbfc; /* Arco bg-2 */
}
.layout-mix-row {
  flex: 1;
  display: flex;
}
.layout-mix-row .layout-side {
  width: 28%;
  background: #fafbfc;
}

/* 暗色：参考 saiadmin classics 皮肤 #1c1e23 / #2e3033 / #232324 三档 */
html.dark .setting__layout-preview {
  border-color: #2e3033;
  background: #1c1e23;
}
html.dark .layout-side,
html.dark .layout-rail {
  background: #1c1e23;
}
html.dark .layout-side.narrow,
html.dark .layout-mix-row .layout-side {
  background: #232324;
}
html.dark .layout-top {
  background: #232324;
  border-bottom-color: #2e3033;
}
html.dark .layout-top.filled {
  background: color-mix(in srgb, var(--el-color-primary) 22%, #232324);
  border-bottom-color: transparent;
}
html.dark .layout-content {
  background: #17171a;
}
.setting__layout-label {
  display: block;
  margin-top: 6px;
  text-align: center;
  font-size: 12px;
  color: var(--app-text);
  font-weight: 500;
}
.setting__layout-check {
  position: absolute;
  top: 4px;
  right: 4px;
  font-size: 14px;
  color: var(--el-color-primary);
}
</style>
