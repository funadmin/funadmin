import { withComposerConflictConfirm } from './pluginComposerConflicts';
import http from '@/utils/http';
import { adminApiUrl } from '@/utils/apiUrl';

const PREFIX = '/system/plugin';

export type PluginState = 'discovered' | 'installing' | 'disabled' | 'updating' | 'enabling' | 'enabled' | 'disabling' | 'uninstalling' | 'failed';

export interface PluginAccount {
  id: number;
  username: string;
  nickname: string;
  avatar: string;
}

export interface PluginItem {
  code: string;
  name: string;
  version: string;
  latestVersion: string;
  dbVersion: string;
  state: PluginState;
  dependencies: Record<string, string>;
  migrationPending: boolean;
  lastError: string;
  source: 'installed' | 'local' | 'cloud';
  needsReinstall: boolean;
  operation: string;
  progress: number;
  modified: boolean;
  disabledReason?: string;
}

export interface UpdateCheckRequest {
  code: string;
  code_version: string;
  db_version: string;
  modified: boolean;
}

export interface UpdateCheck {
  code: string;
  installedVersion: string;
  latestVersion: string;
  updateAvailable: boolean;
  compatible: boolean;
  databaseCompatible: boolean;
  requiresManualMerge: boolean;
  reason: string;
}

export interface MarketplaceVersion {
  id: number;
  pluginCode: string;
  version: string;
  changelog: string;
  compatible: boolean;
  requires: Record<string, unknown>;
  compatibleRange: string;
  publishedAt: string;
  sha256: string;
  signature: string | null;
  signatureAlgorithm: string | null;
  size: number;
  manifestSchema: number;
  packageFormat: string;
  treeHash: string;
  databaseCapability: string;
  applications: Record<string, boolean>;
  compatibleReason: string;
}

export interface MarketplacePlugin {
  id: number;
  code: string;
  name: string;
  description: string;
  author: string;
  versions: MarketplaceVersion[];
  /** 自建市场提供的价格说明与购买页地址；官方或旧版市场可能不返回。 */
  priceText?: string;
  storeUrl?: string;
}

export interface PluginConfigDefinition {
  title?: string;
  type?: string;
  value: unknown;
  options?: Record<string, string> | Array<{ label: string; value: string }>;
  tip?: string;
}

export interface PluginVersionHistory {
  id: number;
  version: string;
  source: string;
  package_hash: string;
  signature_algorithm?: string;
  signature_verified: boolean;
  downloadable: boolean;
  createdAt: string;
}

export interface PluginRecoveryInfo {
  available: boolean;
  stage: string;
  message: string;
}

export interface PluginOperation {
  id: number;
  operation: string;
  stage: string;
  progress: number;
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
  code: string;
  version: string;
  components: Record<string, string>;
  routes: PluginRouteDto[];
  generation?: string;
  clientHooks?: import('@/shared/plugins/hooks/types').HookDescriptor[];
}

const success = { requestOptions: { showSuccessMsg: true } };
const composerSuccess = { requestOptions: { showSuccessMsg: true, confirmComposerConflicts: true } };

export const pluginApi = {
  accountLogin: (account: string, password: string) => http.post<PluginAccount>(`${PREFIX}/account/login`, { account, password }, success),
  accountRefresh: () => http.post<PluginAccount>(`${PREFIX}/account/refresh`, undefined, success),
  accountLogout: () => http.post<{ authenticated: false }>(`${PREFIX}/account/logout`, undefined, success),
  currentAccount: () => http.get<PluginAccount | null>(`${PREFIX}/account/current`),
  categories: () => http.get<Array<{ id: number; name: string }>>(`${PREFIX}/market/categories`),
  marketSearch: (params: API.PageQuery & { categoryId?: number }) => http.get<API.PageResult<MarketplacePlugin>>(`${PREFIX}/market/search`, params),
  marketDetail: (code: string) => http.get<MarketplacePlugin>(`${PREFIX}/market/${code}`),
  marketVersions: (code: string) => http.get<MarketplaceVersion[]>(`${PREFIX}/market/${code}/versions`),
  checkUpdates: (installed: UpdateCheckRequest[]) => http.post<UpdateCheck[]>(`${PREFIX}/market/check-updates`, { installed }),
  discovered: () => http.get<PluginItem[]>(`${PREFIX}/local/discovered`),
  installed: () => http.get<PluginItem[]>(`${PREFIX}/local/installed`),
  detail: (code: string) => http.get<PluginItem>(`${PREFIX}/local/${code}`),
  installLocal: (file: File) => withComposerConflictConfirm((acceptComposerConflicts) => {
    const form = new FormData();
    form.append('file', file);
    form.append('acceptComposerConflicts', String(acceptComposerConflicts));
    return http.upload<unknown>(`${PREFIX}/local/install`, form, composerSuccess);
  }),
  installDiscovered: (code: string) => withComposerConflictConfirm((acceptComposerConflicts) => http.post<unknown>(`${PREFIX}/local/${code}/install`, { acceptComposerConflicts }, composerSuccess)),
  updateLocal: (code: string, file: File, migrate = true) => withComposerConflictConfirm((acceptComposerConflicts) => {
    const form = new FormData();
    form.append('file', file);
    form.append('acceptComposerConflicts', String(acceptComposerConflicts));
    form.append('migrate', String(migrate));
    return http.upload<unknown>(`${PREFIX}/local/${code}/update`, form, composerSuccess);
  }),
  installCloud: (code: string, version: string) => withComposerConflictConfirm((acceptComposerConflicts) => http.post<unknown>(`${PREFIX}/cloud/${code}/install`, { version, acceptComposerConflicts }, composerSuccess)),
  update: (code: string, version: string, migrate = true) => withComposerConflictConfirm((acceptComposerConflicts) => http.post<unknown>(`${PREFIX}/${code}/update`, { version, migrate, acceptComposerConflicts }, composerSuccess)),
  migrate: (code: string) => http.post<unknown>(`${PREFIX}/${code}/migrate`, undefined, success),
  enable: (code: string) => withComposerConflictConfirm((acceptComposerConflicts) => http.post<unknown>(`${PREFIX}/${code}/enable`, { acceptComposerConflicts }, composerSuccess)),
  disable: (code: string) => http.post<unknown>(`${PREFIX}/${code}/disable`, undefined, success),
  config: (code: string) => http.get<Record<string, PluginConfigDefinition>>(`${PREFIX}/${code}/config`),
  saveConfig: (code: string, values: Record<string, unknown>) => http.put<unknown>(`${PREFIX}/${code}/config`, { values }, success),
  uninstall: (code: string) => http.delete<unknown>(`${PREFIX}/${code}/uninstall`, undefined, success),
  purge: (code: string, purgeConfirm: string) => http.delete<unknown>(`${PREFIX}/${code}/purge`, { purgeConfirm }, success),
  deletePackage: (code: string) => http.delete<unknown>(`${PREFIX}/${code}/package`, undefined, success),
  history: (code: string) => http.get<PluginVersionHistory[]>(`${PREFIX}/${code}/history`),
  historyDownloadUrl: (code: string, id: number) => adminApiUrl(`${PREFIX}/${encodeURIComponent(code)}/history/${id}/download`),
  redeployHistory: (code: string, id: number, migrate = false) => withComposerConflictConfirm((acceptComposerConflicts) => http.post<unknown>(`${PREFIX}/${code}/history/${id}/redeploy`, { migrate, acceptComposerConflicts }, composerSuccess)),
  recoveryInfo: (code: string) => http.get<PluginRecoveryInfo>(`${PREFIX}/${code}/recovery`),
  operations: (code: string) => http.get<PluginOperation[]>(`${PREFIX}/${code}/operations`),
  enabledModules: () => http.get<EnabledPluginModule[]>(`${PREFIX}/modules/enabled`)
};
