<template>
  <div
    class="relative flex min-h-full w-full items-center justify-center overflow-hidden rounded-app-lg border border-app-border bg-app-card p-6 shadow-app-sm"
  >
    <div class="relative flex max-w-[1100px] flex-wrap items-center justify-center gap-16">
      <div class="w-[420px] max-w-full [&_svg]:h-auto [&_svg]:w-full">
        <component :is="artwork" />
      </div>
      <div class="max-w-[360px] text-left max-md:mx-auto max-md:text-center">
        <div class="text-[96px] leading-none font-bold tracking-[4px] text-app-primary">{{ code }}</div>
        <h2 class="mt-3 mb-2 text-2xl font-semibold text-app-text">{{ title }}</h2>
        <p class="mb-6 text-sm leading-relaxed text-app-muted">{{ desc }}</p>
        <div class="flex flex-wrap gap-3 max-md:justify-center">
          <el-button type="primary" @click="goHome">
            <i class="i-ep-house mr-1" /> 返回首页
          </el-button>
          <el-button @click="goBack">
            <i class="i-ep-back mr-1" /> 上一页
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import Art403 from './Art403.vue';
import Art404 from './Art404.vue';
import Art500 from './Art500.vue';

interface Props {
  code: '403' | '404' | '500';
  title?: string;
  desc?: string;
}
const props = withDefaults(defineProps<Props>(), {
  title: '',
  desc: ''
});

const router = useRouter();

const presetMap = {
  '403': { title: '无权访问', desc: '抱歉，你没有访问该页面的权限' },
  '404': { title: '页面不存在', desc: '你访问的资源已被移除或暂时不可用' },
  '500': { title: '服务异常', desc: '服务器开了点小差，稍后再试' }
} as const;

const title = computed(() => props.title || presetMap[props.code].title);
const desc = computed(() => props.desc || presetMap[props.code].desc);

const artwork = computed(() => {
  if (props.code === '403') return Art403;
  if (props.code === '500') return Art500;
  return Art404;
});

function goHome() {
  router.replace('/');
}
function goBack() {
  if (window.history.length > 1) router.back();
  else router.replace('/');
}
</script>
