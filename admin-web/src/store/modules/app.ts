import { defineStore } from 'pinia';
import { CACHE_KEYS, APP_CONFIG } from '@/config';
import { i18n } from '@/locales';
import { setWatermark, removeWatermark } from '@/utils/watermark';

export type ThemeMode = 'light' | 'dark' | 'auto';
export type LocaleType = 'zh-CN' | 'en-US';
export type LayoutMode = 'vertical' | 'horizontal' | 'mix' | 'columns';
export type MenuTheme = 'dark' | 'light' | 'fresh';
/** 标签页风格：默认 / 卡片 / 简约下划线 / 胶囊 */
export type TabStyle = 'default' | 'card' | 'minimal' | 'pill';
/** 路由切换动画 */
export type PageTransition = 'fade' | 'fade-slide' | 'slide-left' | 'fade-scale' | 'zoom-in' | 'none';
export type PresetTheme =
  | 'elegant'
  | 'fresh'
  | 'classic'
  | 'minimal'
  | 'vibrant'
  | 'sunset'
  | 'midnight';

export interface PresetThemeConfig {
  value: PresetTheme;
  label: string;
  desc: string;
  themeMode: ThemeMode;
  primaryColor: string;
  menuTheme: MenuTheme;
}

/** 预设主题方案：一键切换氛围 */
export const PRESET_THEMES: PresetThemeConfig[] = [
  { value: 'elegant',  label: '雅致',  desc: '浅色 + 清新蓝',  themeMode: 'light', primaryColor: '#5D87FF', menuTheme: 'light' },
  { value: 'fresh',    label: '清新',  desc: '浅色 + 青蓝',    themeMode: 'light', primaryColor: '#13c2c2', menuTheme: 'light' },
  { value: 'classic',  label: '经典',  desc: '深色侧栏 + 蓝',  themeMode: 'light', primaryColor: '#1677ff', menuTheme: 'dark'  },
  { value: 'minimal',  label: '极简',  desc: '浅色 + 石墨',    themeMode: 'light', primaryColor: '#0f172a', menuTheme: 'light' },
  { value: 'vibrant',  label: '活力',  desc: '清新渐变 + 紫',  themeMode: 'light', primaryColor: '#8b5cf6', menuTheme: 'fresh' },
  { value: 'sunset',   label: '暖阳',  desc: '浅色 + 橙红',    themeMode: 'light', primaryColor: '#f97316', menuTheme: 'light' },
  { value: 'midnight', label: '暗夜',  desc: '深色 + 蓝紫',    themeMode: 'dark',  primaryColor: '#6366f1', menuTheme: 'dark'  }
];

interface AppState {
  /** 侧栏折叠 */
  sidebarCollapsed: boolean;
  /** 是否小屏（< 1024px），含平板和手机 */
  isMobile: boolean;
  /** 移动端抽屉菜单是否展开 */
  mobileDrawerOpen: boolean;
  /** 进入手机端前的布局模式，用于恢复（非持久化） */
  _savedLayoutMode: LayoutMode | null;
  /** 主题色 */
  themeMode: ThemeMode;
  /** 主色 */
  primaryColor: string;
  /** 语言 */
  locale: LocaleType;
  /** 布局模式 */
  layoutMode: LayoutMode;
  /** 侧栏主题 */
  menuTheme: MenuTheme;
  /** 是否显示标签页 */
  showTabs: boolean;
  /** 是否显示面包屑 */
  showBreadcrumb: boolean;
  /** 是否固定头部 */
  fixedHeader: boolean;
  /** 是否显示 Logo */
  showLogo: boolean;
  /** 是否显示页脚 */
  showFooter: boolean;
  /** 灰色模式（缅怀模式） */
  grayMode: boolean;
  /** 色弱模式 */
  weakMode: boolean;
  /** 内容区是否最大化（隐藏侧栏） */
  contentFull: boolean;
  /** 当前预设主题方案 */
  preset: PresetTheme;
  /** 全局水印开关 */
  watermark: boolean;
  /** 水印文字 */
  watermarkText: string;
  /** 侧栏宽度（px） */
  sidebarWidth: number;
  /** 标签页风格 */
  tabStyle: TabStyle;
  /** 页面切换动画 */
  pageTransition: PageTransition;
  /** 圆角缩放倍率（基准 8px → 8px * radiusScale） */
  radiusScale: number;
}

/** 把 hex 颜色按 ratio 与白/黑混合，生成 ElementPlus 的 light-1~9 / dark-2 派生色 */
function mixColor(hex: string, ratio: number, mixWith: 'white' | 'black' = 'white'): string {
  const sanitized = hex.replace('#', '');
  if (sanitized.length !== 6) return hex;
  const r = parseInt(sanitized.slice(0, 2), 16);
  const g = parseInt(sanitized.slice(2, 4), 16);
  const b = parseInt(sanitized.slice(4, 6), 16);
  const target = mixWith === 'white' ? 255 : 0;
  const mix = (c: number) => Math.round(c * (1 - ratio) + target * ratio);
  const toHex = (n: number) => n.toString(16).padStart(2, '0');
  return `#${toHex(mix(r))}${toHex(mix(g))}${toHex(mix(b))}`;
}

/**
 * Element Plus 多处使用 `rgba(var(--el-color-primary-rgb), α)`（按钮阴影、菜单等）。
 * 仅改 --el-color-primary 不同步 rgb 时，会残留默认 RGB，导致「实心一块色」与「投影/半透明」两套蓝。
 */
function hexToPrimaryRgbString(hex: string): string | null {
  const sanitized = hex.replace('#', '');
  if (sanitized.length !== 6) return null;
  const r = parseInt(sanitized.slice(0, 2), 16);
  const g = parseInt(sanitized.slice(2, 4), 16);
  const b = parseInt(sanitized.slice(4, 6), 16);
  if ([r, g, b].some((n) => Number.isNaN(n))) return null;
  return `${r}, ${g}, ${b}`;
}

export const useAppStore = defineStore('app', {
  state: (): AppState => ({
    sidebarCollapsed: APP_CONFIG.layout.sidebarCollapsed,
    isMobile: false,
    mobileDrawerOpen: false,
    _savedLayoutMode: null,
    // 默认：雅致风格
    themeMode: 'light',
    primaryColor: '#5D87FF',
    locale: 'zh-CN',
    layoutMode: 'vertical',
    menuTheme: 'light',
    showTabs: APP_CONFIG.layout.showTabs,
    showBreadcrumb: APP_CONFIG.layout.showBreadcrumb,
    fixedHeader: APP_CONFIG.layout.fixedHeader,
    showLogo: APP_CONFIG.layout.showLogo,
    showFooter: false,
    grayMode: false,
    weakMode: false,
    contentFull: false,
    preset: 'elegant',
    watermark: false,
    watermarkText: 'Admin Console',
    sidebarWidth: 220,
    tabStyle: 'default',
    pageTransition: 'fade-slide',
    radiusScale: 1
  }),

  actions: {
    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed;
    },
    /**
     * 响应式断点切换
     * phone = true  → 强制切换 vertical 布局（保存原布局用于恢复）
     * phone = false + isMobile = false → 桌面端，恢复原布局
     */
    setIsMobile(v: boolean, isPhone = false) {
      const wasDesktop = !this.isMobile;
      this.isMobile = v;

      if (v) {
        // 进入小屏
        this.mobileDrawerOpen = false;
        if (isPhone && wasDesktop && this.layoutMode !== 'vertical') {
          // 手机尺寸：保存当前布局并切换到 vertical
          this._savedLayoutMode = this.layoutMode;
          this.layoutMode = 'vertical';
        }
      } else {
        // 恢复桌面端
        this.mobileDrawerOpen = false;
        if (this._savedLayoutMode) {
          this.layoutMode = this._savedLayoutMode;
          this._savedLayoutMode = null;
        }
      }
    },
    openMobileDrawer() {
      this.mobileDrawerOpen = true;
    },
    closeMobileDrawer() {
      this.mobileDrawerOpen = false;
    },
    toggleContentFull() {
      this.contentFull = !this.contentFull;
    },
    setTheme(mode: ThemeMode) {
      this.themeMode = mode;
      this.applyTheme();
    },
    setLocale(locale: LocaleType) {
      this.locale = locale;
      i18n.global.locale.value = locale;
    },
    setLayoutMode(mode: LayoutMode) {
      this.layoutMode = mode;
    },
    setMenuTheme(theme: MenuTheme) {
      this.menuTheme = theme;
      this.applyTheme();
    },
    setPrimaryColor(color: string) {
      this.primaryColor = color;
      this.applyPrimaryColor();
    },
    /** 应用预设主题方案 */
    setPreset(preset: PresetTheme) {
      const cfg = PRESET_THEMES.find((p) => p.value === preset);
      if (!cfg) return;
      this.preset = cfg.value;
      this.themeMode = cfg.themeMode;
      this.primaryColor = cfg.primaryColor;
      this.menuTheme = cfg.menuTheme;
      this.applyTheme();
    },
    setGrayMode(v: boolean) {
      this.grayMode = v;
      document.documentElement.classList.toggle('gray-mode', v);
    },
    setWeakMode(v: boolean) {
      this.weakMode = v;
      document.documentElement.classList.toggle('weak-mode', v);
    },

    /** 水印开关 */
    setWatermark(v: boolean) {
      this.watermark = v;
      this.applyWatermark();
    },
    /** 修改水印文字（仅在开启时生效） */
    setWatermarkText(text: string) {
      this.watermarkText = text || 'Admin Console';
      if (this.watermark) this.applyWatermark();
    },
    applyWatermark() {
      if (this.watermark) {
        const isDark = document.documentElement.classList.contains('dark');
        setWatermark(this.watermarkText, isDark);
      } else {
        removeWatermark();
      }
    },

    /** 侧栏宽度（160 ~ 320） */
    setSidebarWidth(w: number) {
      const clamped = Math.max(160, Math.min(320, Math.round(w)));
      this.sidebarWidth = clamped;
      this.applySidebarWidth();
    },
    applySidebarWidth() {
      document.documentElement.style.setProperty(
        '--app-sidebar-w',
        `${this.sidebarWidth}px`
      );
    },

    /** 标签页风格 */
    setTabStyle(style: TabStyle) {
      this.tabStyle = style;
      document.documentElement.dataset.tabStyle = style;
    },

    /** 页面切换动画 */
    setPageTransition(t: PageTransition) {
      this.pageTransition = t;
    },

    /** 圆角缩放（0.25 ~ 2） */
    setRadiusScale(scale: number) {
      const clamped = Math.max(0, Math.min(2, scale));
      this.radiusScale = clamped;
      this.applyRadiusScale();
    },
    applyRadiusScale() {
      const root = document.documentElement;
      const s = this.radiusScale;
      root.style.setProperty('--app-radius-sm', `${4 * s}px`);
      root.style.setProperty('--app-radius', `${8 * s}px`);
      root.style.setProperty('--app-radius-lg', `${12 * s}px`);
      root.style.setProperty('--app-radius-xl', `${16 * s}px`);
    },

    /** 把主色派生成 ElementPlus 全套 CSS 变量 */
    applyPrimaryColor() {
      const root = document.documentElement;
      root.style.setProperty('--el-color-primary', this.primaryColor);
      const rgb = hexToPrimaryRgbString(this.primaryColor);
      if (rgb) {
        root.style.setProperty('--el-color-primary-rgb', rgb);
      }
      // light-1 ~ light-9
      for (let i = 1; i <= 9; i++) {
        root.style.setProperty(
          `--el-color-primary-light-${i}`,
          mixColor(this.primaryColor, i * 0.1, 'white')
        );
      }
      // dark-2
      root.style.setProperty(
        '--el-color-primary-dark-2',
        mixColor(this.primaryColor, 0.2, 'black')
      );
    },

    /** 应用主题（明/暗 + 灰/色弱 + 主色派生 + preset 钩子） */
    applyTheme() {
      // 老版本持久化值兼容：'brand' 已被 'fresh' 取代
      if ((this.menuTheme as string) === 'brand') {
        this.menuTheme = 'fresh';
      }
      const html = document.documentElement;
      const isDark =
        this.themeMode === 'dark' ||
        (this.themeMode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
      html.classList.toggle('dark', isDark);
      html.dataset.theme = isDark ? 'dark' : 'light';
      html.dataset.preset = this.preset;
      html.classList.toggle('gray-mode', this.grayMode);
      html.classList.toggle('weak-mode', this.weakMode);
      html.dataset.tabStyle = this.tabStyle;
      this.applyPrimaryColor();
      this.applySidebarWidth();
      this.applyRadiusScale();
      this.applyWatermark();
    },

    /**
     * 历史默认主色自动迁移：避免老用户被 localStorage 卡死在旧默认色。
     * 仅当用户当前主色 == 某个历史默认值时才替换，自定义过的颜色不受影响。
     */
    migrateLegacyPrimary() {
      const LEGACY_DEFAULT_PRIMARIES = ['#2c7be5', '#165dff'];
      const current = (this.primaryColor || '').toLowerCase();
      if (LEGACY_DEFAULT_PRIMARIES.includes(current)) {
        this.primaryColor = '#5D87FF';
      }
    },

    /** 重置为出厂设置（默认雅致方案） */
    resetSettings() {
      this.preset = 'elegant';
      this.themeMode = 'light';
      this.primaryColor = '#5D87FF';
      this.layoutMode = 'vertical';
      this.menuTheme = 'light';
      this.showTabs = true;
      this.showBreadcrumb = true;
      this.fixedHeader = true;
      this.showLogo = true;
      this.showFooter = false;
      this.grayMode = false;
      this.weakMode = false;
      this.watermark = false;
      this.watermarkText = 'Admin Console';
      this.sidebarWidth = 220;
      this.tabStyle = 'default';
      this.pageTransition = 'fade-slide';
      this.radiusScale = 1;
      this.applyTheme();
    }
  },

  persist: {
    key: CACHE_KEYS.THEME,
    pick: [
      'sidebarCollapsed',
      'themeMode',
      'primaryColor',
      'locale',
      'layoutMode',
      'menuTheme',
      'showTabs',
      'showBreadcrumb',
      'fixedHeader',
      'showLogo',
      'showFooter',
      'grayMode',
      'weakMode',
      'preset',
      'watermark',
      'watermarkText',
      'sidebarWidth',
      'tabStyle',
      'pageTransition',
      'radiusScale'
    ]
  }
});
