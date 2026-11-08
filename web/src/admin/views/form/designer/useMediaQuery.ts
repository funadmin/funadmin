import { getCurrentInstance, onBeforeUnmount, ref, type Ref } from 'vue';

/** 订阅媒体查询；环境不支持 matchMedia（SSR、jsdom）时恒为 false，即按宽屏布局。 */
export function useMediaQuery(query: string): Ref<boolean> {
  const list = typeof window !== 'undefined' && typeof window.matchMedia === 'function' ? window.matchMedia(query) : null;
  const matches = ref(Boolean(list?.matches));
  if (!list) return matches;
  const update = (event: MediaQueryListEvent) => { matches.value = event.matches; };
  list.addEventListener?.('change', update);
  if (getCurrentInstance()) onBeforeUnmount(() => list.removeEventListener?.('change', update));
  return matches;
}
