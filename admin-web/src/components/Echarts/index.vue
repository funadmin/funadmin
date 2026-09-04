<template>
  <div ref="el" class="echarts-box" :style="{ height: typeof height === 'number' ? `${height}px` : height }" />
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import * as echarts from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { BarChart, LineChart, PieChart, RadarChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, TitleComponent, LegendComponent } from 'echarts/components';
import { useDebounceFn } from '@vueuse/core';
import { useAppStore } from '@/store/modules/app';

echarts.use([
  CanvasRenderer,
  BarChart,
  LineChart,
  PieChart,
  RadarChart,
  GridComponent,
  TooltipComponent,
  TitleComponent,
  LegendComponent
]);

interface Props {
  option: any;
  height?: number | string;
  theme?: string;
  loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  height: 320,
  loading: false
});

const appStore = useAppStore();
const el = ref<HTMLDivElement>();
const inst = shallowRef<echarts.ECharts>();

function init() {
  if (!el.value) return;
  inst.value = echarts.init(el.value, appStore.themeMode === 'dark' ? 'dark' : undefined);
  inst.value.setOption(props.option || {});
  if (props.loading) inst.value.showLoading();
}

const resize = useDebounceFn(() => inst.value?.resize(), 80);

// 节流式 setOption：把同一 tick 内多次 option 重算合并为一次渲染，避免主色 / 主题切换时连续重绘
const applyOption = useDebounceFn((notMerge = false) => {
  inst.value?.setOption(props.option || {}, notMerge);
}, 16);

let ro: ResizeObserver | null = null;

onMounted(() => {
  init();
  window.addEventListener('resize', resize);
  // 容器自身尺寸变化（侧栏折叠 / 卡片自适应）也要重绘，避免出现内边距 0 的空白图
  if (el.value && typeof ResizeObserver !== 'undefined') {
    ro = new ResizeObserver(() => resize());
    ro.observe(el.value);
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', resize);
  ro?.disconnect();
  ro = null;
  inst.value?.dispose();
});

// option 变化：父级用 computed 返回新对象引用即可触发；不用 deep:true，避免对大对象建立深度依赖
watch(
  () => props.option,
  () => applyOption(true)
);

watch(
  () => props.loading,
  (v) => (v ? inst.value?.showLoading() : inst.value?.hideLoading())
);

watch(
  () => appStore.themeMode,
  () => {
    inst.value?.dispose();
    init();
  }
);

// 主题色切换时，强制 notMerge 重渲染，避免残留旧主色的渐变 / 透明叠层
watch(
  () => appStore.primaryColor,
  () => applyOption(true)
);

defineExpose({ instance: inst });
</script>

<style scoped>
.echarts-box {
  width: 100%;
}
</style>
