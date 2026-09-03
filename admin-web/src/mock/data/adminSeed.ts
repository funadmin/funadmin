/**
 * Generic mock seed data aligned with docs/api.md.
 */
import type { MockRoleRow, MockUserRow } from './adminSeed.types';

export type { MockRoleRow, MockUserRow } from './adminSeed.types';

/** 默认演示账号 */
export const ADMIN_DEMO_ACCOUNTS = {
  admin: { username: 'admin', password: '123456' },
  guest: { username: 'guest', password: '123456' }
} as const;

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
    menuIds: [100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114],
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
          { id: 112, parentId: 11, name: '后端组', sort: 2, status: 1 as const, leader: '赵后端' },
          { id: 113, parentId: 11, name: '测试组', sort: 3, status: 1 as const, leader: '钱测试' }
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
      name: 'System',
      path: '/system',
      component: 'Layout',
      redirect: '/system/user',
      type: 'M',
      icon: 'i-ep-setting',
      title: '系统管理',
      sort: 10,
      hidden: false,
      keepAlive: false,
      affix: false,
      children: [
        {
          id: 101,
          parentId: 100,
          name: 'SystemUser',
          path: 'user',
          component: 'system/user/index',
          type: 'C',
          icon: 'i-ep-user',
          title: '用户管理',
          sort: 1,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:user:list'
        },
        {
          id: 102,
          parentId: 100,
          name: 'SystemRole',
          path: 'role',
          component: 'system/role/index',
          type: 'C',
          icon: 'i-ep-avatar',
          title: '角色管理',
          sort: 2,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:role:list'
        },
        {
          id: 103,
          parentId: 100,
          name: 'SystemMenu',
          path: 'menu',
          component: 'system/menu/index',
          type: 'C',
          icon: 'i-ep-menu',
          title: '菜单管理',
          sort: 3,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:menu:list'
        },
        {
          id: 107,
          parentId: 100,
          name: 'SystemDept',
          path: 'dept',
          component: 'system/dept/index',
          type: 'C',
          icon: 'i-ep-office-building',
          title: '部门管理',
          sort: 4,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:dept:list'
        },
        {
          id: 108,
          parentId: 100,
          name: 'SystemDict',
          path: 'dict',
          component: 'system/dict/index',
          type: 'C',
          icon: 'i-ep-collection',
          title: '字典管理',
          sort: 5,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:dict:list'
        },
        {
          id: 109,
          parentId: 100,
          name: 'SystemUploadDemo',
          path: 'upload-demo',
          component: 'system/upload-demo/index',
          type: 'C',
          icon: 'i-ep-upload-filled',
          title: '上传演示',
          sort: 6,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:upload:demo'
        },
        {
          id: 113,
          parentId: 100,
          name: 'SystemRichEditorDemo',
          path: 'rich-editor-demo',
          component: 'system/rich-editor-demo/index',
          type: 'C',
          icon: 'i-ep-edit',
          title: '富文本编辑器',
          sort: 8,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:rich:demo'
        },
        {
          id: 114,
          parentId: 100,
          name: 'SystemSortableDemo',
          path: 'sortable-demo',
          component: 'demo/sortable',
          type: 'C',
          icon: 'i-ep-rank',
          title: '拖拽排序演示',
          sort: 9,
          hidden: false,
          keepAlive: true,
          affix: false,
          permission: 'system:demo:sortable'
        },
        {
          id: 110,
          parentId: 100,
          name: 'SystemLog',
          path: 'log',
          type: 'M',
          icon: 'i-ep-document-copy',
          title: '日志管理',
          sort: 7,
          hidden: false,
          keepAlive: false,
          affix: false,
          children: [
            {
              id: 111,
              parentId: 110,
              name: 'SystemLogOperation',
              path: 'operation',
              component: 'system/log/operation',
              type: 'C',
              icon: 'i-ep-tickets',
              title: '操作日志',
              sort: 1,
              hidden: false,
              keepAlive: true,
              affix: false,
              permission: 'system:log:operation'
            },
            {
              id: 112,
              parentId: 110,
              name: 'SystemLogLogin',
              path: 'login',
              component: 'system/log/login',
              type: 'C',
              icon: 'i-ep-key',
              title: '登录日志',
              sort: 2,
              hidden: false,
              keepAlive: true,
              affix: false,
              permission: 'system:log:login'
            }
          ]
        },
        {
          id: 104,
          parentId: 100,
          name: 'SystemDemoFolder',
          path: 'demo-nested',
          type: 'M' as const,
          icon: 'i-ep-folder-opened',
          title: '多级演示',
          sort: 4,
          hidden: false,
          keepAlive: false,
          affix: false,
          children: [
            {
              id: 105,
              parentId: 104,
              name: 'SystemDemoSub',
              path: 'sub',
              type: 'M' as const,
              icon: 'i-ep-files',
              title: '二级目录',
              sort: 1,
              hidden: false,
              keepAlive: false,
              affix: false,
              children: [
                {
                  id: 106,
                  parentId: 105,
                  name: 'SystemDemoLeaf',
                  path: 'leaf',
                  component: 'system/user/index',
                  type: 'C' as const,
                  icon: 'i-ep-document',
                  title: '三级页面',
                  sort: 1,
                  hidden: false,
                  keepAlive: true,
                  affix: false,
                  permission: 'system:user:list'
                }
              ]
            }
          ]
        }
      ]
    }
  ];
}

export function cloneDeep<T>(v: T): T {
  return JSON.parse(JSON.stringify(v));
}
