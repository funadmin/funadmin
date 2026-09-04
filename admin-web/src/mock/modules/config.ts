import type { ConfigGroupModel, ConfigModel } from '@/api/system/config';
import { fail, ok, page, type MockRoute } from '../types';

const now = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
let configSeq = 44;
let groupSeq = 4;

const groups: ConfigGroupModel[] = [
  { id: 1, name: 'site', title: '站点配置', status: 1, createdAt: '', updatedAt: '' },
  { id: 2, name: 'upload', title: '上传配置', status: 1, createdAt: '', updatedAt: '' },
  { id: 3, name: 'email', title: '邮件配置', status: 1, createdAt: '', updatedAt: '' },
  { id: 4, name: 'custom', title: '自定义配置', status: 1, createdAt: now(), updatedAt: now() }
];

const rows: ConfigModel[] = [
  { id: 1, code: 'site_name', group: 'site', type: 'text', verify: '', value: 'FunAdmin管理系统', extra: '', remark: '网站名称', status: 1, isSystem: 1, createdAt: '', updatedAt: '' },
  { id: 3, code: 'site_state', group: 'site', type: 'radio', verify: '', value: '1', extra: '0:关闭\n1:开启', remark: '网站状态', status: 1, isSystem: 1, createdAt: '', updatedAt: '' },
  { id: 4, code: 'site_logo', group: 'site', type: 'image', verify: '', value: '', extra: '', remark: '网站 Logo', status: 1, isSystem: 1, createdAt: '', updatedAt: '' },
  { id: 31, code: 'upload_file_type', group: 'upload', type: 'text', verify: '', value: 'png,jpg,jpeg,webp,zip', extra: '', remark: '允许上传类型', status: 1, isSystem: 1, createdAt: '', updatedAt: '' },
  { id: 44, code: 'upload_editor', group: 'upload', type: 'select', verify: '', value: 'tinymce', extra: 'tinymce:tinymce\nckeditor:ckeditor', remark: '默认编辑器', status: 1, isSystem: 1, createdAt: '', updatedAt: '' }
];

const types = [
  ['text', '输入框', false], ['textarea', '多行文本', false], ['radio', '单选按钮', true],
  ['switch', '开关', false], ['array', '数组', true], ['select', '下拉框', true],
  ['image', '单张图', false], ['images', '多张图', false], ['tags', '标签', false],
  ['number', '整数', false], ['datetime', '日期时间', false], ['editor', '编辑器', false],
  ['color', '颜色值', false], ['file', '单文件', false], ['files', '多文件', false],
  ['hidden', '隐藏域', false], ['range', '日期范围', false], ['float', '浮点数', false],
  ['decimal', '小数', false], ['checkbox', '复选框', true]
].map(([name, title, requiresOptions]) => ({ name: String(name), title: String(title), requiresOptions: Boolean(requiresOptions) }));
const verifies = [
  { value: 'required', title: '必须' }, { value: 'email', title: '邮箱' },
  { value: 'required|number', title: '数字' }, { value: 'date', title: '日期' }, { value: 'url', title: '地址' }
];

function options(extra: string): Map<string, string> {
  return new Map(extra.split(/\r?\n/).map((line) => line.trim()).filter(Boolean).map((line) => {
    const index = line.indexOf(':');
    return index < 0 ? [line, line] : [line.slice(0, index).trim(), line.slice(index + 1).trim()];
  }));
}

function normalizeValue(type: string, raw: unknown, extra = ''): { value: string; error?: string } {
  let value: string;
  if (['checkbox', 'images', 'files'].includes(type)) {
    const source = Array.isArray(raw) ? raw : String(raw ?? '').split(/[\r\n,]+/);
    value = Array.from(new Set(source.map((item) => String(item).trim()).filter(Boolean))).join('\n');
  } else if (type === 'switch') {
    value = [1, '1', true, 'true', 'on'].includes(raw as never) ? '1' : '0';
  } else {
    value = raw == null || ['string', 'number', 'boolean'].includes(typeof raw) ? String(raw ?? '') : '';
  }
  if (type === 'number' && value && !/^-?\d+$/.test(value)) return { value: '', error: '配置值必须为整数' };
  if (['float', 'decimal'].includes(type) && value && !Number.isFinite(Number(value))) return { value: '', error: '配置值必须为数字' };
  if (['radio', 'select', 'checkbox'].includes(type) && extra) {
    const allowed = options(extra);
    const values = type === 'checkbox' ? value.split('\n').filter(Boolean) : [value];
    if (values.some((item) => item && !allowed.has(item))) return { value: '', error: '配置值不在允许的选项范围内' };
  }
  return { value };
}

function validatePayload(body: Record<string, any>, current?: ConfigModel): { data?: ConfigModel; error?: string } {
  const code = String(body.code ?? current?.code ?? '').trim();
  const group = String(body.group ?? current?.group ?? 'site').trim();
  const type = String(body.type ?? current?.type ?? 'text').trim();
  const verify = String(body.verify ?? current?.verify ?? '').trim();
  const extra = String(body.extra ?? current?.extra ?? '').trim();
  const remark = String(body.remark ?? current?.remark ?? '').trim();
  if (!/^[A-Za-z][A-Za-z0-9_.-]{0,29}$/.test(code)) return { error: '配置编码格式不正确' };
  if (!groups.some((item) => item.name === group)) return { error: '请选择有效的配置分组' };
  if (!types.some((item) => item.name === type)) return { error: '请选择有效的字段类型' };
  if (verify && verify !== '0' && !verifies.some((item) => item.value === verify)) return { error: '请选择有效的验证规则' };
  if (extra.length > 255) return { error: '选项定义不能超过 255 个字符' };
  if (remark.length > 100) return { error: '配置备注不能超过 100 个字符' };
  const normalized = normalizeValue(type, body.value ?? current?.value ?? '', extra);
  if (normalized.error) return { error: normalized.error };
  return { data: { id: current?.id ?? 0, code, group, type, verify, value: normalized.value, extra, remark, status: Number(body.status ?? current?.status ?? 1) === 1 ? 1 : 0, isSystem: current?.isSystem ?? 0, createdAt: current?.createdAt ?? now(), updatedAt: now() } };
}

export const configMockHandlers: MockRoute[] = [
  { method: 'GET', url: '/system/config/options', handler: () => ok({ groups: groups.map((item) => ({ ...item })), types, verifies }) },
  { method: 'GET', url: '/system/config', handler: ({ params }) => {
    const keyword = String(params.keyword || '').trim().toLowerCase();
    const filtered = rows.filter((row) => (!keyword || row.code.toLowerCase().includes(keyword) || row.remark.toLowerCase().includes(keyword))
      && (!params.group || row.group === params.group) && (!params.type || row.type === params.type)
      && (params.status === undefined || params.status === '' || row.status === Number(params.status)));
    const current = Math.max(1, Number(params.page) || 1); const pageSize = Math.max(1, Number(params.pageSize) || 20); const start = (current - 1) * pageSize;
    return ok(page(filtered.slice(start, start + pageSize).map((item) => ({ ...item })), filtered.length, current, pageSize));
  } },
  { method: 'GET', url: /^\/system\/config\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => { const row = rows.find((item) => item.id === Number(pathParams.id)); return row ? ok({ ...row }) : fail('配置项不存在', 404); } },
  { method: 'POST', url: '/system/config', handler: ({ body }) => {
    const result = validatePayload(body); if (result.error || !result.data) return fail(result.error, 422);
    if (rows.some((item) => item.code.toLowerCase() === result.data!.code.toLowerCase())) return fail('配置编码已存在', 422);
    result.data.id = ++configSeq; rows.push(result.data); return ok({ ...result.data }, '创建成功');
  } },
  { method: 'PUT', url: /^\/system\/config\/(\d+)\/value$/, paramNames: ['id'], handler: ({ pathParams, body }) => {
    const row = rows.find((item) => item.id === Number(pathParams.id)); if (!row) return fail('配置项不存在', 404);
    const result = normalizeValue(row.type, body.value, row.extra); if (result.error) return fail(result.error, 422);
    row.value = result.value; row.updatedAt = now(); return ok({ ...row }, '配置值已更新');
  } },
  { method: 'PUT', url: /^\/system\/config\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams, body }) => {
    const row = rows.find((item) => item.id === Number(pathParams.id)); if (!row) return fail('配置项不存在', 404);
    const result = validatePayload(body, row); if (result.error || !result.data) return fail(result.error, 422);
    if (row.isSystem === 1 && result.data.code !== row.code) return fail('系统配置编码不能修改', 422);
    if (rows.some((item) => item.id !== row.id && item.code.toLowerCase() === result.data!.code.toLowerCase())) return fail('配置编码已存在', 422);
    Object.assign(row, result.data, { id: row.id, isSystem: row.isSystem, createdAt: row.createdAt }); return ok({ ...row }, '保存成功');
  } },
  { method: 'POST', url: /^\/system\/config\/(\d+)\/status$/, paramNames: ['id'], handler: ({ pathParams, body }) => { const row = rows.find((item) => item.id === Number(pathParams.id)); if (!row) return fail('配置项不存在', 404); row.status = Number(body.status) === 1 ? 1 : 0; row.updatedAt = now(); return ok({ ...row }, '状态更新成功'); } },
  { method: 'DELETE', url: '/system/config', handler: ({ body }) => {
    const ids = Array.from(new Set((Array.isArray(body.ids) ? body.ids : []).map(Number).filter((id) => id > 0)));
    if (!ids.length) return fail('请选择要删除的配置项', 422);
    const selected = rows.filter((item) => ids.includes(item.id)); if (selected.length !== ids.length) return fail('部分配置项不存在', 404);
    if (selected.some((item) => item.isSystem === 1)) return fail('系统配置不能删除', 422);
    selected.forEach((item) => rows.splice(rows.indexOf(item), 1)); return ok({ removed: selected.length }, '删除成功');
  } },
  { method: 'GET', url: '/system/config-group', handler: () => ok(groups.map((item) => ({ ...item }))) },
  { method: 'POST', url: '/system/config-group', handler: ({ body }) => {
    const name = String(body.name || '').trim(); const title = String(body.title || '').trim();
    if (!/^[A-Za-z][A-Za-z0-9_-]{0,29}$/.test(name) || !title || title.length > 60) return fail('配置分组参数不正确', 422);
    if (groups.some((item) => item.name.toLowerCase() === name.toLowerCase())) return fail('配置分组编码已存在', 422);
    const row: ConfigGroupModel = { id: ++groupSeq, name, title, status: Number(body.status) === 1 ? 1 : 0, createdAt: now(), updatedAt: now() }; groups.push(row); return ok({ ...row }, '创建成功');
  } },
  { method: 'PUT', url: /^\/system\/config-group\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams, body }) => {
    const row = groups.find((item) => item.id === Number(pathParams.id)); if (!row) return fail('配置分组不存在', 404);
    const name = String(body.name ?? row.name).trim(); const title = String(body.title ?? row.title).trim();
    if (!/^[A-Za-z][A-Za-z0-9_-]{0,29}$/.test(name) || !title || title.length > 60) return fail('配置分组参数不正确', 422);
    if (name !== row.name && rows.some((item) => item.group === row.name)) return fail('配置分组已被配置项引用，不能修改编码', 422);
    if (groups.some((item) => item.id !== row.id && item.name.toLowerCase() === name.toLowerCase())) return fail('配置分组编码已存在', 422);
    Object.assign(row, { name, title, status: Number(body.status ?? row.status) === 1 ? 1 : 0, updatedAt: now() }); return ok({ ...row }, '保存成功');
  } },
  { method: 'DELETE', url: /^\/system\/config-group\/(\d+)$/, paramNames: ['id'], handler: ({ pathParams }) => {
    const index = groups.findIndex((item) => item.id === Number(pathParams.id)); if (index < 0) return fail('配置分组不存在', 404);
    if (rows.some((item) => item.group === groups[index].name)) return fail('配置分组仍被配置项引用，不能删除', 422);
    groups.splice(index, 1); return ok(null, '删除成功');
  } }
];
