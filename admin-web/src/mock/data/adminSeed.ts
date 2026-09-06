/**
 * Generic mock seed data aligned with docs/api.md.
 */
import type { MockRoleRow, MockUserRow } from './adminSeed.types';

export type { MockRoleRow, MockUserRow } from './adminSeed.types';

/** 默认演示账号 */
export const ADMIN_DEMO_ACCOUNTS: Record<'admin' | 'guest', { username: string; password: string }> = {
  admin: { username: 'admin', password: '123456' },
  guest: { username: 'guest', password: '123456' }
};

export const ADMIN_DEMO_USER = {
  id: 1,
  username: 'admin',
  email: 'admin@admin.com',
  mobile: '18397423845',
  roleIds: [1],
  createTimeUnix: 1482132862,
  updateTimeUnix: 1662192742
} as const;

function unixToDatetime(u: number): string {
  return new Date(u * 1000).toISOString().slice(0, 19).replace('T', ' ');
}

function ts(offsetDays = 0): string {
  return new Date(Date.now() - offsetDays * 86400000).toISOString().slice(0, 19).replace('T', ' ');
}

/** 角色：首条为超级管理员，其余为分页演示用 */
export const ADMIN_ROLE_ROWS: MockRoleRow[] = [
  {
    id: 1,
    name: '超级管理员',
    code: 'admin',
    remark: '拥有全部菜单与权限',
    status: 1,
    menuIds: [100, 101, 102, 103, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118],
    createdAt: unixToDatetime(1554298659)
  },
  {
    id: 2,
    name: '运营人员',
    code: 'operation',
    remark: '业务运营，可查看用户与角色',
    status: 1,
    menuIds: [101],
    createdAt: '2024-01-01 00:00:00'
  },
  {
    id: 3,
    name: '访客',
    code: 'guest',
    remark: '只读',
    status: 0,
    menuIds: [],
    createdAt: '2024-01-01 00:00:00'
  }
];

/**
 * 部门树：服务 Admin Console `/system/dept` 契约
 */
export const ADMIN_DEPT_TREE_SEED = [
  {
    id: 1,
    parentId: 0,
    name: '总公司',
    sort: 0,
    status: 1 as const,
    leader: '张总',
    phone: '13800000001',
    email: 'hq@demo.local',
    children: [
      {
        id: 11,
        parentId: 1,
        name: '研发中心',
        sort: 1,
        status: 1 as const,
        leader: '李工',
        phone: '13800000011',
        children: [
          { id: 111, parentId: 11, name: '前端组', sort: 1, status: 1 as const, leader: '王前端' },
          { id: 112, parentId: 11, name: '后端组', sort: 2, status: 1 as const, leader: '赵后端' }
        ]
      },
      {
        id: 12,
        parentId: 1,
        name: '产品中心',
        sort: 2,
        status: 1 as const,
        leader: '孙产品',
        phone: '13800000012',
        children: [
          { id: 121, parentId: 12, name: '设计组', sort: 1, status: 1 as const },
          { id: 122, parentId: 12, name: '运营组', sort: 2, status: 1 as const }
        ]
      },
      { id: 13, parentId: 1, name: '市场部', sort: 3, status: 1 as const },
      { id: 14, parentId: 1, name: '人事部', sort: 4, status: 0 as const }
    ]
  }
];

const DEPT_CYCLE = [1, 11, 111, 112, 12, 121, 13, 14];

/**
 * 用户表逻辑行：首条为超级管理员，其余填充多部门、多角色组合
 */
export function buildAdminUserRows(): MockUserRow[] {
  return Array.from({ length: 26 }, (_, i) => {
    const id = i + 1;
    const isAdmin = id === 1;
    return {
      id,
      username: isAdmin ? ADMIN_DEMO_USER.username : `user${String(i).padStart(2, '0')}`,
      nickname: isAdmin ? '超级管理员' : `演示用户${i}`,
      email: isAdmin ? ADMIN_DEMO_USER.email : `user${i}@demo.local`,
      mobile: isAdmin ? ADMIN_DEMO_USER.mobile : `138${String(10000000 + i).padStart(8, '0')}`,
      status: (isAdmin ? 1 : i % 5 === 0 ? 0 : 1) as 0 | 1,
      roleIds: isAdmin ? [...ADMIN_DEMO_USER.roleIds] : i % 3 === 0 ? [1, 2] : [2],
      deptId: DEPT_CYCLE[i % DEPT_CYCLE.length],
      createdAt: isAdmin ? unixToDatetime(ADMIN_DEMO_USER.createTimeUnix) : ts(i),
      updatedAt: isAdmin ? unixToDatetime(ADMIN_DEMO_USER.updateTimeUnix) : ts(Math.max(0, i - 1))
    };
  });
}

/** 菜单/权限规则树：与 `/auth/menus`、`/system/menu/tree` 同源快照 */
export function getAdminMenuTreeSeed(): API.MenuItem[] {
  return [
    {
      id: 100,
      parentId: 0,
      routeName: 'System',
      path: '/system',
      component: 'Layout',
      redirect: '/system/user',
      type: 'M',
      icon: 'i-ep-setting',
      name: '系统管理',
      sort: 10,
      hidden: false,
      keepAlive: false,
      affix: false,
      children: [
        {
          id: 101,
          parentId: 100,
          routeName: 'SystemUser',
          path: 'user',
          component: 'system/user/index',
          type: 'C',
          icon: 'i-ep-user',
          name: '用户管理',
          sort: 1,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:user:list'
        },
        {
          id: 102,
          parentId: 100,
          routeName: 'SystemRole',
          path: 'role',
          component: 'system/role/index',
          type: 'C',
          icon: 'i-ep-avatar',
          name: '角色管理',
          sort: 2,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:role:list'
        },
        {
          id: 103,
          parentId: 100,
          routeName: 'SystemMenu',
          path: 'menu',
          component: 'system/menu/index',
          type: 'C',
          icon: 'i-ep-menu',
          name: '菜单管理',
          sort: 3,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:menu:list'
        },
        {
          id: 107,
          parentId: 100,
          routeName: 'SystemDept',
          path: 'dept',
          component: 'system/dept/index',
          type: 'C',
          icon: 'i-ep-office-building',
          name: '部门管理',
          sort: 4,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:dept:list'
        },
        {
          id: 108,
          parentId: 100,
          routeName: 'SystemDict',
          path: 'dict',
          component: 'system/dict/index',
          type: 'C',
          icon: 'i-ep-collection',
          name: '字典管理',
          sort: 5,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:dict:list'
        },
        {
          id: 118,
          parentId: 100,
          routeName: 'SystemConfig',
          path: 'config',
          component: 'system/config/index',
          type: 'C',
          icon: 'i-ep-setting',
          name: '配置管理',
          sort: 14,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:config:list'
        },
        {
          id: 117,
          parentId: 100,
          routeName: 'SystemAttachment',
          path: 'attachment',
          component: 'system/attachment/index',
          type: 'C',
          icon: 'i-ep-picture-filled',
          name: '附件库',
          sort: 13,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:attachment:list'
        },
        {
          id: 116,
          parentId: 100,
          routeName: 'SystemMember',
          path: 'member',
          component: 'system/member/index',
          type: 'C',
          icon: 'i-ep-user',
          name: '会员管理',
          sort: 12,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:member:list'
        },
        {
          id: 115,
          parentId: 100,
          routeName: 'SystemMemberLevel',
          path: 'member-level',
          component: 'system/member-level/index',
          type: 'C',
          icon: 'i-ep-medal',
          name: '会员等级',
          sort: 11,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:member-level:list'
        },
        {
          id: 116,
          parentId: 100,
          routeName: 'SystemPlugin',
          path: 'plugin',
          component: 'system/plugin/index',
          type: 'C',
          icon: 'i-ep-box',
          name: '插件中心',
          sort: 13,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:plugin:list'
        },
        {
          id: 119,
          parentId: 100,
          routeName: 'SystemUpgrade',
          path: 'upgrade',
          component: 'system/upgrade/index',
          type: 'C',
          icon: 'i-ep-upload-filled',
          name: '系统升级',
          sort: 15,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:upgrade:list'
        },
        {
          id: 114,
          parentId: 100,
          routeName: 'SystemMemberGroup',
          path: 'member-group',
          component: 'system/member-group/index',
          type: 'C',
          icon: 'i-ep-user-filled',
          name: '会员组',
          sort: 10,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:member-group:list'
        },
        {
          id: 113,
          parentId: 100,
          routeName: 'SystemLanguage',
          path: 'language',
          component: 'system/language/index',
          type: 'C',
          icon: 'i-ep-connection',
          name: '多语言',
          sort: 9,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:language:list'
        },
        {
          id: 109,
          parentId: 100,
          routeName: 'SystemPermission',
          path: 'permission',
          component: 'system/permission/index',
          type: 'C',
          icon: 'i-ep-lock',
          name: '权限资源',
          sort: 6,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:permission:list'
        },
        {
          id: 110,
          parentId: 100,
          routeName: 'SystemLog',
          path: 'log',
          type: 'M',
          icon: 'i-ep-document-copy',
          name: '日志管理',
          sort: 8,
          hidden: false,
          keepAlive: false,
          affix: false,
          children: [
            {
              id: 111,
              parentId: 110,
              routeName: 'SystemLogOperation',
              path: 'operation',
              component: 'system/log/operation',
              type: 'C',
              icon: 'i-ep-tickets',
              name: '操作日志',
              sort: 1,
              hidden: false,
              keepAlive: true,
              affix: false,
              permission: 'system:log:operation'
            }
]
        }
      ]
    },
    {
      id: 200,
      parentId: 0,
      routeName: 'Development',
      path: '/development',
      component: 'Layout',
      redirect: '/development/crud',
      type: 'M',
      icon: 'i-ep-tools',
      name: '开发工具',
      sort: 20,
      hidden: false,
      keepAlive: false,
      affix: false,
      children: [
        {
          id: 201,
          parentId: 200,
          routeName: 'DevelopmentCrud',
          path: 'crud',
          component: 'development/crud/index',
          type: 'C',
          icon: 'i-ep-magic-stick',
          name: 'CRUD 生成器',
          sort: 1,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'development:crud:list'
        }
      ]
    }
  ];
}

export function cloneDeep<T>(v: T): T {
  return JSON.parse(JSON.stringify(v));
}
