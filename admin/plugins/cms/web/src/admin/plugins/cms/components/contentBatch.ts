import type { ContentRow } from '../types';

export type BatchAction = 'publish' | 'offline';
export interface BatchFailure { id: string; title: string; message: string }
export interface BatchResult { succeeded: string[]; failed: BatchFailure[] }

/** 发布：尚未发布或草稿修订与线上修订不同的条目；下架：已发布条目。 */
export function batchTargets(rows: ContentRow[], action: BatchAction): ContentRow[] {
  return rows.filter(row => action === 'offline'
    ? row.status === 'published'
    : row.status !== 'published' || String(row.published_revision_id ?? '') !== String(row.draft_revision_id));
}

/**
 * 逐条调用现有的单条接口（各自带修订与 lock_version，沿用服务端权限、版本冲突与缓存失效），
 * 依次执行以免同站点锁竞争；单条失败只记录原因，继续处理其余条目。
 */
export async function runBatch(rows: ContentRow[], call: (row: ContentRow) => Promise<unknown>, fallback = 'Operation failed'): Promise<BatchResult> {
  const result: BatchResult = { succeeded: [], failed: [] };
  for (const row of rows) {
    try {
      await call(row);
      result.succeeded.push(String(row.id));
    } catch (cause: any) {
      const message = typeof cause?.msg === 'string' && cause.msg !== '' ? cause.msg : cause instanceof Error && cause.message !== '' ? cause.message : fallback;
      result.failed.push({ id: String(row.id), title: String(row.revision?.title ?? row.id), message });
    }
  }
  return result;
}
