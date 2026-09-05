import http from '@/utils/http';

const PREFIX = '/system/plugin';

export type PluginState = 'discovered' | 'installing' | 'disabled' | 'updating' | 'enabling' | 'enabled' | 'disabling' | 'uninstalling' | 'failed';

export interface PluginAccount {
  id: number;
  username: string;
  nickname: string;
  avatar: string;
}

export interface PluginItem {
  name: string;
  title: string;
  version: string;
  latestVersion: string;
  dbVersion: string;
  state: PluginState;
  dependencies: Record<string, string>;
  migrationPending: boolean;
  lastError: string;
  source: 'installed' | 'local' | 'cloud';
}

export interface UpdateCheck {
  name: string;
  installedVersion: string;
  latestVersion: string;
  updateAvailable: boolean;
}

export interface MarketplaceVersion {
  id: number;
  pluginName: string;
  version: string;
  changelog: string;
  compatible: boolean;
}

export interface MarketplacePlugin {
  id: number;
  name: string;
  title: string;
  description: string;
  author: string;
  versions: MarketplaceVersion[];
}

export interface PluginConfigDefinition {
  title?: string;
  type?: string;
  value: unknown;
  options?: Record<string, string> | Array<{ label: string; value: string }>;
  tip?: string;
}

export interface PluginOperation {
  id: number;
  operation: string;
  stage: string;
  progress: number;
  recovery_path?: string;
  from_version: string;
  to_version: string;
  source: string;
  result: string;
  error_message?: string;
  createdAt: string;
}

export interface PluginRouteDto {
  path: string;
  name: string;
  component: string;
  meta: Record<string, unknown>;
}

export interface EnabledPluginModule {
  name: string;
  version: string;
  hash: string;
  entryUrl: string;
  routes: PluginRouteDto[];
}

const success = { requestOptions: { showSuccessMsg: true } };

export const pluginApi = {
  accountLogin: (account: string, password: string) => http.post<PluginAccount>(`${PREFIX}/account/login`, { account, password }, success),
  accountLogout: () => http.post<{ authenticated: false }>(`${PREFIX}/account/logout`, undefined, success),
  currentAccount: () => http.get<PluginAccount | null>(`${PREFIX}/account/current`),
  categories: () => http.get<Array<{ id: number; name: string }>>(`${PREFIX}/market/categories`),
  marketSearch: (params: API.PageQuery & { categoryId?: number }) => http.get<API.PageResult<MarketplacePlugin>>(`${PREFIX}/market/search`, params),
  marketDetail: (name: string) => http.get<MarketplacePlugin>(`${PREFIX}/market/${name}`),
  marketVersions: (name: string) => http.get<MarketplaceVersion[]>(`${PREFIX}/market/${name}/versions`),
  checkUpdates: (installed: Array<{ name: string; version: string }>) => http.post<UpdateCheck[]>(`${PREFIX}/market/check-updates`, { installed }),
  discovered: () => http.get<PluginItem[]>(`${PREFIX}/local/discovered`),
  installed: () => http.get<PluginItem[]>(`${PREFIX}/local/installed`),
  detail: (name: string) => http.get<PluginItem>(`${PREFIX}/local/${name}`),
  installLocal: (file: File) => {
    const form = new FormData();
    form.append('file', file);
    return http.upload<unknown>(`${PREFIX}/local/install`, form, success);
  },
  installCloud: (name: string, version: string) => http.post<unknown>(`${PREFIX}/cloud/${name}/install`, { version }, success),
  update: (name: string, version: string, migrate = true) => http.post<unknown>(`${PREFIX}/${name}/update`, { version, migrate }, success),
  migrate: (name: string) => http.post<unknown>(`${PREFIX}/${name}/migrate`, undefined, success),
  enable: (name: string) => http.post<unknown>(`${PREFIX}/${name}/enable`, undefined, success),
  disable: (name: string) => http.post<unknown>(`${PREFIX}/${name}/disable`, undefined, success),
  config: (name: string) => http.get<Record<string, PluginConfigDefinition>>(`${PREFIX}/${name}/config`),
  saveConfig: (name: string, values: Record<string, unknown>) => http.put<unknown>(`${PREFIX}/${name}/config`, { values }, success),
  uninstall: (name: string) => http.delete<unknown>(`${PREFIX}/${name}/uninstall`, undefined, success),
  purge: (name: string, purgeConfirm: string) => http.delete<unknown>(`${PREFIX}/${name}/purge`, { purgeConfirm }, success),
  deletePackage: (name: string) => http.delete<unknown>(`${PREFIX}/${name}/package`, undefined, success),
  history: (name: string) => http.get<Array<Record<string, unknown>>>(`${PREFIX}/${name}/history`),
  operations: (name: string) => http.get<PluginOperation[]>(`${PREFIX}/${name}/operations`),
  enabledModules: () => http.get<EnabledPluginModule[]>(`${PREFIX}/modules/enabled`)
};

export type { EnabledPluginModule as PluginModuleDescriptor };
