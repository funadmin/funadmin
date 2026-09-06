/**
 * 应用全局配置
 * 优先读取 .env 中以 VITE_APP_ 开头的变量；没有则使用默认值
 */
const env = import.meta.env;

export const APP_CONFIG = {
  /** 应用标题 */
  title: env.VITE_APP_TITLE || 'Admin Console',
  /** 版本号 */
  version: env.VITE_APP_VERSION || '0.1.0',
  /** API 基础路径（与 vite proxy 前缀一致） */
  baseApi: env.VITE_APP_BASE_API || '/console',
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

/**
 * 免登录路由白名单（path 前缀匹配）
 * 命中白名单的路由不会触发 token 校验
 */
export const ROUTE_WHITELIST: string[] = ['/install', '/login', '/redirect', '/403', '/404'];
