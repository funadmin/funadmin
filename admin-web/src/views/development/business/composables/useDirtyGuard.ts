import { onBeforeUnmount, onMounted, type Ref } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';

export interface DirtyGuardOptions {
  message?: string;
  confirm?: (message: string) => boolean;
}

export function useDirtyGuard(dirty: Ref<boolean>, options: DirtyGuardOptions = {}) {
  const message = options.message || '当前内容尚未保存，确认离开吗？';
  const confirmLeave = options.confirm || ((text: string) => window.confirm(text));
  const beforeUnload = (event: BeforeUnloadEvent) => {
    if (!dirty.value) return;
    event.preventDefault();
    event.returnValue = '';
  };
  const routeGuard = () => !dirty.value || confirmLeave(message);

  onMounted(() => window.addEventListener('beforeunload', beforeUnload));
  onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
  onBeforeRouteLeave(routeGuard);

  return { beforeUnload, routeGuard };
}
