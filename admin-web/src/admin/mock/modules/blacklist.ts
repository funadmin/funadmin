import type { BlacklistModel } from '@/api/system/blacklist';
import { fail, ok, page, type MockRoute } from '../types';

interface MockBlacklist extends BlacklistModel {
  deleted: boolean;
}

const timestamp = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
const rows: MockBlacklist[] = [
  {
    id: 1,
    ip: '192.0.2.10',
    remark: '演示黑名单记录',
    status: 1,
    createdAt: timestamp(),
    updatedAt: timestamp(),
    deletedAt: '',
    deleted: false
  }
];

function visible(item: MockBlacklist): BlacklistModel {
  const { deleted: _deleted, ...data } = item;
  return { ...data };
}

function selectedIds(body: Record<string, any>, params: Record<string, any>): number[] {
  const raw = (body?.ids || params?.ids || []) as Array<number | string>;
  return Array.from(new Set(raw.map(Number).filter((id) => id > 0)));
}

function matches(item: MockBlacklist, params: Record<string, any>): boolean {
  const recycled = Number(params.recycled || 0) === 1;
  const ip = String(params.ip || '').trim().toLowerCase();
  const status = params.status;
  return item.deleted === recycled
    && (!ip || item.ip.toLowerCase().includes(ip))
    && (status === undefined || status === '' || item.status === Number(status));
}

function validate(data: Pick<BlacklistModel, 'ip' | 'remark'>): string | null {
  if (!data.ip.trim()) return 'IP/规则不能为空';
  if (data.ip.length > 50) return 'IP/规则不能超过 50 个字符';
  if (data.remark.length > 200) return '备注不能超过 200 个字符';
  return null;
}

export const blacklistMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/system/blacklist',
    handler: ({ params }) => {
      const list = rows.filter((item) => matches(item, params)).sort((a, b) => b.id - a.id).map(visible);
      const current = Math.max(1, Number(params.page) || 1);
      const pageSize = Math.max(1, Number(params.pageSize) || 20);
      const start = (current - 1) * pageSize;
      return ok(page(list.slice(start, start + pageSize), list.length, current, pageSize));
    }
  },
  {
    method: 'GET',
    url: '/system/blacklist/export',
    handler: ({ params }) => ok(rows.filter((item) => matches(item, params)).sort((a, b) => b.id - a.id).map(visible))
  },
  {
    method: 'GET',
    url: /^\/system\/blacklist\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const item = rows.find((row) => row.id === Number(pathParams.id));
      return item ? ok(visible(item)) : fail('黑名单记录不存在', 404);
    }
  },
  {
    method: 'POST',
    url: '/system/blacklist',
    handler: ({ body }) => {
      const ip = String(body.ip || '').trim();
      const remark = String(body.remark || '').trim();
      const error = validate({ ip, remark });
      if (error) return fail(error, 422);
      const item: MockBlacklist = {
        id: Math.max(0, ...rows.map((row) => row.id)) + 1,
        ip,
        remark,
        status: Number(body.status) === 1 ? 1 : 0,
        createdAt: timestamp(),
        updatedAt: timestamp(),
        deletedAt: '',
        deleted: false
      };
      rows.push(item);
      return ok(visible(item), '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/blacklist\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const item = rows.find((row) => row.id === Number(pathParams.id) && !row.deleted);
      if (!item) return fail('黑名单记录不存在', 404);
      const ip = String(body.ip ?? item.ip).trim();
      const remark = String(body.remark ?? item.remark).trim();
      const error = validate({ ip, remark });
      if (error) return fail(error, 422);
      Object.assign(item, { ip, remark, status: Number(body.status ?? item.status) === 1 ? 1 : 0, updatedAt: timestamp() });
      return ok(visible(item), '保存成功');
    }
  },
  {
    method: 'POST',
    url: /^\/system\/blacklist\/(\d+)\/status$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const item = rows.find((row) => row.id === Number(pathParams.id) && !row.deleted);
      if (!item) return fail('黑名单记录不存在', 404);
      item.status = Number(body.status) === 1 ? 1 : 0;
      item.updatedAt = timestamp();
      return ok(visible(item), '状态更新成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/blacklist',
    handler: ({ body, params }) => {
      const ids = selectedIds(body, params);
      if (!ids.length) return fail('请选择要移入回收站的记录', 422);
      const targets = rows.filter((item) => ids.includes(item.id) && !item.deleted);
      if (targets.length !== ids.length) return fail('部分黑名单记录不存在或已在回收站', 404);
      targets.forEach((item) => {
        item.deleted = true;
        item.deletedAt = timestamp();
      });
      return ok({ removed: targets.length }, '已移入回收站');
    }
  },
  {
    method: 'POST',
    url: '/system/blacklist/restore',
    handler: ({ body, params }) => {
      const ids = selectedIds(body, params);
      if (!ids.length) return fail('请选择要恢复的记录', 422);
      const targets = rows.filter((item) => ids.includes(item.id) && item.deleted);
      if (targets.length !== ids.length) return fail('部分黑名单记录不存在或不在回收站', 404);
      targets.forEach((item) => {
        item.deleted = false;
        item.deletedAt = '';
        item.updatedAt = timestamp();
      });
      return ok({ restored: targets.length }, '恢复成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/blacklist/destroy',
    handler: ({ body, params }) => {
      const ids = selectedIds(body, params);
      if (!ids.length) return fail('请选择要永久删除的记录', 422);
      const targets = rows.filter((item) => ids.includes(item.id) && item.deleted);
      if (targets.length !== ids.length) return fail('部分黑名单记录不存在或不在回收站', 404);
      for (let index = rows.length - 1; index >= 0; index--) {
        if (ids.includes(rows[index].id)) rows.splice(index, 1);
      }
      return ok({ removed: targets.length }, '永久删除成功');
    }
  },
  {
    method: 'POST',
    url: '/system/blacklist/import',
    handler: ({ body }) => {
      const input = Array.isArray(body.rows) ? body.rows : [];
      if (!input.length) return fail('导入数据不能为空', 422);
      if (input.length > 1000) return fail('单次最多导入 1000 条记录', 422);
      const errors: string[] = [];
      let created = 0;
      input.forEach((row, index) => {
        const ip = String(row.ip || '').trim();
        const remark = String(row.remark || '').trim();
        const error = validate({ ip, remark });
        if (error) {
          errors.push(`第 ${index + 2} 行：${error}`);
          return;
        }
        rows.push({
          id: Math.max(0, ...rows.map((item) => item.id)) + 1,
          ip,
          remark,
          status: Number(row.status) === 1 ? 1 : 0,
          createdAt: timestamp(),
          updatedAt: timestamp(),
          deletedAt: '',
          deleted: false
        });
        created++;
      });
      return ok({ created, skipped: errors.length, errors }, errors.length ? '导入完成，部分记录已跳过' : '导入成功');
    }
  }
];
