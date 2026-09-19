import { i18n } from '@/locales';
import { componentRegistry, type FormComponentRegistry } from './componentRegistry';
import { flattenSchemaNodes, type FormSchemaDocument } from './types';

const tNamed = (key: string, named: Record<string, unknown>, fallback: string): string => i18n.global.t(key, named, fallback);

export interface UnknownFormComponent {
  id: string;
  type: string;
  path: string;
}

export const findUnknownComponents = (
  schema: FormSchemaDocument,
  registry: FormComponentRegistry = componentRegistry
): UnknownFormComponent[] => flattenSchemaNodes(schema.nodes)
  .filter(({ node }) => !registry.resolve(node.type))
  .map(({ node, path }) => ({ id: node.id, type: node.type, path: `${path}/type` }));

export const assertFieldComponents = (
  fields: Array<{ type: string }>,
  registry: FormComponentRegistry = componentRegistry
): void => {
  const unknown = [...new Set(fields.filter((field) => !registry.resolve(field.type)).map((field) => field.type))];
  if (unknown.length) throw new Error(tNamed('formData.unregisteredComponent', { type: unknown.join('、') }, '未注册的表单组件：{type}'));
};

export const assertRuntimeComponents = (
  schema: FormSchemaDocument,
  registry: FormComponentRegistry = componentRegistry
): void => {
  const unknown = findUnknownComponents(schema, registry);
  if (!unknown.length) return;
  throw new Error(tNamed('formData.unregisteredComponent', { type: unknown.map((item) => item.type).join('、') }, '未注册的表单组件：{type}'));
};
