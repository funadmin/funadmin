import { computed, ref, shallowRef } from 'vue';
import type { FormDefinition, FormFieldDef } from '@/api/form';
import { createField } from '../registry';

const HISTORY_LIMIT = 50;

/** 设计器状态：字段集合＋选中＋undo/redo 快照栈（纯前端，保存时提交服务端） */
export function useDesigner() {
  const form = ref<Partial<FormDefinition>>({
    form_key: '',
    name: '',
    table_name: '',
    connection: 'mysql',
    source_type: 'created',
    status: 1,
    remark: '',
    sort_order: 0,
    list_config: null,
    form_config: null
  });
  const fields = ref<FormFieldDef[]>([]);
  const selectedKey = ref<string | null>(null);
  const dirty = ref(false);
  const undoStack = shallowRef<string[]>([]);
  const redoStack = shallowRef<string[]>([]);

  const selected = computed(() => fields.value.find((f) => f.field_name === selectedKey.value) ?? null);
  const canUndo = computed(() => undoStack.value.length > 0);
  const canRedo = computed(() => redoStack.value.length > 0);

  const snapshot = () => JSON.stringify(fields.value);
  const pushHistory = () => {
    undoStack.value = [...undoStack.value.slice(-HISTORY_LIMIT + 1), snapshot()];
    redoStack.value = [];
    dirty.value = true;
  };
  const restore = (raw: string) => {
    fields.value = JSON.parse(raw) as FormFieldDef[];
    if (selectedKey.value && !fields.value.some((f) => f.field_name === selectedKey.value)) {
      selectedKey.value = null;
    }
  };
  const undo = () => {
    const previous = undoStack.value[undoStack.value.length - 1];
    if (previous === undefined) return;
    undoStack.value = undoStack.value.slice(0, -1);
    redoStack.value = [...redoStack.value, snapshot()];
    restore(previous);
    dirty.value = true;
  };
  const redo = () => {
    const next = redoStack.value[redoStack.value.length - 1];
    if (next === undefined) return;
    redoStack.value = redoStack.value.slice(0, -1);
    undoStack.value = [...undoStack.value, snapshot()];
    restore(next);
    dirty.value = true;
  };

  const addField = (type: string) => {
    pushHistory();
    const field = createField(type, fields.value.length + 1);
    let candidate = field.field_name;
    let suffix = 2;
    while (fields.value.some((f) => f.field_name === candidate)) {
      candidate = `${field.field_name}_${suffix}`;
      suffix += 1;
    }
    field.field_name = candidate;
    fields.value = [...fields.value, field];
    selectedKey.value = field.field_name;
  };
  const removeField = (fieldName: string) => {
    pushHistory();
    fields.value = fields.value.filter((f) => f.field_name !== fieldName);
    if (selectedKey.value === fieldName) selectedKey.value = null;
  };
  const duplicateField = (fieldName: string) => {
    const source = fields.value.find((f) => f.field_name === fieldName);
    if (!source) return;
    pushHistory();
    const copy: FormFieldDef = { ...JSON.parse(JSON.stringify(source)) };
    let candidate = `${source.field_name}_copy`;
    let suffix = 2;
    while (fields.value.some((f) => f.field_name === candidate)) {
      candidate = `${source.field_name}_copy_${suffix}`;
      suffix += 1;
    }
    copy.field_name = candidate;
    copy.label = `${source.label}(副本)`;
    const index = fields.value.findIndex((f) => f.field_name === fieldName);
    fields.value = [...fields.value.slice(0, index + 1), copy, ...fields.value.slice(index + 1)];
    selectedKey.value = copy.field_name;
  };
  const moveField = (from: number, to: number) => {
    if (from === to || from < 0 || to < 0 || from >= fields.value.length || to >= fields.value.length) return;
    pushHistory();
    const next = [...fields.value];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    fields.value = next.map((f, index) => ({ ...f, sort_order: index }));
  };
  const updateField = (patch: Partial<FormFieldDef>) => {
    if (!selectedKey.value) return;
    pushHistory();
    fields.value = fields.value.map((f) => (f.field_name === selectedKey.value ? { ...f, ...patch } : f));
  };
  const replaceFields = (next: Partial<FormFieldDef>[]) => {
    pushHistory();
    fields.value = next.map((row, index) => ({ ...createField(row.type ?? 'input', index + 1), ...row, sort_order: index })) as FormFieldDef[];
    selectedKey.value = null;
  };
  const load = (definition: FormDefinition) => {
    form.value = { ...definition };
    fields.value = (definition.fields ?? []).map((f, index) => ({ ...f, sort_order: f.sort_order ?? index }));
    undoStack.value = [];
    redoStack.value = [];
    dirty.value = false;
    selectedKey.value = null;
  };
  const markSaved = (definition: FormDefinition) => {
    load(definition);
  };

  return {
    form,
    fields,
    selectedKey,
    selected,
    dirty,
    canUndo,
    canRedo,
    undo,
    redo,
    addField,
    removeField,
    duplicateField,
    moveField,
    updateField,
    replaceFields,
    load,
    markSaved
  };
}

export type DesignerStore = ReturnType<typeof useDesigner>;
