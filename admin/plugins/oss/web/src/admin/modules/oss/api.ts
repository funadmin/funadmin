import http from '@/utils/http';
import type { StorageOverview, StorageProfile, StorageProfilePayload } from './types';

const PREFIX = '/plugin/oss/storage';

export const ossStorageApi = {
  list: () => http.get<StorageOverview>(PREFIX),
  save: (data: StorageProfilePayload) => http.post<StorageProfile>(`${PREFIX}/profiles`, data),
  activate: (id: string) => http.post<{ current: string }>(`${PREFIX}/activate`, { id }),
  test: (id: string) => http.post<{ success: true; message: string }>(`${PREFIX}/test`, { id })
};
