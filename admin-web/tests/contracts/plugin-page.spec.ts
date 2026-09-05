import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const page = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/index.vue'), 'utf8');
const actions = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/pluginActions.ts'), 'utf8');

describe('插件中心页面契约', () => {
  it('提供已安装、本地包、云市场三个标签和关键状态字段', () => {
    for (const text of ['已安装', '本地包', '云市场', 'latestVersion', 'dbVersion', 'migrationPending', 'lastError', 'dependencies', 'source']) {
      expect(page).toContain(text);
    }
  });

  it('关键操作均声明 v-perm 权限', () => {
    for (const permission of ['install', 'update', 'migrate', 'enable', 'disable', 'config', 'uninstall', 'package-delete', 'history']) {
      expect(page).toContain(`system:plugin:${permission}`);
    }
  });

  it('purge 二次确认必须精确输入插件名', () => {
    expect(actions).toContain("confirmation !== pluginName");
    expect(actions).toContain('purgeConfirm');
  });

  it('包含账号登录、市场详情版本、本地 ZIP、动态配置和错误历史 UI', () => {
    for (const marker of ['PluginAccountDrawer', 'PluginMarketDrawer', 'PluginConfigDrawer', 'PluginHistoryDrawer', 'accept=".zip"']) {
      expect(page).toContain(marker);
    }
  });
});
