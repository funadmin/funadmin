/**
 * 应用全局配置
 * 优先读取 .env 中以 VITE_APP_ 开头的变量；没有则使用默认值
 */
const env = import.meta.env;
export const isWebmanBackend = (): boolean => env.VITE_APP_BACKEND === 'webman';
let mockModeEnabled = env.VITE_APP_MOCK === 'true';

export function isMockModeEnabled(): boolean {
  return mockModeEnabled;
}

export function setMockModeEnabled(enabled: boolean): void {
  mockModeEnabled = enabled;
}

export const APP_CONFIG = {
  /** 应用标题 */
  title: env.VITE_APP_TITLE || 'Admin Console',
  /** 版本号 */
  version: env.VITE_APP_VERSION || '0.1.0',
  /** API 基础路径（与 vite proxy 前缀一致） */
  baseApi: env.VITE_APP_BASE_API || '/admin',
  /** 是否生产环境 */
  isProd: env.PROD,

  /** 默认请求超时（ms） */
  requestTimeout: 30 * 1000,
  /** 路由模式：history/hash */
  routerMode: 'hash' as 'history' | 'hash',

  /** 本地缓存 key 集合（在下方还会以 CACHE_KEYS 形式单独导出） */
  cache: {
    token: 'ADMIN_ACCESS_TOKEN',
    userInfo: 'ADMIN_USER_INFO',
    theme: 'ADMIN_THEME',
    tabs: 'ADMIN_TABS',
    permission: 'ADMIN_PERMISSION'
  },

  /** 布局默认配置 */
  layout: {
    /** 侧栏默认是否折叠 */
    sidebarCollapsed: false,
    /** 是否显示标签页 */
    showTabs: true,
    /** 是否显示面包屑 */
    showBreadcrumb: true,
    /** 是否固定顶栏 */
    fixedHeader: true,
    /** 是否显示 Logo */
    showLogo: true
  }
};

export type AppConfig = typeof APP_CONFIG;

/**
 * 本地缓存键名常量
 * 与 APP_CONFIG.cache 保持单一来源
 */
export const CACHE_KEYS = {
  TOKEN: APP_CONFIG.cache.token,
  USER_INFO: APP_CONFIG.cache.userInfo,
  THEME: APP_CONFIG.cache.theme,
  TABS: APP_CONFIG.cache.tabs,
  PERMISSION: APP_CONFIG.cache.permission
} as const;

/**
 * HTTP response business codes.
 * 与 FunAdmin ThinkPHP Session API 响应契约保持一致。
 */
export const RESP_CODE = {
  /** 业务成功 */
  SUCCESS: 200,
  /** 普通业务失败 */
  FAIL: 400,
  /** 未登录 / Token 无效 */
  UNAUTHORIZED: 401,
  /** 无权限 */
  FORBIDDEN: 403,
  /** 资源不存在 */
  NOT_FOUND: 404,
  /** 参数验证失败 */
  VALIDATION_FAILED: 422,
  /** 服务端异常 */
  SERVER_ERROR: 500,
} as const;

export const ADMIN_ROUTE_PREFIX = '/admin';
export const DEFAULT_ADMIN_LOGIN_ENTRY = 'admin';
export const ADMIN_LOGIN_ENTRY_KEY = 'ADMIN_LOGIN_ENTRY';
export const MEMBER_LOGIN_PATH = '/web/login';
export const MEMBER_REGISTER_PATH = '/web/register';
export const MEMBER_PROFILE_PATH = '/web/profile';


export const ADMIN_LOGIN_PATH = `${ADMIN_ROUTE_PREFIX}/login`;
export const ADMIN_HOME_PATH = `${ADMIN_ROUTE_PREFIX}/dashboard`;

const ADMIN_LOGIN_ENTRY_PATTERN = /^[a-z][a-z0-9-]{3,31}$/;
const ADMIN_LOGIN_ENTRY_RESERVED = new Set(['auth', 'api', 'install', 'system', 'login', 'health']);

export function normalizeAdminLoginEntry(value: unknown): string {
  const entry = String(value ?? '').trim().toLowerCase();
  if (!ADMIN_LOGIN_ENTRY_PATTERN.test(entry) || ADMIN_LOGIN_ENTRY_RESERVED.has(entry)) {
    throw new Error('后台登录入口格式不正确');
  }
  return entry;
}

export function getAdminLoginEntry(): string {
  if (typeof window === 'undefined') return DEFAULT_ADMIN_LOGIN_ENTRY;
  try {
    const value = window.localStorage.getItem(ADMIN_LOGIN_ENTRY_KEY);
    return value ? normalizeAdminLoginEntry(value) : DEFAULT_ADMIN_LOGIN_ENTRY;
  } catch {
    return DEFAULT_ADMIN_LOGIN_ENTRY;
  }
}

export function setAdminLoginEntry(value: unknown): string {
  const entry = normalizeAdminLoginEntry(value);
  if (typeof window !== 'undefined') {
    try { window.localStorage.setItem(ADMIN_LOGIN_ENTRY_KEY, entry); } catch { /* storage may be unavailable */ }
  }
  return entry;
}

export function adminLoginPath(entry?: unknown): string {
  const normalized = entry === undefined ? getAdminLoginEntry() : normalizeAdminLoginEntry(entry);
  return normalized === DEFAULT_ADMIN_LOGIN_ENTRY
    ? ADMIN_LOGIN_PATH
    : `${ADMIN_ROUTE_PREFIX}/${normalized}/login`;
}

export function adminAuthPrefix(entry?: unknown): string {
  const normalized = entry === undefined ? getAdminLoginEntry() : normalizeAdminLoginEntry(entry);
  return normalized === DEFAULT_ADMIN_LOGIN_ENTRY
    ? `${ADMIN_ROUTE_PREFIX}/auth`
    : `${ADMIN_ROUTE_PREFIX}/${normalized}/auth`;
}

export function adminLoginEntryFromRoute(path: string): string {
  const match = path.match(/^\/admin\/([^/]+)\/login(?:$|\/)/);
  if (!match) return DEFAULT_ADMIN_LOGIN_ENTRY;
  try { return normalizeAdminLoginEntry(match[1]); } catch { return DEFAULT_ADMIN_LOGIN_ENTRY; }
}

/**
 * 免登录路由白名单（path 前缀匹配）
 * 命中白名单的路由不会触发 token 校验
 */
export const ROUTE_WHITELIST: string[] = ['/', '/features', '/quick-start', `${ADMIN_ROUTE_PREFIX}/install`, ADMIN_LOGIN_PATH, `${ADMIN_ROUTE_PREFIX}/redirect`, `${ADMIN_ROUTE_PREFIX}/403`, `${ADMIN_ROUTE_PREFIX}/404`];
