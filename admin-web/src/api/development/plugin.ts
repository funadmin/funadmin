import http from '@/utils/http';

const PREFIX = '/development/plugin';

export interface PluginCreateInput {
  name: string;
  title: string;
  application: boolean;
  console: boolean;
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
  valid?: boolean;
  manifest?: Record<string, unknown>;
  downloadPath?: string;
  sha256?: string;
}

export interface DevelopmentPluginOption {
  code: string;
  name: string;
  manifestVersion: 2;
  version: string;
  scopes: Array<'application' | 'console' | 'both'>;
}

export const pluginDevelopmentApi = {
  previewCreate: (input: PluginCreateInput) => http.post<PluginDevelopmentResult>(`${PREFIX}/create/preview`, input),
  create: (input: PluginCreateInput) => http.post<PluginDevelopmentResult>(`${PREFIX}/create`, input),
  validate: (code: string) => http.post<PluginDevelopmentResult>(`${PREFIX}/validate`, { code }),
  package: (code: string) => http.post<PluginDevelopmentResult>(`${PREFIX}/package`, { code }),
  options: () => http.get<DevelopmentPluginOption[]>(`${PREFIX}/options`)
};
