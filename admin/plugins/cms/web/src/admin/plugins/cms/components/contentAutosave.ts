import type { FormData } from '../types';
import { CONTENT_EDITABLE_KEYS, contentFingerprint } from './contentForm';

/**
 * 内容编辑器的本地草稿暂存：未保存修改按间隔写入本机浏览器存储，离开页面时立即写一次。
 * 不经过服务器，不产生修订；保存成功或主动丢弃后清除。服务端仍以 lock_version 防止覆盖他人修改。
 */
export interface StorageLike { getItem(key: string): string | null; setItem(key: string, value: string): void; removeItem(key: string): void }
export interface AutosaveEntry { form: FormData; base_lock_version: number | null; base_revision_id: string | null; saved_at: number }

export const AUTOSAVE_INTERVALS = [0, 15, 30, 60] as const;
export const AUTOSAVE_MAX_AGE = 7 * 24 * 3600 * 1000;
const INTERVAL_KEY = 'cms-autosave-interval';
const DEFAULT_INTERVAL = 30;

function browserStorage(): StorageLike | null {
  try { return typeof window !== 'undefined' ? window.localStorage : null; } catch { return null; }
}

/** 已有译文按译文 ID；尚未保存的新译文按内容 ID；全新内容每个站点语言一个位置。 */
export function autosaveKey(siteId: string, locale: string, form: FormData): string {
  const slot = form.id ? `l${form.id}` : form.content_id ? `c${form.content_id}` : 'new';
  return `cms-autosave:${siteId}:${locale}:${slot}`;
}

export function writeAutosave(key: string, form: FormData, now = Date.now(), storage: StorageLike | null = browserStorage()): boolean {
  if (!storage) return false;
  const entry: AutosaveEntry = {
    form: JSON.parse(JSON.stringify(form)),
    base_lock_version: typeof form.lock_version === 'number' ? form.lock_version : null,
    base_revision_id: form.draft_revision_id ? String(form.draft_revision_id) : null,
    saved_at: now,
  };
  try { storage.setItem(key, JSON.stringify(entry)); return true; } catch { return false; }
}

export function readAutosave(key: string, now = Date.now(), storage: StorageLike | null = browserStorage()): AutosaveEntry | null {
  if (!storage) return null;
  let raw: string | null;
  try { raw = storage.getItem(key); } catch { return null; }
  if (!raw) return null;
  try {
    const entry = JSON.parse(raw) as AutosaveEntry;
    if (!entry || typeof entry !== 'object' || !entry.form || typeof entry.form !== 'object' || typeof entry.saved_at !== 'number') return null;
    if (now - entry.saved_at > AUTOSAVE_MAX_AGE) { clearAutosave(key, storage); return null; }
    return entry;
  } catch { return null; }
}

export function clearAutosave(key: string, storage: StorageLike | null = browserStorage()): void {
  try { storage?.removeItem(key); } catch { /* 存储不可用时无需清理 */ }
}

/** 本地暂存与服务端草稿不同的字段名；模型字段展开为 data.名称。 */
export function autosaveChanges(local: FormData, server: FormData): string[] {
  const changes: string[] = [];
  for (const key of CONTENT_EDITABLE_KEYS) {
    if (key === 'data') {
      const a = (local.data ?? {}) as Record<string, unknown>, b = (server.data ?? {}) as Record<string, unknown>;
      for (const name of [...new Set([...Object.keys(a), ...Object.keys(b)])].sort()) {
        if (JSON.stringify(a[name] ?? null) !== JSON.stringify(b[name] ?? null)) changes.push(`data.${name}`);
      }
    } else if (contentFingerprint({ [key]: local[key] }) !== contentFingerprint({ [key]: server[key] })) changes.push(key);
  }
  return changes;
}

export function readAutosaveInterval(storage: StorageLike | null = browserStorage()): number {
  let value: string | null = null;
  try { value = storage?.getItem(INTERVAL_KEY) ?? null; } catch { /* 默认值 */ }
  const seconds = Number(value);
  return value !== null && (AUTOSAVE_INTERVALS as readonly number[]).includes(seconds) ? seconds : DEFAULT_INTERVAL;
}

export function writeAutosaveInterval(seconds: number, storage: StorageLike | null = browserStorage()): void {
  if (!(AUTOSAVE_INTERVALS as readonly number[]).includes(seconds)) return;
  try { storage?.setItem(INTERVAL_KEY, String(seconds)); } catch { /* 仅本次会话生效 */ }
}
