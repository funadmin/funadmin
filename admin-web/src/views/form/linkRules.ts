import type { FormFieldDef } from '@/api/form';

/** 联动规则 v1：when(field, op, value) → then(hide/show/disable/enable/setValue) */
export interface LinkRule {
  when: { field: string; op: 'eq' | 'neq' | 'empty' | 'notEmpty'; value?: unknown };
  then: { action: 'hide' | 'show' | 'disable' | 'enable' | 'setValue'; value?: unknown; fromField?: string };
}

export interface LinkEffect {
  hidden: boolean;
  disabled: boolean;
}

const matches = (op: LinkRule['when']['op'], actual: unknown, expected: unknown): boolean => {
  switch (op) {
    case 'eq':
      return String(actual ?? '') === String(expected ?? '');
    case 'neq':
      return String(actual ?? '') !== String(expected ?? '');
    case 'empty':
      return actual === null || actual === undefined || actual === '' || (Array.isArray(actual) && actual.length === 0);
    case 'notEmpty':
      return !(actual === null || actual === undefined || actual === '' || (Array.isArray(actual) && actual.length === 0));
    default:
      return false;
  }
};

/** 解释联动规则：返回每字段可见/可用状态与需回写的值 */
export function evaluateLinkRules(
  fields: FormFieldDef[],
  values: Record<string, unknown>
): { effects: Record<string, LinkEffect>; writes: Record<string, unknown> } {
  const effects: Record<string, LinkEffect> = {};
  const writes: Record<string, unknown> = {};
  for (const field of fields) {
    effects[field.field_name] = { hidden: field.form_show === 0, disabled: field.form_readonly === 1 };
  }
  for (const field of fields) {
    const rules = Array.isArray(field.link_rules?.rules) ? (field.link_rules?.rules as LinkRule[]) : [];
    for (const rule of rules) {
      if (!rule?.when || !rule?.then) continue;
      if (!matches(rule.when.op, values[rule.when.field], rule.when.value)) continue;
      const target = field.field_name;
      switch (rule.then.action) {
        case 'hide':
          effects[target].hidden = true;
          break;
        case 'show':
          effects[target].hidden = false;
          break;
        case 'disable':
          effects[target].disabled = true;
          break;
        case 'enable':
          effects[target].disabled = false;
          break;
        case 'setValue':
          writes[target] = rule.then.fromField ? values[rule.then.fromField] : rule.then.value;
          break;
      }
    }
  }
  return { effects, writes };
}
