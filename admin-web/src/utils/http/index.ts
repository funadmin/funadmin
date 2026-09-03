import axios, {
  type AxiosInstance,
  type AxiosRequestConfig,
  type AxiosResponse,
  type InternalAxiosRequestConfig
} from 'axios';
import qs from 'qs';
import { ElMessage, ElMessageBox } from 'element-plus';
import { APP_CONFIG, RESP_CODE } from '@/config';
import { clearAuth, getCsrfToken, setCsrfToken } from '@/utils/auth';

interface RequestOptions {
  showSuccessMsg?: boolean;
  showErrorMsg?: boolean;
  isReturnNativeResponse?: boolean;
  errorMessageMode?: 'message' | 'modal' | 'none';
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
  errorMessageMode: 'message'
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

    const { code, msg, data } = response.data;
    if (code === RESP_CODE.SUCCESS) {
      if (opt.showSuccessMsg && msg) ElMessage.success(msg);
      return data;
    }
    if (code === RESP_CODE.UNAUTHORIZED) {
      return handleUnauthorized(msg || '登录已失效，请重新登录');
    }
    if (opt.showErrorMsg) showError(msg || '请求失败', opt.errorMessageMode);
    return Promise.reject(response.data);
  },
  (error) => {
    const payload = error?.response?.data as API.Response | undefined;
    const status = error?.response?.status;
    const config = error?.config as AdminInternalConfig | undefined;
    const opt = { ...DEFAULT_REQUEST_OPTIONS, ...config?.requestOptions };

    if (status === 401 || payload?.code === RESP_CODE.UNAUTHORIZED) {
      return handleUnauthorized(payload?.msg || '登录已失效，请重新登录');
    }

    let message = payload?.msg || error?.message || '网络异常';
    if (status === 403) message = payload?.msg || '没有访问权限';
    else if (status === 404) message = payload?.msg || '请求资源不存在';
    else if (status === 422) message = payload?.msg || '参数验证失败';
    else if (status === 500) message = '服务器内部错误';
    else if (status === 502) message = '网关错误';
    else if (status === 504) message = '网关超时';
    else if (error?.code === 'ECONNABORTED') message = '请求超时';

    if (opt.showErrorMsg) showError(message, opt.errorMessageMode);
    return Promise.reject(payload || error);
  }
);

function showError(message: string, mode: RequestOptions['errorMessageMode']) {
  if (mode === 'modal') {
    ElMessageBox.alert(message, '错误提示', { type: 'error' });
  } else if (mode === 'message') {
    ElMessage.error(message);
  }
}

function handleUnauthorized(message: string): Promise<never> {
  clearAuth();
  ElMessageBox.confirm(message, '系统提示', {
    confirmButtonText: '重新登录',
    cancelButtonText: '取消',
    type: 'warning'
  })
    .then(() => {
      const redirect = encodeURIComponent(location.pathname + location.search);
      location.href = `${import.meta.env.BASE_URL}login?redirect=${redirect}`;
    })
    .catch(() => {});
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
  delete: <T = any>(url: string, params?: any, config?: AdminRequestConfig): Promise<T> =>
    request({ ...config, url, method: 'DELETE', params }),
  upload: <T = any>(url: string, formData: FormData, config?: AdminRequestConfig): Promise<T> =>
    request({
      ...config,
      url,
      method: 'POST',
      data: formData,
      headers: { 'Content-Type': 'multipart/form-data', ...(config?.headers || {}) }
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
  }
};

export default http;
