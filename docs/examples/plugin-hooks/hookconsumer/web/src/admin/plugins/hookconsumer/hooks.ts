import type { HookContext } from '@/shared/plugins/hooks';

// The bound dispatcher inherits this invocation's cancellation and depth.
export function relay(value: unknown, context: HookContext): Promise<unknown> {
  return context.hooks.filter('plugin.hookdemo.decorate', value);
}
