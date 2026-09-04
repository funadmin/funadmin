import axios from 'axios';
import type { EnvironmentCheck, InstallForm } from '@/views/install/install';

const installHttp = axios.create({
  baseURL: '/install.php/index',
  timeout: 120_000,
  withCredentials: true,
  headers: { 'X-Requested-With': 'XMLHttpRequest' }
});

installHttp.interceptors.response.use((response) => {
  const payload = response.data as API.Response;
  if (payload.code === 200) return payload.data;
  return Promise.reject(new Error(payload.msg || '安装请求失败'));
});

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
