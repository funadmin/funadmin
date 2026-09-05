import type { MemberGroupModel } from '@/api/system/memberGroup';
import { fail, ok, page, type MockRoute } from '../types';

interface MockMemberGroup extends MemberGroupModel {
  deleted: boolean;
}

const now = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
const referencedIds = new Set([1]);
const rows: MockMemberGroup[] = [
  { id: 1, name: '默认组', icon: 'i-ep-user', status: 1, isDefault: 1, createdAt: '2018-01-08 12:41:08', updatedAt: '', deletedAt: '', deleted: false },
  { id: 2, name: 'VIP 会员', icon: 'i-ep-wallet', status: 1, isDefault: 0, createdAt: now(), updatedAt: now(), deletedAt: '', deleted: false }
];

function visible(row: MockMemberGroup): MemberGroupModel {
  const { deleted: _deleted, ...data } = row;
  return { ...data };
}

function idsFrom(body: Record<string, any>): number[] {
  const raw = Array.isArray(body.ids) ? body.ids : [];
  return Array.from(new Set(raw.map(Number).filter((id) => id > 0)));
}

function matches(row: MockMemberGroup, params: Record<string, any>) {
  const recycled = Number(params.recycled || 0) === 1;
  const name = String(params.name || '').trim().toLowerCase();
  const status = params.status;
  return row.deleted === recycled
    && (!name || row.name.toLowerCase().includes(name))
    && (status === undefined || status === '' || row.status === Number(status));
}

function validate(nameValue: unknown): { name: string; error?: string } {
  const name = String(nameValue ?? '').trim();
  if (!name) return { name, error: '会员组名称不能为空' };
  if (name.length > 50) return { name, error: '会员组名称不能超过 50 个字符' };
  return { name };
}

function targets(ids: number[], onlyTrashed: boolean): MockMemberGroup[] | ReturnType<typeof fail> {
  if (!ids.length) return fail('请选择要操作的会员组', 422);
  if (ids.includes(1)) return fail('默认会员组不能删除', 422);
  const selected = rows.filter((row) => ids.includes(row.id) && row.deleted === onlyTrashed);
  if (selected.length !== ids.length) return fail(onlyTrashed ? '部分会员组不存在或不在回收站' : '部分会员组不存在或已在回收站', 404);
  if (ids.some((id) => referencedIds.has(id))) return fail('会员组仍被会员引用，不能删除', 422);
  return selected;
}

export const memberGroupMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/system/member-group',
    handler: ({ params }) => {
      const list = rows.filter((row) => matches(row, params)).sort((a, b) => a.id - b.id).map(visible);
      const current = Math.max(1, Number(params.page) || 1);
      const pageSize = Math.max(1, Number(params.pageSize) || 20);
      const start = (current - 1) * pageSize;
      return ok(page(list.slice(start, start + pageSize), list.length, current, pageSize));
    }
  },
  {
    method: 'GET',
    url: '/system/member-group/export',
    handler: ({ params }) => ok(rows.filter((row) => matches(row, params)).sort((a, b) => a.id - b.id).map(visible))
  },
  {
    method: 'POST',
    url: '/system/member-group/import',
    handler: ({ body }) => {
      const imported = Array.isArray(body.rows) ? body.rows : [];
      if (!imported.length) return fail('导入数据不能为空', 422);
      const names = new Set(rows.map((row) => row.name.toLowerCase()));
      const pending: MockMemberGroup[] = [];
      for (const item of imported) {
        const { name, error } = validate(item.name);
        if (error) return fail(error, 422);
        if (names.has(name.toLowerCase())) return fail(`会员组名称“${name}”已存在`, 422);
        names.add(name.toLowerCase());
        pending.push({
          id: Math.max(0, ...rows.map((row) => row.id), ...pending.map((row) => row.id)) + 1,
          name,
          icon: String(item.icon ?? '').slice(0, 50),
          status: Number(item.status) === 0 ? 0 : 1,
          isDefault: 0,
          createdAt: now(),
          updatedAt: now(),
          deletedAt: '',
          deleted: false
        });
      }
      rows.push(...pending);
      return ok({ created: pending.length }, '导入成功');
    }
  },
  {
    method: 'GET',
    url: /^\/system\/member-group\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const row = rows.find((item) => item.id === Number(pathParams.id));
      return row ? ok(visible(row)) : fail('会员组不存在', 404);
    }
  },
  {
    method: 'POST',
    url: '/system/member-group',
    handler: ({ body }) => {
      const { name, error } = validate(body.name);
      if (error) return fail(error, 422);
      if (rows.some((row) => row.name.toLowerCase() === name.toLowerCase())) return fail('会员组名称已存在', 422);
      const row: MockMemberGroup = {
        id: Math.max(...rows.map((item) => item.id)) + 1,
        name,
        icon: String(body.icon ?? '').slice(0, 50),
        status: Number(body.status) === 1 ? 1 : 0,
        isDefault: 0,
        createdAt: now(),
        updatedAt: now(),
        deletedAt: '',
        deleted: false
      };
      rows.push(row);
      return ok(visible(row), '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/member-group\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const id = Number(pathParams.id);
      const row = rows.find((item) => item.id === id && !item.deleted);
      if (!row) return fail('会员组不存在', 404);
      const { name, error } = validate(body.name ?? row.name);
      if (error) return fail(error, 422);
      if (rows.some((item) => item.id !== id && item.name.toLowerCase() === name.toLowerCase())) return fail('会员组名称已存在', 422);
      row.name = name;
      row.icon = String(body.icon ?? row.icon).slice(0, 50);
      row.status = Number(body.status ?? row.status) === 1 ? 1 : 0;
      row.updatedAt = now();
      return ok(visible(row), '保存成功');
    }
  },
  {
    method: 'POST',
    url: /^\/system\/member-group\/(\d+)\/status$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const row = rows.find((item) => item.id === Number(pathParams.id) && !item.deleted);
      if (!row) return fail('会员组不存在', 404);
      row.status = Number(body.status) === 1 ? 1 : 0;
      row.updatedAt = now();
      return ok(visible(row), '状态更新成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/member-group',
    handler: ({ body }) => {
      const selected = targets(idsFrom(body), false);
      if (!Array.isArray(selected)) return selected;
      selected.forEach((row) => {
        row.deleted = true;
        row.deletedAt = now();
      });
      return ok({ removed: selected.length }, '已移入回收站');
    }
  },
  {
    method: 'POST',
    url: '/system/member-group/restore',
    handler: ({ body }) => {
      const ids = idsFrom(body);
      if (!ids.length) return fail('请选择要恢复的会员组', 422);
      const selected = rows.filter((row) => ids.includes(row.id) && row.deleted);
      if (selected.length !== ids.length) return fail('部分会员组不存在或不在回收站', 404);
      selected.forEach((row) => {
        row.deleted = false;
        row.deletedAt = '';
        row.updatedAt = now();
      });
      return ok({ restored: selected.length }, '恢复成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/member-group/destroy',
    handler: ({ body }) => {
      const ids = idsFrom(body);
      const selected = targets(ids, true);
      if (!Array.isArray(selected)) return selected;
      for (let index = rows.length - 1; index >= 0; index--) {
        if (ids.includes(rows[index].id)) rows.splice(index, 1);
      }
      return ok({ removed: selected.length }, '永久删除成功');
    }
  }
];
