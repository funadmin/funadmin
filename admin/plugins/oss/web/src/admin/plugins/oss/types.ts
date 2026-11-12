export type StorageProvider = 'aliyun' | 'tencent' | 'qiniu' | 's3';

export interface StorageProfile {
  id: string;
  name: string;
  provider: StorageProvider;
  bucket: string;
  region: string;
  endpoint: string;
  domain: string;
  prefix: string;
  path_style: boolean;
  has_credentials: boolean;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
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
  path_style: boolean;
  access_key: string;
  secret_key: string;
}

export interface StorageOverview {
  profiles: StorageProfile[];
  current: string | null;
  managed: boolean;
}

export type ConnectionStepName = 'upload' | 'read' | 'delete';
export type ConnectionStepStatus = 'ok' | 'failed' | 'skipped';
export type ConnectionStepReason = 'error' | 'mismatch' | 'timeout' | 'upload_failed' | null;

export interface ConnectionStep {
  step: ConnectionStepName;
  status: ConnectionStepStatus;
  reason: ConnectionStepReason;
  message: string;
  ms: number;
}

export interface ConnectionTestResult {
  success: boolean;
  steps: ConnectionStep[];
  elapsed_ms: number;
}
