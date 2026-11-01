import type { RouteRecordRaw } from 'vue-router';
// Layout 在 dynamic.ts 中也是静态导入，这里同样静态导入避免「dynamic + static 混用」打包警告
import Layout from '@/layout/index.vue';
import { ADMIN_LOGIN_PATH, ADMIN_ROUTE_PREFIX, ADMIN_HOME_PATH } from '@/config';

/**
 * 静态路由（无需登录或所有用户共享）
 * 注意：业务模块路由由后端菜单驱动，统一在 dynamic.ts 中根据用户菜单生成
 */
export const staticRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import('@/web/layout/public.vue'),
    name: 'PublicHome',
    meta: { title: 'FunAdmin', hidden: true, public: true }
  },
  {
    path: '/features',
    component: () => import('@/web/layout/public.vue'),
    name: 'PublicFeatures',
    meta: { title: '产品能力', hidden: true, public: true }
  },
  {
    path: '/quick-start',
    component: () => import('@/web/layout/public.vue'),
    name: 'PublicQuickStart',
    meta: { title: '快速开始', hidden: true, public: true }
  },
  {
    path: `${ADMIN_ROUTE_PREFIX}/install`,
    name: 'Install',
    component: () => import('@/views/install/index.vue'),
    meta: { title: '安装向导', hidden: true }
  },
  {
    path: ADMIN_LOGIN_PATH,
    name: 'Login',
    component: () => import('@/views/login/index.vue'),
    meta: { title: '登录', hidden: true }
  },
  {
    path: `${ADMIN_ROUTE_PREFIX}/redirect`,
    component: Layout,
    meta: { hidden: true },
    children: [
      {
        path: `${ADMIN_ROUTE_PREFIX}/redirect/:path(.*)`,
        name: 'Redirect',
        component: () => import('@/views/redirect/index.vue'),
        meta: { hidden: true }
      }
    ]
  },
  {
    path: ADMIN_ROUTE_PREFIX,
    component: Layout,
    redirect: ADMIN_HOME_PATH,
    meta: { hidden: true },
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/index.vue'),
        meta: {
          title: 'Dashboard',
          icon: 'i-ep-monitor',
          affix: true,
          keepAlive: true,
          rank: 0
        }
      },
      {
        path: 'profile',
        name: 'Profile',
        component: () => import('@/views/profile/index.vue'),
        meta: { title: '个人中心', icon: 'i-ep-user', hidden: true }
      },
      {
        // 会话/任务深链：直接定位到 AI 助手页并选中指定会话（tasks 面板随工作区展示）
        path: 'development/ai/conversations/:id/:panel?',
        name: 'AiConversationDeepLink',
        component: () => import('@/views/development/ai/index.vue'),
        meta: { title: 'AI 助手', icon: 'i-ep-chat-dot-round', hidden: true }
      },
      {
        path: 'form/data/:key',
        name: 'FormData',
        component: () => import('@/views/form/data.vue'),
        meta: { title: '表单数据', hidden: true }
      },
      {
        path: 'development/business/designer',
        name: 'BusinessDesigner',
        component: () => import('@/views/form/designer/index.vue'),
        meta: { title: '业务设计器', hidden: true, activeMenu: `${ADMIN_ROUTE_PREFIX}/development/business/mine`, permission: 'development:business:save' }
      },
      {
        path: 'development/business/runtime/:formKey',
        name: 'PublishedFormRuntime',
        component: () => import('@/views/form/published.vue'),
        meta: { title: '已发布表单', hidden: true }
      }
    ]
  },
  {
    path: `${ADMIN_ROUTE_PREFIX}/403`,
    name: 'Forbidden',
    component: () => import('@/views/error/403.vue'),
    meta: { title: '无权访问', hidden: true }
  },
  {
    path: `${ADMIN_ROUTE_PREFIX}/500`,
    name: 'ServerError',
    component: () => import('@/views/error/500.vue'),
    meta: { title: '服务异常', hidden: true }
  },
  {
    path: `${ADMIN_ROUTE_PREFIX}/:pathMatch(.*)*`,
    name: 'BootstrapNotFound',
    component: () => import('@/views/error/404.vue'),
    meta: { title: '页面加载中', hidden: true }
  }
];
