import type { HookContext } from '@/shared/plugins/hooks';

export function dashboardLoaded(_payload: unknown, context: HookContext): void {
  const onResize = () => {
    // Put page-scoped behavior here; do not retain privileged page data.
  };
  window.addEventListener('resize', onResize);
  context.onDispose(() => window.removeEventListener('resize', onResize));
}

export function decorate(value: unknown): string {
  return String(value) + ' · hookdemo';
}
