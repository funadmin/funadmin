<template>
  <i v-if="isUno" :class="['svg-icon', name, sizeClass]" :style="iconStyle" />
  <span v-else class="svg-icon" :style="iconStyle" v-html="svgHtml" />
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  /** Icon class name such as i-ep-user, or a local SVG file name. */
  name: string;
  size?: number | string;
  color?: string;
}

const props = withDefaults(defineProps<Props>(), {
  size: 16
});

/** i-* classes are provided by generated local icon CSS. */
const isUno = computed(() => props.name.startsWith('i-'));

const sizeClass = computed(() => (typeof props.size === 'number' ? '' : ''));

const iconStyle = computed(() => ({
  width: typeof props.size === 'number' ? `${props.size}px` : props.size,
  height: typeof props.size === 'number' ? `${props.size}px` : props.size,
  color: props.color
}));

// 预留：本地 SVG 注册可在此扩展
const svgHtml = computed(() => '');
</script>

<style scoped>
.svg-icon {
  display: inline-block;
  vertical-align: -0.15em;
  fill: currentColor;
  overflow: hidden;
}
</style>
