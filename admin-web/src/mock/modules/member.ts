import type { MemberModel, MemberPayload } from '@/api/system/member';
import { fail, ok, page, type MockRoute } from '../types';

interface MockMember extends MemberModel {
  deleted: boolean;
}

const groupOptions = [
  { id: 1, name: '默认组' },
  { id: 2, name: 'VIP 会员' }
];
const levelOptions = [
  { id: 1, name: '倔强青铜' },
  { id: 2, name: '秩序白银' },
  { id: 3, name: '荣耀黄金' },
  { id: 4, name: '尊贵铂金' },
  { id: 5, name: '永恒钻石' },
  { id: 7, name: '默认' }
];
const now = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
const rows: MockMember[] = [
  {
    id: 1,
    username: 'demo_member',
    mobile: '13800138000',
    email: 'member@example.com',
    sex: '0',
    groupIds: [1],
    groupNames: ['默认组'],
    levelId: 1,
    levelName: '倔强青铜',
    avatar: '',
    status: 1,
    loginCount: 3,
    lastLoginAt: '2024-01-10 12:00:00',
    lastLoginIp: '127.0.0.1',
    createdAt: '2024-01-01 00:00:00',
    updatedAt: '2024-01-10 12:00:00',
    deletedAt: '',
    deleted: false
  }
];

function visible(row: MockMember): MemberModel {
  const { deleted: _deleted, ...data } = row;
  return { ...data, groupIds: [...data.groupIds], groupNames: [...data.groupNames] };
}

function idsFrom(body: Record<string, any>): number[] {
  return Array.from(new Set((Array.isArray(body.ids) ? body.ids : []).map(Number).filter((id) => id > 0)));
}

function groupNames(ids: number[]) {
  const map = new Map(groupOptions.map((item) => [item.id, item.name]));
  return ids.map((id) => map.get(id) || `#${id}`);
}

function levelName(id: number) {
  return levelOptions.find((item) => item.id === id)?.name || '';
}

function normalize(body: Record<string, any>, current?: MockMember): MemberPayload {
  let groupIds = body.groupIds ?? body.group_ids ?? body.groupId ?? body.group_id ?? current?.groupIds ?? [];
  if (!Array.isArray(groupIds)) groupIds = String(groupIds).split(/[,，]/);
  groupIds = Array.from(new Set(groupIds.map(Number).filter((id: number) => id > 0)));
  return {
    username: String(body.username ?? current?.username ?? '').trim(),
    mobile: String(body.mobile ?? current?.mobile ?? '').trim(),
    email: String(body.email ?? current?.email ?? '').trim(),
    sex: String(body.sex ?? current?.sex ?? '0') as MemberPayload['sex'],
    groupIds,
    levelId: Number(body.levelId ?? body.level_id ?? current?.levelId ?? 0),
    avatar: String(body.avatar ?? current?.avatar ?? '').trim(),
    status: Number(body.status ?? current?.status ?? 1) === 1 ? 1 : 0
  };
}

function validate(data: MemberPayload, excludeId = 0): string {
  if (data.username.length < 2 || data.username.length > 80) return '用户名长度必须为 2 至 80 个字符';
  if (/[\x00-\x1F\x7F<>]/u.test(data.username)) return '用户名包含非法字符';
  if (!/^[0-9+\- ]{6,20}$/.test(data.mobile)) return '请输入 6 至 20 位有效手机号';
  if (data.email && (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email) || data.email.length > 60)) {
    return '邮箱格式不正确或超过 60 个字符';
  }
  if (!['0', '1', '2'].includes(data.sex)) return '性别参数无效';
  if (!data.groupIds.length || data.groupIds.join(',').length > 50) return '请选择有效会员组，且分组数据不能超过 50 个字符';
  if (data.groupIds.some((id) => !groupOptions.some((item) => item.id === id))) return '会员组不存在、已删除或已停用';
  if (!levelOptions.some((item) => item.id === data.levelId)) return '会员等级不存在、已删除或已停用';
  if (data.avatar.length > 255) return '头像地址不能超过 255 个字符';
  if (rows.some((row) => row.id !== excludeId && row.username.toLowerCase() === data.username.toLowerCase())) return '用户名已存在';
  if (rows.some((row) => row.id !== excludeId && row.mobile === data.mobile)) return '手机号已存在';
  if (data.email && rows.some((row) => row.id !== excludeId && row.email.toLowerCase() === data.email.toLowerCase())) return '邮箱已存在';
  return '';
}

function matches(row: MockMember, params: Record<string, any>) {
  const recycled = Number(params.recycled || 0) === 1;
  const keyword = String(params.keyword || '').trim().toLowerCase();
  const status = params.status;
  const groupId = Number(params.groupId || 0);
  const levelId = Number(params.levelId || 0);
  return row.deleted === recycled
    && (!keyword || [row.username, row.mobile, row.email].some((value) => value.toLowerCase().includes(keyword)))
    && (status === undefined || status === '' || row.status === Number(status))
    && (!groupId || row.groupIds.includes(groupId))
    && (!levelId || row.levelId === levelId);
}

function createRow(data: MemberPayload): MockMember {
  const time = now();
  return {
    id: rows.length ? Math.max(...rows.map((item) => item.id)) + 1 : 1,
    ...data,
    groupIds: [...data.groupIds],
    groupNames: groupNames(data.groupIds),
    levelName: levelName(data.levelId),
    loginCount: 0,
    lastLoginAt: '',
    lastLoginIp: '0',
    createdAt: time,
    updatedAt: time,
    deletedAt: '',
    deleted: false
  };
}

export const memberMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/system/member',
    handler: ({ params }) => {
      const list = rows.filter((row) => matches(row, params)).sort((a, b) => b.id - a.id).map(visible);
      const current = Math.max(1, Number(params.page) || 1);
      const pageSize = Math.max(1, Number(params.pageSize) || 20);
      const start = (current - 1) * pageSize;
      return ok(page(list.slice(start, start + pageSize), list.length, current, pageSize));
    }
  },
  {
    method: 'GET',
    url: '/system/member/options',
    handler: () => ok({ groups: groupOptions.map((item) => ({ ...item })), levels: levelOptions.map((item) => ({ ...item })) })
  },
  {
    method: 'GET',
    url: '/system/member/export',
    handler: ({ params }) => ok(rows.filter((row) => matches(row, params)).sort((a, b) => b.id - a.id).map(visible))
  },
  {
    method: 'GET',
    url: /^\/system\/member\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const row = rows.find((item) => item.id === Number(pathParams.id));
      return row ? ok(visible(row)) : fail('会员不存在', 404);
    }
  },
  {
    method: 'POST',
    url: '/system/member',
    handler: ({ body }) => {
      const data = normalize(body);
      const error = validate(data);
      if (error) return fail(error, 422);
      const row = createRow(data);
      rows.push(row);
      return ok(visible(row), '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/member\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const id = Number(pathParams.id);
      const row = rows.find((item) => item.id === id && !item.deleted);
      if (!row) return fail('会员不存在', 404);
      const data = normalize(body, row);
      const error = validate(data, id);
      if (error) return fail(error, 422);
      Object.assign(row, data, {
        groupIds: [...data.groupIds],
        groupNames: groupNames(data.groupIds),
        levelName: levelName(data.levelId),
        updatedAt: now()
      });
      return ok(visible(row), '保存成功');
    }
  },
  {
    method: 'POST',
    url: /^\/system\/member\/(\d+)\/status$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const row = rows.find((item) => item.id === Number(pathParams.id) && !item.deleted);
      if (!row) return fail('会员不存在', 404);
      row.status = Number(body.status) === 1 ? 1 : 0;
      row.updatedAt = now();
      return ok(visible(row), '状态更新成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/member',
    handler: ({ body }) => {
      const ids = idsFrom(body);
      if (!ids.length) return fail('请选择要操作的会员', 422);
      const selected = rows.filter((row) => ids.includes(row.id) && !row.deleted);
      if (selected.length !== ids.length) return fail('部分会员不存在或已在回收站', 404);
      selected.forEach((row) => {
        row.deleted = true;
        row.deletedAt = now();
      });
      return ok({ removed: selected.length }, '已移入回收站');
    }
  },
  {
    method: 'POST',
    url: '/system/member/restore',
    handler: ({ body }) => {
      const ids = idsFrom(body);
      if (!ids.length) return fail('请选择要恢复的会员', 422);
      const selected = rows.filter((row) => ids.includes(row.id) && row.deleted);
      if (selected.length !== ids.length) return fail('部分会员不存在或不在回收站', 404);
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
    url: '/system/member/destroy',
    handler: ({ body }) => {
      const ids = idsFrom(body);
      if (!ids.length) return fail('请选择要操作的会员', 422);
      const selected = rows.filter((row) => ids.includes(row.id) && row.deleted);
      if (selected.length !== ids.length) return fail('部分会员不存在或不在回收站', 404);
      for (let index = rows.length - 1; index >= 0; index--) {
        if (ids.includes(rows[index].id)) rows.splice(index, 1);
      }
      return ok({ removed: selected.length }, '永久删除成功');
    }
  },
  {
    method: 'POST',
    url: '/system/member/import',
    handler: ({ body }) => {
      const imported = Array.isArray(body.rows) ? body.rows : [];
      if (!imported.length) return fail('导入数据不能为空', 422);
      if (imported.length > 1000) return fail('单次最多导入 1000 条会员', 422);
      let created = 0;
      const errors: string[] = [];
      imported.forEach((source, index) => {
        const data = normalize(source || {});
        const error = validate(data);
        if (error) {
          errors.push(`第 ${index + 2} 行：${error}`);
          return;
        }
        rows.push(createRow(data));
        created++;
      });
      return ok({ created, skipped: imported.length - created, errors }, errors.length ? '导入完成，部分数据未导入' : '导入成功');
    }
  }
];
