/**
 * 日志 Mock：操作日志、登录日志
 * - 启动时按规则生成 200 条种子数据
 * - 支持分页、按 username/module/status/时间区间过滤
 * - 删除单条 / 批量删除 / 清空
 */
import { ok, fail, type MockRoute } from '../types';

interface OperationLog {
  id: number;
  username: string;
  module: string;
  action: string;
  method: 'GET' | 'POST' | 'PUT' | 'DELETE';
  url: string;
  ip: string;
  location: string;
  status: 0 | 1;
  duration: number;
  errorMsg?: string;
  createdAt: string;
}

interface LoginLog {
  id: number;
  username: string;
  ip: string;
  location: string;
  browser: string;
  os: string;
  status: 0 | 1;
  message?: string;
  createdAt: string;
}

const MODULES = [
  { module: '用户管理', actions: ['新增用户', '编辑用户', '删除用户', '重置密码'], url: '/system/user', methods: ['POST', 'PUT', 'DELETE'] as const },
  { module: '角色管理', actions: ['新增角色', '编辑角色', '删除角色'], url: '/system/role', methods: ['POST', 'PUT', 'DELETE'] as const },
  { module: '菜单管理', actions: ['新增菜单', '编辑菜单'], url: '/system/menu', methods: ['POST', 'PUT'] as const },
  { module: '部门管理', actions: ['新增部门', '编辑部门'], url: '/system/dept', methods: ['POST', 'PUT'] as const },
  { module: '字典管理', actions: ['新增字典', '编辑字典项'], url: '/system/dict', methods: ['POST', 'PUT'] as const },
  { module: '认证模块', actions: ['退出登录', '刷新令牌'], url: '/auth/logout', methods: ['POST'] as const }
];

const USERS = ['admin', 'manager', 'designer', 'tester', 'developer', 'guest'];
const LOCATIONS = ['北京', '上海', '广州', '深圳', '杭州', '成都', '武汉'];
const BROWSERS = ['Chrome 120', 'Edge 119', 'Firefox 121', 'Safari 17'];
const OS_LIST = ['Windows 11', 'Windows 10', 'macOS 14', 'Ubuntu 22.04'];

function randIp(): string {
  return `${rand(10, 220)}.${rand(0, 255)}.${rand(0, 255)}.${rand(1, 254)}`;
}

function rand(min: number, max: number): number {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function pick<T>(arr: readonly T[]): T {
  return arr[rand(0, arr.length - 1)];
}

function fmtDate(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

let opSeq = 1;
let loginSeq = 1;

function buildOperation(): OperationLog[] {
  const list: OperationLog[] = [];
  const now = Date.now();
  for (let i = 0; i < 200; i++) {
    const m = pick(MODULES);
    const success = Math.random() > 0.15;
    const created = new Date(now - rand(0, 60 * 24 * 60) * 60 * 1000); // 60 天内
    list.push({
      id: opSeq++,
      username: pick(USERS),
      module: m.module,
      action: pick(m.actions),
      method: pick(m.methods),
      url: m.url,
      ip: randIp(),
      location: pick(LOCATIONS),
      status: (success ? 1 : 0) as 0 | 1,
      duration: rand(15, 800),
      errorMsg: success ? undefined : pick(['权限不足', '参数校验失败', '业务异常: 数据已存在', '数据库连接超时']),
      createdAt: fmtDate(created)
    });
  }
  return list.sort((a, b) => (a.createdAt < b.createdAt ? 1 : -1));
}

function buildLogin(): LoginLog[] {
  const list: LoginLog[] = [];
  const now = Date.now();
  for (let i = 0; i < 200; i++) {
    const success = Math.random() > 0.1;
    const created = new Date(now - rand(0, 60 * 24 * 60) * 60 * 1000);
    list.push({
      id: loginSeq++,
      username: pick(USERS),
      ip: randIp(),
      location: pick(LOCATIONS),
      browser: pick(BROWSERS),
      os: pick(OS_LIST),
      status: (success ? 1 : 0) as 0 | 1,
      message: success ? '登录成功' : pick(['密码错误', '账号被锁定', '验证码错误', '账号不存在']),
      createdAt: fmtDate(created)
    });
  }
  return list.sort((a, b) => (a.createdAt < b.createdAt ? 1 : -1));
}

const operationLogs: OperationLog[] = buildOperation();
const loginLogs: LoginLog[] = buildLogin();

function paginate<T>(arr: T[], page = 1, pageSize = 10): { list: T[]; total: number; page: number; pageSize: number } {
  const total = arr.length;
  const start = (page - 1) * pageSize;
  return { list: arr.slice(start, start + pageSize), total, page, pageSize };
}

function inRange(time: string, start?: string, end?: string): boolean {
  if (start && time < start) return false;
  if (end && time > end) return false;
  return true;
}

function parseIds(body: any, params: any): number[] {
  const raw = (body && body.ids) ?? (params && params.ids) ?? [];
  if (Array.isArray(raw)) return raw.map((v) => Number(v)).filter((v) => Number.isFinite(v));
  if (typeof raw === 'string') {
    return raw
      .split(',')
      .map((v) => Number(v))
      .filter((v) => Number.isFinite(v));
  }
  return [];
}

export const logMockHandlers: MockRoute[] = [
  /* ---------- 操作日志 ---------- */
  {
    method: 'GET',
    url: '/system/log/operation',
    handler: ({ params }) => {
      const { page = 1, pageSize = 10, username, module, status, startTime, endTime } = params || {};
      const filtered = operationLogs.filter((it) => {
        if (username && !it.username.includes(String(username))) return false;
        if (module && it.module !== module) return false;
        if (status !== undefined && status !== '' && Number(it.status) !== Number(status)) return false;
        if (!inRange(it.createdAt, startTime, endTime)) return false;
        return true;
      });
      return ok(paginate(filtered, Number(page), Number(pageSize)));
    }
  },
  {
    method: 'GET',
    url: /^\/system\/log\/operation\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const id = Number(pathParams.id);
      const item = operationLogs.find((it) => it.id === id);
      return item ? ok(item) : fail('日志不存在');
    }
  },
  {
    method: 'DELETE',
    url: '/system/log/operation/clear',
    handler: () => {
      operationLogs.length = 0;
      return ok(null, '已清空操作日志');
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/log\/operation\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const id = Number(pathParams.id);
      const idx = operationLogs.findIndex((it) => it.id === id);
      if (idx < 0) return fail('日志不存在');
      operationLogs.splice(idx, 1);
      return ok(null, '删除成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/log/operation',
    handler: ({ body, params }) => {
      const ids = parseIds(body, params);
      if (!ids.length) return fail('未指定要删除的日志');
      let removed = 0;
      ids.forEach((id) => {
        const idx = operationLogs.findIndex((it) => it.id === id);
        if (idx >= 0) {
          operationLogs.splice(idx, 1);
          removed++;
        }
      });
      return ok(null, `已删除 ${removed} 条`);
    }
  },

  /* ---------- 登录日志 ---------- */
  {
    method: 'GET',
    url: '/system/log/login',
    handler: ({ params }) => {
      const { page = 1, pageSize = 10, username, status, startTime, endTime } = params || {};
      const filtered = loginLogs.filter((it) => {
        if (username && !it.username.includes(String(username))) return false;
        if (status !== undefined && status !== '' && Number(it.status) !== Number(status)) return false;
        if (!inRange(it.createdAt, startTime, endTime)) return false;
        return true;
      });
      return ok(paginate(filtered, Number(page), Number(pageSize)));
    }
  },
  {
    method: 'DELETE',
    url: '/system/log/login/clear',
    handler: () => {
      loginLogs.length = 0;
      return ok(null, '已清空登录日志');
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/log\/login\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const id = Number(pathParams.id);
      const idx = loginLogs.findIndex((it) => it.id === id);
      if (idx < 0) return fail('日志不存在');
      loginLogs.splice(idx, 1);
      return ok(null, '删除成功');
    }
  },
  {
    method: 'DELETE',
    url: '/system/log/login',
    handler: ({ body, params }) => {
      const ids = parseIds(body, params);
      if (!ids.length) return fail('未指定要删除的日志');
      let removed = 0;
      ids.forEach((id) => {
        const idx = loginLogs.findIndex((it) => it.id === id);
        if (idx >= 0) {
          loginLogs.splice(idx, 1);
          removed++;
        }
      });
      return ok(null, `已删除 ${removed} 条`);
    }
  }
];
