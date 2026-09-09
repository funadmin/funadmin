import { computed, ref } from 'vue';
import type { FormComponentCatalog, FormComponentCatalogItem } from '@/api/form';
import { setPluginControls, type ControlGroup, type ControlKind, type ControlMeta } from '../registry';

export type PluginCatalogDiagnosticCode = 'missing-plugin' | 'namespace-mismatch' | 'version-mismatch' | 'load-failed';

export interface PluginCatalogDiagnostic {
  code: PluginCatalogDiagnosticCode;
  message: string;
  type?: string;
}

export interface PluginCatalogRegistration {
  registered: string[];
  diagnostics: PluginCatalogDiagnostic[];
}

const DEFAULT_GROUP: ControlGroup = '业务控件';
const VALID_GROUPS = new Set<ControlGroup>([
  '基础控件', '选择控件', '日期时间', '上传控件', '业务控件', '布局控件'
]);

const defaultColumnType = (valueType: string): string => ({
  string: 'varchar(255)',
  number: 'decimal(10,2)',
  integer: 'int',
  boolean: 'tinyint(1)',
  array: 'json',
  object: 'json'
}[valueType] ?? 'json');

export const catalogItemToControlMeta = (item: FormComponentCatalogItem): ControlMeta => ({
  type: item.type,
  label: item.label?.trim() || item.type,
  kind: (item.kind ?? 'field') as ControlKind,
  group: VALID_GROUPS.has(item.group as ControlGroup) ? item.group as ControlGroup : DEFAULT_GROUP,
  defaultColumnType: item.defaultColumnType?.trim() || defaultColumnType(item.valueType),
  valueType: item.valueType,
  defaultOptions: null,
  defaultProps: item.defaultProps ?? {},
  propertySchema: item.propertySchema ?? {}
});

export interface PluginPropertyControl {
  name: string;
  label: string;
  type: string;
  minimum?: number;
  maximum?: number;
  enum?: unknown[];
}

export const propertyControls = (meta: ControlMeta): PluginPropertyControl[] => {
  const properties = meta.propertySchema?.properties;
  if (!properties || typeof properties !== 'object' || Array.isArray(properties)) return [];
  return Object.entries(properties).map(([name, raw]) => {
    const schema = raw && typeof raw === 'object' && !Array.isArray(raw) ? raw as Record<string, unknown> : {};
    return {
      name,
      label: typeof schema.title === 'string' ? schema.title : name,
      type: typeof schema.type === 'string' ? schema.type : 'string',
      ...(typeof schema.minimum === 'number' ? { minimum: schema.minimum } : {}),
      ...(typeof schema.maximum === 'number' ? { maximum: schema.maximum } : {}),
      ...(Array.isArray(schema.enum) ? { enum: schema.enum } : {})
    };
  });
};

export const createPluginCatalog = () => {
  const controls = ref<ControlMeta[]>([]);
  const diagnostics = ref<PluginCatalogDiagnostic[]>([]);
  const loaded = ref(false);

  const accept = (catalog: FormComponentCatalog, registration: PluginCatalogRegistration): void => {
    loaded.value = true;
    diagnostics.value = [...registration.diagnostics];
    if (catalog.schemaVersion !== 2) {
      controls.value = [];
      diagnostics.value.unshift({
        code: 'version-mismatch',
        message: `组件目录版本不匹配：期望 2，收到 ${String(catalog.schemaVersion)}`
      });
      return;
    }
    const registered = new Set(registration.registered);
    controls.value = catalog.components
      .filter((item) => item.namespace !== 'core' && registered.has(item.type))
      .map(catalogItemToControlMeta);
    setPluginControls(controls.value);
  };

  const fail = (error: unknown): void => {
    loaded.value = true;
    controls.value = [];
    setPluginControls([]);
    diagnostics.value = [{
      code: 'load-failed',
      message: error instanceof Error ? error.message : String(error)
    }];
  };

  const diagnoseFields = (fields: Array<{ type: string }>): PluginCatalogDiagnostic[] => {
    const known = new Set(controls.value.map((control) => control.type));
    const missing = [...new Set(fields.map((field) => field.type)
      .filter((type) => type.includes(':') && !known.has(type)))];
    return missing.map((type) => ({
      code: 'missing-plugin',
      type,
      message: `插件组件缺失或未通过白名单加载：${type}`
    }));
  };

  const fieldDiagnostics = (fields: Array<{ type: string }>) => [
    ...diagnostics.value,
    ...diagnoseFields(fields)
  ];
  const canPublish = (fields: Array<{ type: string }>): boolean =>
    loaded.value && fieldDiagnostics(fields).length === 0;

  return {
    controls: computed(() => controls.value),
    diagnostics: computed(() => diagnostics.value),
    loaded: computed(() => loaded.value),
    accept,
    fail,
    diagnoseFields,
    fieldDiagnostics,
    canPublish
  };
};

export type PluginCatalog = ReturnType<typeof createPluginCatalog>;
export const pluginCatalog = createPluginCatalog();