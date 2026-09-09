export const ACTION_TYPES = [
  'setValue', 'copyValue', 'clearValue', 'show', 'hide', 'enable', 'disable', 'setRequired',
  'validate', 'request', 'notify', 'openDialog', 'navigate', 'submit', 'reset'
] as const;

export type ActionType = typeof ACTION_TYPES[number];
export type RequestConcurrency = 'parallel' | 'latest' | 'queue' | 'drop';

export interface FormAction {
  type: ActionType;
  [key: string]: unknown;
}

export interface RequestAction extends FormAction {
  type: 'request';
  key: string;
  concurrency: RequestConcurrency;
}

export interface ActionContext {
  readonly values: Readonly<Record<string, unknown>>;
  readonly event?: unknown;
  readonly [key: string]: unknown;
}

export type ActionHandler<T extends FormAction = FormAction> = (
  action: T,
  context: ActionContext
) => void | Promise<void>;

export type ActionHandlers = {
  [Type in ActionType]: ActionHandler<Extract<FormAction, { type: Type }> extends never
    ? FormAction & { type: Type }
    : Extract<FormAction, { type: Type }>>;
};

export interface ActionExecutorOptions {
  maxSteps?: number;
  requestKeys?: readonly string[];
}

const DEFAULT_MAX_STEPS = 100;
const REQUEST_CONCURRENCY: readonly RequestConcurrency[] = ['parallel', 'latest', 'queue', 'drop'];
const ACTION_TYPE_SET = new Set<string>(ACTION_TYPES);
interface RequestExecutionState {
  active: Promise<void> | null;
  controller: AbortController | null;
}
type RequestHandler = ActionHandlers['request'];
const requestStates = new WeakMap<RequestHandler, Map<string, RequestExecutionState>>();

const requestState = (handler: RequestHandler, key: string): RequestExecutionState => {
  let states = requestStates.get(handler);
  if (!states) {
    states = new Map();
    requestStates.set(handler, states);
  }
  let state = states.get(key);
  if (!state) {
    state = { active: null, controller: null };
    states.set(key, state);
  }
  return state;
};

const executeRequest = async (
  action: RequestAction,
  context: ActionContext,
  handler: RequestHandler
): Promise<void> => {
  if (action.concurrency === 'parallel') {
    await handler(action, context);
    return;
  }
  const state = requestState(handler, action.key);
  if (action.concurrency === 'drop' && state.active) return;
  if (action.concurrency === 'latest') state.controller?.abort();
  if (action.concurrency === 'queue' && state.active) {
    try { await state.active; } catch { /* 前序失败不阻断队列中的后续请求。 */ }
  }
  const controller = new AbortController();
  state.controller = controller;
  const execution = Promise.resolve(handler(action, { ...context, signal: controller.signal }));
  state.active = execution;
  try {
    await execution;
  } finally {
    if (state.active === execution) {
      state.active = null;
      state.controller = null;
    }
  }
};

const validateActions = (
  actions: readonly FormAction[],
  maxSteps: number,
  requestKeys: ReadonlySet<string>
): void => {
  if (!Number.isSafeInteger(maxSteps) || maxSteps < 1) throw new Error('动作链最大步数必须为正整数');
  if (actions.length > maxSteps) throw new Error(`动作链超过最大步数：${maxSteps}`);

  for (const action of actions) {
    if (!action || !ACTION_TYPE_SET.has(action.type)) throw new Error(`动作未注册：${String(action?.type ?? '')}`);
    if (action.type !== 'request') continue;
    const request = action as RequestAction;
    if (!request.key || !requestKeys.has(request.key)) throw new Error(`request key 未注册：${String(request.key ?? '')}`);
    if (!REQUEST_CONCURRENCY.includes(request.concurrency)) {
      throw new Error(`request 并发策略不合法：${String(request.concurrency ?? '')}`);
    }
  }
};

/**
 * 顺序执行经过白名单校验的声明式动作。所有副作用均由调用方注入，核心不发起真实网络请求。
 */
export const executeActionChain = async (
  actions: readonly FormAction[],
  context: ActionContext,
  handlers: ActionHandlers,
  options: ActionExecutorOptions = {}
): Promise<void> => {
  const maxSteps = options.maxSteps ?? DEFAULT_MAX_STEPS;
  validateActions(actions, maxSteps, new Set(options.requestKeys ?? []));

  for (const action of actions) {
    if (action.type === 'request') {
      await executeRequest(action as RequestAction, context, handlers.request);
      continue;
    }
    await handlers[action.type](action as never, context);
  }
};
