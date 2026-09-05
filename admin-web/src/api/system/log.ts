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
}

const PREFIX = '/system/log/operation';

export const operationLogApi = {
  list: (params: OperationLogQuery) => http.get<API.PageResult<OperationLog>>(PREFIX, params),
  detail: (id: number) => http.get<OperationLog>(`${PREFIX}/${id}`),
  remove: (ids: number | number[]) =>
    http.delete<{ removed: number }>(PREFIX, { ids: Array.isArray(ids) ? ids : [ids] }, {
      requestOptions: { showSuccessMsg: true }
    })
};
