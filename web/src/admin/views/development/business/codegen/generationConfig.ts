import type { FormDefinition, FormPublishConfig } from '@/api/form';

/** 与后端 FormCrudDefinitionFactory::config 的默认值一致。 */
export const DEFAULT_PUBLISH_CONFIG: FormPublishConfig = {
  module: 'generated', apiPrefix: '', routePath: '', menuEnabled: true, parentId: null,
  parentSourceName: '', menuName: '', icon: 'i-ep-document', sortOrder: 999,
  softDeletes: true, batchDelete: true, import: true, export: true, formMode: 'dialog',
  dataScopeEnabled: false, dataScopeField: '', memberApiEnabled: false, memberApiOwnerField: 'member_id'
};

export const FORM_WIDTH_MIN = 360;
export const FORM_WIDTH_MAX = 1600;

export const entityOf = (formKey: string): string => formKey.replace(/_/g, '-');

// PHP: str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)))
const studly = (value: string): string => value.split(/[-_ ]+/).filter(Boolean).map((part) => part[0].toUpperCase() + part.slice(1)).join('');

// PermissionCode::kebab
const kebab = (value: string): string => value
  .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
  .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
  .replace(/_/g, '-')
  .toLowerCase();

export const classNameOf = (formKey: string): string => studly(entityOf(formKey));

/**
 * 生成控制器各方法的权限码（PermissionCode::derive：{模块}:{资源}:{操作}），只用于只读展示。
 * 方法集合按 ProductionTemplateContext 的功能开关推导；status、options 等随字段出现的方法以生成结果为准。
 */
export const derivedPermissionCodes = (formKey: string, config: FormPublishConfig, pluginCode: string | null | undefined): string[] => {
  const resource = kebab(classNameOf(formKey));
  const module = pluginCode || 'generated';
  const actions = ['index', 'detail', 'create', 'update', 'remove'];
  if (config.softDeletes) actions.push('restore', 'destroy');
  if (config.batchDelete) actions.push('recycle');
  if (config.softDeletes && config.batchDelete) actions.push('restore-many', 'destroy-many');
  if (config.import) actions.push('import');
  if (config.export) actions.push('export');
  return actions.map((action) => `${module}:${resource}:${action}`);
};

/** 以已落库的发布设置填充编辑表单；空的接口前缀、路由、菜单名按后端规则派生。 */
export const publishConfigOf = (form: Pick<FormDefinition, 'form_key' | 'name' | 'publish_config'>): FormPublishConfig => {
  const entity = entityOf(String(form.form_key ?? ''));
  const stored = form.publish_config ?? {};
  return {
    ...DEFAULT_PUBLISH_CONFIG,
    ...stored,
    apiPrefix: stored.apiPrefix || `/generated/${entity}`,
    routePath: stored.routePath || `/generated/${entity}`,
    menuName: stored.menuName || String(form.name || entity)
  };
};

/** 只提交改动过的键（后端按键合并）；被清空的可选值提交 null。 */
export const changedPublishConfig = (base: FormPublishConfig, current: FormPublishConfig): Partial<Record<keyof FormPublishConfig, unknown>> => {
  const keys = new Set([...Object.keys(base), ...Object.keys(current)] as Array<keyof FormPublishConfig>);
  const changes: Partial<Record<keyof FormPublishConfig, unknown>> = {};
  for (const key of keys) {
    if (JSON.stringify(base[key] ?? null) === JSON.stringify(current[key] ?? null)) continue;
    changes[key] = current[key] ?? null;
  }
  return changes;
};

/** 与设计器一致：上级菜单只列出有来源的菜单，并排除当前模块自身的菜单。 */
export const pruneParentMenus = (items: API.MenuItem[], formKey: string): API.MenuItem[] => {
  const current = entityOf(formKey);
  return items
    .filter((menu) => menu.sourceType && menu.sourceName && menu.sourceName !== current)
    .map((menu) => ({ ...menu, children: menu.children ? pruneParentMenus(menu.children, formKey) : undefined }));
};
