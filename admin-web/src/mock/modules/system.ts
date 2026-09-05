/**
 * 系统模块 Mock：用户/角色/菜单（数据源自 `mock/data/adminSeed`，对齐通用 RBAC）
 */
import {
  ADMIN_ROLE_ROWS,
  buildAdminUserRows,
  cloneDeep,
  getAdminMenuTreeSeed
} from '../data/adminSeed';
import type { MockRoleRow, MockUserRow } from '../data/adminSeed.types';
import { fail, ok, page, type MockRoute } from '../types';

const userList: MockUserRow[] = buildAdminUserRows();
const roleList: MockRoleRow[] = cloneDeep(ADMIN_ROLE_ROWS);
const menuTree: API.MenuItem[] = getAdminMenuTreeSeed();

function paginate<T>(list: T[], params: any) {
  const p = Number(params.page) || 1;
  const size = Number(params.pageSize) || 10;
  const start = (p - 1) * size;
  return page(list.slice(start, start + size), list.length, p, size);
}

function filterByKeyword<T extends { username?: string; nickname?: string; name?: string; code?: string }>(
  list: T[],
  keyword?: string
): T[] {
  if (!keyword) return list;
  const k = String(keyword).toLowerCase();
  return list.filter((item) =>
    [item.username, item.nickname, item.name, item.code].some((v) => v && String(v).toLowerCase().includes(k))
  );
}

export const systemMockHandlers: MockRoute[] = [
  // ========== 用户 ==========
  {
    method: 'GET',
    url: '/system/user',
    handler: ({ params }) => {
      const filtered = filterByKeyword(userList, params.keyword);
      return ok(paginate(filtered, params));
    }
  },
  {
    method: 'GET',
    url: /^\/system\/user\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const u = userList.find((x) => x.id === Number(pathParams.id));
      return u ? ok(u) : fail('用户不存在');
    }
  },
  {
    method: 'POST',
    url: '/system/user',
    handler: ({ body }) => {
      const id = Math.max(...userList.map((u) => u.id)) + 1;
      const newUser: MockUserRow = {
        id,
        username: body.username,
        nickname: body.nickname || body.username,
        email: body.email || '',
        mobile: body.mobile || '',
        status: body.status ?? 1,
        roleIds: body.roleIds || [],
        deptId: body.deptId || 1,
        createdAt: new Date().toISOString().slice(0, 19).replace('T', ' '),
        updatedAt: new Date().toISOString().slice(0, 19).replace('T', ' ')
      };
      userList.unshift(newUser);
      return ok(newUser, '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/user\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const u = userList.find((x) => x.id === Number(pathParams.id));
      if (!u) return fail('用户不存在');
      Object.assign(u, body);
      return ok(u, '更新成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/user',
    handler: ({ params, body }) => {
      const raw = (params?.ids || body?.ids || []) as Array<number | string>;
      const ids: number[] = raw.map(Number);
      for (let i = userList.length - 1; i >= 0; i--) {
        if (ids.includes(userList[i].id)) userList.splice(i, 1);
      }
      return ok(null, '删除成功');
    }
  },
  {
    method: 'POST',
    url: '/system/user/import',
    handler: ({ body }) => {
      const rows: any[] = Array.isArray(body?.rows) ? body.rows : [];
      const errors: string[] = [];
      let created = 0;
      let skipped = 0;
      const usedNames = new Set(userList.map((u) => u.username));
      const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

      rows.forEach((row, idx) => {
        const lineNo = idx + 2; // 含表头
        const username = String(row.username || '').trim();
        if (!username) {
          errors.push(`第 ${lineNo} 行：账号为空，已跳过`);
          skipped++;
          return;
        }
        if (usedNames.has(username)) {
          errors.push(`第 ${lineNo} 行：账号「${username}」已存在，已跳过`);
          skipped++;
          return;
        }
        const id = Math.max(0, ...userList.map((u) => u.id)) + 1;
        userList.unshift({
          id,
          username,
          nickname: String(row.nickname || username),
          email: String(row.email || ''),
          mobile: String(row.mobile || ''),
          status: row.status === 0 || row.status === '0' ? 0 : 1,
          roleIds: Array.isArray(row.roleIds) ? row.roleIds.map(Number) : [],
          deptId: Number(row.deptId) || 1,
          createdAt: now,
          updatedAt: now
        });
        usedNames.add(username);
        created++;
      });

      return ok({ created, skipped, errors }, `导入完成：成功 ${created}，跳过 ${skipped}`);
    }
  },

  // ========== 角色 ==========
  {
    method: 'GET',
    url: '/system/role',
    handler: ({ params }) => {
      const filtered = filterByKeyword(roleList, params.keyword);
      return ok(paginate(filtered, params));
    }
  },
  {
    method: 'GET',
    url: '/system/role/all',
    handler: () => ok(roleList)
  },
  {
    method: 'GET',
    url: /^\/system\/role\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const r = roleList.find((x) => x.id === Number(pathParams.id));
      return r ? ok(r) : fail('角色不存在');
    }
  },
  {
    method: 'POST',
    url: '/system/role',
    handler: ({ body }) => {
      const id = Math.max(...roleList.map((r) => r.id), 0) + 1;
      const role: MockRoleRow = {
        id,
        name: body.name,
        code: body.code,
        remark: body.remark || '',
        status: body.status ?? 1,
        menuIds: body.menuIds || [],
        createdAt: new Date().toISOString().slice(0, 19).replace('T', ' ')
      };
      roleList.unshift(role);
      return ok(role, '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/role\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const r = roleList.find((x) => x.id === Number(pathParams.id));
      if (!r) return fail('角色不存在');
      Object.assign(r, body);
      return ok(r, '更新成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/role',
    handler: ({ params }) => {
      const ids: number[] = (params.ids || []).map(Number);
      for (let i = roleList.length - 1; i >= 0; i--) {
        if (ids.includes(roleList[i].id)) roleList.splice(i, 1);
      }
      return ok(null, '删除成功');
    }
  },
  {
    method: 'POST',
    url: /^\/system\/role\/(\d+)\/menus$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const r = roleList.find((x) => x.id === Number(pathParams.id));
      if (!r) return fail('角色不存在');
      r.menuIds = body.menuIds || [];
      return ok(null, '权限分配成功');
    }
  },
  {
    method: 'POST',
    url: /^\/system\/user\/(\d+)\/reset-password$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const u = userList.find((x) => x.id === Number(pathParams.id));
      return u ? ok(null, '密码已重置') : fail('用户不存在');
    }
  },
  {
    method: 'POST',
    url: /^\/system\/user\/(\d+)\/status$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const u = userList.find((x) => x.id === Number(pathParams.id));
      if (!u) return fail('用户不存在');
      u.status = (body.status === 1 ? 1 : 0) as 0 | 1;
      return ok(null, '状态已更新');
    }
  },

  // ========== 菜单 ==========
  {
    method: 'GET',
    url: '/system/menu/tree',
    handler: () => ok(menuTree)
  },
  {
    method: 'GET',
    url: /^\/system\/menu\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const id = Number(pathParams.id);
      const found = findMenuById(menuTree, id);
      return found ? ok(found) : fail('菜单不存在');
    }
  },
  {
    method: 'POST',
    url: '/system/menu',
    handler: ({ body }) => {
      const id = Date.now();
      const node: API.MenuItem = {
        id,
        parentId: body.parentId || 0,
        routeName: body.routeName || '',
        path: body.path || '',
        component: body.component,
        redirect: body.redirect,
        type: body.type || 'C',
        icon: body.icon,
        name: body.name,
        sort: body.sort ?? 0,
        hidden: !!body.hidden,
        keepAlive: !!body.keepAlive,
        affix: !!body.affix,
        permission: body.permission
      };
      insertMenu(menuTree, node);
      return ok(node, '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/menu\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const id = Number(pathParams.id);
      const found = findMenuById(menuTree, id);
      if (!found) return fail('菜单不存在');
      const oldParentId = found.parentId;
      Object.assign(found, body, { id });
      if (body.parentId !== undefined && body.parentId !== oldParentId) {
        removeMenu(menuTree, id);
        insertMenu(menuTree, found);
      }
      return ok(found, '更新成功');
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/menu\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      removeMenu(menuTree, Number(pathParams.id));
      return ok(null, '删除成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/menu',
    handler: ({ params, body }) => {
      const raw = (params?.ids ?? body?.ids ?? []) as Array<number | string>;
      const ids = (Array.isArray(raw) ? raw : [raw]).map(Number);
      let removed = 0;
      ids.forEach((id) => {
        if (removeMenu(menuTree, id)) removed++;
      });
      return ok({ removed }, `已删除 ${removed} 条`);
    }
  }
];

function findMenuById(list: API.MenuItem[], id: number): API.MenuItem | undefined {
  for (const m of list) {
    if (m.id === id) return m;
    if (m.children?.length) {
      const r = findMenuById(m.children, id);
      if (r) return r;
    }
  }
  return undefined;
}

function insertMenu(list: API.MenuItem[], node: API.MenuItem) {
  if (!node.parentId) {
    list.push(node);
    return;
  }
  const parent = findMenuById(list, node.parentId);
  if (parent) {
    parent.children = parent.children || [];
    parent.children.push(node);
  } else {
    list.push(node);
  }
}

function removeMenu(list: API.MenuItem[], id: number): boolean {
  for (let i = 0; i < list.length; i++) {
    if (list[i].id === id) {
      list.splice(i, 1);
      return true;
    }
    if (list[i].children?.length && removeMenu(list[i].children!, id)) return true;
  }
  return false;
}
