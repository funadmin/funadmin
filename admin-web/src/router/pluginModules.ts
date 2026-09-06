import type { Component } from 'vue';
import type { RouteRecordRaw, Router } from 'vue-router';
import type { EnabledPluginModule, PluginRouteDto } from '@/api/system/plugin';

export type { EnabledPluginModule } from '@/api/system/plugin';

export interface PluginRegistration {
  components: Record<string, Component>;
}

export interface PluginEsmModule {
  register: (context: { name: string; version: string }) => PluginRegistration;
}

export type PluginModuleErrorStage = 'import' | 'register' | 'component' | 'route';

interface SyncOptions {
  origin?: string;
  base?: string;
  importer?: (url: string) => Promise<PluginEsmModule>;
}

const mounted = new Map<string, { signature: string; routeNames: string[] }>();
const dynamicImporter = async (url: string): Promise<PluginEsmModule> => import(/* @vite-ignore */ url);
const PluginModuleError = () => import('@/views/system/plugin/PluginModuleError.vue');

export function isAllowedPluginModuleUrl(value: string, origin = window.location.origin, base = '/'): boolean {
  try {
    if (value.includes('..') || value.includes('\\') || value.includes('\0')) return false;
    const url = new URL(value, origin);
    const normalizedBase = `/${base.replace(/^\/+|\/+$/g, '')}`.replace(/^\/$/, '');
    const prefixes = ['/plugin-assets/', `${normalizedBase}/plugin-assets/`];
    return url.origin === origin && prefixes.some((prefix) => {
      if (!url.pathname.startsWith(prefix)) return false;
      const relative = url.pathname.slice(prefix.length);
      return /^[a-z][a-z0-9]*\/[A-Za-z0-9._/-]+\.m?js$/.test(relative);
    });
  } catch {
    return false;
  }
}

export async function syncPluginModules(
  router: Router,
  descriptors: EnabledPluginModule[],
  options: SyncOptions = {}
): Promise<{ loaded: string[]; errors: Array<{ name: string; stage: PluginModuleErrorStage; message: string }> }> {
  const origin = options.origin ?? window.location.origin;
  const base = options.base ?? import.meta.env.BASE_URL;
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
  const errors: Array<{ name: string; stage: PluginModuleErrorStage; message: string }> = [];
  for (const descriptor of descriptors) {
    const signature = `${descriptor.version}:${descriptor.hash}`;
    const previous = mounted.get(descriptor.name);
    if (previous?.signature === signature && previous.routeNames.every((routeName) => router.hasRoute(routeName))) {
      loaded.push(descriptor.name);
      continue;
    }
    if (!isAllowedPluginModuleUrl(descriptor.entryUrl, origin, base)) {
      mountErrorRoute(router, descriptor, 'import', '插件模块 URL 不受信任');
      errors.push({ name: descriptor.name, stage: 'import', message: '插件模块 URL 不受信任' });
      continue;
    }
    let stage: PluginModuleErrorStage = 'import';
    try {
      previous?.routeNames.forEach((routeName) => {
        if (router.hasRoute(routeName)) router.removeRoute(routeName);
      });
      const entryUrl = import.meta.env.DEV && descriptor.entryUrl.startsWith('/plugin-assets/')
        ? `${base.replace(/\/$/, '')}${descriptor.entryUrl}`
        : descriptor.entryUrl;
      const module = await importer(new URL(entryUrl, origin).href);
      stage = 'register';
      if (!module || typeof module.register !== 'function') throw new Error('插件模块缺少 register 导出');
      const registration = module.register({ name: descriptor.name, version: descriptor.version });
      if (!registration || typeof registration.components !== 'object') throw new Error('插件 register 返回契约无效');
      stage = 'component';
      descriptor.routes.forEach((route) => {
        if (!registration.components[route.component]) throw new Error(`插件组件未注册：${route.component}`);
      });
      stage = 'route';
      const routes = descriptor.routes.map((route) => routeFromDto(route, registration.components, descriptor.name));
      routes.forEach((route) => router.addRoute(route));
      mounted.set(descriptor.name, { signature, routeNames: routes.map((route) => String(route.name)) });
      loaded.push(descriptor.name);
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      mountErrorRoute(router, descriptor, stage, message);
      errors.push({ name: descriptor.name, stage, message });
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

function mountErrorRoute(router: Router, descriptor: EnabledPluginModule, stage: PluginModuleErrorStage, message: string): void {
  const name = `Plugin_${descriptor.name}_Error`;
  if (router.hasRoute(name)) router.removeRoute(name);
  router.addRoute({
    path: `/plugin/${descriptor.name}/error`,
    name,
    component: PluginModuleError,
    props: { plugin: descriptor.name, stage, message },
    meta: { title: `${descriptor.name} 插件错误` }
  });
  mounted.set(descriptor.name, { signature: `${descriptor.version}:${descriptor.hash}:error`, routeNames: [name] });
}

function routeFromDto(dto: PluginRouteDto, components: Record<string, Component>, pluginName: string): RouteRecordRaw {
  if (!dto.path.startsWith(`/plugin/${pluginName}/`) || !dto.name.startsWith(`Plugin_${pluginName}`)) {
    throw new Error('插件路由 DTO 越界');
  }
  const component = components[dto.component];
  if (!component) throw new Error(`插件组件未注册：${dto.component}`);
  return { path: dto.path, name: dto.name, component, meta: dto.meta } as RouteRecordRaw;
}
