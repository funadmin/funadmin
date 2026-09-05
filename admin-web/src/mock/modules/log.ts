import { ok, fail, type MockRoute } from '../types';

interface OperationLog {
  id: number;
  username: string;
  appName: string;
  sourceType: 'system' | 'plugin';
  sourceName: string;
  controller: string;
  action: string;
  name: string;
  method: string;
  url: string;
  ip: string;
  status: 0 | 1;
  responseCode: number;
  durationMs: number;
  requestId: string;
  createdAt: string;
  getData?: string;
  postData?: string;
  agent?: string;
  errorMessage?: string;
}

const operationLogs: OperationLog[] = [
  {
    id: 1,
    username: 'admin',
    appName: 'backend',
    sourceType: 'system',
    sourceName: 'core',
    controller: 'SystemRole',
    action: 'update',
    name: 'Update role',
    method: 'PUT',
    url: 'system/role/2',
    ip: '127.0.0.1',
    status: 1,
    responseCode: 200,
    durationMs: 12,
    requestId: 'mock-request-1',
    createdAt: '2026-01-01 10:00:00',
    getData: '{}',
    postData: '{"name":"运营角色"}',
    agent: 'Mock browser'
  }
];

function paginate<T>(rows: T[], page = 1, pageSize = 10) {
  const start = Math.max(0, page - 1) * pageSize;
  return { list: rows.slice(start, start + pageSize), total: rows.length, page, pageSize };
}

function idsFrom(body: any, pathId?: string): number[] {
  if (pathId) return [Number(pathId)];
  const raw = body?.ids ?? [];
  return (Array.isArray(raw) ? raw : String(raw).split(','))
    .map(Number)
    .filter((id) => Number.isInteger(id) && id > 0);
}

export const logMockHandlers: MockRoute[] = [
  {
    method: 'GET',
    url: '/system/log/operation',
    handler: ({ params }) => {
      const { page = 1, pageSize = 10, username, appName, sourceType, sourceName, status, startTime, endTime } = params || {};
      const rows = operationLogs.filter((row) => {
        if (username && !row.username.includes(String(username))) return false;
        if (appName && row.appName !== appName) return false;
        if (sourceType && row.sourceType !== sourceType) return false;
        if (sourceName && row.sourceName !== sourceName) return false;
        if (status !== undefined && status !== '' && row.status !== Number(status)) return false;
        if (startTime && row.createdAt < String(startTime)) return false;
        if (endTime && row.createdAt > String(endTime)) return false;
        return true;
      });
      return ok(paginate(rows, Number(page), Number(pageSize)));
    }
  },
  {
    method: 'GET',
    url: /^\/system\/log\/operation\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const row = operationLogs.find((item) => item.id === Number(pathParams.id));
      return row ? ok(row) : fail('日志不存在');
    }
  },
  {
    method: 'DELETE',
    url: '/system/log/operation',
    handler: ({ body }) => {
      const ids = idsFrom(body);
      if (!ids.length) return fail('请选择要删除的日志');
      for (let index = operationLogs.length - 1; index >= 0; index--) {
        if (ids.includes(operationLogs[index].id)) operationLogs.splice(index, 1);
      }
      return ok({ removed: ids.length }, '删除成功');
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/log\/operation\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const ids = idsFrom({}, pathParams.id);
      const index = operationLogs.findIndex((row) => row.id === ids[0]);
      if (index < 0) return fail('日志不存在');
      operationLogs.splice(index, 1);
      return ok({ removed: 1 }, '删除成功');
    }
  }
];
