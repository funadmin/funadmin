import { i18n } from './index';
import zhCN from './zh-CN';
import enUS from './en-US';
import { languageApi } from '@/api/system/language';
import { hasSession } from '@/utils/auth';

const cacheKey = (locale: string) => `funadmin-i18n-pack-${locale}`;
const versionKey = (locale: string) => `funadmin-i18n-pack-version-${locale}`;

/** 点分 key 平铺表还原为 vue-i18n 需要的嵌套消息对象。 */
export const unflattenMessages = (flat: Record<string, string>): Record<string, unknown> => {
  const root: Record<string, unknown> = {};
  for (const [path, value] of Object.entries(flat)) {
    const segments = path.split('.');
    if (segments.some((segment) => !segment || ['__proto__', 'constructor', 'prototype'].includes(segment)) || typeof value !== 'string') continue;
    let cursor = root;
    for (const [index, segment] of segments.entries()) {
      if (index === segments.length - 1) {
        cursor[segment] = value;
      } else {
        const next = cursor[segment];
        if (!next || typeof next !== 'object') cursor[segment] = {};
        cursor = cursor[segment] as Record<string, unknown>;
      }
    }
  }
  return root;
};

/** 嵌套消息对象展平为点分 key 平铺表（译文编辑页的静态 key 候选源）。 */
export const flattenMessages = (messages: Record<string, unknown>, prefix = ''): Record<string, string> => {
  const flat: Record<string, string> = {};
  for (const [key, value] of Object.entries(messages)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (value && typeof value === 'object') Object.assign(flat, flattenMessages(value as Record<string, unknown>, path));
    else flat[path] = String(value);
  }
  return flat;
};

const readCache = (locale: string): Record<string, string> | null => {
  try {
    const cached = localStorage.getItem(cacheKey(locale));
    return cached ? (JSON.parse(cached) as Record<string, string>) : null;
  } catch {
    return null;
  }
};

const readVersion = (locale: string): number => {
  try {
    const version = Number(localStorage.getItem(versionKey(locale)) ?? 0);
    return Number.isFinite(version) && version > 0 ? version : 0;
  } catch {
    return 0;
  }
};

const writeCache = (locale: string, messages: Record<string, string>, version: number | undefined): void => {
  try {
    localStorage.setItem(cacheKey(locale), JSON.stringify(messages));
    localStorage.setItem(versionKey(locale), String(version ?? 0));
  } catch {
    /* 隐私模式或配额不足时忽略缓存写入。 */
  }
};

/**
 * 拉取后端译文包并合并覆盖静态语言包；请求失败时回落 localStorage 缓存。
 * 版本协商：本地版本与服务端一致时服务端返回 unchanged 空包，直接复用缓存；
 * 缓存缺失（被清理）则强制全量重拉。静态包继续作为最终兜底（t(key, fallback) 模式不变）。
 */
function replaceRemoteMessages(locale: string, messages: Record<string, string>): void {
  const canonical = locale.toLowerCase() === 'zh-cn' ? 'zh-CN' : locale.toLowerCase() === 'en-us' ? 'en-US' : locale;
  const baseline = canonical === 'zh-CN' ? zhCN : canonical === 'en-US' ? enUS : {};
  i18n.global.setLocaleMessage(canonical, structuredClone(baseline) as typeof zhCN);
  i18n.global.mergeLocaleMessage(canonical, unflattenMessages(messages));
}

export async function applyRemotePack(locale: string): Promise<void> {
  let messages: Record<string, string> | null = null;
  if (!hasSession()) {
    // 译文包接口需要登录；登录前只用缓存/静态包，登录成功后再拉取。
    messages = readCache(locale);
    if (messages) replaceRemoteMessages(locale, messages);
    return;
  }
  try {
    const pack = await languageApi.pack(locale, readVersion(locale));
    if (pack.unchanged) {
      messages = readCache(locale);
      if (!messages) {
        const full = await languageApi.pack(locale, 0);
        messages = full.messages ?? {};
        writeCache(locale, messages, full.version);
      }
    } else {
      messages = pack.messages ?? {};
      writeCache(locale, messages, pack.version);
    }
  } catch {
    messages = readCache(locale);
    if (!messages) return;
  }
  replaceRemoteMessages(locale, messages);
}
