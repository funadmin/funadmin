<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 32 32"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
    class="logo-mark"
  >
    <defs>
      <!--
        月牙 mask：满月白圆 - 偏移黑圆 = 月牙
        - 黑圆右上偏移，挖出「左下凸、开口右上」的弯月
      -->
      <mask :id="maskId">
        <rect width="32" height="32" fill="white" />
        <circle cx="20.5" cy="11" r="8.2" fill="black" />
      </mask>
    </defs>

    <!--
      纯实色背景 squircle
      - 扁平化第一原则：单色，无渐变
      - currentColor 跟随 .logo-mark 的 color，方便主题统一切换
    -->
    <rect width="32" height="32" rx="8" fill="currentColor" />

    <!--
      白月牙：纯白实心，无半透
      - 扁平化第二原则：高对比、纯色块
    -->
    <circle
      cx="14"
      cy="15"
      r="8"
      fill="white"
      :mask="`url(#${maskId})`"
    />

    <!--
      点睛小星：右下角对位
      - 与月牙形成对角呼应
      - 纯白实心，无透明度
    -->
    <circle cx="23" cy="22.5" r="1.3" fill="white" />
  </svg>
</template>

<script setup lang="ts">
import { useId } from "vue";

withDefaults(
  defineProps<{
    /** 图标边长（px） */
    size?: number | string;
  }>(),
  { size: 28 }
);

// 多实例并存避免 mask id 冲突
const maskId = useId();
</script>

<style scoped>
/*
 * 扁平化规范：
 * - 纯实色（violet-600 #7C3AED，现代 SaaS 主流紫，比纯蓝有性格）
 * - 零阴影、零渐变、零滤镜
 * - color 跟随主题：外层包一层 .logo-mark { color: var(--brand) } 即可换色
 */
.logo-mark {
  color: #7c3aed;
  display: block;
}
</style>
