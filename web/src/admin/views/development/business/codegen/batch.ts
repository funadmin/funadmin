import { isBusinessApiError } from '@/api/development/business';
import { i18n } from '@/locales';

export type SequentialResult<T, R> = { item: T; ok: true; value: R } | { item: T; ok: false; error: string };

/** 把任意异常转为一行可读文本：接口错误取 msg，Error 取 message。 */
export function sequentialErrorText(error: unknown): string {
  if (isBusinessApiError(error)) return error.msg;
  if (error instanceof Error && error.message) return error.message;
  if (typeof error === 'string' && error) return error;
  if (error && typeof error === 'object' && typeof (error as { msg?: unknown }).msg === 'string' && (error as { msg: string }).msg) {
    return (error as { msg: string }).msg;
  }
  return i18n.global.t('business.codegen.batch.requestFailed', '请求失败');
}

/**
 * 逐个（不并发）执行 worker；单项失败只记录在该项结果里，不中断后续项。
 * 结果顺序与输入一致；onProgress 在每项完成（无论成败）后收到已完成数量。
 */
export async function runSequential<T, R>(
  items: T[],
  worker: (item: T) => Promise<R>,
  onProgress?: (done: number) => void
): Promise<Array<SequentialResult<T, R>>> {
  const results: Array<SequentialResult<T, R>> = [];
  for (const item of items) {
    try {
      results.push({ item, ok: true, value: await worker(item) });
    } catch (error) {
      results.push({ item, ok: false, error: sequentialErrorText(error) });
    }
    onProgress?.(results.length);
  }
  return results;
}
