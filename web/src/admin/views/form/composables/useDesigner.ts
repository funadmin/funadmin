import { computed, ref, shallowRef } from 'vue';
import { i18n } from '@/locales';
import type { FormDefinition, FormSchemaDocument, FormSchemaNode } from '@/api/form';
import { createNode } from '../registry';
import { fieldNodes as collectFieldNodes } from '../schema/fieldNodes';

const t = (key: string, fallback: string): string => i18n.global.t(key, fallback);
const tNamed = (key: string, named: Record<string, unknown>, fallback: string): string => i18n.global.t(key, named, fallback);

const HISTORY_LIMIT = 50;
const CONTAINER_TYPES = new Set(['group', 'grid', 'collapse', 'tabs', 'repeatable', 'subform']);
/** updateNode 不改写的结构键：节点身份与子树只经由增删移动操作变化。 */
const STRUCTURAL_KEYS = new Set(['id', 'children']);

interface DesignerSnapshot {
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

/** 表单设计器状态：FormSchema v2 节点是唯一的编辑模型，属性面板补丁直接写入节点。 */
export function useDesigner() {
  const form = ref<Partial<FormDefinition>>({
    form_key: '', name: '', table_name: '', connection: 'mysql', source_type: 'created',
    status: 1, remark: '', sort_order: 0,
    schema_version: 2, schema_document: null, schema_origin: 'designer'
  });
  const nodes = ref<FormSchemaNode[]>([]);
  const selectedNodeId = ref<string | null>(null);
  const dirty = ref(false);
  const saveStatus = ref<DesignerSaveStatus>('saved');
  const undoStack = shallowRef<DesignerSnapshot[]>([]);
  const redoStack = shallowRef<DesignerSnapshot[]>([]);
  let nodeSequence = 0;

  const flattenedNodes = computed(() => flattenNodes(nodes.value));
  /** 主表字段节点（布局内展开，repeatable/subform 子节点不展开）。 */
  const fieldNodes = computed(() => collectFieldNodes(nodes.value));
  const selectedNode = computed(() => flattenedNodes.value.find((entry) => entry.node.id === selectedNodeId.value)?.node ?? null);
  const canUndo = computed(() => undoStack.value.length > 0);
  const canRedo = computed(() => redoStack.value.length > 0);
  const historyDepth = computed(() => ({ undo: undoStack.value.length, redo: redoStack.value.length }));

  const currentSnapshot = (): DesignerSnapshot => ({
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
    nodes.value = snapshotValue.nodes;
    form.value = { ...snapshotValue.form, schema_hash: form.value.schema_hash };
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
  const usedFieldNames = () => new Set(collectFieldNodes(nodes.value).map((node) => node.field).filter((name): name is string => typeof name === 'string' && name !== ''));
  const findNode = (nodeId: string) => findLocation(nodes.value, nodeId)?.node ?? null;
  const selectNode = (nodeId: string | null) => {
    selectedNodeId.value = nodeId;
  };
  const targetChildren = (parentId: string | null): FormSchemaNode[] | null => {
    if (parentId === null) return nodes.value;
    const parent = findNode(parentId);
    return parent && CONTAINER_TYPES.has(parent.type) ? parent.children : null;
  };
  const addNode = (type: string, parentId: string | null = null, index?: number) => {
    const target = targetChildren(parentId);
    if (!target) throw new Error(t('formDesigner.targetNotContainer', '目标节点不是容器'));
    pushHistory();
    const used = usedFieldNames();
    let sequence = collectFieldNodes(nodes.value).length + 1;
    while (used.has(`field_${sequence}`)) sequence += 1;
    const node = createNode(type, sequence, nextNodeId(type));
    const at = Math.max(0, Math.min(index ?? target.length, target.length));
    target.splice(at, 0, node);
    selectNode(node.id);
    return node;
  };
  const removeNode = (nodeId: string) => {
    const location = findLocation(nodes.value, nodeId);
    if (!location) return false;
    pushHistory();
    location.siblings.splice(location.index, 1);
    if (selectedNodeId.value && !findLocation(nodes.value, selectedNodeId.value)) selectNode(null);
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
    // 复制出的主表字段各取唯一字段名 <name>_copy[_n]；repeatable/subform 子字段属于子表作用域，保持原名。
    const used = usedFieldNames();
    for (const node of collectFieldNodes([copy])) {
      if (typeof node.field !== 'string' || node.field === '') continue;
      const base = `${node.field}_copy`;
      let candidate = base;
      for (let suffix = 2; used.has(candidate); suffix += 1) candidate = `${base}_${suffix}`;
      used.add(candidate);
      node.title = tNamed('formDesigner.copySuffix', { label: node.title }, '{label}(副本)');
      node.field = candidate;
    }
    source.siblings.splice(source.index + 1, 0, copy);
    selectNode(copy.id);
    return copy;
  };
  /**
   * 把节点补丁写入当前选中节点：只替换补丁中出现的键（值为 undefined 表示删除该键），
   * 其余键（events、access、attrs、info、valueType 等）原样保留；id 与 children 不经此处改写。
   */
  const updateNode = (patch: Partial<FormSchemaNode>) => {
    if (!selectedNodeId.value) return;
    const node = findNode(selectedNodeId.value);
    if (!node) return;
    const changes = Object.entries(patch).filter(([key, value]) => !STRUCTURAL_KEYS.has(key)
      && JSON.stringify(Reflect.get(node, key)) !== JSON.stringify(value));
    if (!changes.length) return;
    pushHistory();
    for (const [key, value] of changes) {
      if (value === undefined) Reflect.deleteProperty(node, key);
      else Reflect.set(node, key, clone(value));
    }
  };
  const updateForm = (patch: Partial<FormDefinition>) => {
    pushHistory();
    form.value = { ...form.value, ...clone(patch) };
  };
  const replaceSchema = (schema: FormSchemaDocument) => {
    if (schema.schemaVersion !== 2 || !Array.isArray(schema.nodes)) return { ok: false, error: t('formDesigner.replaceSchemaV2Only', '仅支持 FormSchema v2 文档') };
    const ids = flattenNodes(schema.nodes).map((entry) => entry.node.id);
    if (ids.some((id) => !id) || new Set(ids).size !== ids.length) return { ok: false, error: t('formDesigner.nodeIdInvalid', 'nodeId 不能为空且必须唯一') };
    pushHistory();
    nodes.value = clone(schema.nodes);
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
    const list: NonNullable<FormSchemaDocument['list']> = { ...schemaDocument.value.list, ...clone(patch) };
    // 值为 undefined 的键表示删除（如清空 customActions 时不保留空数组）。
    for (const [key, value] of Object.entries(patch)) if (value === undefined) delete list[key];
    form.value = {
      ...form.value,
      schema_document: { ...schemaDocument.value, list }
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
  /** 只加载 FormSchema v2 定义；其他版本直接报错，不做 v1 字段回退。 */
  const load = (definition: FormDefinition) => {
    const document = definition.schema_document;
    if (document?.schemaVersion !== 2 || !Array.isArray(document.nodes)) {
      throw new Error(t('formDesigner.replaceSchemaV2Only', '仅支持 FormSchema v2 文档'));
    }
    form.value = { ...definition };
    nodes.value = clone(document.nodes);
    nodeSequence = flattenNodes(nodes.value).length;
    undoStack.value = [];
    redoStack.value = [];
    dirty.value = false;
    saveStatus.value = 'saved';
    selectedNodeId.value = null;
  };
  const beginSave = () => { saveStatus.value = 'saving'; };
  const failSave = () => { saveStatus.value = 'failed'; };
  const acknowledgeSave = (schemaHash: string) => {
    form.value = { ...form.value, schema_hash: schemaHash };
    saveStatus.value = 'unsaved';
  };
  const markSaved = (definition: FormDefinition) => {
    // 保存确认不是重新加载：保留画布引用、选择和历史，仅同步服务端规范化结果。
    const document = definition.schema_document;
    if (document?.schemaVersion === 2) {
      if (JSON.stringify(nodes.value) !== JSON.stringify(document.nodes)) {
        const existing = new Map(flattenedNodes.value.map(({ node }) => [node.id, node]));
        const reconcile = (target: FormSchemaNode[], source: FormSchemaNode[]) => {
          const next = source.map((saved) => {
            const node = existing.get(saved.id);
            if (!node) return clone(saved);
            const { children, ...properties } = saved;
            for (const key of Object.keys(node)) {
              if (key !== 'children' && !(key in properties)) Reflect.deleteProperty(node, key);
            }
            for (const [key, value] of Object.entries(properties)) {
              if (JSON.stringify(Reflect.get(node, key)) !== JSON.stringify(value)) Reflect.set(node, key, clone(value));
            }
            reconcile(node.children, children ?? []);
            return node;
          });
          if (target.length !== next.length || next.some((node, index) => node !== target[index])) target.splice(0, target.length, ...next);
        };
        reconcile(nodes.value, document.nodes);
        if (selectedNodeId.value && !findNode(selectedNodeId.value)) selectNode(null);
      }
      form.value.schema_document = clone(document);
    }
    form.value.schema_hash = definition.schema_hash;
    dirty.value = false;
    saveStatus.value = 'saved';
  };

  return {
    form, nodes, selectedNodeId, selectedNode, flattenedNodes, fieldNodes, schemaDocument,
    dirty, saveStatus, beginSave, failSave, acknowledgeSave, canUndo, canRedo, historyDepth, undo, redo, findNode, selectNode,
    addNode, removeNode, duplicateNode, moveNode, moveNodeByKeyboard, updateNode, updateForm, updateList, replaceSchema, load, markSaved
  };
}

export type DesignerStore = ReturnType<typeof useDesigner>;
