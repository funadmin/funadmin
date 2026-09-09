import type { Component } from 'vue';
import { formDesignerApi, type FormComponentCatalog, type FormComponentCatalogItem } from '@/api/form';
import { componentRegistry, type FormComponentRegistry, type FormValueCodec } from './componentRegistry';
import type { FormValueType } from './types';
import { pluginCatalog, type PluginCatalogDiagnostic, type PluginCatalogRegistration } from '../designer/pluginCatalog';

type ComponentModule = { default: Component };
type ComponentLoader = () => Promise<ComponentModule>;

const sourceModules = import.meta.glob<ComponentModule>('../../../modules/**/*.{vue,tsx}');
const identityCodec: FormValueCodec = {
  encode: (value: unknown) => value,
  decode: (value: unknown) => value
};

const propertyNames = (definition: FormComponentCatalogItem): string[] =>
  Object.keys(definition.propertySchema?.properties ?? {});

export const registerPluginFormComponents = (
  catalog: FormComponentCatalog,
  registry: FormComponentRegistry = componentRegistry,
  modules: Record<string, ComponentLoader> = sourceModules
): PluginCatalogRegistration => {
  const registered: string[] = [];
  const diagnostics: PluginCatalogDiagnostic[] = [];
  for (const definition of catalog.components) {
    if (definition.namespace === 'core') continue;
    if (!definition.type.startsWith(`${definition.namespace}:`)) {
      diagnostics.push({ code: 'namespace-mismatch', type: definition.type, message: `插件组件命名空间不一致：${definition.type}` });
      continue;
    }
    const key = `../../../modules/${definition.namespace}/${definition.component}.vue`;
    const fallbackKey = `../../../modules/${definition.namespace}/${definition.component}.tsx`;
    const loader = modules[key] ?? modules[fallbackKey];
    if (!loader) {
      diagnostics.push({ code: 'missing-plugin', type: definition.type, message: `插件表单组件未包含在当前构建中：${definition.type}` });
      continue;
    }
    registry.register({
      type: definition.type,
      namespace: definition.namespace,
      kind: definition.kind ?? 'field',
      componentKey: definition.component,
      valueType: normalizeValueType(definition.valueType),
      defaultValue: definition.defaultValue,
      defaultProps: definition.defaultProps ?? {},
      propertySchema: definition.propertySchema ?? {},
      codec: identityCodec,
      allowedProps: propertyNames(definition),
      allowedAttrs: definition.allowedAttrs ?? [],
      allowedEvents: definition.allowedEvents ?? [],
      renderer: async () => (await loader()).default
    });
    registered.push(definition.type);
  }
  return { registered, diagnostics };
};

let catalogPromise: Promise<void> | undefined;

/** 拉取服务端可信 catalog；保留成功白名单项和逐项诊断供设计器使用。 */
export const loadPluginFormComponents = (): Promise<void> => {
  catalogPromise ??= formDesignerApi.catalog()
    .then((catalog) => pluginCatalog.accept(catalog, registerPluginFormComponents(catalog)))
    .catch((error: unknown) => pluginCatalog.fail(error));
  return catalogPromise;
};

const normalizeValueType = (valueType: string): FormValueType => {
  if (valueType === 'integer') return 'number';
  if (['string', 'number', 'boolean', 'array', 'object'].includes(valueType)) return valueType as FormValueType;
  return 'object';
};
