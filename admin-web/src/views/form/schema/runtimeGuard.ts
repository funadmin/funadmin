import { componentRegistry, type FormComponentRegistry } from './componentRegistry';
import { flattenSchemaNodes, type FormSchemaDocument } from './types';

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
  if (unknown.length) throw new Error(`未注册的表单组件：${unknown.join('、')}`);
};

export const assertRuntimeComponents = (
  schema: FormSchemaDocument,
  registry: FormComponentRegistry = componentRegistry
): void => {
  const unknown = findUnknownComponents(schema, registry);
  if (!unknown.length) return;
  throw new Error(`未注册的表单组件：${unknown.map((item) => item.type).join('、')}`);
};
