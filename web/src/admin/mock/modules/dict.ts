/**
 * 字典 Mock：
 *   1) 通用字典数据查询：`/system/dict/:code/options`、`/system/dict/batch`
 *   2) 字典分类管理：`/system/dict/types` CRUD
 *   3) 字典项管理：    `/system/dict/items`  CRUD（按 typeCode 关联分类）
 */
import { fail, ok, type MockRoute } from '../types';

interface DictItem {
  id: number;
  typeCode: string;
  label: string;
  value: string;
  sort: number;
  status: 0 | 1;
  cssClass?: string;
  remark?: string;
}

interface DictType {
  id: number;
  code: string;
  name: string;
  status: 0 | 1;
  remark?: string;
  createdAt: string;
}

let typeSeq = 100;
let itemSeq = 1000;
function nextTypeId() {
  return ++typeSeq;
}
function nextItemId() {
  return ++itemSeq;
}
function now() {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

/** 字典分类（向 dictTypes 添加新分类时记得同步添加 dictItems） */
const dictTypes: DictType[] = [
  { id: 1, code: 'sys_normal_disable', name: '系统状态', status: 1, remark: '通用启用/停用', createdAt: '2024-01-01 00:00:00' },
  { id: 2, code: 'sys_user_sex', name: '用户性别', status: 1, remark: '', createdAt: '2024-01-01 00:00:00' },
  { id: 3, code: 'sys_show_hide', name: '显示隐藏', status: 1, remark: '菜单/列展示开关', createdAt: '2024-01-01 00:00:00' },
  { id: 4, code: 'yes_no', name: '是否', status: 1, remark: '', createdAt: '2024-01-01 00:00:00' },
  { id: 5, code: 'menu_type', name: '菜单类型', status: 1, remark: '目录/菜单/按钮', createdAt: '2024-01-01 00:00:00' },
  { id: 6, code: 'notice_type', name: '通知类型', status: 1, remark: '', createdAt: '2024-01-01 00:00:00' }
];

const dictItems: DictItem[] = [
  // sys_normal_disable
  { id: 101, typeCode: 'sys_normal_disable', label: '正常', value: '1', sort: 1, status: 1, cssClass: 'success' },
  { id: 102, typeCode: 'sys_normal_disable', label: '停用', value: '0', sort: 2, status: 1, cssClass: 'danger' },
  // sys_user_sex
  { id: 201, typeCode: 'sys_user_sex', label: '未知', value: '0', sort: 1, status: 1 },
  { id: 202, typeCode: 'sys_user_sex', label: '男', value: '1', sort: 2, status: 1, cssClass: 'primary' },
  { id: 203, typeCode: 'sys_user_sex', label: '女', value: '2', sort: 3, status: 1, cssClass: 'danger' },
  // sys_show_hide
  { id: 301, typeCode: 'sys_show_hide', label: '显示', value: '1', sort: 1, status: 1, cssClass: 'success' },
  { id: 302, typeCode: 'sys_show_hide', label: '隐藏', value: '0', sort: 2, status: 1, cssClass: 'info' },
  // yes_no
  { id: 401, typeCode: 'yes_no', label: '是', value: '1', sort: 1, status: 1, cssClass: 'success' },
  { id: 402, typeCode: 'yes_no', label: '否', value: '0', sort: 2, status: 1, cssClass: 'info' },
  // menu_type
  { id: 501, typeCode: 'menu_type', label: '目录', value: 'M', sort: 1, status: 1, cssClass: 'primary' },
  { id: 502, typeCode: 'menu_type', label: '菜单', value: 'C', sort: 2, status: 1, cssClass: 'success' },
  { id: 503, typeCode: 'menu_type', label: '按钮', value: 'B', sort: 3, status: 1, cssClass: 'info' },
  // notice_type
  { id: 601, typeCode: 'notice_type', label: '通知', value: 'notice', sort: 1, status: 1, cssClass: 'primary' },
  { id: 602, typeCode: 'notice_type', label: '消息', value: 'message', sort: 2, status: 1, cssClass: 'success' },
  { id: 603, typeCode: 'notice_type', label: '待办', value: 'todo', sort: 3, status: 1, cssClass: 'warning' }
];

/** 把字典项转成 options 形态 */
function toOptions(items: DictItem[]) {
  return items
    .filter((it) => it.status === 1)
    .sort((a, b) => a.sort - b.sort)
    .map((it) => ({
      label: it.label,
      value: it.value,
      status: it.status,
      cssClass: it.cssClass
    }));
}

function paginate<T>(rows: T[], page = 1, pageSize = 10) {
  const start = (page - 1) * pageSize;
  return rows.slice(start, start + pageSize);
}

export const dictMockHandlers: MockRoute[] = [
  // ===== ① 通用字典查询 =====
  {
    method: 'GET',
    url: /^\/system\/dict\/([\w-]+)\/options$/,
    paramNames: ['code'],
    handler: ({ pathParams }) => {
      const items = dictItems.filter((it) => it.typeCode === pathParams.code);
      return ok(toOptions(items));
    }
  },
  {
    method: 'POST',
    url: '/system/dict/batch',
    handler: ({ body }) => {
      const codes: string[] = body?.codes || [];
      const result: Record<string, ReturnType<typeof toOptions>> = {};
      codes.forEach((c) => {
        result[c] = toOptions(dictItems.filter((it) => it.typeCode === c));
      });
      return ok(result);
    }
  },

  // ===== ② 字典分类 CRUD =====
  {
    method: 'GET',
    url: '/system/dict/types',
    handler: ({ params }) => {
      const page = Number(params.page || 1);
      const pageSize = Number(params.pageSize || 10);
      const code = String(params.code || '').trim();
      const name = String(params.name || '').trim();
      const status = params.status;
      let rows = [...dictTypes];
      if (code) rows = rows.filter((r) => r.code.includes(code));
      if (name) rows = rows.filter((r) => r.name.includes(name));
      if (status === 0 || status === 1 || status === '0' || status === '1') {
        rows = rows.filter((r) => r.status === Number(status));
      }
      return ok({ list: paginate(rows, page, pageSize), total: rows.length, page, pageSize });
    }
  },
  {
    method: 'POST',
    url: '/system/dict/types',
    handler: ({ body }) => {
      const code = String(body?.code || '').trim();
      if (!code) return fail('字典编码必填');
      if (dictTypes.some((t) => t.code === code)) return fail('字典编码已存在');
      const item: DictType = {
        id: nextTypeId(),
        code,
        name: String(body?.name || ''),
        status: (body?.status ?? 1) as 0 | 1,
        remark: body?.remark || '',
        createdAt: now()
      };
      dictTypes.unshift(item);
      return ok(item, '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/dict\/types\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const t = dictTypes.find((x) => x.id === Number(pathParams.id));
      if (!t) return fail('分类不存在');
      // code 改动需要级联更新 items.typeCode
      if (body?.code && body.code !== t.code) {
        const oldCode = t.code;
        if (dictTypes.some((x) => x.code === body.code)) return fail('字典编码已存在');
        dictItems.forEach((it) => {
          if (it.typeCode === oldCode) it.typeCode = body.code;
        });
        t.code = body.code;
      }
      Object.assign(t, body, { id: t.id, code: t.code, createdAt: t.createdAt });
      return ok(t, '更新成功');
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/dict\/types\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const idx = dictTypes.findIndex((x) => x.id === Number(pathParams.id));
      if (idx < 0) return fail('分类不存在');
      const t = dictTypes[idx];
      // 级联删 items
      for (let i = dictItems.length - 1; i >= 0; i--) {
        if (dictItems[i].typeCode === t.code) dictItems.splice(i, 1);
      }
      dictTypes.splice(idx, 1);
      return ok(null, '已删除');
    }
  },
  {
    method: 'DELETE',
    url: '/system/dict/types',
    handler: ({ params, body }) => {
      const raw = (params?.ids ?? body?.ids ?? []) as Array<number | string>;
      const ids = (Array.isArray(raw) ? raw : [raw]).map(Number);
      let removed = 0;
      ids.forEach((id) => {
        const idx = dictTypes.findIndex((x) => x.id === id);
        if (idx >= 0) {
          const t = dictTypes[idx];
          for (let i = dictItems.length - 1; i >= 0; i--) {
            if (dictItems[i].typeCode === t.code) dictItems.splice(i, 1);
          }
          dictTypes.splice(idx, 1);
          removed++;
        }
      });
      return ok({ removed }, `已删除 ${removed} 条`);
    }
  },

  // ===== ③ 字典项 CRUD =====
  {
    method: 'GET',
    url: '/system/dict/items',
    handler: ({ params }) => {
      const page = Number(params.page || 1);
      const pageSize = Number(params.pageSize || 10);
      const typeCode = String(params.typeCode || '').trim();
      const label = String(params.label || '').trim();
      const status = params.status;
      let rows = [...dictItems];
      if (typeCode) rows = rows.filter((r) => r.typeCode === typeCode);
      if (label) rows = rows.filter((r) => r.label.includes(label));
      if (status === 0 || status === 1 || status === '0' || status === '1') {
        rows = rows.filter((r) => r.status === Number(status));
      }
      rows.sort((a, b) => a.sort - b.sort);
      return ok({ list: paginate(rows, page, pageSize), total: rows.length, page, pageSize });
    }
  },
  {
    method: 'POST',
    url: '/system/dict/items',
    handler: ({ body }) => {
      const typeCode = String(body?.typeCode || '').trim();
      if (!typeCode) return fail('请选择字典分类');
      if (!dictTypes.some((t) => t.code === typeCode)) return fail('字典分类不存在');
      const item: DictItem = {
        id: nextItemId(),
        typeCode,
        label: String(body?.label || ''),
        value: String(body?.value ?? ''),
        sort: Number(body?.sort || 0),
        status: (body?.status ?? 1) as 0 | 1,
        cssClass: body?.cssClass || '',
        remark: body?.remark || ''
      };
      dictItems.push(item);
      return ok(item, '创建成功');
    }
  },
  {
    method: 'PUT',
    url: /^\/system\/dict\/items\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams, body }) => {
      const it = dictItems.find((x) => x.id === Number(pathParams.id));
      if (!it) return fail('字典项不存在');
      Object.assign(it, body, { id: it.id });
      it.value = String(it.value);
      return ok(it, '更新成功');
    }
  },
  {
    method: 'DELETE',
    url: /^\/system\/dict\/items\/(\d+)$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const idx = dictItems.findIndex((x) => x.id === Number(pathParams.id));
      if (idx < 0) return fail('字典项不存在');
      dictItems.splice(idx, 1);
      return ok(null, '已删除');
    }
  },
  {
    method: 'DELETE',
    url: '/system/dict/items',
    handler: ({ params, body }) => {
      const raw = (params?.ids ?? body?.ids ?? []) as Array<number | string>;
      const ids = (Array.isArray(raw) ? raw : [raw]).map(Number);
      let removed = 0;
      ids.forEach((id) => {
        const idx = dictItems.findIndex((x) => x.id === id);
        if (idx >= 0) {
          dictItems.splice(idx, 1);
          removed++;
        }
      });
      return ok({ removed }, `已删除 ${removed} 条`);
    }
  }
];
