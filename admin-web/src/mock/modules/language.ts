import type { LanguageModel } from '@/api/system/language';
import { fail, ok, page, type MockRoute } from '../types';

interface MockLanguage extends LanguageModel {
  deleted: boolean;
}

const now = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
const rows: MockLanguage[] = [
  { id: 1, name: 'zh-cn', isDefault: 1, status: 1, createdAt: '', updatedAt: '', deleted: false },
  { id: 2, name: 'en-us', isDefault: 0, status: 1, createdAt: '2021-07-14 10:21:58', updatedAt: '2021-07-14 10:21:58', deleted: false }
];

function visible(row: MockLanguage): LanguageModel {
  const { deleted: _deleted, ...data } = row;
  return { ...data };
}

function validName(value: unknown): { name: string; error?: string } {
  const name = String(value ?? '').trim();
  if (!name) return { name, error: '语言名称不能为空' };
  if (name.length > 20) return { name, error: '语言名称不能超过 20 个字符' };
  return { name };
}

function isDefault(row: MockLanguage) {
  return row.isDefault === 1 || row.name.toLowerCase() === 'zh-cn';
}

function idsFrom(body: Record<string, any>, pathId?: string): number[] {
  const raw = pathId ? [pathId] : Array.isArray(body.ids) ? body.ids : [];
  return Array.from(new Set(raw.map(Number).filter((id) => id > 0)));
}

export const languageMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/system/language',
    handler: ({ params }) => {
      const keyword = String(params.name || '').trim().toLowerCase();
      const list = rows
        .filter((row) => !row.deleted && (!keyword || row.name.toLowerCase().includes(keyword)))
        .sort((a, b) => b.isDefault - a.isDefault || a.id - b.id)
        .map(visible);
      const current = Math.max(1, Number(params.page) || 1);
      const pageSize = Math.max(1, Number(params.pageSize) || 20);
      const start = (current - 1) * pageSize;
      return ok(page(list.slice(start, start + pageSize), list.length, current, pageSize));
    }
  },
  {
    method: 'GET',
    url: /^\/system\/language\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const row = rows.find((item) => item.id === Number(pathParams.id) && !item.deleted);
      return row ? ok(visible(row)) : fail('语言不存在', 404);
    }
  },
  {
    method: 'POST',
    url: '/system/language',
    handler: ({ body }) => {
      const { name, error } = validName(body.name);
      if (error) return fail(error, 422);
      if (rows.some((row) => row.name.toLowerCase() === name.toLowerCase())) return fail('语言名称已存在', 422);
      const row: MockLanguage = {
        id: Math.max(...rows.map((item) => item.id)) + 1,
        name,
        isDefault: 0,
        status: 1,
        createdAt: now(),
        updatedAt: now(),
        deleted: false
      };
      rows.push(row);
      return ok(visible(row), '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/language\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const id = Number(pathParams.id);
      const row = rows.find((item) => item.id === id && !item.deleted);
      if (!row) return fail('语言不存在', 404);
      if (isDefault(row)) return fail('默认语言不能重命名', 422);
      const { name, error } = validName(body.name ?? row.name);
      if (error) return fail(error, 422);
      if (rows.some((item) => item.id !== id && item.name.toLowerCase() === name.toLowerCase())) return fail('语言名称已存在', 422);
      row.name = name;
      row.updatedAt = now();
      return ok(visible(row), '保存成功');
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/language\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ body, pathParams }) => remove(idsFrom(body, pathParams.id))
  },
  {
    method: 'DELETE',
    url: '/system/language',
    handler: ({ body }) => remove(idsFrom(body))
  }
];

function remove(ids: number[]) {
  if (!ids.length) return fail('请选择要删除的语言', 422);
  const targets = rows.filter((row) => ids.includes(row.id));
  if (targets.length !== ids.length) return fail('部分语言不存在', 404);
  if (targets.some(isDefault)) return fail('默认语言不能删除', 422);
  for (let index = rows.length - 1; index >= 0; index--) {
    if (ids.includes(rows[index].id)) rows.splice(index, 1);
  }
  return ok({ removed: targets.length }, '删除成功');
}
