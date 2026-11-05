import { APP_CONFIG } from '@/config';

/** 浏览器下载链接使用与 HTTP 客户端相同的管理 API 前缀。 */
export function adminApiUrl(path: string): string {
  return `${APP_CONFIG.baseApi.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`;
}
