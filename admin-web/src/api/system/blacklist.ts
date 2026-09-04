import http from '@/utils/http';

const PREFIX = '/system/blacklist';

export interface BlacklistModel {
  id: number;
  ip: string;
  remark: string;
  status: 0 | 1;
  createdAt: string;
  updatedAt: string;
  deletedAt: string;
}

export interface BlacklistQuery {
  page: number;
  pageSize: number;
  ip?: string;
  status?: 0 | 1;
  recycled?: 0 | 1;
}

export interface BlacklistImportResult {
  created: number;
  skipped: number;
  errors: string[];
}

export const blacklistApi = {
  list: (params: BlacklistQuery) => http.get<API.PageResult<BlacklistModel>>(PREFIX, { params }),
  detail: (id: number) => http.get<BlacklistModel>(`${PREFIX}/${id}`),
  create: (data: Partial<BlacklistModel>) =>
    http.post<BlacklistModel>(PREFIX, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<BlacklistModel>) =>
    http.put<BlacklistModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  updateStatus: (id: number, status: 0 | 1) =>
    http.post<BlacklistModel>(`${PREFIX}/${id}/status`, { status }, { requestOptions: { showSuccessMsg: true } }),
  removeMany: (ids: number[]) =>
    http.delete<{ removed: number }>(PREFIX, { ids }, { requestOptions: { showSuccessMsg: true } }),
  restore: (ids: number[]) =>
    http.post<{ restored: number }>(`${PREFIX}/restore`, { ids }, { requestOptions: { showSuccessMsg: true } }),
  destroy: (ids: number[]) =>
    http.delete<{ removed: number }>(`${PREFIX}/destroy`, { ids }, { requestOptions: { showSuccessMsg: true } }),
  importRows: (rows: Array<Pick<BlacklistModel, 'ip' | 'remark' | 'status'>>) =>
    http.post<BlacklistImportResult>(`${PREFIX}/import`, { rows }),
  exportRows: (params: Omit<BlacklistQuery, 'page' | 'pageSize'>) =>
    http.get<BlacklistModel[]>(`${PREFIX}/export`, { params })
};
