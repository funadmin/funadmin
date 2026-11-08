/**
 * 与后端 ContentValidator::safeUrl 一致：只允许 http(s)、mailto、站内路径和锚点。
 * 不 trim：前导空白/控制字符是常见的过滤绕过手法，直接拒绝。
 */
export function sanitizeDocumentUrl(value: unknown): string | null {
  if (typeof value !== 'string' || value === '') return null;
  if (/[\x00-\x20\\]/.test(value)) return null;
  return /^(?:https?:\/\/|mailto:|\/(?!\/)|#)/i.test(value) ? value : null;
}
