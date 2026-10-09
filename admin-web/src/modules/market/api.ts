import http from '@/utils/http';

const BASE = '/plugin/market';

export type LicenseType = 'free' | 'grant';
export type VersionStatus = 'draft' | 'published' | 'withdrawn';

export interface MarketSigning {
  sodium: boolean;
  keySource: 'env' | 'file' | 'none';
  publicKey: string;
  publicUrl: string;
  https: boolean;
}

export interface MarketOverview {
  plugins: number;
  publishedVersions: number;
  downloads: number;
  grants: number;
  paidOrders: number;
  revenue: number;
  activeInstalls: number;
  signing: MarketSigning;
  payment: string[];
}

export type OrderStatus = 'pending' | 'paid' | 'closed';

export interface MarketOrder {
  orderNo: string;
  pluginCode: string;
  pluginName: string;
  memberId: number;
  username: string;
  nickname: string;
  email: string;
  plan: 'perpetual' | 'yearly';
  planText: string;
  amount: number;
  paidAmount: number;
  status: OrderStatus;
  channel: string;
  channelText: string;
  tradeNo: string;
  remark: string;
  createdAt: string;
  paidAt: string;
  expireAt: string;
  clientIp: string;
  logs?: Array<{ channel: string; event: string; verified: boolean; result: string; createdAt: string }>;
}

export interface MarketStats {
  dates: string[];
  downloads: number[];
  installs: number[];
  uninstalls: number[];
  orders: number[];
  revenue: number[];
  totals: { downloads: number; activeInstalls: number; enabledInstalls: number; sites: number; paidOrders: number; revenue: number; members: number };
  top: Array<{ code: string; name: string; downloads: number; activeInstalls: number; revenue: number }>;
}

export interface PaymentSettings {
  alipay: { enabled: boolean; sandbox: boolean; app_id: string; alipay_public_key: string; private_key_set: boolean };
  wechat: { enabled: boolean; mch_id: string; app_id: string; serial_no: string; platform_public_key_id: string; platform_public_key: string; private_key_set: boolean; api_v3_key_set: boolean };
  mock: { enabled: boolean; allowed: boolean };
  notifyUrls: { alipay: string; wechat: string };
}

export interface MarketCategory {
  id: number;
  name: string;
  sort: number;
  status: number;
  pluginCount: number;
}

export interface MarketPlugin {
  id: number;
  code: string;
  name: string;
  description: string;
  author: string;
  categoryId: number;
  categoryName: string;
  licenseType: LicenseType;
  pricePerpetual: number;
  priceYearly: number;
  cover: string;
  homepage: string;
  status: number;
  sort: number;
  latestVersion: string;
  versionCount: number;
  draftCount: number;
  downloads: number;
  updatedAt: string;
}

export interface MarketVersion {
  id: number;
  pluginId: number;
  version: string;
  changelog: string;
  requires: { php?: string; funadmin?: string; plugins?: Record<string, string> };
  sha256: string;
  size: number;
  treeHash: string;
  databaseCapability: string;
  applications: { app: boolean; admin: boolean };
  signed: boolean;
  status: VersionStatus;
  downloadCount: number;
  publishedAt: string;
  createdAt: string;
}

export interface MarketGrant {
  id: number;
  pluginId: number;
  pluginCode: string;
  pluginName: string;
  memberId: number;
  username: string;
  nickname: string;
  expiresAt: string;
  status: number;
  active: boolean;
  remark: string;
  source: string;
  plan: string;
  createdAt: string;
}

export type PluginPayload = Pick<MarketPlugin, 'name' | 'description' | 'author' | 'categoryId' | 'licenseType' | 'status' | 'sort' | 'cover' | 'homepage'> & { pricePerpetual: string; priceYearly: string };
export type CategoryPayload = Pick<MarketCategory, 'name' | 'sort' | 'status'>;
export interface GrantPayload { pluginId?: number; account?: string; expiresAt: string; status: number; remark: string }

export const marketApi = {
  overview: () => http.get<MarketOverview>(`${BASE}/plugin/overview`),
  plugins: (params: { keyword?: string; page: number; pageSize: number }) => http.get<API.PageResult<MarketPlugin>>(`${BASE}/plugin`, params),
  updatePlugin: (id: number, data: PluginPayload) => http.put(`${BASE}/plugin/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  deletePlugin: (id: number) => http.delete(`${BASE}/plugin/${id}`, undefined, { requestOptions: { showSuccessMsg: true } }),
  versions: (pluginId: number) => http.get<MarketVersion[]>(`${BASE}/plugin/${pluginId}/versions`),
  upload: (file: File, changelog: string) => {
    const form = new FormData();
    form.append('file', file);
    form.append('changelog', changelog);
    return http.upload<MarketVersion>(`${BASE}/plugin/upload`, form, { timeout: 300000, requestOptions: { showSuccessMsg: true } });
  },
  updateVersion: (id: number, changelog: string) => http.put(`${BASE}/plugin/version/${id}`, { changelog }, { requestOptions: { showSuccessMsg: true } }),
  publishVersion: (id: number) => http.post(`${BASE}/plugin/version/${id}/publish`, undefined, { requestOptions: { showSuccessMsg: true } }),
  withdrawVersion: (id: number) => http.post(`${BASE}/plugin/version/${id}/withdraw`, undefined, { requestOptions: { showSuccessMsg: true } }),
  republishVersion: (id: number) => http.post(`${BASE}/plugin/version/${id}/republish`, undefined, { requestOptions: { showSuccessMsg: true } }),
  deleteVersion: (id: number) => http.delete(`${BASE}/plugin/version/${id}`, undefined, { requestOptions: { showSuccessMsg: true } }),
  downloadVersion: (id: number) => http.download(`${BASE}/plugin/version/${id}/package`),
  categories: () => http.get<MarketCategory[]>(`${BASE}/category`),
  createCategory: (data: CategoryPayload) => http.post(`${BASE}/category`, data, { requestOptions: { showSuccessMsg: true } }),
  updateCategory: (id: number, data: CategoryPayload) => http.put(`${BASE}/category/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  deleteCategory: (id: number) => http.delete(`${BASE}/category/${id}`, undefined, { requestOptions: { showSuccessMsg: true } }),
  grants: (params: { pluginId?: number; page: number; pageSize: number }) => http.get<API.PageResult<MarketGrant>>(`${BASE}/grant`, params),
  createGrant: (data: GrantPayload) => http.post(`${BASE}/grant`, data, { requestOptions: { showSuccessMsg: true } }),
  updateGrant: (id: number, data: GrantPayload) => http.put(`${BASE}/grant/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  deleteGrant: (id: number) => http.delete(`${BASE}/grant/${id}`, undefined, { requestOptions: { showSuccessMsg: true } }),
  setting: () => http.get<MarketSigning>(`${BASE}/setting`),
  generateKey: () => http.post<MarketSigning>(`${BASE}/setting/key`, undefined, { requestOptions: { showSuccessMsg: true } }),
  payment: () => http.get<PaymentSettings>(`${BASE}/setting/payment`),
  savePayment: (data: Record<string, Record<string, unknown>>) => http.put<PaymentSettings>(`${BASE}/setting/payment`, data, { requestOptions: { showSuccessMsg: true } }),
  orders: (params: { status?: string; keyword?: string; pluginId?: number; page: number; pageSize: number }) => http.get<API.PageResult<MarketOrder> & { summary: { paidCount: number; paidAmount: number } }>(`${BASE}/order`, params),
  order: (orderNo: string) => http.get<MarketOrder>(`${BASE}/order/${orderNo}`),
  confirmOrder: (orderNo: string, remark: string) => http.post(`${BASE}/order/${orderNo}/confirm`, { remark }, { requestOptions: { showSuccessMsg: true } }),
  closeOrder: (orderNo: string) => http.post(`${BASE}/order/${orderNo}/close`, undefined, { requestOptions: { showSuccessMsg: true } }),
  stats: (days: number) => http.get<MarketStats>(`${BASE}/stat`, { days })
};

export const yuan = (cents: number) => (cents / 100).toFixed(2);

export const formatSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
};
