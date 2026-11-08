import type { FormListActionMetadata, FormListButton, FormListButtonLocation, FormListConfiguration, FormListCustomAction, FormListCustomActionLocation, FormListCustomActionParameterType } from '../../schema/types';

/**
 * 列表自定义动作（list.customActions）的换算规则，与后端 app\common\form\action\CustomActions 保持一致：
 * 注册键 generated.{entity}.{key}；权限码 generated:{entity}:action-{key 的 kebab 形式}；entity 为表单标识的 kebab 形式。
 */
export const CUSTOM_ACTION_KEY_PATTERN = /^[a-z][a-z0-9_]{0,40}$/;
export const CUSTOM_ACTION_PARAMETER_PATTERN = /^[a-z][a-z0-9_]{0,39}$/;
export const CUSTOM_ACTION_PARAMETER_TYPES = ['string', 'integer', 'number', 'boolean'] as const;
export const CUSTOM_ACTION_LOCATIONS: readonly FormListCustomActionLocation[] = ['row', 'toolbar'];
export const CUSTOM_ACTION_MAX_ACTIONS = 50;
export const CUSTOM_ACTION_MAX_PARAMETERS = 5;
export const CUSTOM_ACTION_MAX_REGISTRY_KEY = 64;
export const CUSTOM_ACTION_CAPABILITY_VERSION = '1';
/** 尚未生成的注册动作统一前缀：运行时目录缺少该前缀的键时提示「正式生成后可用」。 */
export const GENERATED_ACTION_PREFIX = 'generated.';

export const customActionEntity = (formKey?: string): string => (formKey ?? '').replace(/_/g, '-');
export const customActionRegistryKey = (entity: string, key: string): string => `${GENERATED_ACTION_PREFIX}${entity}.${key}`;
export const customActionPermission = (entity: string, key: string): string => `generated:${entity}:action-${key.replace(/_/g, '-')}`;
/** 生成的 PHP 方法名（PHP 方法名不区分大小写，判重时按小写比较）。 */
export const customActionMethod = (key: string): string => key.replace(/_+([a-z0-9])/g, (_, char: string) => char.toUpperCase());
export const customActionAppliesTo = (action: FormListCustomAction, location: FormListButtonLocation): boolean => (location === 'row' || location === 'toolbar') && action.locations.includes(location);

/** 与后端 CustomActions::descriptor 同形的设计期元数据（未生成前作为按钮校验与参数绑定的依据）。 */
export function customActionMetadata(entity: string, action: FormListCustomAction): FormListActionMetadata {
  return {
    permission: customActionPermission(entity, action.key),
    capabilityVersion: CUSTOM_ACTION_CAPABILITY_VERSION,
    parameters: action.parameters.map(parameter => parameter.name),
    parameterTypes: Object.fromEntries(action.parameters.map(parameter => [parameter.name, parameter.type])),
    locations: [...action.locations],
    targets: [...new Set(action.locations.map(location => location === 'row' ? 'record' : 'selection'))],
    batch: action.batch,
    effect: action.effect,
    resultContract: 'json',
    requiresConfirmation: action.requiresConfirmation
  };
}

/** 由名称建议标识：英文名称转为 snake_case；无法转写（如中文）时给出不重复的 action_n。 */
export function suggestCustomActionKey(label: string, taken: readonly string[]): string {
  const base = label.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^[^a-z]+/, '').replace(/_+$/, '').slice(0, 41).replace(/_+$/, '');
  if (base && !taken.includes(base)) return base;
  const stem = base ? base.slice(0, 36) : 'action';
  let index = 1;
  while (taken.includes(`${stem}_${index}`)) index++;
  return `${stem}_${index}`;
}

/** 绑定到某注册键的按钮数量与移除后的按钮配置（只改动显式配置了按钮的位置）。 */
export function withoutBoundButtons(buttons: FormListConfiguration['buttons'], registryKey: string, only?: readonly FormListButtonLocation[]) {
  const bound = (button: FormListButton) => button.action.type === 'registered' && button.action.key === registryKey;
  const patch: Partial<Record<FormListButtonLocation, FormListButton[]>> = {};
  let count = 0;
  for (const [location, list] of Object.entries(buttons ?? {}) as Array<[FormListButtonLocation, FormListButton[] | undefined]>) {
    if (!list || (only && !only.includes(location))) continue;
    const removed = list.filter(bound).length;
    if (!removed) continue;
    count += removed;
    patch[location] = list.filter(button => !bound(button));
  }
  return { count, patch: count ? patch : undefined };
}

const literalDefault = (type: FormListCustomActionParameterType): string | number | boolean => type === 'boolean' ? false : type === 'string' ? '' : 0;
const literalMatches = (type: FormListCustomActionParameterType, value: unknown): boolean =>
  type === 'boolean' ? typeof value === 'boolean' : type === 'string' ? typeof value === 'string' : typeof value === 'number' && (type === 'number' || Number.isInteger(value));

/**
 * 编辑自定义动作的参数后同步绑定它的按钮（与后端 FORM_ACTION_PARAMETER_MISSING 校验对应）：
 * 新增参数按类型绑定常量默认值（'' / 0 / false），删除的参数移除绑定；类型改变且常量值不再匹配时重置为新类型默认值。
 * added 为按默认值绑定的参数名（用于提示），patch 只包含有改动的位置。
 */
export function syncBoundButtonParameters(buttons: FormListConfiguration['buttons'], registryKey: string, previous: FormListCustomAction, next: FormListCustomAction) {
  const types = Object.fromEntries(next.parameters.map(parameter => [parameter.name, parameter.type]));
  const previousTypes = Object.fromEntries(previous.parameters.map(parameter => [parameter.name, parameter.type]));
  const patch: Partial<Record<FormListButtonLocation, FormListButton[]>> = {};
  const added = new Set<string>();
  let count = 0;
  for (const [location, list] of Object.entries(buttons ?? {}) as Array<[FormListButtonLocation, FormListButton[] | undefined]>) {
    if (!list) continue;
    let changed = false;
    const updated = list.map(button => {
      if (button.action.type !== 'registered' || button.action.key !== registryKey) return button;
      const current = button.params ?? {};
      const params: NonNullable<FormListButton['params']> = {};
      let dirty = Object.keys(current).some(name => !(name in types));
      for (const parameter of next.parameters) {
        const binding = current[parameter.name];
        const stale = binding?.source === 'literal' && previousTypes[parameter.name] !== parameter.type && !literalMatches(parameter.type, binding.value);
        if (binding && !stale) { params[parameter.name] = binding; continue; }
        params[parameter.name] = { source: 'literal', value: literalDefault(parameter.type) };
        added.add(parameter.name);
        dirty = true;
      }
      if (!dirty) return button;
      changed = true;
      count++;
      const { params: _previous, ...rest } = button;
      return next.parameters.length ? { ...rest, params } : rest;
    });
    if (changed) patch[location] = updated;
  }
  return { count, added: [...added], patch: count ? patch : undefined };
}
