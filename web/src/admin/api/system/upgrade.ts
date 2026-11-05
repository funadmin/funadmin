import http from '@/utils/http';

export interface UpgradeTask {
  id: number;
  from_version: string;
  to_version: string;
  status: 'pending' | 'running' | 'recovering' | 'success' | 'failed' | 'restored' | 'requires_manual_recovery';
  stage: string;
  progress: number;
  backup_path?: string;
  error_message?: string;
  created_at?: string;
}

export interface UpgradeStatus {
  currentVersion: string;
  tasks: UpgradeTask[];
}

export interface UpgradeManifest {
  manifestId: string;
  version: string;
  changelog?: string;
  expiresAt: string;
}

export interface ExecuteUpgrade {
  manifestId: string;
  operationToken: string;
}

const success = { requestOptions: { showSuccessMsg: true } };

export const upgradeApi = {
  status: () => http.get<UpgradeStatus>('/system/upgrade/status'),
  check: () => http.get<UpgradeManifest>('/system/upgrade/check'),
  execute: (payload: ExecuteUpgrade) => http.post<UpgradeTask>('/system/upgrade/execute', payload, success),
  restore: (id: number, operationToken: string) => http.post<UpgradeTask>(`/system/upgrade/${id}/restore`, { operationToken }, success),
  recoverStale: () => http.post<{ recovered: boolean; task?: UpgradeTask }>('/system/upgrade/recover-stale', {}, success),
  upload: (file: File, operationToken: string) => {
    const form = new FormData();
    form.append('file', file);
    form.append('operationToken', operationToken);
    return http.upload<UpgradeTask>('/system/upgrade/upload', form, success);
  }
};

export default upgradeApi;
