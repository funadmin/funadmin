import type { PermissionModel } from '@/api/system/permission';
import { fail, ok, type MockRoute } from '../types';

const now = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
const menuBoundPermissionIds = new Set([228]);

const permissionList: PermissionModel[] = [
  {
    id: 192,
    parentId: 0,
    module: 'backend',
    code: '',
    object: '',
    action: '',
    name: '系统管理',
    resourceType: 'group',
    status: 1,
    isPublic: 0,
    sort: 10,
    sourceType: 'admin_web',
    sourceName: 'system'
  },
  {
    id: 227,
    parentId: 192,
    module: 'backend',
    code: '',
    object: '',
    action: '',
    name: '权限资源',
    resourceType: 'group',
    status: 1,
    isPublic: 0,
    sort: 45,
    sourceType: 'admin_web',
    sourceName: 'permission'
  },
  ...[
    [228, 'tree', '权限资源树', 10],
    [229, 'detail', '权限资源详情', 20],
    [230, 'create', '新增权限资源', 30],
    [231, 'update', '编辑权限资源', 40],
    [232, 'delete', '删除权限资源', 50]
  ].map(([id, action, name, sort]) => ({
    id: Number(id),
    parentId: 227,
    module: 'backend',
    code: `backend/systempermission:${action}`,
    object: 'systempermission',
    action: String(action),
    name: String(name),
    resourceType: 'route' as const,
    status: 1 as const,
    isPublic: 0 as const,
    sort: Number(sort),
    sourceType: 'admin_web',
    sourceName: 'permission'
  }))
];

function normalize(body: Record<string, any>, current?: PermissionModel): PermissionModel {
  const module = String(body.module ?? current?.module ?? 'backend').trim().toLowerCase();
  const resourceType = body.resourceType === 'group' ? 'group' : 'route';
  const object = resourceType === 'route'
    ? String(body.object ?? current?.object ?? '').trim().toLowerCase().replace(new RegExp(`^${module}[\\/.]`, 'i'), '')
    : '';
  const action = resourceType === 'route' ? String(body.action ?? current?.action ?? '').trim().toLowerCase() : '';
  return {
    id: current?.id ?? Math.max(0, ...permissionList.map((item) => item.id)) + 1,
    parentId: Math.max(0, Number(body.parentId ?? current?.parentId ?? 0)),
    module,
    code: resourceType === 'route' && object && action ? `${module}/${object.replace(/[\\/]/g, '.')}:${action}` : '',
    object: object.replace(/[\\/]/g, '.'),
    action,
    name: String(body.name ?? current?.name ?? '').trim(),
    resourceType,
    status: Number(body.status ?? current?.status ?? 1) === 1 ? 1 : 0,
    isPublic: Number(body.isPublic ?? current?.isPublic ?? 0) === 1 ? 1 : 0,
    sort: Math.max(0, Number(body.sort ?? current?.sort ?? 999)),
    sourceType: current?.sourceType ?? 'manual',
    sourceName: current?.sourceName ?? '',
    createdAt: current?.createdAt ?? now(),
    updatedAt: now()
  };
}

function validate(item: PermissionModel, currentId = 0): string | null {
  if (!item.name) return '权限资源名称不能为空';
  if (!/^[a-z][a-z0-9_]{0,49}$/.test(item.module)) return '应用标识格式不正确';
  if (item.parentId > 0 && !permissionList.some((row) => row.id === item.parentId)) return '上级权限资源不存在';
  if (item.parentId === currentId || descendantIds(currentId).includes(item.parentId)) {
    return '不能将权限资源移动到自身或下级节点';
  }
  if (item.resourceType === 'route' && (!item.object || !item.action)) return '路由资源必须填写控制器和动作';
  if (item.code && permissionList.some((row) => row.id !== currentId && row.code === item.code)) return '权限标识已存在';
  return null;
}

function descendantIds(id: number): number[] {
  if (!id) return [];
  const result: number[] = [];
  const queue = [id];
  while (queue.length) {
    const parentId = queue.shift()!;
    permissionList.forEach((item) => {
      if (item.parentId === parentId && !result.includes(item.id)) {
        result.push(item.id);
        queue.push(item.id);
      }
    });
  }
  return result;
}

function buildTree(rows: PermissionModel[], parentId = 0): PermissionModel[] {
  return rows
    .filter((item) => item.parentId === parentId)
    .sort((a, b) => a.sort - b.sort || a.id - b.id)
    .map((item) => {
      const children = buildTree(rows, item.id);
      return children.length ? { ...item, children } : { ...item };
    });
}

function remove(ids: number[]) {
  if (!ids.length) return fail('请选择要删除的权限资源', 422);
  if (ids.some((id) => !permissionList.some((item) => item.id === id))) return fail('部分权限资源不存在', 404);
  if (permissionList.some((item) => ids.includes(item.parentId))) return fail('请先删除下级权限资源', 422);
  if (ids.some((id) => menuBoundPermissionIds.has(id))) return fail('权限资源已绑定菜单，不能删除', 422);
  ids.forEach((id) => {
    const index = permissionList.findIndex((item) => item.id === id);
    if (index >= 0) permissionList.splice(index, 1);
  });
  return ok({ removed: ids.length }, '删除成功');
}

export const permissionMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/system/permission/tree',
    handler: () => ok(buildTree(permissionList))
  },
  {
    method: 'GET',
    url: /^\/system\/permission\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const item = permissionList.find((row) => row.id === Number(pathParams.id));
      return item ? ok({ ...item }) : fail('权限资源不存在', 404);
    }
  },
  {
    method: 'POST',
    url: '/system/permission',
    handler: ({ body }) => {
      const item = normalize(body);
      const error = validate(item);
      if (error) return fail(error, 422);
      permissionList.push(item);
      return ok(item, '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/permission\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const id = Number(pathParams.id);
      const index = permissionList.findIndex((row) => row.id === id);
      if (index < 0) return fail('权限资源不存在', 404);
      const item = normalize(body, permissionList[index]);
      const error = validate(item, id);
      if (error) return fail(error, 422);
      permissionList[index] = item;
      return ok(item, '保存成功');
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/permission\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => remove([Number(pathParams.id)])
  },
  {
    method: 'DELETE',
    url: '/system/permission',
    handler: ({ body, params }) => {
      const raw = (body?.ids || params?.ids || []) as Array<number | string>;
      return remove(Array.from(new Set(raw.map(Number).filter((id) => id > 0))));
    }
  }
];
