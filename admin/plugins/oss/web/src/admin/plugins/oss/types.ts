export type StorageProvider = 'aliyun' | 'tencent' | 'qiniu';

export interface StorageProfile {
  id: string;
  name: string;
  provider: StorageProvider;
  bucket: string;
  region: string;
  endpoint: string;
  domain: string;
  prefix: string;
  has_credentials: boolean;
  created_at: string;
  updated_at: string;
}

export interface StorageProfilePayload {
  id?: string;
  name: string;
  provider: StorageProvider;
  bucket: string;
  region: string;
  endpoint: string;
  domain: string;
  prefix: string;
  access_key: string;
  secret_key: string;
}

export interface StorageOverview {
  profiles: StorageProfile[];
  current: string | null;
  managed: boolean;
}
