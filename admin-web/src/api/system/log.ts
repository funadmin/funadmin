import http from '@/utils/http';

/** 操作日志 */
export interface OperationLog {
  id: number;
  username: string;
  module: string;
  action: string;
  method: 'GET' | 'POST' | 'PUT' | 'DELETE';
  url: string;
  ip: string;
  location: string;
  status: 0 | 1; // 0 失败 1 成功
  duration: number; // 毫秒
  errorMsg?: string;
  createdAt: string;
}

/** 登录日志 */
export interface LoginLog {
  id: number;
  username: string;
  ip: string;
  location: string;
  browser: string;
  os: string;
  status: 0 | 1; // 0 失败 1 成功
  message?: string;
  createdAt: string;
}

export interface OperationLogQuery {
  page?: number;
  pageSize?: number;
  username?: string;
  module?: string;
  status?: 0 | 1;
  startTime?: string;
  endTime?: string;
}

export interface LoginLogQuery {
  page?: number;
  pageSize?: number;
  username?: string;
  status?: 0 | 1;
  startTime?: string;
  endTime?: string;
}

const PREFIX = '/system/log';

/** 操作日志 API */
export const operationLogApi = {
  list: (params: OperationLogQuery) =>
    http.get<API.PageResult<OperationLog>>(`${PREFIX}/operation`, params),
  detail: (id: number) => http.get<OperationLog>(`${PREFIX}/operation/${id}`),
  remove: (id: number) =>
    http.delete<void>(`${PREFIX}/operation/${id}`, undefined, {
      requestOptions: { showSuccessMsg: true }
    }),
  removeMany: (ids: number[]) =>
    http.delete<void>(`${PREFIX}/operation`, { ids }, { requestOptions: { showSuccessMsg: true } }),
  clear: () =>
    http.delete<void>(`${PREFIX}/operation/clear`, undefined, {
      requestOptions: { showSuccessMsg: true }
    })
};

/** 登录日志 API */
export const loginLogApi = {
  list: (params: LoginLogQuery) =>
    http.get<API.PageResult<LoginLog>>(`${PREFIX}/login`, params),
  remove: (id: number) =>
    http.delete<void>(`${PREFIX}/login/${id}`, undefined, {
      requestOptions: { showSuccessMsg: true }
    }),
  removeMany: (ids: number[]) =>
    http.delete<void>(`${PREFIX}/login`, { ids }, { requestOptions: { showSuccessMsg: true } }),
  clear: () =>
    http.delete<void>(`${PREFIX}/login/clear`, undefined, {
      requestOptions: { showSuccessMsg: true }
    })
};
