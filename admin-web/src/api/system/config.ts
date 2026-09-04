import http from '@/utils/http';

const PREFIX = '/system/config';
const GROUP_PREFIX = '/system/config-group';

export interface ConfigModel {
  id: number;
  code: string;
  group: string;
  type: string;
  verify: string;
  value: string;
  extra: string;
  remark: string;
  status: 0 | 1;
  isSystem: 0 | 1;
  createdAt: string;
  updatedAt: string;
}

export interface ConfigGroupModel {
  id: number;
  name: string;
  title: string;
  status: 0 | 1;
  createdAt: string;
  updatedAt: string;
}

export interface ConfigTypeOption {
  name: string;
  title: string;
  requiresOptions: boolean;
}

export interface ConfigVerifyOption {
  value: string;
  title: string;
}

export interface ConfigOptions {
  groups: ConfigGroupModel[];
  types: ConfigTypeOption[];
  verifies: ConfigVerifyOption[];
}

export interface ConfigQuery {
  page: number;
  pageSize: number;
  keyword?: string;
  group?: string;
  type?: string;
  status?: 0 | 1;
}

export type ConfigPayload = Pick<ConfigModel, 'code' | 'group' | 'type' | 'verify' | 'value' | 'extra' | 'remark' | 'status'>;
export type ConfigGroupPayload = Pick<ConfigGroupModel, 'name' | 'title' | 'status'>;

export const configApi = {
  list: (params: ConfigQuery) => http.get<API.PageResult<ConfigModel>>(PREFIX, { params }),
  detail: (id: number) => http.get<ConfigModel>(`${PREFIX}/${id}`),
  options: () => http.get<ConfigOptions>(`${PREFIX}/options`),
  create: (data: ConfigPayload) =>
    http.post<ConfigModel>(PREFIX, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: ConfigPayload) =>
    http.put<ConfigModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  updateValue: (id: number, value: string | string[]) =>
    http.put<ConfigModel>(`${PREFIX}/${id}/value`, { value }, { requestOptions: { showSuccessMsg: true } }),
  updateStatus: (id: number, status: 0 | 1) =>
    http.post<ConfigModel>(`${PREFIX}/${id}/status`, { status }, { requestOptions: { showSuccessMsg: true } }),
  remove: (ids: number[]) =>
    http.delete<{ removed: number }>(PREFIX, { ids }, { requestOptions: { showSuccessMsg: true } }),
  groups: () => http.get<ConfigGroupModel[]>(GROUP_PREFIX),
  createGroup: (data: ConfigGroupPayload) =>
    http.post<ConfigGroupModel>(GROUP_PREFIX, data, { requestOptions: { showSuccessMsg: true } }),
  updateGroup: (id: number, data: ConfigGroupPayload) =>
    http.put<ConfigGroupModel>(`${GROUP_PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  removeGroup: (id: number) =>
    http.delete<void>(`${GROUP_PREFIX}/${id}`, undefined, { requestOptions: { showSuccessMsg: true } })
};
