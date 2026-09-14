import type { Router } from 'vue-router';
export interface ListResource { type: 'navigate' | 'external'; permission: string; capabilityVersion: string; route?: string; params: string[]; query: string[]; origin?: string; path?: string; paths?: string[] }
export const isListResource = (type: string) => ['navigate', 'external', 'copy', 'download', 'refresh'].includes(type);
interface Host { permission: (code: string) => boolean; router?: Router; resource?: ListResource; copy?: (text: string) => unknown; open?: (url: string, target: string, features: string) => unknown; download?: () => unknown; refresh?: () => unknown }
const fail = (): never => { throw Error('FORM_LIST_RESOURCE_FORBIDDEN'); };
const values = (value: unknown, keys: string[]): Record<string, string> => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return fail();
  for (const [key, item] of Object.entries(value)) if (!keys.includes(key) || ['__proto__', 'constructor', 'prototype'].includes(key) || !['string', 'number'].includes(typeof item) || !/^[\p{L}\p{N}_ -]{1,200}$/u.test(String(item))) fail();
  return value as Record<string, string>;
};
/** 仅消费后端已重新授权的结果，浏览器副作用仍按同一目录复检。 */
export async function executeListResource(result: unknown, host: Host): Promise<void> {
  if (!result || typeof result !== 'object') fail();
  const r = result as Record<string, unknown>;
  if (r.type === 'copy') {
    if (typeof r.text !== 'string' || r.text.length > 10000 || !host.copy) fail();
    await host.copy!(r.text as string); return;
  }
  if (r.type === 'refresh') { if (!host.refresh) fail(); await host.refresh!(); return; }
  if (r.type === 'download') { if (r.key !== 'export' || !host.download) fail(); await host.download!(); return; }
  const resource = host.resource;
  if (!resource || resource.type !== r.type || typeof r.permission !== 'string' || r.permission !== resource.permission || !host.permission(r.permission)) fail();
  if (r.type === 'navigate') {
    if (r.route !== resource!.route || !host.router || typeof r.route !== 'string' || !host.router.hasRoute(r.route)) fail();
    const to = { name: r.route as string, params: values(r.params, resource!.params), query: values(r.query, resource!.query) };
    const resolved = host.router!.resolve(to);
    if (!resolved.matched.length || resolved.matched.some(record => record.redirect || (typeof record.meta.permission === 'string' && !host.permission(record.meta.permission)))) fail();
    await host.router!.push(to); return;
  }
  if (r.type === 'external') {
    if (typeof r.url !== 'string' || !host.open) fail();
    const url = new URL(r.url as string);
    if (url.protocol !== 'https:' || url.origin !== resource!.origin || url.pathname !== resource!.path || url.username || url.password || url.hash) fail();
    values(Object.fromEntries(url.searchParams), resource!.query);
    host.open!(url.href, '_blank', 'noopener,noreferrer'); return;
  }
  fail();
}
