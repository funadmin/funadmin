import http from '@/utils/http';

export interface OperationLog {
  id: number;
  username: string;
  appName: string;
  sourceType: 'system' | 'plugin';
  sourceName: string;
  controller: string;
  action: string;
  name: string;
  method: string;
  url: string;
  ip: string;
  status: 0 | 1;
  responseCode: number;
  durationMs: number;
  requestId: string;
  createdAt: string;
  /** 软删除时间；未删除日志为空字符串。 */
  deletedAt?: string;
  getData?: string;
  postData?: string;
  agent?: string;
  errorMessage?: string;
}

export interface OperationLogQuery {
  page?: number;
  pageSize?: number;
  username?: string;
  appName?: string;
  sourceType?: 'system' | 'plugin' | '';
  sourceName?: string;
  status?: 0 | 1;
  startTime?: string;
  endTime?: string;
  /** 1 = 只查询回收站中的日志 */
  trashed?: 0 | 1;
}

const PREFIX = '/system/log/operation';

export const operationLogApi = {
  list: (params: OperationLogQuery) => http.get<API.PageResult<OperationLog>>(PREFIX, params),
  detail: (id: number) => http.get<OperationLog>(`${PREFIX}/${id}`),
  remove: (ids: number | number[]) =>
    http.delete<{ removed: number }>(PREFIX, { ids: Array.isArray(ids) ? ids : [ids] }, {
      requestOptions: { showSuccessMsg: true }
    }),
  clear: () =>
    http.delete<{ removed: number }>(`${PREFIX}/clear`, {}, {
      requestOptions: { showSuccessMsg: true }
    }),
  /** 彻底删除回收站中的日志；包含未删除日志时后端整体拒绝。 */
  forceRemove: (ids: number | number[]) =>
    http.delete<{ removed: number }>(`${PREFIX}/force`, { ids: Array.isArray(ids) ? ids : [ids] }, {
      requestOptions: { showSuccessMsg: true }
    }),
  /** 清空回收站（仅删除当前数据范围内已软删除的日志）。 */
  clearForce: () =>
    http.delete<{ removed: number }>(`${PREFIX}/clear-force`, {}, {
      requestOptions: { showSuccessMsg: true }
    })
};
