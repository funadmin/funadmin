import axios from 'axios';
import type { EnvironmentCheck, InstallForm } from '@/views/install/install';

export const installHttp = axios.create({
  baseURL: '/install.php/index',
  timeout: 120_000,
  withCredentials: true,
  headers: { 'X-Requested-With': 'XMLHttpRequest' }
});

installHttp.interceptors.response.use(
  (response) => {
    const payload = response.data as API.Response;
    if (payload.code === 200) return payload.data;
    return Promise.reject(new Error(payload.msg || '安装请求失败'));
  },
  (error) => {
    // 后端 fail() 走 HTTP 状态码：优先透出业务 msg，避免 axios 通用报错淹没原因
    const payload = error?.response?.data as API.Response | undefined;
    return Promise.reject(new Error(payload?.msg || error?.message || '安装请求失败'));
  }
);

export interface InstallEnvironment {
  installed: boolean;
  siteName: string;
  siteVersion: string;
  checks: Array<EnvironmentCheck & {
    label: string;
    requiredValue: string;
    currentValue: string;
  }>;
}

export interface InstallResult {
  username: string;
  password: string;
  backend: string;
}

let installedCache: Promise<boolean> | null = null;

/** 缓存读取安装状态；取不到（无后端/Mock 环境）时默认已安装，避免误入安装页 */
export const isSystemInstalled = (): Promise<boolean> => {
  if (!installedCache) {
    installedCache = installHttp
      .get<never, { installed?: boolean }>('/step2')
      .then((data) => Boolean(data?.installed))
      .catch(() => true);
  }
  return installedCache;
};

export const installApi = {
  environment: () => installHttp.get<never, InstallEnvironment>('/step2'),
  install: (form: InstallForm) => installHttp.post<never, InstallResult>('/step3', {
    hostname: form.hostname,
    port: form.port,
    database: form.database,
    prefix: form.prefix,
    username: form.username,
    password: form.password,
    adminUserName: form.adminUserName,
    adminPassword: form.adminPassword,
    rePassword: form.rePassword,
    email: form.email,
    app_debug: form.appDebug ? 1 : 0
  }),
  finish: () => installHttp.post<never, InstallResult>('/step4')
};
