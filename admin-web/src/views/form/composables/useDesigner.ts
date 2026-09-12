import { computed, ref, shallowRef } from 'vue';
import type { FormDefinition, FormFieldDef, FormSchemaDocument, FormSchemaNode } from '@/api/form';
import { createField, controlMeta } from '../registry';

const HISTORY_LIMIT = 50;
const CONTAINER_TYPES = new Set(['group', 'grid', 'collapse', 'tabs', 'repeatable', 'subform']);

interface DesignerSnapshot {
  fields: FormFieldDef[];
  nodes: FormSchemaNode[];
  form: Partial<FormDefinition>;
}

interface NodeLocation {
  node: FormSchemaNode;
  siblings: FormSchemaNode[];
  index: number;
  parent: FormSchemaNode | null;
}

export type DesignerKeyboardMove = 'up' | 'down' | 'indent' | 'outdent';
export type DesignerSaveStatus = 'unsaved' | 'saving' | 'failed' | 'saved';

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

const validationFromField = (field: FormFieldDef): FormSchemaNode['validation'] => {
  const rules = Object.entries(field.validate_rules ?? {}).map(([type, value]) => ({ type, value }));
  if (field.form_required) rules.unshift({ type: 'required', value: true });
  return rules;
};

const fieldToNode = (field: FormFieldDef, nodeId: string): FormSchemaNode => ({
  id: nodeId,
  kind: controlMeta(field.type).kind === 'layout' ? 'layout' : 'field',
  type: field.type,
  field: controlMeta(field.type).kind === 'layout' ? null : field.field_name,
  title: field.label,
  defaultValue: field.default_value,
  valueType: controlMeta(field.type).valueType as FormSchemaNode['valueType'],
  props: clone(field.control_props ?? {}),
  children: [],
  validation: validationFromField(field),
  dataSource: clone(field.options_source ?? null),
  conditions: clone((field.link_rules?.rules as unknown[]) ?? []),
  events: {},
  hidden: field.form_show === 0,
  disabled: field.form_readonly === 1,
  database: {
    columnType: field.column_type,
    nullable: field.nullable === 1,
    comment: field.comment,
    unsigned: field.unsigned === 1,
    index: field.index_type,
    relation: {
      type: field.relation_type,
      table: field.relation_table,
      labelField: field.relation_label_field,
      valueField: field.relation_value_field,
      multiple: field.relation_multiple === 1,
      onDelete: field.relation_on_delete
    }
  },
  list: {
    show: field.list_show === 1,
    sort: field.list_sort === 1,
    filter: field.list_filter,
    formatter: field.list_formatter,
    width: field.list_width
  },
  layout: { span: field.form_span, group: field.form_group }
});

const nodeToField = (node: FormSchemaNode, index: number, current?: FormFieldDef): FormFieldDef => {
  const database = node.database ?? {};
  const relation = (database.relation as Record<string, unknown> | undefined) ?? {};
  const list = node.list ?? {};
  const validation = node.validation ?? [];
  const validationRules = Object.fromEntries(validation
    .filter((rule) => rule.type !== 'required')
    .map((rule) => [rule.type, rule.value ?? true]));
  const base = current ?? createField(node.type, index + 1);
  return {
    ...base,
    field_name: String(node.field ?? base.field_name),
    label: node.title,
    type: node.type,
    default_value: node.defaultValue == null ? '' : String(node.defaultValue),
    control_props: clone(node.props ?? {}),
    options_source: clone(node.dataSource ?? null),
    column_type: String(database.columnType ?? base.column_type),
    nullable: (database.nullable ?? base.nullable === 1) ? 1 : 0,
    comment: String(database.comment ?? base.comment),
    unsigned: (database.unsigned ?? base.unsigned === 1) ? 1 : 0,
    index_type: (database.index ?? base.index_type) as FormFieldDef['index_type'],
    relation_type: (relation.type ?? base.relation_type) as FormFieldDef['relation_type'],
    relation_table: String(relation.table ?? base.relation_table),
    relation_label_field: String(relation.labelField ?? base.relation_label_field),
    relation_value_field: String(relation.valueField ?? base.relation_value_field),
    relation_multiple: (relation.multiple ?? base.relation_multiple === 1) ? 1 : 0,
    relation_on_delete: (relation.onDelete ?? base.relation_on_delete) as FormFieldDef['relation_on_delete'],
    list_show: (list.show ?? base.list_show === 1) ? 1 : 0,
    list_sort: (list.sort ?? base.list_sort === 1) ? 1 : 0,
    list_filter: String(list.filter ?? base.list_filter),
    list_formatter: String(list.formatter ?? base.list_formatter),
    list_width: Number(list.width ?? base.list_width),
    form_show: node.hidden ? 0 : 1,
    form_required: validation.some((rule) => rule.type === 'required') ? 1 : 0,
    form_group: String(node.layout?.group ?? base.form_group),
    form_span: Number(node.layout?.span ?? base.form_span),
    form_readonly: node.disabled ? 1 : 0,
    validate_rules: Object.keys(validationRules).length ? validationRules : null,
    link_rules: { rules: clone(node.conditions ?? []) },
    sort_order: index
  };
};

const projectFields = (nodes: FormSchemaNode[], current: FormFieldDef[]): FormFieldDef[] => flattenNodes(nodes)
  .map(({ node }) => node.field ? node : null)
  .filter((node): node is FormSchemaNode => node !== null)
  .map((node, index) => nodeToField(node, index, current.find((field) => field.field_name === node.field)));

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
  const saveStatus = ref<DesignerSaveStatus>('saved');
  const undoStack = shallowRef<DesignerSnapshot[]>([]);
  const redoStack = shallowRef<DesignerSnapshot[]>([]);
  let nodeSequence = 0;

  const flattenedNodes = computed(() => flattenNodes(nodes.value));
  const selected = computed(() => fields.value.find((field) => field.field_name === selectedKey.value) ?? null);
  const selectedNode = computed(() => flattenedNodes.value.find((entry) => entry.node.id === selectedNodeId.value)?.node ?? null);
  const canUndo = computed(() => undoStack.value.length > 0);
  const canRedo = computed(() => redoStack.value.length > 0);
  const historyDepth = computed(() => ({ undo: undoStack.value.length, redo: redoStack.value.length }));

  const currentSnapshot = (): DesignerSnapshot => ({
    fields: fields.value,
    nodes: nodes.value,
    form: form.value
  });
  const snapshot = (): DesignerSnapshot => clone(currentSnapshot());
  const pushHistory = () => {
    undoStack.value = [...undoStack.value.slice(-HISTORY_LIMIT + 1), snapshot()];
    redoStack.value = [];
    dirty.value = true;
    saveStatus.value = 'unsaved';
  };
  const restore = (snapshotValue: DesignerSnapshot) => {
    // 快照从历史栈弹出后即转移为当前状态，无需再次深拷贝大型 AST。
    fields.value = snapshotValue.fields;
    nodes.value = snapshotValue.nodes;
    form.value = snapshotValue.form;
    if (selectedKey.value && !fields.value.some((field) => field.field_name === selectedKey.value)) selectedKey.value = null;
    if (selectedNodeId.value && !findLocation(nodes.value, selectedNodeId.value)) selectedNodeId.value = null;
  };
  const undo = () => {
    const previous = undoStack.value.at(-1);
    if (previous === undefined) return;
    undoStack.value = undoStack.value.slice(0, -1);
    redoStack.value = [...redoStack.value.slice(-HISTORY_LIMIT + 1), currentSnapshot()];
    restore(previous);
    dirty.value = true;
    saveStatus.value = 'unsaved';
  };
  const redo = () => {
    const next = redoStack.value.at(-1);
    if (next === undefined) return;
    redoStack.value = redoStack.value.slice(0, -1);
    undoStack.value = [...undoStack.value.slice(-HISTORY_LIMIT + 1), currentSnapshot()];
    restore(next);
    dirty.value = true;
    saveStatus.value = 'unsaved';
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
    const adjusted = source.siblings === destination && source.index < index ? index - 1 : index;
    const targetIndex = Math.max(0, Math.min(adjusted, destination.length - (source.siblings === destination ? 1 : 0)));
    if (source.siblings === destination && source.index === targetIndex) return false;
    pushHistory();
    const [moving] = source.siblings.splice(source.index, 1);
    destination.splice(Math.max(0, Math.min(adjusted, destination.length)), 0, moving);
    fields.value = projectFields(nodes.value, fields.value);
    return true;
  };
  const moveNodeByKeyboard = (nodeId: string, direction: DesignerKeyboardMove) => {
    const source = findLocation(nodes.value, nodeId);
    if (!source) return false;
    const parentId = source.parent?.id ?? null;
    if (direction === 'up') return source.index > 0 && moveNode(nodeId, parentId, source.index - 1);
    if (direction === 'down') return source.index < source.siblings.length - 1 && moveNode(nodeId, parentId, source.index + 2);
    if (direction === 'indent') {
      const previous = source.siblings[source.index - 1];
      return Boolean(previous && CONTAINER_TYPES.has(previous.type) && moveNode(nodeId, previous.id, previous.children.length));
    }
    if (!source.parent) return false;
    const parentLocation = findLocation(nodes.value, source.parent.id);
    return Boolean(parentLocation && moveNode(nodeId, parentLocation.parent?.id ?? null, parentLocation.index + 1));
  };
  const moveField = (from: number, to: number) => {
    if (from === to || from < 0 || to < 0 || from >= fields.value.length || to >= fields.value.length) return;
    pushHistory();
    const next = [...fields.value];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    fields.value = next.map((field, index) => ({ ...field, sort_order: index }));
    const orderedNodes = fields.value
      .map((field) => nodes.value.find((node) => node.field === field.field_name))
      .filter((node): node is FormSchemaNode => node !== undefined);
    if (orderedNodes.length === nodes.value.length) nodes.value = orderedNodes;
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
    const currentName = selectedKey.value;
    pushHistory();
    fields.value = fields.value.map((field) => field.field_name === currentName ? { ...field, ...patch } : field);
    const field = fields.value.find((item) => item.field_name === (patch.field_name ?? currentName));
    const node = flattenedNodes.value.find((entry) => entry.node.field === currentName)?.node;
    if (field && node) Object.assign(node, fieldToNode(field, node.id), { children: node.children });
    if (patch.field_name) {
      selectedKey.value = patch.field_name;
      if (node) node.field = patch.field_name;
    }
  };
  const updateNode = (patch: Partial<FormSchemaNode>) => {
    if (!selectedNodeId.value) return;
    const node = findNode(selectedNodeId.value);
    if (!node) return;
    pushHistory();
    Object.assign(node, clone(patch));
    fields.value = projectFields(nodes.value, fields.value);
    selectedKey.value = node.field ?? null;
  };
  const updateForm = (patch: Partial<FormDefinition>) => {
    pushHistory();
    form.value = { ...form.value, ...clone(patch) };
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
    fields.value = projectFields(nodes.value, fields.value);
    form.value = {
      ...form.value,
      form_key: schema.key,
      name: schema.title,
      schema_version: 2,
      schema_document: clone(schema),
      schema_origin: 'designer'
    };
    selectNode(null);
    return { ok: true, error: '' };
  };
  const updateList = (patch: NonNullable<FormSchemaDocument['list']>) => {
    pushHistory();
    form.value = {
      ...form.value,
      schema_document: { ...schemaDocument.value, list: { ...schemaDocument.value.list, ...clone(patch) } }
    };
  };
  const schemaDocument = computed<FormSchemaDocument>(() => ({
    ...(form.value.schema_document ?? {}),
    schemaVersion: 2,
    key: String(form.value.form_key ?? ''),
    title: String(form.value.name ?? ''),
    list: clone(form.value.schema_document?.list ?? {}),
    nodes: clone(nodes.value)
  }));
  const load = (definition: FormDefinition) => {
    form.value = { ...definition };
    fields.value = (definition.fields ?? []).map((field, index) => ({ ...field, sort_order: field.sort_order ?? index }));
    nodes.value = definition.schema_document?.schemaVersion === 2
      ? clone(definition.schema_document.nodes)
      : fields.value.map((field, index) => fieldToNode(field, `node_${field.type}_${index + 1}`));
    if (definition.schema_document?.schemaVersion === 2) fields.value = projectFields(nodes.value, fields.value);
    nodeSequence = flattenNodes(nodes.value).length;
    undoStack.value = [];
    redoStack.value = [];
    dirty.value = false;
    saveStatus.value = 'saved';
    selectedKey.value = null;
    selectedNodeId.value = null;
  };
  const beginSave = () => { saveStatus.value = 'saving'; };
  const failSave = () => { saveStatus.value = 'failed'; };
  const markSaved = (definition: FormDefinition) => load(definition);

  return {
    form, fields, nodes, selectedKey, selectedNodeId, selected, selectedNode, flattenedNodes, schemaDocument,
    dirty, saveStatus, beginSave, failSave, canUndo, canRedo, historyDepth, undo, redo, findNode, selectNode, addNode, addField, removeNode, removeField,
    duplicateNode, duplicateField, moveNode, moveNodeByKeyboard, moveField, updateField, updateNode, updateForm, updateList, replaceFields, replaceSchema, load, markSaved
  };
}

export type DesignerStore = ReturnType<typeof useDesigner>;
