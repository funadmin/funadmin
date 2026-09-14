import type { FormListButton, FormListButtonLocation, FormListConfiguration } from './types';

const owns = (value: object, key: PropertyKey) => Object.prototype.hasOwnProperty.call(value, key);
const forbidden = new Set(['__proto__', 'constructor', 'prototype']);

/** 缺省才继承宿主默认值；损坏或空集合绝不能重新启用默认写按钮。 */
export function resolveListButtons(
  list: FormListConfiguration | undefined,
  location: FormListButtonLocation,
  defaults: readonly FormListButton[]
): FormListButton[] {
  const collections = list?.buttons;
  if (list && owns(list, 'buttons') && (!collections || typeof collections !== 'object' || Array.isArray(collections))) {
    throw new Error('FORM_LIST_BUTTON_INVALID');
  }
  const source = collections && owns(collections, location) ? collections[location] : defaults;
  if (!Array.isArray(source) || source.length > 50) throw new Error('FORM_LIST_BUTTON_INVALID');
  const ids = new Set<string>();
  for (const button of source) {
    if (!button || typeof button.id !== 'string' || !/^[a-z][a-z0-9_.-]{0,63}$/.test(button.id)
      || button.id.split('.').some((part) => forbidden.has(part)) || ids.has(button.id)) throw new Error('FORM_LIST_BUTTON_INVALID');
    ids.add(button.id);
  }
  const result = JSON.parse(JSON.stringify(source)) as FormListButton[];
  return result.sort((left, right) => (left.order ?? 0) - (right.order ?? 0));
}

/** 映射声明使用数据库字段名；禁止歧义及原型字段。 */
export function buildListFieldMap(fields: readonly string[], mode: 'snake' | 'camel'): Record<string, string> {
  const mapping: Record<string, string> = {};
  const aliases = new Set<string>();
  for (const field of fields) {
    if (!/^[a-z][a-z0-9_]{0,60}$/.test(field) || forbidden.has(field) || owns(mapping, field)) {
      throw new Error('FORM_LIST_FIELD_INVALID');
    }
    const alias = mode === 'camel' ? field.replace(/_+([a-z0-9])/g, (_, character: string) => character.toUpperCase()) : field;
    if (aliases.has(alias) || forbidden.has(alias)) throw new Error('FORM_LIST_FIELD_COLLISION');
    aliases.add(alias);
    mapping[field] = alias;
  }
  return mapping;
}
