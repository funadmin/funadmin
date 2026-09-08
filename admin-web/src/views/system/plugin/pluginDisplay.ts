import type { PluginState } from '@/api/plugin';

const stateLabels: Record<PluginState, string> = {
  discovered: '待安装',
  installing: '安装中',
  disabled: '已禁用',
  updating: '更新中',
  enabling: '启用中',
  enabled: '已启用',
  disabling: '禁用中',
  uninstalling: '卸载中',
  failed: '失败'
};

const sourceLabels: Record<string, string> = {
  installed: '已安装',
  local: '本地插件',
  cloud: '云市场'
};

const operationLabels: Record<string, string> = {
  install: '安装',
  update: '更新',
  migrate: '数据库迁移',
  enable: '启用',
  disable: '禁用',
  uninstall: '卸载',
  purge: '清除数据',
  package: '打包',
  validate: '校验'
};

const applicationLabels: Record<string, string> = {
  app: '独立应用',
  application: '独立应用',
  console: '管理后台',
  adminWeb: '管理前端'
};

const scopeLabels: Record<string, string> = {
  application: '独立应用',
  console: '管理后台',
  both: '独立应用和管理后台'
};

export const pluginStateLabel = (value: string) => stateLabels[value as PluginState] || '未知状态';
export const pluginSourceLabel = (value: string) => sourceLabels[value] || '未知来源';
export const operationLabel = (value: string) => operationLabels[value] || '未知操作';
export const applicationLabel = (value: string) => applicationLabels[value] || '其他能力';
export const scopeLabel = (value: string) => scopeLabels[value] || '其他范围';
