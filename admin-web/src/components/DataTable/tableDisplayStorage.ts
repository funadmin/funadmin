import type { DataTableDisplayState, DataTableSize } from './types';

const PREFIX = 'data-table';

export function defaultDisplayState(): DataTableDisplayState {
  return {
    size: 'default',
    stripe: true,
    border: true,
    headerBg: true
  };
}

export function loadDisplayState(storageKey: string): Partial<DataTableDisplayState> | null {
  if (!storageKey) return null;
  try {
    const raw = localStorage.getItem(`${PREFIX}:${storageKey}:display`);
    if (!raw) return null;
    const o = JSON.parse(raw) as Record<string, unknown>;
    const size = o.size as DataTableSize | undefined;
    const validSize = size === 'small' || size === 'default' || size === 'large' ? size : undefined;
    return {
      ...(validSize ? { size: validSize } : {}),
      ...(typeof o.stripe === 'boolean' ? { stripe: o.stripe } : {}),
      ...(typeof o.border === 'boolean' ? { border: o.border } : {}),
      ...(typeof o.headerBg === 'boolean' ? { headerBg: o.headerBg } : {})
    } as Partial<DataTableDisplayState>;
  } catch {
    return null;
  }
}

export function saveDisplayState(storageKey: string, state: DataTableDisplayState) {
  if (!storageKey) return;
  try {
    localStorage.setItem(`${PREFIX}:${storageKey}:display`, JSON.stringify(state));
  } catch {
    /* ignore quota */
  }
}

export function loadColumnKeys(storageKey: string): string[] | null {
  if (!storageKey) return null;
  try {
    const raw = localStorage.getItem(`${PREFIX}:${storageKey}:columns`);
    if (!raw) return null;
    const arr = JSON.parse(raw) as unknown;
    return Array.isArray(arr) && arr.every((x) => typeof x === 'string') ? (arr as string[]) : null;
  } catch {
    return null;
  }
}

export function saveColumnKeys(storageKey: string, keys: string[]) {
  if (!storageKey) return;
  try {
    localStorage.setItem(`${PREFIX}:${storageKey}:columns`, JSON.stringify(keys));
  } catch {
    /* ignore */
  }
}
