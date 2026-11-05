import http from '@/utils/http';
import { adminApiUrl } from '@/utils/apiUrl';

const PREFIX = '/development/plugin';

export interface PluginCreateInput {
  name: string;
  title: string;
  application: boolean;
  admin: boolean;
  adminWeb: boolean;
}

export interface PluginDevelopmentPlan {
  operation: 'create' | 'validate' | 'package';
  status?: string;
  target?: string;
  output?: string;
  files: Array<{ path: string; status?: string } | string>;
}

export interface PluginDevelopmentResult {
  auditId: number | string;
  plan?: PluginDevelopmentPlan;
  conflicts: string[];
  plugin?: { code: string; directory: string };
  valid?: boolean;
  manifest?: Record<string, unknown>;
  downloadPath?: string;
  downloadUrl?: string;
  sha256?: string;
}

export interface DevelopmentPluginOption {
  code: string;
  name: string;
  manifestVersion: 2;
  version: string;
  scopes: Array<'application' | 'admin' | 'both'>;
}

export const pluginDevelopmentApi = {
  previewCreate: (input: PluginCreateInput) => http.post<PluginDevelopmentResult>(`${PREFIX}/create/preview`, input),
  create: (input: PluginCreateInput) => http.post<PluginDevelopmentResult>(`${PREFIX}/create`, input),
  validate: (code: string) => http.post<PluginDevelopmentResult>(`${PREFIX}/validate`, { code }),
  package: async (code: string) => {
    const result = await http.post<PluginDevelopmentResult>(`${PREFIX}/package`, { code });
    return result?.downloadUrl ? { ...result, downloadUrl: pluginDevelopmentApi.downloadUrl(code) } : result;
  },
  downloadUrl: (code: string) => adminApiUrl(`${PREFIX}/package/${encodeURIComponent(code)}/download`),
  options: () => http.get<DevelopmentPluginOption[]>(`${PREFIX}/options`)
};
