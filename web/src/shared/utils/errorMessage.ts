/** Convert structured API/client failures into a user-visible string. */
export function formatErrorMessage(value: unknown, fallback = '操作失败，请重试'): string {
  if (value instanceof Error && value.message.trim()) return value.message;
  if (typeof value === 'string' && value.trim()) return value;
  if (value == null) return fallback;
  if (typeof value === 'object') {
    const record = value as Record<string, unknown>;
    for (const candidate of [record.msg, record.message, record.error]) {
      if (typeof candidate === 'string' && candidate.trim()) return candidate;
    }
    const data = record.data;
    if (data && typeof data === 'object') {
      const nested = (data as Record<string, unknown>).message ?? (data as Record<string, unknown>).msg;
      if (typeof nested === 'string' && nested.trim()) return nested;
    }
    try {
      const json = JSON.stringify(value);
      if (json && json !== '{}') return json;
    } catch {
      // Fall through to the stable fallback when an error payload is cyclic.
    }
  }
  return fallback;
}
