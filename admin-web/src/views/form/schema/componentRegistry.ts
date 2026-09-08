import type { Component } from 'vue';
import type { FormNodeKind, FormValueType } from './types';

export interface FormComponentDefinition {
  type: string;
  kind: FormNodeKind;
  componentKey: string;
  valueType: FormValueType;
  allowedProps: readonly string[];
  component?: Component;
  trustedPlugin?: string;
}

class FormComponentRegistry {
  private readonly definitions = new Map<string, FormComponentDefinition>();

  register(definition: FormComponentDefinition): void {
    if (this.definitions.has(definition.type)) throw new Error(`表单组件已注册：${definition.type}`);
    if (definition.trustedPlugin && !definition.type.startsWith(`${definition.trustedPlugin}:`)) {
      throw new Error('插件组件必须使用插件命名空间');
    }
    this.definitions.set(definition.type, Object.freeze({ ...definition }));
  }

  resolve(type: string): FormComponentDefinition | undefined {
    return this.definitions.get(type);
  }

  all(): FormComponentDefinition[] {
    return [...this.definitions.values()];
  }
}

export const componentRegistry = new FormComponentRegistry();

const inputProps = ['placeholder', 'clearable', 'disabled', 'readonly', 'maxlength'] as const;
const core = (type: string, componentKey: string, valueType: FormValueType = 'string', kind: FormNodeKind = 'field') =>
  componentRegistry.register({ type, kind, componentKey, valueType, allowedProps: inputProps });

core('input', 'ElInput');
core('password', 'ElInput');
core('textarea', 'ElInput');
core('number', 'ElInputNumber', 'number');
core('select', 'ElSelect');
core('selectV2', 'ElSelectV2');
core('treeSelect', 'ElTreeSelect');
core('cascader', 'ElCascader', 'array');
core('radio', 'ElRadioGroup');
core('checkbox', 'ElCheckboxGroup', 'array');
core('switch', 'ElSwitch', 'boolean');
core('date', 'ElDatePicker');
core('datetime', 'ElDatePicker');
core('image', 'Upload');
core('images', 'Upload', 'array');
core('file', 'Upload', 'object');
core('files', 'Upload', 'array');
core('dictionary', 'ElSelect');
core('relation', 'ElSelect');
core('department', 'ElTreeSelect');
core('user', 'ElSelect');
core('group', 'ElAlert', 'string', 'layout');
core('grid', 'ElRow', 'string', 'layout');
core('divider', 'ElDivider', 'string', 'layout');
core('text', 'ElText', 'string', 'layout');
core('collapse', 'ElCollapse', 'string', 'layout');
core('tabs', 'ElTabs', 'string', 'layout');
