import type { Component } from 'vue';
import type { FormNodeKind, FormValueType } from './types';

export interface FormValueCodec {
  encode: (value: unknown) => unknown;
  decode: (value: unknown) => unknown;
}

export interface FormComponentDefinition {
  type: string;
  namespace: string;
  kind: FormNodeKind;
  componentKey: string;
  valueType: FormValueType;
  defaultValue: unknown;
  defaultProps: Readonly<Record<string, unknown>>;
  propertySchema: Readonly<Record<string, unknown>>;
  codec: FormValueCodec;
  allowedProps: readonly string[];
  allowedAttrs: readonly string[];
  allowedEvents: readonly string[];
  renderer: () => Promise<Component>;
}

export interface ComponentBindings {
  props?: Record<string, unknown>;
  attrs?: Record<string, unknown>;
}

export class FormComponentRegistry {
  private readonly definitions = new Map<string, FormComponentDefinition>();

  register(definition: FormComponentDefinition): void {
    if (this.definitions.has(definition.type)) throw new Error(`表单组件已注册：${definition.type}`);
    if (definition.namespace !== 'core' && !definition.type.startsWith(`${definition.namespace}:`)) {
      throw new Error('插件组件必须使用插件命名空间');
    }
    this.definitions.set(definition.type, Object.freeze({
      ...definition,
      defaultProps: Object.freeze({ ...definition.defaultProps }),
      propertySchema: Object.freeze({ ...definition.propertySchema }),
      allowedProps: Object.freeze([...definition.allowedProps]),
      allowedAttrs: Object.freeze([...definition.allowedAttrs]),
      allowedEvents: Object.freeze([...definition.allowedEvents])
    }));
  }

  resolve(type: string): FormComponentDefinition | undefined {
    return this.definitions.get(type);
  }

  all(): FormComponentDefinition[] {
    return [...this.definitions.values()];
  }
}

export const createFormComponentRegistry = (): FormComponentRegistry => new FormComponentRegistry();
export const componentRegistry = createFormComponentRegistry();

const identityCodec: FormValueCodec = Object.freeze({
  encode: (value: unknown) => value,
  decode: (value: unknown) => value
});
const coreRenderer = async (): Promise<Component> => (await import('../components/CoreControlAdapter.vue')).default;
const richTextRenderer = async (): Promise<Component> => (await import('../components/RichTextControl.vue')).default;
const commonAttrs = ['autocomplete', 'aria-label', 'aria-describedby', 'name'] as const;
const commonEvents = ['change', 'blur', 'focus'] as const;

interface CoreDefinitionOptions {
  valueType?: FormValueType;
  kind?: FormNodeKind;
  defaultValue?: unknown;
  defaultProps?: Record<string, unknown>;
  allowedProps?: readonly string[];
  allowedAttrs?: readonly string[];
  allowedEvents?: readonly string[];
  renderer?: () => Promise<Component>;
}

const core = (type: string, componentKey: string, options: CoreDefinitionOptions = {}): void => {
  const allowedProps = [...(options.allowedProps ?? [])];
  componentRegistry.register({
    type,
    namespace: 'core',
    kind: options.kind ?? 'field',
    componentKey,
    valueType: options.valueType ?? 'string',
    defaultValue: options.defaultValue ?? '',
    defaultProps: options.defaultProps ?? {},
    propertySchema: {
      type: 'object',
      additionalProperties: false,
      properties: Object.fromEntries(allowedProps.map((name) => [name, {}]))
    },
    codec: identityCodec,
    allowedProps,
    allowedAttrs: options.allowedAttrs ?? commonAttrs,
    allowedEvents: options.allowedEvents ?? commonEvents,
    renderer: options.renderer ?? coreRenderer
  });
};

core('input', 'ElInput', { allowedProps: ['placeholder', 'clearable', 'disabled', 'readonly', 'maxlength'] });
core('password', 'ElInput', { allowedProps: ['placeholder', 'clearable', 'disabled', 'readonly', 'maxlength', 'showPassword'] });
core('textarea', 'ElInput', { allowedProps: ['placeholder', 'clearable', 'disabled', 'readonly', 'maxlength', 'rows', 'autosize'] });
core('mention', 'ElMention', { allowedProps: ['placeholder', 'disabled', 'readonly', 'rows', 'type'] });
core('number', 'ElInputNumber', { valueType: 'number', defaultValue: 0, allowedProps: ['disabled', 'readonly', 'min', 'max', 'step', 'precision', 'controlsPosition'] });
core('select', 'ElSelect', { allowedProps: ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable'] });
core('selectV2', 'ElSelectV2', { allowedProps: ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable'] });
core('treeSelect', 'ElTreeSelect', { allowedProps: ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable', 'checkStrictly'] });
core('cascader', 'ElCascader', { valueType: 'array', defaultValue: [], allowedProps: ['placeholder', 'clearable', 'disabled', 'filterable', 'props'] });
core('radio', 'ElRadioGroup', { allowedProps: ['disabled', 'size', 'textColor', 'fill'] });
core('checkbox', 'ElCheckboxGroup', { valueType: 'array', defaultValue: [], allowedProps: ['disabled', 'min', 'max', 'size', 'fill', 'textColor'] });
core('switch', 'ElSwitch', { valueType: 'boolean', defaultValue: false, allowedProps: ['disabled', 'loading', 'activeValue', 'inactiveValue', 'activeText', 'inactiveText'] });
core('transfer', 'ElTransfer', { valueType: 'array', defaultValue: [], allowedProps: ['disabled', 'filterable', 'filterPlaceholder', 'titles', 'buttonTexts'] });
for (const type of ['date', 'datetime', 'daterange', 'datetimerange']) core(type, 'ElDatePicker', { valueType: type.endsWith('range') ? 'array' : 'string', defaultValue: type.endsWith('range') ? [] : '', allowedProps: ['placeholder', 'disabled', 'readonly', 'clearable', 'format', 'valueFormat', 'rangeSeparator', 'startPlaceholder', 'endPlaceholder'] });
core('time', 'ElTimePicker', { allowedProps: ['placeholder', 'disabled', 'readonly', 'clearable', 'format', 'valueFormat'] });
core('timeSelect', 'ElTimeSelect', { allowedProps: ['placeholder', 'disabled', 'clearable', 'start', 'end', 'step', 'minTime', 'maxTime'] });
core('slider', 'ElSlider', { valueType: 'number', defaultValue: 0, allowedProps: ['disabled', 'min', 'max', 'step', 'showStops', 'range'] });
core('rate', 'ElRate', { valueType: 'number', defaultValue: 0, allowedProps: ['disabled', 'max', 'allowHalf', 'showText', 'showScore'] });
core('color', 'ElColorPicker', { allowedProps: ['disabled', 'showAlpha', 'colorFormat', 'predefine'] });
core('image', 'Upload', { allowedProps: ['disabled', 'maxSize', 'bizType', 'accept'] });
core('images', 'Upload', { valueType: 'array', defaultValue: [], allowedProps: ['disabled', 'maxSize', 'maxCount', 'bizType', 'accept'] });
core('file', 'Upload', { valueType: 'object', defaultValue: null, allowedProps: ['disabled', 'maxSize', 'maxCount', 'bizType', 'accept'] });
core('files', 'Upload', { valueType: 'array', defaultValue: [], allowedProps: ['disabled', 'maxSize', 'maxCount', 'bizType', 'accept'] });
core('dictionary', 'ElSelect', { allowedProps: ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable'] });
core('relation', 'ElSelect', { allowedProps: ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable'] });
core('department', 'ElTreeSelect', { allowedProps: ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable', 'checkStrictly'] });
core('user', 'ElSelect', { allowedProps: ['placeholder', 'clearable', 'disabled', 'multiple', 'filterable'] });
core('richtext', 'RichTextControl', { allowedProps: ['placeholder', 'disabled', 'readonly', 'maxlength', 'rows'], renderer: richTextRenderer });
core('json', 'ElInput', { valueType: 'object', defaultValue: {}, allowedProps: ['placeholder', 'disabled', 'readonly', 'rows', 'autosize'] });
core('hidden', 'input', { allowedProps: ['name'] });
core('readonly', 'ElText', { allowedProps: ['type', 'size', 'truncated'] });
core('repeatable', 'RepeatableField', { valueType: 'array', defaultValue: [], allowedProps: ['disabled', 'minRows', 'maxRows', 'primaryKey', 'columns'] });
core('subform', 'RepeatableField', { valueType: 'array', defaultValue: [], allowedProps: ['disabled', 'minRows', 'maxRows', 'primaryKey', 'columns'] });
core('group', 'ElCard', { kind: 'layout', allowedProps: ['title', 'shadow'] });
core('grid', 'ElRow', { kind: 'layout', allowedProps: ['columns', 'gutter', 'justify', 'align'] });
core('divider', 'ElDivider', { kind: 'layout', allowedProps: ['contentPosition', 'direction', 'borderStyle'] });
core('text', 'ElText', { kind: 'layout', allowedProps: ['content', 'type', 'size', 'truncated'] });
core('collapse', 'ElCollapse', { kind: 'layout', allowedProps: ['title', 'accordion'] });
core('tabs', 'ElTabs', { kind: 'layout', allowedProps: ['tabs', 'type', 'tabPosition', 'stretch'] });

const pickAllowed = (source: Record<string, unknown> | undefined, allowed: readonly string[]): Record<string, unknown> => {
  if (!source) return {};
  const allowlist = new Set(allowed);
  return Object.fromEntries(Object.entries(source).filter(([key]) => allowlist.has(key)));
};

/** Schema 输入先经过组件独立白名单，再交给 v-bind。 */
export const sanitizeComponentBindings = (
  definition: FormComponentDefinition,
  bindings: ComponentBindings
): Record<string, unknown> => ({
  ...definition.defaultProps,
  ...pickAllowed(bindings.props, definition.allowedProps),
  ...pickAllowed(bindings.attrs, definition.allowedAttrs)
});
