import http from '@/utils/http';

export interface StorageDriver {
  name: string;
  label: string;
  source: string;
  available: boolean;
}

export interface StorageSettings {
  driver: string;
  fallback: boolean;
  drivers: StorageDriver[];
}

export const storageApi = {
  settings: () => http.get<StorageSettings>('/system/storage'),
  update: (driver: string) => http.put<{ driver: string }>('/system/storage', { driver }, { requestOptions: { showSuccessMsg: true } })
};

export default storageApi;
