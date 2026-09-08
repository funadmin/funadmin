import { computed, ref, shallowRef } from 'vue';
import type { FormDefinition, FormFieldDef, FormSchemaDocument, FormSchemaNode } from '@/api/form';
import { createField, controlMeta } from '../registry';

const HISTORY_LIMIT = 50;
const CONTAINER_TYPES = new Set(['group', 'grid', 'collapse', 'tabs', 'repeatable', 'subform']);

interface DesignerSnapshot {
  fields: FormFieldDef[];
  nodes: FormSchemaNode[];
  formKey: string;
  formTitle: string;
}

interface NodeLocation {
  node: FormSchemaNode;
  siblings: FormSchemaNode[];
  index: number;
  parent: FormSchemaNode | null;
}

const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;

const flattenNodes = (
  nodes: FormSchemaNode[],
  parentId: string | null = null,
  depth = 0
): Array<{ node: FormSchemaNode; parentId: string | null; depth: number }> => nodes.flatMap((node) => [
  { node, parentId, depth },
  ...flattenNodes(node.children ?? [], node.id, depth + 1)
]);

const findLocation = (
  nodes: FormSchemaNode[],
  nodeId: string,
  parent: FormSchemaNode | null = null
): NodeLocation | null => {
  for (let index = 0; index < nodes.length; index += 1) {
    const node = nodes[index];
    if (node.id === nodeId) return { node, siblings: nodes, index, parent };
    const nested = findLocation(node.children ?? [], nodeId, node);
    if (nested) return nested;
  }
  return null;
};

const fieldToNode = (field: FormFieldDef, nodeId: string): FormSchemaNode => ({
  id: nodeId,
  kind: controlMeta(field.type).kind === 'layout' ? 'layout' : 'field',
  type: field.type,
  field: controlMeta(field.type).kind === 'layout' ? null : field.field_name,
  title: field.label,
  defaultValue: field.default_value,
  props: clone(field.control_props ?? {}),
  children: [],
  validation: [],
  dataSource: clone(field.options_source ?? null),
  conditions: [],
  events: {},
  layout: { span: field.form_span, group: field.form_group }
});

/** 表单设计器状态：兼容字段投影，并以 FormSchema v2 AST 作为布局编辑模型。 */
export function useDesigner() {
  const form = ref<Partial<FormDefinition>>({
    form_key: '', name: '', table_name: '', connection: 'mysql', source_type: 'created',
    status: 1, remark: '', sort_order: 0, list_config: null, form_config: null,
    schema_version: 2, schema_document: null, schema_origin: 'designer'
  });
  const fields = ref<FormFieldDef[]>([]);
  const nodes = ref<FormSchemaNode[]>([]);
  const selectedKey = ref<string | null>(null);
  const selectedNodeId = ref<string | null>(null);
  const dirty = ref(false);
  const undoStack = shallowRef<string[]>([]);
  const redoStack = shallowRef<string[]>([]);
  let nodeSequence = 0;

  const flattenedNodes = computed(() => flattenNodes(nodes.value));
  const selected = computed(() => fields.value.find((field) => field.field_name === selectedKey.value) ?? null);
  const selectedNode = computed(() => flattenedNodes.value.find((entry) => entry.node.id === selectedNodeId.value)?.node ?? null);
  const canUndo = computed(() => undoStack.value.length > 0);
  const canRedo = computed(() => redoStack.value.length > 0);

  const snapshot = () => JSON.stringify({
    fields: fields.value,
    nodes: nodes.value,
    formKey: String(form.value.form_key ?? ''),
    formTitle: String(form.value.name ?? '')
  } satisfies DesignerSnapshot);
  const pushHistory = () => {
    undoStack.value = [...undoStack.value.slice(-HISTORY_LIMIT + 1), snapshot()];
    redoStack.value = [];
    dirty.value = true;
  };
  const restore = (raw: string) => {
    const state = JSON.parse(raw) as DesignerSnapshot;
    fields.value = state.fields;
    nodes.value = state.nodes;
    form.value.form_key = state.formKey;
    form.value.name = state.formTitle;
    if (selectedKey.value && !fields.value.some((field) => field.field_name === selectedKey.value)) selectedKey.value = null;
    if (selectedNodeId.value && !findLocation(nodes.value, selectedNodeId.value)) selectedNodeId.value = null;
  };
  const undo = () => {
    const previous = undoStack.value.at(-1);
    if (previous === undefined) return;
    undoStack.value = undoStack.value.slice(0, -1);
    redoStack.value = [...redoStack.value, snapshot()];
    restore(previous);
    dirty.value = true;
  };
  const redo = () => {
    const next = redoStack.value.at(-1);
    if (next === undefined) return;
    redoStack.value = redoStack.value.slice(0, -1);
    undoStack.value = [...undoStack.value, snapshot()];
    restore(next);
    dirty.value = true;
  };

  const usedNodeIds = () => new Set(flattenNodes(nodes.value).map((entry) => entry.node.id));
  const nextNodeId = (type: string) => {
    const used = usedNodeIds();
    let candidate = '';
    do {
      nodeSequence += 1;
      candidate = `node_${type}_${nodeSequence}`;
    } while (used.has(candidate));
    return candidate;
  };
  const uniqueFieldName = (base: string) => {
    let candidate = base;
    let suffix = 2;
    while (fields.value.some((field) => field.field_name === candidate)) {
      candidate = `${base}_${suffix}`;
      suffix += 1;
    }
    return candidate;
  };
  const findNode = (nodeId: string) => findLocation(nodes.value, nodeId)?.node ?? null;
  const selectNode = (nodeId: string | null) => {
    selectedNodeId.value = nodeId;
    const fieldName = nodeId ? findNode(nodeId)?.field : null;
    selectedKey.value = typeof fieldName === 'string' ? fieldName : null;
  };
  const targetChildren = (parentId: string | null): FormSchemaNode[] | null => {
    if (parentId === null) return nodes.value;
    const parent = findNode(parentId);
    return parent && CONTAINER_TYPES.has(parent.type) ? parent.children : null;
  };
  const addNode = (type: string, parentId: string | null = null, index?: number) => {
    const target = targetChildren(parentId);
    if (!target) throw new Error('目标节点不是容器');
    pushHistory();
    const field = createField(type, fields.value.length + 1);
    field.field_name = uniqueFieldName(field.field_name);
    const node = fieldToNode(field, nextNodeId(type));
    const at = Math.max(0, Math.min(index ?? target.length, target.length));
    target.splice(at, 0, node);
    if (node.kind === 'field') fields.value = [...fields.value, field];
    selectNode(node.id);
    return node;
  };
  const addField = (type: string) => {
    const node = addNode(type);
    return node.field ? fields.value.find((field) => field.field_name === node.field) : undefined;
  };
  const removeNode = (nodeId: string) => {
    const location = findLocation(nodes.value, nodeId);
    if (!location) return false;
    pushHistory();
    const removedIds = new Set(flattenNodes([location.node]).map((entry) => entry.node.field).filter(Boolean));
    location.siblings.splice(location.index, 1);
    fields.value = fields.value.filter((field) => !removedIds.has(field.field_name));
    if (selectedNodeId.value === nodeId) selectNode(null);
    return true;
  };
  const removeField = (fieldName: string) => {
    const node = flattenedNodes.value.find((entry) => entry.node.field === fieldName)?.node;
    if (node) return removeNode(node.id);
    pushHistory();
    fields.value = fields.value.filter((field) => field.field_name !== fieldName);
    if (selectedKey.value === fieldName) selectedKey.value = null;
    return true;
  };
  const moveNode = (nodeId: string, parentId: string | null, index: number) => {
    const source = findLocation(nodes.value, nodeId);
    const destination = targetChildren(parentId);
    if (!source || !destination || nodeId === parentId || flattenNodes(source.node.children).some((entry) => entry.node.id === parentId)) return false;
    pushHistory();
    const [moving] = source.siblings.splice(source.index, 1);
    const adjusted = source.siblings === destination && source.index < index ? index - 1 : index;
    destination.splice(Math.max(0, Math.min(adjusted, destination.length)), 0, moving);
    fields.value = fields.value.map((field, sortOrder) => ({ ...field, sort_order: sortOrder }));
    return true;
  };
  const moveField = (from: number, to: number) => {
    if (from === to || from < 0 || to < 0 || from >= fields.value.length || to >= fields.value.length) return;
    pushHistory();
    const next = [...fields.value];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    fields.value = next.map((field, index) => ({ ...field, sort_order: index }));
  };
  const cloneNodeTree = (source: FormSchemaNode): FormSchemaNode => {
    const copy = clone(source);
    copy.id = nextNodeId(copy.type);
    copy.children = copy.children.map(cloneNodeTree);
    return copy;
  };
  const duplicateNode = (nodeId: string) => {
    const source = findLocation(nodes.value, nodeId);
    if (!source) return null;
    pushHistory();
    const copy = cloneNodeTree(source.node);
    const fieldCopies = flattenNodes([copy]).filter((entry) => entry.node.field).map((entry) => {
      const original = fields.value.find((field) => field.field_name === entry.node.field);
      if (!original) return null;
      const field = clone(original);
      field.field_name = uniqueFieldName(`${original.field_name}_copy`);
      field.label = `${original.label}(副本)`;
      entry.node.field = field.field_name;
      entry.node.title = field.label;
      return field;
    }).filter((field): field is FormFieldDef => field !== null);
    source.siblings.splice(source.index + 1, 0, copy);
    const sourceFieldIndex = source.node.field
      ? fields.value.findIndex((field) => field.field_name === source.node.field)
      : fields.value.length - 1;
    const nextFields = [...fields.value];
    nextFields.splice(sourceFieldIndex + 1, 0, ...fieldCopies);
    fields.value = nextFields.map((field, index) => ({ ...field, sort_order: index }));
    selectNode(copy.id);
    return copy;
  };
  const duplicateField = (fieldName: string) => {
    const node = flattenedNodes.value.find((entry) => entry.node.field === fieldName)?.node;
    if (node) return duplicateNode(node.id);
    const source = fields.value.find((field) => field.field_name === fieldName);
    if (!source) return null;
    pushHistory();
    const copy = clone(source);
    copy.field_name = uniqueFieldName(`${source.field_name}_copy`);
    copy.label = `${source.label}(副本)`;
    const index = fields.value.indexOf(source);
    fields.value.splice(index + 1, 0, copy);
    selectedKey.value = copy.field_name;
    return null;
  };
  const updateField = (patch: Partial<FormFieldDef>) => {
    if (!selectedKey.value) return;
    pushHistory();
    fields.value = fields.value.map((field) => field.field_name === selectedKey.value ? { ...field, ...patch } : field);
  };
  const updateNode = (patch: Partial<FormSchemaNode>) => {
    if (!selectedNodeId.value) return;
    const node = findNode(selectedNodeId.value);
    if (!node) return;
    pushHistory();
    Object.assign(node, clone(patch));
  };
  const replaceFields = (next: Partial<FormFieldDef>[]) => {
    pushHistory();
    fields.value = next.map((row, index) => ({ ...createField(row.type ?? 'input', index + 1), ...row, sort_order: index })) as FormFieldDef[];
    nodes.value = fields.value.map((field) => fieldToNode(field, nextNodeId(field.type)));
    selectNode(null);
  };
  const replaceSchema = (schema: FormSchemaDocument) => {
    if (schema.schemaVersion !== 2 || !Array.isArray(schema.nodes)) return { ok: false, error: '仅支持 FormSchema v2 文档' };
    const ids = flattenNodes(schema.nodes).map((entry) => entry.node.id);
    if (ids.some((id) => !id) || new Set(ids).size !== ids.length) return { ok: false, error: 'nodeId 不能为空且必须唯一' };
    pushHistory();
    nodes.value = clone(schema.nodes);
    form.value.form_key = schema.key;
    form.value.name = schema.title;
    form.value.schema_version = 2;
    form.value.schema_document = clone(schema);
    form.value.schema_origin = 'designer';
    selectNode(null);
    return { ok: true, error: '' };
  };
  const schemaDocument = computed<FormSchemaDocument>(() => ({
    ...(form.value.schema_document ?? {}),
    schemaVersion: 2,
    key: String(form.value.form_key ?? ''),
    title: String(form.value.name ?? ''),
    nodes: clone(nodes.value)
  }));
  const load = (definition: FormDefinition) => {
    form.value = { ...definition };
    fields.value = (definition.fields ?? []).map((field, index) => ({ ...field, sort_order: field.sort_order ?? index }));
    nodes.value = definition.schema_document?.schemaVersion === 2
      ? clone(definition.schema_document.nodes)
      : fields.value.map((field, index) => fieldToNode(field, `node_${field.type}_${index + 1}`));
    nodeSequence = flattenNodes(nodes.value).length;
    undoStack.value = [];
    redoStack.value = [];
    dirty.value = false;
    selectedKey.value = null;
    selectedNodeId.value = null;
  };
  const markSaved = (definition: FormDefinition) => load(definition);

  return {
    form, fields, nodes, selectedKey, selectedNodeId, selected, selectedNode, flattenedNodes, schemaDocument,
    dirty, canUndo, canRedo, undo, redo, findNode, selectNode, addNode, addField, removeNode, removeField,
    duplicateNode, duplicateField, moveNode, moveField, updateField, updateNode, replaceFields, replaceSchema, load, markSaved
  };
}

export type DesignerStore = ReturnType<typeof useDesigner>;
