import type { RouteRecordRaw } from 'vue-router';
// Layout 在 dynamic.ts 中也是静态导入，这里同样静态导入避免「dynamic + static 混用」打包警告
import Layout from '@/layout/index.vue';

/**
 * 静态路由（无需登录或所有用户共享）
 * 注意：业务模块路由由后端菜单驱动，统一在 dynamic.ts 中根据用户菜单生成
 */
export const staticRoutes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/index.vue'),
    meta: { title: '登录', hidden: true }
  },
  {
    path: '/redirect',
    component: Layout,
    meta: { hidden: true },
    children: [
      {
        path: '/redirect/:path(.*)',
        name: 'Redirect',
        component: () => import('@/views/redirect/index.vue'),
        meta: { hidden: true }
      }
    ]
  },
  {
    path: '/',
    component: Layout,
    redirect: '/dashboard',
    meta: { hidden: true },
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/index.vue'),
        meta: {
          title: '仪表盘',
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
        path: 'demo/sortable',
        name: 'DemoSortable',
        component: () => import('@/views/demo/sortable.vue'),
        meta: { title: '拖拽排序 Demo', icon: 'i-ep-rank', rank: 999 }
      }
    ]
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('@/views/error/403.vue'),
    meta: { title: '无权访问', hidden: true }
  },
  {
    path: '/500',
    name: 'ServerError',
    component: () => import('@/views/error/500.vue'),
    meta: { title: '服务异常', hidden: true }
  }
  // 注意：/:pathMatch(.*)* 通配符不在此注册，
  // 改为在动态路由加载完成后由 guard.ts addRoute，确保业务路由优先匹配
];
