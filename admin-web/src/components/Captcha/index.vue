<template>
  <button
    class="captcha-box"
    type="button"
    :style="{ width: `${width}px`, height: `${height}px` }"
    title="点击刷新验证码"
    @click="refresh"
  >
    <img :src="src" alt="验证码" />
  </button>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';

interface Props {
  width?: number;
  height?: number;
}

const props = withDefaults(defineProps<Props>(), {
  width: 110,
  height: 40
});

const src = ref('');

defineExpose({ refresh });

function refresh() {
  const baseApi = import.meta.env.VITE_APP_BASE_API || '/backend';
  src.value = `${baseApi}/auth/captcha?t=${Date.now()}`;
}

onMounted(refresh);
</script>

<style scoped>
.captcha-box {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 1px solid var(--el-border-color);
  border-radius: 6px;
  background: var(--el-fill-color-light);
  cursor: pointer;
  overflow: hidden;
  flex-shrink: 0;
}
.captcha-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
