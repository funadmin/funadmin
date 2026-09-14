import type { FormListButton } from '../schema/types';
import type { FormListActionCatalog, FormListActionRequest } from '@/api/formData';

export interface ListButtonContext extends Omit<FormListActionRequest, 'buttonId' | 'idempotencyKey' | 'input' | 'confirmation'> {
  formKey: string;
  sourceKey?: string;
}
const validId = (id: unknown) => (typeof id === 'string' || (typeof id === 'number' && Number.isSafeInteger(id))) && /^[A-Za-z0-9_-]{1,64}$/.test(String(id));
export function registeredListButtonAvailable(button: FormListButton, context: ListButtonContext, catalog: FormListActionCatalog | undefined, permission: (code: string) => boolean): boolean {
  if (button.action.type !== 'registered' || !catalog || catalog.schemaHash !== context.schemaHash || !context.schemaHash) return false;
  const action = Object.hasOwn(catalog.actions, button.action.key) ? catalog.actions[button.action.key] : undefined;
  const category = context.location === 'categoryNode' || context.location === 'categoryToolbar';
  const target = { row: 'record', toolbar: 'selection', categoryNode: 'category', categoryToolbar: 'none' }[context.location];
  if (!action || action.capabilityVersion !== button.action.capabilityVersion || !action.permission || button.permission !== action.permission || !permission(action.permission)
    || !action.locations?.includes(context.location) || !action.targets?.includes(target) || !['read', 'write'].includes(action.effect) || action.resultContract !== 'json') return false;
  if (category && (!context.sourceSchemaHash || catalog.sourceSchemaHash !== context.sourceSchemaHash || (context.sourceKey && catalog.sourceKey !== context.sourceKey))) return false;
  if (context.ids.some(id => !validId(id)) || new Set(context.ids.map(String)).size !== context.ids.length) return false;
  if (category) return context.ids.length === 0 && (context.location !== 'categoryNode' || validId(context.category?.id));
  return context.location === 'row' ? context.ids.length === 1 : action.batch === true && context.ids.length > 0 && context.ids.length <= 200;
}
/** 只传身份和声明输入；参数绑定和来源记录必须由后端解析。 */
export function listButtonRequest(button: FormListButton, context: ListButtonContext, input: Record<string, unknown>, key: string, confirmation?: string): FormListActionRequest {
  return { buttonId: button.id, location: context.location, schemaHash: context.schemaHash, ids: [...context.ids], idempotencyKey: key,
    ...(Object.keys(input).length ? { input } : {}), ...(context.filter ? { filter: context.filter } : {}),
    ...(context.category ? { category: { id: context.category.id } } : {}), ...(context.sourceSchemaHash ? { sourceSchemaHash: context.sourceSchemaHash } : {}),
    ...(confirmation ? { confirmation } : {}) };
}

type Input = Record<string, unknown>;
export interface ListButtonReply {
  status: 'success' | 'confirmation_required';
  result?: unknown;
  confirmation?: string;
}
export interface ListButtonExecutionHost {
  /** 宿主必须检查能力、权限、记录条件及当前上下文，不得只检查按钮显示。 */
  check: (button: FormListButton) => Promise<boolean>;
  context?: () => string;
  interact: (button: FormListButton, previous: Input) => Promise<Input | null>;
  invoke: (button: FormListButton, input: Input, key: string, confirmation?: string) => Promise<ListButtonReply>;
  confirm?: (button: FormListButton) => Promise<boolean>;
  refresh?: () => Promise<void>;
  clearSelection?: () => void;
  close?: () => void;
}
export type ListButtonExecutionResult = { status: 'busy' | 'cancelled' } | { status: 'success'; result?: unknown; effectError?: unknown };

/** 每个宿主实例共用一把锁，覆盖输入、确认、权限复检及异步成功效果。 */
export function createListButtonExecutor(host: ListButtonExecutionHost) {
  let locked = false;
  const drafts = new Map<string, Input>();
  let draftContext = host.context?.();
  const syncContext = () => { const current = host.context?.(); if (current !== draftContext) { drafts.clear(); draftContext = current; } return current; };
  const clone = (value: Input): Input => JSON.parse(JSON.stringify(value)) as Input;
  return {
    busy: () => locked,
    input: (id: string) => { syncContext(); return clone(drafts.get(id) ?? {}); },
    clear: () => { drafts.clear(); },
    async execute(source: FormListButton, submittedInput?: Input): Promise<ListButtonExecutionResult> {
      if (locked) return { status: 'busy' };
      locked = true;
      try {
        const context = syncContext();
        const button = JSON.parse(JSON.stringify(source)) as FormListButton;
        const assertContext = () => { if (syncContext() !== context) { drafts.clear(); throw new Error('FORM_LIST_CONTEXT_CHANGED'); } };
        const check = async () => {
          assertContext();
          if (button.hidden || button.disabled || !await host.check(button)) throw new Error('FORM_LIST_BUTTON_FORBIDDEN');
          assertContext();
        };
        await check();
        const input = submittedInput === undefined ? await host.interact(button, clone(drafts.get(button.id) ?? {})) : clone(submittedInput);
        if (input === null) return { status: 'cancelled' };
        assertContext();
        drafts.set(button.id, clone(input));
        await check();
        const key = crypto.randomUUID();
        let reply = await host.invoke(button, clone(input), key);
        if (reply.status === 'confirmation_required') {
          if (!reply.confirmation || !host.confirm) throw new Error('FORM_LIST_CONFIRMATION_UNAVAILABLE');
          if (!await host.confirm(button)) return { status: 'cancelled' };
          await check();
          reply = await host.invoke(button, clone(input), key, reply.confirmation);
        }
        if (reply.status !== 'success') throw new Error('FORM_LIST_RESULT_UNKNOWN');
        drafts.delete(button.id);
        // 成功后的 UI 故障不等于写入失败，不重发动作。
        let effectError: unknown;
        for (const effect of [button.success?.clearSelection && host.clearSelection, button.success?.close && host.close, button.success?.refresh && host.refresh]) {
          if (effect) try { await effect(); } catch (error) { effectError ??= error; }
        }
        if (effectError) return { status: 'success', result: reply.result, effectError };
        return { status: 'success', result: reply.result };
      } finally {
        locked = false;
      }
    }
  };
}
