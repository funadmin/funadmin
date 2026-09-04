import http from '@/utils/http';

const PREFIX = '/system/member';

export interface MemberOption {
  id: number;
  name: string;
}

export interface MemberOptions {
  groups: MemberOption[];
  levels: MemberOption[];
}

export interface MemberModel {
  id: number;
  username: string;
  mobile: string;
  email: string;
  sex: '0' | '1' | '2';
  groupIds: number[];
  groupNames: string[];
  levelId: number;
  levelName: string;
  avatar: string;
  status: 0 | 1;
  loginCount: number;
  lastLoginAt: string;
  lastLoginIp: string;
  createdAt: string;
  updatedAt: string;
  deletedAt: string;
}

export interface MemberQuery {
  page: number;
  pageSize: number;
  keyword?: string;
  status?: 0 | 1;
  groupId?: number;
  levelId?: number;
  recycled?: 0 | 1;
}

export type MemberPayload = Pick<MemberModel, 'username' | 'mobile' | 'email' | 'sex' | 'groupIds' | 'levelId' | 'avatar' | 'status'>;

export interface MemberImportResult {
  created: number;
  skipped: number;
  errors: string[];
}

export const memberApi = {
  list: (params: MemberQuery) => http.get<API.PageResult<MemberModel>>(PREFIX, { params }),
  detail: (id: number) => http.get<MemberModel>(`${PREFIX}/${id}`),
  options: () => http.get<MemberOptions>(`${PREFIX}/options`),
  create: (data: MemberPayload) =>
    http.post<MemberModel>(PREFIX, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: MemberPayload) =>
    http.put<MemberModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  updateStatus: (id: number, status: 0 | 1) =>
    http.post<MemberModel>(`${PREFIX}/${id}/status`, { status }, { requestOptions: { showSuccessMsg: true } }),
  recycle: (ids: number[]) =>
    http.delete<{ removed: number }>(PREFIX, { ids }, { requestOptions: { showSuccessMsg: true } }),
  restore: (ids: number[]) =>
    http.post<{ restored: number }>(`${PREFIX}/restore`, { ids }, { requestOptions: { showSuccessMsg: true } }),
  destroy: (ids: number[]) =>
    http.delete<{ removed: number }>(`${PREFIX}/destroy`, { ids }, { requestOptions: { showSuccessMsg: true } }),
  importRows: (rows: Array<Partial<MemberPayload>>) =>
    http.post<MemberImportResult>(`${PREFIX}/import`, { rows }),
  exportRows: (params: Omit<MemberQuery, 'page' | 'pageSize'>) =>
    http.get<MemberModel[]>(`${PREFIX}/export`, { params })
};
