import type { AttachmentGroupModel, AttachmentModel } from '@/api/system/attachment';
import { fail, ok, page, type MockRoute } from '../types';

interface MockAttachment extends AttachmentModel {
  deleted?: boolean;
}

const now = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
let groupSeq = 2;
let attachmentSeq = 2;

const groups: AttachmentGroupModel[] = [
  { id: 1, parentId: 0, title: '默认', thumb: '', status: 1, sort: 999, isDefault: 1, createdAt: '', updatedAt: '' },
  { id: 2, parentId: 1, title: '示例图片', thumb: '', status: 1, sort: 1000, isDefault: 0, createdAt: now(), updatedAt: now() }
];

const attachments: MockAttachment[] = [
  {
    id: 1,
    groupId: 1,
    name: 'demo.png',
    originalName: '示例图片.png',
    path: 'https://picsum.photos/seed/funadmin-attachment/320/200',
    url: 'https://picsum.photos/seed/funadmin-attachment/320/200',
    thumb: 'https://picsum.photos/seed/funadmin-attachment/320/200',
    ext: 'png',
    size: 24576,
    mime: 'image/png',
    driver: 'mock',
    width: 320,
    height: 200,
    status: 1,
    createdAt: now(),
    updatedAt: now()
  }
];

export function attachmentGroupExists(id: number) {
  return groups.some((group) => group.id === id);
}

export function registerMockAttachment(input: {
  groupId: number;
  name: string;
  url: string;
  size: number;
  ext: string;
  mime: string;
}) {
  const time = now();
  attachments.push({
    id: ++attachmentSeq,
    groupId: input.groupId,
    name: `${Date.now()}-${input.name}`,
    originalName: input.name,
    path: input.url,
    url: input.url,
    thumb: input.mime.startsWith('image/') ? input.url : '',
    ext: input.ext,
    size: input.size,
    mime: input.mime,
    driver: 'mock',
    width: 0,
    height: 0,
    status: 1,
    createdAt: time,
    updatedAt: time
  });
}

function isDescendant(candidateId: number, currentId: number): boolean {
  const visited = new Set<number>();
  let cursor = candidateId;
  while (cursor > 0 && !visited.has(cursor)) {
    if (cursor === currentId) return true;
    visited.add(cursor);
    cursor = groups.find((item) => item.id === cursor)?.parentId || 0;
  }
  return false;
}

function buildTree(parentId = 0): AttachmentGroupModel[] {
  return groups
    .filter((group) => group.parentId === parentId)
    .sort((a, b) => a.sort - b.sort || a.id - b.id)
    .map((group) => {
      const children = buildTree(group.id);
      return { ...group, ...(children.length ? { children } : {}) };
    });
}

function idsFrom(body: Record<string, any>) {
  return Array.from(new Set((Array.isArray(body.ids) ? body.ids : []).map(Number).filter((id) => id > 0)));
}

function matchAttachment(row: AttachmentModel, params: Record<string, any>) {
  const keyword = String(params.keyword || '').trim().toLowerCase();
  const groupId = params.groupId;
  const mimeType = String(params.mimeType || '');
  const typeMatched = !mimeType
    || (mimeType === 'image' && row.mime.startsWith('image/'))
    || (mimeType === 'video' && row.mime.startsWith('video/'))
    || (mimeType === 'audio' && row.mime.startsWith('audio/'))
    || (mimeType === 'document' && ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt'].includes(row.ext))
    || (mimeType === 'archive' && ['zip', 'rar', '7z', 'tar', 'gz'].includes(row.ext));
  return (!keyword || row.originalName.toLowerCase().includes(keyword) || row.name.toLowerCase().includes(keyword))
    && (groupId === undefined || groupId === '' || row.groupId === Number(groupId))
    && typeMatched;
}

export const attachmentMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/system/attachment-group/tree',
    handler: () => ok(buildTree())
  },
  {
    method: 'GET',
    url: /^\/system\/attachment-group\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const group = groups.find((item) => item.id === Number(pathParams.id));
      return group ? ok({ ...group }) : fail('附件分组不存在', 404);
    }
  },
  {
    method: 'POST',
    url: '/system/attachment-group',
    handler: ({ body }) => {
      const title = String(body.title || '').trim();
      const parentId = Number(body.parentId || 0);
      if (!title) return fail('附件分组名称不能为空', 422);
      if (title.length > 100) return fail('附件分组名称不能超过 100 个字符', 422);
      if (parentId > 0 && !attachmentGroupExists(parentId)) return fail('上级附件分组不存在', 422);
      const time = now();
      const group: AttachmentGroupModel = {
        id: ++groupSeq,
        parentId,
        title,
        thumb: String(body.thumb || '').trim(),
        status: Number(body.status) === 1 ? 1 : 0,
        sort: Math.max(0, Number(body.sort) || 0),
        isDefault: 0,
        createdAt: time,
        updatedAt: time
      };
      groups.push(group);
      return ok({ ...group }, '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/attachment-group\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const id = Number(pathParams.id);
      const group = groups.find((item) => item.id === id);
      if (!group) return fail('附件分组不存在', 404);
      const title = String(body.title ?? group.title).trim();
      const parentId = Number(body.parentId ?? group.parentId);
      if (!title) return fail('附件分组名称不能为空', 422);
      if (title.length > 100) return fail('附件分组名称不能超过 100 个字符', 422);
      if (parentId > 0 && !attachmentGroupExists(parentId)) return fail('上级附件分组不存在', 422);
      if (parentId === id || isDescendant(parentId, id)) return fail('不能将附件分组移动到自身或下级', 422);
      Object.assign(group, {
        parentId,
        title,
        thumb: String(body.thumb ?? group.thumb).trim(),
        status: Number(body.status ?? group.status) === 1 ? 1 : 0,
        sort: Math.max(0, Number(body.sort ?? group.sort) || 0),
        updatedAt: now()
      });
      return ok({ ...group }, '保存成功');
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/attachment-group\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const id = Number(pathParams.id);
      if (id === 1) return fail('默认附件分组不能删除', 422);
      const index = groups.findIndex((item) => item.id === id);
      if (index < 0) return fail('附件分组不存在', 404);
      if (groups.some((item) => item.parentId === id)) return fail('请先删除下级附件分组', 422);
      attachments.forEach((item) => { if (item.groupId === id) item.groupId = 0; });
      groups.splice(index, 1);
      return ok(null, '删除成功，组内附件已移至未分组');
    }
  },
  {
    method: 'GET',
    url: '/system/attachment',
    handler: ({ params }) => {
      const list = attachments.filter((item) => matchAttachment(item, params)).sort((a, b) => b.id - a.id);
      const current = Math.max(1, Number(params.page) || 1);
      const pageSize = Math.max(1, Number(params.pageSize) || 20);
      const start = (current - 1) * pageSize;
      return ok(page(list.slice(start, start + pageSize).map((item) => ({ ...item })), list.length, current, pageSize));
    }
  },
  {
    method: 'GET',
    url: /^\/system\/attachment\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const item = attachments.find((row) => row.id === Number(pathParams.id));
      return item ? ok({ ...item }) : fail('附件不存在', 404);
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/attachment\/(\d+)\/name$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const item = attachments.find((row) => row.id === Number(pathParams.id));
      if (!item) return fail('附件不存在', 404);
      const name = String(body.name || '').trim();
      if (!name || name.length > 255) return fail('文件名称不能为空且不能超过 255 个字符', 422);
      item.originalName = name;
      item.updatedAt = now();
      return ok({ ...item }, '重命名成功');
    }
  },
  {
    method: 'POST',
    url: '/system/attachment/move',
    handler: ({ body }) => {
      const ids = idsFrom(body);
      const groupId = Number(body.groupId || 0);
      if (!ids.length) return fail('请选择要移动的附件', 422);
      if (groupId > 0 && !attachmentGroupExists(groupId)) return fail('目标附件分组不存在', 422);
      const selected = attachments.filter((item) => ids.includes(item.id));
      if (selected.length !== ids.length) return fail('部分附件不存在', 404);
      selected.forEach((item) => { item.groupId = groupId; item.updatedAt = now(); });
      return ok({ moved: selected.length }, '移动成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/attachment',
    handler: ({ body }) => {
      const ids = idsFrom(body);
      if (!ids.length) return fail('请选择要删除的附件', 422);
      const selected = attachments.filter((item) => ids.includes(item.id));
      if (selected.length !== ids.length) return fail('部分附件不存在', 404);
      for (let index = attachments.length - 1; index >= 0; index--) {
        if (ids.includes(attachments[index].id)) attachments.splice(index, 1);
      }
      return ok({ removed: selected.length }, '删除成功');
    }
  }
];
