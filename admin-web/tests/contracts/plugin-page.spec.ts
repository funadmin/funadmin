import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const page = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/index.vue'), 'utf8');
const actions = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/pluginActions.ts'), 'utf8');
const configDrawer = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/components/PluginConfigDrawer.vue'), 'utf8');
const marketDrawer = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/components/PluginMarketDrawer.vue'), 'utf8');

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
    expect(configDrawer).toContain("v-perm=\"'system:plugin:config'\"");
    expect(marketDrawer).toContain("v-perm=\"'system:plugin:install'\"");
  });

  it('启用、禁用和迁移均要求操作确认', () => {
    expect(page).toContain('操作确认');
    expect(page).toContain('确认迁移插件');
    expect(page).toContain('确认启用插件');
    expect(page).toContain('确认禁用插件');
  });

  it('所有插件确认操作统一通过 confirmAction 处理取消', () => {
    expect(actions).toContain('export async function confirmAction');
    expect(actions).toContain("reason === 'cancel' || reason === 'close'");
    for (const operation of ['uploadZip', 'operate', 'updatePlugin', 'uninstall', 'deletePackage', 'installMarket', 'installSelectedVersion']) {
      expect(page.match(new RegExp(`async function ${operation}\\([\\s\\S]*?confirmAction\\(`))?.[0]).toBeTruthy();
    }
    expect(page).toContain('if (!completed) return;');
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

  it('动态配置支持开关、选择、复选、多选和数值输入', () => {
    for (const marker of ['el-switch', 'el-select', 'el-checkbox-group', 'multiple', 'el-input-number']) {
      expect(configDrawer).toContain(marker);
    }
  });

  it('已安装列表将 UpdateCheck 数组按 name 建 map 并仅合并可用更新', () => {
    expect(page).toContain('pluginApi.checkUpdates');
    expect(page).toContain('new Map(updates.map((item) => [item.name, item]))');
    expect(page).toContain('update?.updateAvailable ? update.latestVersion :');
    expect(page).not.toContain('updates[item.name]');
  });
});
