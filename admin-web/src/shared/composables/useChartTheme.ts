import { computed, onMounted, ref, watch, type ComputedRef } from 'vue';
import { storeToRefs } from 'pinia';
import { useAppStore } from '@/store/modules/app';

/**
 * 图表配色 Hook：所有图表都从这里取色，跟随主题（亮/暗 + 主题色）变化。
 *
 * 设计原则：
 * - 主色：从 CSS 变量 --el-color-primary 取，自动跟随用户选择的主题色
 * - 辅助色：成功/警告/错误，从 EP 语义色变量取
 * - 文本/边框/分割线：从 --app-* 取，亮暗模式自动切换
 * - 调色板（palette）：以主色为锚点，配合中性色 + 语义色，避免硬编码花花绿绿
 */

type CssVarName =
  | '--el-color-primary'
  | '--el-color-success'
  | '--el-color-warning'
  | '--el-color-danger'
  | '--el-color-info'
  | '--app-text'
  | '--app-text-secondary'
  | '--app-border'
  | '--app-card-bg';

function readVar(name: CssVarName, fallback = ''): string {
  if (typeof window === 'undefined') return fallback;
  const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return v || fallback;
}

/** 将 #rgb / #rrggbb 解析为 [r, g, b]；不支持的格式回退灰色 */
function hexToRgb(hex: string): [number, number, number] {
  const s = hex.trim().replace('#', '');
  if (s.length === 3) {
    const r = parseInt(s[0] + s[0], 16);
    const g = parseInt(s[1] + s[1], 16);
    const b = parseInt(s[2] + s[2], 16);
    return [r, g, b];
  }
  if (s.length === 6) {
    return [parseInt(s.slice(0, 2), 16), parseInt(s.slice(2, 4), 16), parseInt(s.slice(4, 6), 16)];
  }
  return [128, 128, 128];
}

/** ECharts 不识别 color-mix()，统一转 rgba 给 canvas 渲染 */
function toRgba(color: string, alpha: number): string {
  const [r, g, b] = hexToRgb(color);
  const a = Math.max(0, Math.min(1, alpha));
  return `rgba(${r}, ${g}, ${b}, ${a})`;
}

interface ChartTokens {
  primary: string;
  success: string;
  warning: string;
  danger: string;
  info: string;
  text: string;
  textSecondary: string;
  border: string;
  cardBg: string;
  /** 主色透明叠层（面/区域用，0~1） */
  primaryAlpha: (a: number) => string;
  /** 通用调色板：主色锚点 + 语义色 + 中性，避免乱花 */
  palette: string[];
  /** 当前是否暗色模式 */
  isDark: boolean;
}

export function useChartTheme(): {
  tokens: ComputedRef<ChartTokens>;
  /** 主题切换时变化的 key，用于强制重建图表 */
  themeKey: ComputedRef<string>;
} {
  const appStore = useAppStore();
  const { themeMode, primaryColor } = storeToRefs(appStore);

  const tick = ref(0);
  const bump = () => (tick.value += 1);

  watch([themeMode, primaryColor], () => {
    requestAnimationFrame(bump);
  });

  onMounted(bump);

  const tokens = computed<ChartTokens>(() => {
    void tick.value;

    const primary = readVar('--el-color-primary', '#5D87FF');
    const success = readVar('--el-color-success', '#22c55e');
    const warning = readVar('--el-color-warning', '#f59e0b');
    const danger = readVar('--el-color-danger', '#ef4444');
    const info = readVar('--el-color-info', '#909399');
    const isDark = document.documentElement.classList.contains('dark');
    const text = readVar('--app-text', isDark ? '#e5e7eb' : '#1f2937');
    const textSecondary = readVar('--app-text-secondary', isDark ? '#9ca3af' : '#6b7280');
    const border = readVar('--app-border', isDark ? '#374151' : '#e5e6eb');
    const cardBg = readVar('--app-card-bg', isDark ? '#1f2937' : '#ffffff');

    return {
      primary,
      success,
      warning,
      danger,
      info,
      text,
      textSecondary,
      border,
      cardBg,
      primaryAlpha: (a: number) => toRgba(primary, a),
      palette: [primary, success, warning, danger, '#8b5cf6', '#0ea5e9', '#ec4899'],
      isDark
    };
  });

  const themeKey = computed(() => `${themeMode.value}-${primaryColor.value}-${tick.value}`);

  return { tokens, themeKey };
}
