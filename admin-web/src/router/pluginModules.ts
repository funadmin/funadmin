import type { Component } from 'vue';
import type { RouteRecordRaw, Router } from 'vue-router';
import type { EnabledPluginModule, PluginRouteDto } from '@/api/system/plugin';

export type PluginModuleErrorStage = 'component' | 'route';

interface SyncOptions {
  modules?: Record<string, Component>;
}

const mounted = new Map<string, { signature: string; routeNames: string[] }>();
const sourceModules = import.meta.glob<Component>('../modules/**/*.{vue,tsx}');
const PluginModuleError = () => import('@/views/system/plugin/PluginModuleError.vue');

export async function syncPluginModules(
  router: Router,
  descriptors: EnabledPluginModule[],
  options: SyncOptions = {}
): Promise<{ loaded: string[]; errors: Array<{ code: string; stage: PluginModuleErrorStage; message: string }> }> {
  const modules = options.modules ?? sourceModules;
  const activeCodes = new Set(descriptors.map((item) => item.code));

  mounted.forEach((state, code) => {
    if (activeCodes.has(code)) return;
    state.routeNames.forEach((routeName) => {
      if (router.hasRoute(routeName)) router.removeRoute(routeName);
    });
    mounted.delete(code);
  });

  const loaded: string[] = [];
  const errors: Array<{ code: string; stage: PluginModuleErrorStage; message: string }> = [];
  for (const descriptor of descriptors) {
    const signature = `${descriptor.version}:${JSON.stringify(descriptor.components)}:${JSON.stringify(descriptor.routes)}`;
    const previous = mounted.get(descriptor.code);
    if (previous?.signature === signature && previous.routeNames.every((routeName) => router.hasRoute(routeName))) {
      loaded.push(descriptor.code);
      continue;
    }

    let stage: PluginModuleErrorStage = 'component';
    try {
      previous?.routeNames.forEach((routeName) => {
        if (router.hasRoute(routeName)) router.removeRoute(routeName);
      });
      const components = resolveComponents(descriptor, modules);
      stage = 'route';
      const routes = descriptor.routes.map((route) => routeFromDto(route, components, descriptor.code));
      routes.forEach((route) => router.addRoute(route));
      mounted.set(descriptor.code, { signature, routeNames: routes.map((route) => String(route.name)) });
      loaded.push(descriptor.code);
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      mountErrorRoute(router, descriptor, stage, message);
      errors.push({ code: descriptor.code, stage, message });
    }
  }
  return { loaded, errors };
}

export function clearPluginModules(router: Router): void {
  mounted.forEach((state) => state.routeNames.forEach((name) => {
    if (router.hasRoute(name)) router.removeRoute(name);
  }));
  mounted.clear();
}

const resolveComponents = (
  descriptor: EnabledPluginModule,
  modules: Record<string, Component>
): Record<string, Component> => Object.fromEntries(
  Object.entries(descriptor.components).map(([name, relativePath]) => {
    const key = `../modules/${descriptor.code}/${relativePath}`;
    const component = modules[key];
    if (!component) throw new Error(`插件组件未包含在当前构建中：${name}`);
    return [name, component];
  })
);

function mountErrorRoute(router: Router, descriptor: EnabledPluginModule, stage: PluginModuleErrorStage, message: string): void {
  const name = `Plugin_${descriptor.code}_Error`;
  if (router.hasRoute(name)) router.removeRoute(name);
  router.addRoute({
    path: `/plugin/${descriptor.code}/error`,
    name,
    component: PluginModuleError,
    props: { plugin: descriptor.code, stage, message },
    meta: { title: `${descriptor.code} 插件错误` }
  });
  mounted.set(descriptor.code, { signature: `${descriptor.version}:error`, routeNames: [name] });
}

function routeFromDto(dto: PluginRouteDto, components: Record<string, Component>, pluginCode: string): RouteRecordRaw {
  if (!dto.path.startsWith(`/plugin/${pluginCode}/`) || !dto.name.startsWith(`Plugin_${pluginCode}`)) {
    throw new Error('插件路由 DTO 越界');
  }
  const component = components[dto.component];
  if (!component) throw new Error(`插件组件未注册：${dto.component}`);
  return { path: dto.path, name: dto.name, component, meta: dto.meta } as RouteRecordRaw;
}
