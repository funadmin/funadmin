import http from '@/utils/http';
import type { ConnectionTestResult, StorageOverview, StorageProfile, StorageProfilePayload } from './types';

const PREFIX = '/plugins/oss/storage';
/** 页面在自己的提示框里显示本地化错误（按响应 data.code 映射），不再弹出宿主的服务端原文提示。 */
const QUIET = { requestOptions: { showErrorMsg: false } };

export const ossStorageApi = {
  list: () => http.get<StorageOverview>(PREFIX, undefined, QUIET),
  save: (data: StorageProfilePayload) => http.post<StorageProfile>(`${PREFIX}/profiles`, data, QUIET),
  activate: (id: string) => http.post<{ current: string }>(`${PREFIX}/activate`, { id }, QUIET),
  /** 传表单值测试未保存的配置；只传 id 测试已保存的配置。编辑时密钥留空沿用已保存的密钥。 */
  test: (data: Partial<StorageProfilePayload> & { id?: string }) => http.post<ConnectionTestResult>(`${PREFIX}/test`, data, QUIET),
  remove: (id: string) => http.post<{ id: string }>(`${PREFIX}/delete`, { id }, QUIET),
  recycle: () => http.get<{ list: StorageProfile[] }>(`${PREFIX}/recycle`, undefined, QUIET),
  restore: (id: string) => http.post<StorageProfile>(`${PREFIX}/restore`, { id }, QUIET),
  destroy: (id: string) => http.post<{ id: string }>(`${PREFIX}/destroy`, { id }, QUIET)
};
