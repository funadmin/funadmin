import type { MemberLevelModel } from '@/api/system/memberLevel';
import { fail, ok, page, type MockRoute } from '../types';

interface MockMemberLevel extends MemberLevelModel {
  deleted: boolean;
}

const now = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
const referencedIds = new Set([1]);
const rows: MockMemberLevel[] = [
  { id: 1, name: '倔强青铜', amount: '0.00', discount: 100, thumb: '', status: 1, sort: 0, description: '', createdAt: '', updatedAt: '2020-09-05 21:53:28', deletedAt: '', deleted: false },
  { id: 2, name: '秩序白银', amount: '1000.00', discount: 99, thumb: '', status: 1, sort: 0, description: '', createdAt: '', updatedAt: '2020-08-17 19:22:47', deletedAt: '', deleted: false },
  { id: 3, name: '荣耀黄金', amount: '3000.00', discount: 94, thumb: '', status: 1, sort: 0, description: '', createdAt: '', updatedAt: '2020-08-17 19:01:13', deletedAt: '', deleted: false },
  { id: 4, name: '尊贵铂金', amount: '10000.00', discount: 95, thumb: '', status: 1, sort: 0, description: '', createdAt: '', updatedAt: '2020-11-27 23:15:06', deletedAt: '', deleted: false },
  { id: 5, name: '永恒钻石', amount: '50000.00', discount: 93, thumb: '', status: 1, sort: 0, description: '', createdAt: '', updatedAt: '2020-08-17 19:08:47', deletedAt: '', deleted: false },
  { id: 7, name: '默认', amount: '11.00', discount: 100, thumb: '', status: 1, sort: 0, description: '', createdAt: '2020-09-05 22:37:57', updatedAt: '2020-12-13 13:24:41', deletedAt: '', deleted: false }
];

function visible(row: MockMemberLevel): MemberLevelModel {
  const { deleted: _deleted, ...data } = row;
  return { ...data };
}

function idsFrom(body: Record<string, any>): number[] {
  return Array.from(new Set((Array.isArray(body.ids) ? body.ids : []).map(Number).filter((id) => id > 0)));
}

function matches(row: MockMemberLevel, params: Record<string, any>) {
  const recycled = Number(params.recycled || 0) === 1;
  const name = String(params.name || '').trim().toLowerCase();
  const status = params.status;
  return row.deleted === recycled
    && (!name || row.name.toLowerCase().includes(name))
    && (status === undefined || status === '' || row.status === Number(status));
}

function payload(body: Record<string, any>, current?: MockMemberLevel) {
  const name = String(body.name ?? current?.name ?? '').trim();
  const rawAmount = String(body.amount ?? current?.amount ?? '0').trim();
  const discount = Number(body.discount ?? current?.discount ?? 100);
  const sort = Number(body.sort ?? current?.sort ?? 0);
  const description = String(body.description ?? current?.description ?? '').trim();
  const thumb = String(body.thumb ?? current?.thumb ?? '').trim();
  let error = '';
  if (!name) error = '会员等级名称不能为空';
  else if (name.length > 30) error = '会员等级名称不能超过 30 个字符';
  else if (!/^\d{1,8}(?:\.\d{1,2})?$/.test(rawAmount)) error = '等级金额必须是 0 至 99999999.99 的数字，最多两位小数';
  else if (!Number.isInteger(discount) || discount < 0 || discount > 100) error = '等级折扣必须在 0 至 100 之间';
  else if (!Number.isInteger(sort) || sort < 0) error = '排序不能小于 0';
  else if (description.length > 200) error = '等级描述不能超过 200 个字符';
  else if (thumb.length > 255) error = '缩略图地址不能超过 255 个字符';
  return {
    data: {
      name,
      amount: Number(rawAmount || 0).toFixed(2),
      discount,
      thumb,
      status: Number(body.status ?? current?.status ?? 1) === 1 ? 1 as const : 0 as const,
      sort,
      description
    },
    error
  };
}

function targets(ids: number[], onlyTrashed: boolean): MockMemberLevel[] | ReturnType<typeof fail> {
  if (!ids.length) return fail('请选择要操作的会员等级', 422);
  const selected = rows.filter((row) => ids.includes(row.id) && row.deleted === onlyTrashed);
  if (selected.length !== ids.length) return fail(onlyTrashed ? '部分会员等级不存在或不在回收站' : '部分会员等级不存在或已在回收站', 404);
  if (ids.some((id) => referencedIds.has(id))) return fail('会员等级仍被会员引用，不能删除', 422);
  return selected;
}

export const memberLevelMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/system/member-level',
    handler: ({ params }) => {
      const list = rows.filter((row) => matches(row, params)).sort((a, b) => a.sort - b.sort || a.id - b.id).map(visible);
      const current = Math.max(1, Number(params.page) || 1);
      const pageSize = Math.max(1, Number(params.pageSize) || 20);
      const start = (current - 1) * pageSize;
      return ok(page(list.slice(start, start + pageSize), list.length, current, pageSize));
    }
  },
  {
    method: 'GET',
    url: '/system/member-level/export',
    handler: ({ params }) => ok(rows.filter((row) => matches(row, params)).sort((a, b) => a.sort - b.sort || a.id - b.id).map(visible))
  },
  {
    method: 'POST',
    url: '/system/member-level/import',
    handler: ({ body }) => {
      const imported = Array.isArray(body.rows) ? body.rows : [];
      if (!imported.length) return fail('导入数据不能为空', 422);
      const names = new Set(rows.map((row) => row.name.toLowerCase()));
      const pending: MockMemberLevel[] = [];
      for (const item of imported) {
        const result = payload(item);
        if (result.error) return fail(result.error, 422);
        if (names.has(result.data.name.toLowerCase())) return fail(`会员等级名称“${result.data.name}”已存在`, 422);
        names.add(result.data.name.toLowerCase());
        const time = now();
        pending.push({
          id: Math.max(0, ...rows.map((row) => row.id), ...pending.map((row) => row.id)) + 1,
          ...result.data,
          createdAt: time,
          updatedAt: time,
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
    url: /^\/system\/member-level\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const row = rows.find((item) => item.id === Number(pathParams.id));
      return row ? ok(visible(row)) : fail('会员等级不存在', 404);
    }
  },
  {
    method: 'POST',
    url: '/system/member-level',
    handler: ({ body }) => {
      const result = payload(body);
      if (result.error) return fail(result.error, 422);
      if (rows.some((row) => row.name.toLowerCase() === result.data.name.toLowerCase())) return fail('会员等级名称已存在', 422);
      const time = now();
      const row: MockMemberLevel = {
        id: Math.max(...rows.map((item) => item.id)) + 1,
        ...result.data,
        createdAt: time,
        updatedAt: time,
        deletedAt: '',
        deleted: false
      };
      rows.push(row);
      return ok(visible(row), '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/member-level\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const id = Number(pathParams.id);
      const row = rows.find((item) => item.id === id && !item.deleted);
      if (!row) return fail('会员等级不存在', 404);
      const result = payload(body, row);
      if (result.error) return fail(result.error, 422);
      if (rows.some((item) => item.id !== id && item.name.toLowerCase() === result.data.name.toLowerCase())) return fail('会员等级名称已存在', 422);
      Object.assign(row, result.data, { updatedAt: now() });
      return ok(visible(row), '保存成功');
    }
  },
  {
    method: 'POST',
    url: /^\/system\/member-level\/(\d+)\/status$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const row = rows.find((item) => item.id === Number(pathParams.id) && !item.deleted);
      if (!row) return fail('会员等级不存在', 404);
      row.status = Number(body.status) === 1 ? 1 : 0;
      row.updatedAt = now();
      return ok(visible(row), '状态更新成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/member-level',
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
    url: '/system/member-level/restore',
    handler: ({ body }) => {
      const ids = idsFrom(body);
      if (!ids.length) return fail('请选择要恢复的会员等级', 422);
      const selected = rows.filter((row) => ids.includes(row.id) && row.deleted);
      if (selected.length !== ids.length) return fail('部分会员等级不存在或不在回收站', 404);
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
    url: '/system/member-level/destroy',
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
