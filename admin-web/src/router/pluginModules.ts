import type { Component } from 'vue';
import type { RouteRecordRaw, Router } from 'vue-router';
import type { EnabledPluginModule, PluginRouteDto } from '@/api/plugin';

export type { EnabledPluginModule } from '@/api/plugin';

export interface PluginRegistration {
  components: Record<string, Component>;
}

export interface PluginEsmModule {
  register: (context: { name: string; version: string }) => PluginRegistration;
}

interface SyncOptions {
  origin?: string;
  importer?: (url: string) => Promise<PluginEsmModule>;
}

const mounted = new Map<string, { signature: string; routeNames: string[] }>();

const dynamicImporter = async (url: string): Promise<PluginEsmModule> => import(/* @vite-ignore */ url);

export function isAllowedPluginModuleUrl(value: string, origin = window.location.origin): boolean {
  try {
    if (value.includes('..') || value.includes('\\') || value.includes('\0')) return false;
    const url = new URL(value, origin);
    return url.origin === origin && url.pathname.startsWith('/plugin-assets/') && /^\/plugin-assets\/[a-z][a-z0-9]*\/[A-Za-z0-9._/-]+\.m?js$/.test(url.pathname);
  } catch {
    return false;
  }
}

export async function syncPluginModules(
  router: Router,
  descriptors: EnabledPluginModule[],
  options: SyncOptions = {}
): Promise<{ loaded: string[]; errors: Array<{ name: string; message: string }> }> {
  const origin = options.origin ?? window.location.origin;
  const importer = options.importer ?? dynamicImporter;
  const activeNames = new Set(descriptors.map((item) => item.name));

  mounted.forEach((state, name) => {
    if (activeNames.has(name)) return;
    state.routeNames.forEach((routeName) => {
      if (router.hasRoute(routeName)) router.removeRoute(routeName);
    });
    mounted.delete(name);
  });

  const loaded: string[] = [];
  const errors: Array<{ name: string; message: string }> = [];
  for (const descriptor of descriptors) {
    const signature = `${descriptor.version}:${descriptor.hash}`;
    const previous = mounted.get(descriptor.name);
    if (previous?.signature === signature) {
      loaded.push(descriptor.name);
      continue;
    }
    if (!isAllowedPluginModuleUrl(descriptor.entryUrl, origin)) {
      errors.push({ name: descriptor.name, message: '插件模块 URL 不受信任' });
      continue;
    }
    try {
      previous?.routeNames.forEach((routeName) => {
        if (router.hasRoute(routeName)) router.removeRoute(routeName);
      });
      const module = await importer(new URL(descriptor.entryUrl, origin).href);
      if (!module || typeof module.register !== 'function') throw new Error('插件模块缺少 register 导出');
      const registration = module.register({ name: descriptor.name, version: descriptor.version });
      if (!registration || typeof registration.components !== 'object') throw new Error('插件 register 返回契约无效');
      const routes = descriptor.routes.map((route) => routeFromDto(route, registration.components, descriptor.name));
      routes.forEach((route) => router.addRoute(route));
      mounted.set(descriptor.name, { signature, routeNames: routes.map((route) => String(route.name)) });
      loaded.push(descriptor.name);
    } catch (error) {
      errors.push({ name: descriptor.name, message: error instanceof Error ? error.message : String(error) });
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

function routeFromDto(dto: PluginRouteDto, components: Record<string, Component>, pluginName: string): RouteRecordRaw {
  if (!dto.path.startsWith(`/plugin/${pluginName}/`) || !dto.name.startsWith(`Plugin_${pluginName}`)) {
    throw new Error('插件路由 DTO 越界');
  }
  const component = components[dto.component];
  if (!component) throw new Error(`插件组件未注册：${dto.component}`);
  return { path: dto.path, name: dto.name, component, meta: dto.meta } as RouteRecordRaw;
}
