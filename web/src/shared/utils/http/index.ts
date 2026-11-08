import axios, {
  type AxiosInstance,
  type AxiosRequestConfig,
  type AxiosResponse,
  type InternalAxiosRequestConfig
} from 'axios';
import qs from 'qs';
import { ElMessage, ElMessageBox } from 'element-plus';
import { APP_CONFIG, adminLoginEntryFromRoute, adminLoginPath, RESP_CODE } from '@/config';
import { i18n } from '@/locales';
import { clearAuth, getCsrfToken, setCsrfToken } from '@/utils/auth';

const tr = (key: string, fallback: string) => i18n.global.t(key, fallback) as string;

interface RequestOptions {
  showSuccessMsg?: boolean;
  showErrorMsg?: boolean;
  isReturnNativeResponse?: boolean;
  errorMessageMode?: 'message' | 'modal' | 'none';
  /** 401 时不弹「重新登录」，由调用方自行回退（如登录前也会触发的后台请求）。 */
  ignoreUnauthorized?: boolean;
  /** Structured plugin conflicts are rendered by the caller, not as generic errors. */
  confirmComposerConflicts?: boolean;
}

interface AdminRequestConfig<D = any> extends AxiosRequestConfig<D> {
  requestOptions?: RequestOptions;
}

interface AdminInternalConfig<D = any> extends InternalAxiosRequestConfig<D> {
  requestOptions?: RequestOptions;
}

const DEFAULT_REQUEST_OPTIONS: Required<RequestOptions> = {
  showSuccessMsg: false,
  showErrorMsg: true,
  isReturnNativeResponse: false,
  errorMessageMode: 'message',
  ignoreUnauthorized: false,
  confirmComposerConflicts: false
};

export const service: AxiosInstance = axios.create({
  baseURL: APP_CONFIG.baseApi,
  timeout: APP_CONFIG.requestTimeout,
  withCredentials: true,
  paramsSerializer: {
    serialize: (params) => qs.stringify(params, { arrayFormat: 'brackets' })
  }
});

service.interceptors.request.use(
  (config: AdminInternalConfig) => {
    const method = (config.method || 'GET').toUpperCase();
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
      const csrfToken = getCsrfToken();
      if (csrfToken) config.headers['X-CSRF-TOKEN'] = csrfToken;
    }
    // 后端按当前界面语言返回 schema/错误文案（如会员页定义）。
    config.headers['X-Locale'] = i18n.global.locale.value;
    return config;
  },
  (error) => Promise.reject(error)
);

service.interceptors.response.use(
  (response: AxiosResponse<API.Response>) => {
    const config = response.config as AdminInternalConfig;
    const opt = { ...DEFAULT_REQUEST_OPTIONS, ...config.requestOptions };
    const nextCsrfToken = response.headers['x-csrf-token'];
    if (nextCsrfToken) setCsrfToken(String(nextCsrfToken));
    if (opt.isReturnNativeResponse) return response;

    const { code, data } = response.data;
    const msg = businessMessage(response.data);
    if (code === RESP_CODE.SUCCESS) {
      if (opt.showSuccessMsg && msg) ElMessage.success(msg);
      return data;
    }
    if (code === RESP_CODE.UNAUTHORIZED) {
      if (opt.ignoreUnauthorized) {
        if (opt.showErrorMsg) showError(msg || tr('http.unauthorized', '登录已失效，请重新登录'), opt.errorMessageMode);
        return Promise.reject(response.data);
      }
      return handleUnauthorized(msg || tr('http.unauthorized', '登录已失效，请重新登录'));
    }
    if (opt.confirmComposerConflicts && isComposerConflict(response.data)) return Promise.reject(response.data);
    if (opt.showErrorMsg) showError(msg || tr('http.requestFailed', '请求失败'), opt.errorMessageMode);
    return Promise.reject(response.data);
  },
  (error) => {
    const payload = error?.response?.data as API.Response | undefined;
    const status = error?.response?.status;
    const config = error?.config as AdminInternalConfig | undefined;
    const opt = { ...DEFAULT_REQUEST_OPTIONS, ...config?.requestOptions };

    // 真实服务端故障不信任任何业务字段，也不将原始载荷交给页面二次展示。
    if (status >= 500) {
      const message = status === 502 ? tr('http.badGateway', '网关错误') : status === 504 ? tr('http.gatewayTimeout', '网关超时') : tr('http.serverError', '服务器内部错误');
      if (opt.showErrorMsg) showError(message, opt.errorMessageMode);
      return Promise.reject({ code: status, msg: message, message, data: null });
    }
    const safeMessage = businessMessage(payload);
    if (status === 401 || payload?.code === RESP_CODE.UNAUTHORIZED) {
      if (opt.ignoreUnauthorized) {
        if (opt.showErrorMsg) showError(safeMessage || tr('http.unauthorized', '登录已失效，请重新登录'), opt.errorMessageMode);
        return Promise.reject(payload || error);
      }
      return handleUnauthorized(safeMessage || tr('http.unauthorized', '登录已失效，请重新登录'));
    }

    let message = safeMessage || error?.message || tr('http.networkError', '网络异常');
    if (status === 403) message = safeMessage || tr('http.forbidden', '没有访问权限');
    else if (status === 404) message = safeMessage || tr('http.notFound', '请求资源不存在');
    else if (status === 422) message = safeMessage || tr('http.validationFailed', '参数验证失败');
    else if (error?.code === 'ECONNABORTED') message = tr('http.timeout', '请求超时');

    if (opt.confirmComposerConflicts && isComposerConflict(payload)) return Promise.reject(payload);
    if (opt.showErrorMsg) showError(message, opt.errorMessageMode);
    return Promise.reject(payload || error);
  }
);

function isComposerConflict(payload: unknown): boolean {
  const body = payload as { code?: number; data?: { reason?: string; conflicts?: unknown[] } } | undefined;
  return body?.code === 409 && body.data?.reason === 'composer_conflict' && Array.isArray(body.data.conflicts);
}

function businessMessage(payload: unknown): string | undefined {
  if (!payload || typeof payload !== 'object') return undefined;
  const body = payload as Record<string, unknown>;
  for (const value of [body.msg, body.message]) {
    if (typeof value === 'string' && value.trim()) return value;
  }
  return undefined;
}

function showError(message: string, mode: RequestOptions['errorMessageMode']) {
  if (mode === 'modal') {
    ElMessageBox.alert(message, tr('http.errorTitle', '错误提示'), { type: 'error' });
  } else if (mode === 'message') {
    ElMessage.error(message);
  }
}

let unauthorizedPrompting = false;

function handleUnauthorized(message: string): Promise<never> {
  clearAuth();
  const current = location.hash.replace(/^#/, '');
  // 已在登录页无需提示；并发 401 只弹一次。
  const loginPath = adminLoginPath(adminLoginEntryFromRoute(current));
  if (current.startsWith(loginPath) || unauthorizedPrompting) return Promise.reject(new Error(message));
  unauthorizedPrompting = true;
  ElMessageBox.confirm(message, tr('http.systemTip', '系统提示'), {
    confirmButtonText: tr('http.relogin', '重新登录'),
    cancelButtonText: tr('common.cancel', '取消'),
    type: 'warning'
  })
    .then(() => {
      const redirect = current ? `?redirect=${encodeURIComponent(current)}` : '';
      const onBasePath = location.pathname === import.meta.env.BASE_URL;
      location.href = `${import.meta.env.BASE_URL}#${loginPath}${redirect}`;
      // 仅 hash 变化不会刷新页面，内存中的登录态仍在，守卫会把登录页弹回首页。
      if (onBasePath) location.reload();
    })
    .catch(() => {})
    .finally(() => {
      unauthorizedPrompting = false;
    });
  return Promise.reject(new Error(message));
}

export function request<T = any>(config: AdminRequestConfig): Promise<T> {
  return service.request(config) as unknown as Promise<T>;
}

export const http = {
  get: <T = any>(url: string, params?: any, config?: AdminRequestConfig): Promise<T> =>
    request({ ...config, url, method: 'GET', params }),
  post: <T = any>(url: string, data?: any, config?: AdminRequestConfig): Promise<T> =>
    request({ ...config, url, method: 'POST', data }),
  put: <T = any>(url: string, data?: any, config?: AdminRequestConfig): Promise<T> =>
    request({ ...config, url, method: 'PUT', data }),
  patch: <T = any>(url: string, data?: any, config?: AdminRequestConfig): Promise<T> =>
    request({ ...config, url, method: 'PATCH', data }),
  delete: <T = any>(url: string, params?: any, config?: AdminRequestConfig): Promise<T> =>
    request({ ...config, url, method: 'DELETE', params }),
  upload: <T = any>(url: string, formData: FormData, config?: AdminRequestConfig): Promise<T> =>
    request({
      ...config,
      url,
      method: 'POST',
      data: formData
    }),
  download: async (url: string, params?: any, config?: AdminRequestConfig): Promise<Blob> => {
    const res = (await request<AxiosResponse<Blob>>({
      ...config,
      url,
      method: 'GET',
      params,
      responseType: 'blob',
      requestOptions: { ...(config?.requestOptions || {}), isReturnNativeResponse: true }
    })) as unknown as AxiosResponse<Blob>;
    return res.data;
  },
  /**
   * 以 POST 下载二进制（携带 CSRF 头，与其他写请求一致）。服务端失败时返回 JSON 而非文件：
   * 解析为统一响应后按 requestOptions 提示并拒绝，避免把错误 JSON 当作文件保存。
   */
  downloadPost: async (url: string, data?: any, config?: AdminRequestConfig): Promise<Blob> => {
    const opt = { ...DEFAULT_REQUEST_OPTIONS, ...config?.requestOptions };
    const res = (await request<AxiosResponse<Blob>>({
      ...config,
      url,
      method: 'POST',
      data,
      responseType: 'blob',
      // 4xx 的错误体同样是 JSON Blob，交给下面统一解析；5xx 仍走拦截器的通用故障提示。
      validateStatus: (status) => status >= 200 && status < 500,
      requestOptions: { ...(config?.requestOptions || {}), isReturnNativeResponse: true }
    })) as unknown as AxiosResponse<Blob>;
    const blob = res.data;
    if (!isJsonBlob(blob) && res.status < 400) return blob;
    const payload = await blobPayload(blob);
    const msg = businessMessage(payload);
    if (payload?.code === RESP_CODE.UNAUTHORIZED || res.status === 401) {
      if (!opt.ignoreUnauthorized) return handleUnauthorized(msg || tr('http.unauthorized', '登录已失效，请重新登录'));
    }
    const fallback = res.status === 403 ? tr('http.forbidden', '没有访问权限') : res.status === 404 ? tr('http.notFound', '请求资源不存在') : tr('http.requestFailed', '请求失败');
    if (opt.showErrorMsg) showError(msg || fallback, opt.errorMessageMode);
    return Promise.reject(payload ?? { code: res.status, msg: fallback, message: fallback, data: null });
  }
};

function readBlobText(blob: Blob): Promise<string> {
  if (typeof blob.text === 'function') return blob.text();
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result ?? ''));
    reader.onerror = () => reject(reader.error);
    reader.readAsText(blob);
  });
}

function isJsonBlob(blob: unknown): blob is Blob {
  return blob instanceof Blob && /^application\/(?:[\w.+-]+\+)?json\b/i.test(blob.type);
}

async function blobPayload(blob: unknown): Promise<API.Response | undefined> {
  if (!(blob instanceof Blob)) return undefined;
  try {
    const value = JSON.parse(await readBlobText(blob));
    return value && typeof value === 'object' ? value as API.Response : undefined;
  } catch {
    return undefined;
  }
}

export default http;
