/**
 * 部门 Mock：树形 CRUD（数据源自 `mock/data/adminSeed`）
 */
import { ADMIN_DEPT_TREE_SEED, cloneDeep } from '../data/adminSeed';
import { fail, ok, type MockRoute } from '../types';

interface DeptModel {
  id: number;
  parentId: number;
  name: string;
  sort: number;
  status: 0 | 1;
  leader?: string;
  phone?: string;
  email?: string;
  children?: DeptModel[];
}

const tree: DeptModel[] = cloneDeep(ADMIN_DEPT_TREE_SEED);

let seq = 1000;
function nextId() {
  return ++seq;
}

function find(list: DeptModel[], id: number): DeptModel | null {
  for (const node of list) {
    if (node.id === id) return node;
    if (node.children) {
      const found = find(node.children, id);
      if (found) return found;
    }
  }
  return null;
}

function remove(list: DeptModel[], id: number): boolean {
  const idx = list.findIndex((n) => n.id === id);
  if (idx >= 0) {
    list.splice(idx, 1);
    return true;
  }
  for (const node of list) {
    if (node.children && remove(node.children, id)) return true;
  }
  return false;
}

function insert(list: DeptModel[], parentId: number, node: DeptModel): boolean {
  if (!parentId) {
    list.push(node);
    return true;
  }
  const parent = find(list, parentId);
  if (!parent) return false;
  parent.children = parent.children || [];
  parent.children.push(node);
  return true;
}

export const deptMockHandlers: MockRoute[] = [
  { method: 'GET', url: '/system/dept/tree', handler: () => ok(tree) },
  {
    method: 'GET',
    url: /^\/system\/dept\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const node = find(tree, Number(pathParams.id));
      return node ? ok(node) : fail('部门不存在');
    }
  },
  {
    method: 'POST',
    url: '/system/dept',
    handler: ({ body }) => {
      const node: DeptModel = {
        id: nextId(),
        parentId: Number(body?.parentId || 0),
        name: String(body?.name || ''),
        sort: Number(body?.sort || 0),
        status: (body?.status ?? 1) as 0 | 1,
        leader: body?.leader,
        phone: body?.phone,
        email: body?.email
      };
      if (!insert(tree, node.parentId, node)) return fail('父部门不存在');
      return ok(node, '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/dept\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const node = find(tree, Number(pathParams.id));
      if (!node) return fail('部门不存在');
      Object.assign(node, body, { id: node.id });
      return ok(node, '更新成功');
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/dept\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      if (!remove(tree, Number(pathParams.id))) return fail('部门不存在');
      return ok(null, '已删除');
    }
  },
  {
    method: 'DELETE',
    url: '/system/dept',
    handler: ({ params, body }) => {
      const raw = (params?.ids ?? body?.ids ?? []) as Array<number | string>;
      const ids = (Array.isArray(raw) ? raw : [raw]).map(Number);
      let removed = 0;
      ids.forEach((id) => {
        if (remove(tree, id)) removed++;
      });
      return ok({ removed }, `已删除 ${removed} 条`);
    }
  }
];
