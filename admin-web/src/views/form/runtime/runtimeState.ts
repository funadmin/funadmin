import type { FormSchemaNode } from '../schema/types';
import { executeActionChain, type ActionHandlers, type FormAction } from './actionExecutor';
import { evaluateCondition, type Condition } from './conditionEvaluator';

interface RuntimeOptions {
  actions?: Array<{ id?: string; event?: string; steps?: FormAction[] }>;
  submitAction?: string;
  requestKeys?: string[];
  request?: ActionHandlers['request'];
  notify?: ActionHandlers['notify'];
  navigate?: ActionHandlers['navigate'];
  openDialog?: ActionHandlers['openDialog'];
  validate?: ActionHandlers['validate'];
  submit?: ActionHandlers['submit'];
  reset?: ActionHandlers['reset'];
}

interface RuntimeNodeState {
  hidden: boolean;
  disabled: boolean;
  required: boolean;
}

interface ConditionalRule {
  when: Condition;
  then: { action: string; target?: string; value?: unknown };
}

const noop = async () => undefined;

export const createRuntimeState = (
  nodes: FormSchemaNode[],
  values: Record<string, unknown>,
  options: RuntimeOptions = {}
) => {
  const flat = (list: FormSchemaNode[]): FormSchemaNode[] => list.flatMap((node) => [node, ...flat(node.children ?? [])]);
  const allNodes = flat(nodes);
  const states = new Map<string, RuntimeNodeState>();
  const evaluate = () => {
    for (const node of allNodes) {
      const state = { hidden: Boolean(node.hidden), disabled: Boolean(node.disabled), required: false };
      for (const rule of (node.conditions ?? []) as ConditionalRule[]) {
        const matched = evaluateCondition(rule.when, values);
        if (rule.then.action === 'show') state.hidden = !matched;
        if (rule.then.action === 'hide' && matched) state.hidden = true;
        if (rule.then.action === 'enable') state.disabled = !matched;
        if (rule.then.action === 'disable' && matched) state.disabled = true;
        if (rule.then.action === 'setRequired') state.required = matched;
      }
      states.set(node.id, state);
    }
  };
  const setValue = async (action: FormAction) => {
    const target = String(action.target ?? '');
    if (target) values[target] = action.type === 'copyValue' ? values[String(action.source ?? '')] : action.value;
  };
  const setNodeState = async (action: FormAction) => {
    const target = String(action.target ?? '');
    const node = allNodes.find((item) => item.id === target || item.field === target);
    if (!node) return;
    if (action.type === 'show') node.hidden = false;
    if (action.type === 'hide') node.hidden = true;
    if (action.type === 'enable') node.disabled = false;
    if (action.type === 'disable') node.disabled = true;
    if (action.type === 'setRequired') {
      const required = Boolean(action.value ?? true);
      node.validation = (node.validation ?? []).filter((rule) => rule.type !== 'required');
      if (required) node.validation.unshift({ type: 'required' });
    }
  };
  const handlers = {
    setValue,
    copyValue: setValue,
    clearValue: async (action: FormAction) => { values[String(action.target ?? '')] = null; },
    show: setNodeState, hide: setNodeState, enable: setNodeState, disable: setNodeState, setRequired: setNodeState,
    validate: options.validate ?? noop,
    request: options.request ?? noop,
    notify: options.notify ?? noop,
    openDialog: options.openDialog ?? noop,
    navigate: options.navigate ?? noop,
    submit: options.submit ?? noop,
    reset: options.reset ?? noop
  } satisfies ActionHandlers;
  evaluate();
  return {
    refresh: evaluate,
    nodeState: (nodeId: string): RuntimeNodeState => states.get(nodeId) ?? { hidden: false, disabled: false, required: false },
    dispatch: async (nodeId: string, event: string, payload?: unknown) => {
      const node = allNodes.find((item) => item.id === nodeId);
      const declared = (options.actions ?? []).filter((action) => (
        action.event === event || (event === 'submit' && action.id === options.submitAction)
      )).flatMap((action) => action.steps ?? []);
      const actions = [
        ...(node ? node.events?.[event] ?? [] : allNodes.flatMap((item) => item.events?.[event] ?? [])),
        ...declared
      ] as FormAction[];
      await executeActionChain(actions, { values, event: payload }, handlers, { requestKeys: options.requestKeys });
      evaluate();
    }
  };
};
