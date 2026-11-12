import http from '@/utils/http';
import type { ConnectionTestResult, StorageOverview, StorageProfile, StorageProfilePayload } from './types';

const PREFIX = '/plugins/oss/storage';

export const ossStorageApi = {
  list: () => http.get<StorageOverview>(PREFIX),
  save: (data: StorageProfilePayload) => http.post<StorageProfile>(`${PREFIX}/profiles`, data),
  activate: (id: string) => http.post<{ current: string }>(`${PREFIX}/activate`, { id }),
  /** 传表单值测试未保存的配置；只传 id 测试已保存的配置。编辑时密钥留空沿用已保存的密钥。 */
  test: (data: Partial<StorageProfilePayload> & { id?: string }) => http.post<ConnectionTestResult>(`${PREFIX}/test`, data),
  remove: (id: string) => http.post<{ id: string }>(`${PREFIX}/delete`, { id }),
  recycle: () => http.get<{ list: StorageProfile[] }>(`${PREFIX}/recycle`),
  restore: (id: string) => http.post<StorageProfile>(`${PREFIX}/restore`, { id }),
  destroy: (id: string) => http.post<{ id: string }>(`${PREFIX}/destroy`, { id })
};
