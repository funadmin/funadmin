import { describe, expect, it } from 'vitest';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';

const page = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/index.vue'), 'utf8');
const actions = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/pluginActions.ts'), 'utf8');
const configDialog = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/components/ConfigDialog.vue'), 'utf8');
const market = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/components/Market.vue'), 'utf8');
const configDrawer = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/components/PluginConfigDrawer.vue'), 'utf8');
const marketDrawer = readFileSync(resolve(process.cwd(), 'src/views/system/plugin/components/PluginMarketDrawer.vue'), 'utf8');
const componentDirectory = resolve(process.cwd(), 'src/views/system/plugin/components');
const projectRoot = resolve(process.cwd(), '..');

const productionSource = (directory: string): string => readdirSync(resolve(projectRoot, directory), {
  recursive: true,
  withFileTypes: true
})
  .filter((entry) => entry.isFile() && /\.(php|json)$/.test(entry.name))
  .map((entry) => readFileSync(resolve(entry.parentPath, entry.name), 'utf8'))
  .join('\n');

describe('插件中心页面契约', () => {
  it('提供已安装、本地包、云市场三个标签和关键状态字段', () => {
    for (const text of ['已安装', '本地包', '云市场', 'latestVersion', 'dbVersion', 'migrationPending', 'lastError', 'dependencies', 'source']) {
      expect(page).toContain(text);
    }
  });

  it('关键操作均声明 v-perm 权限', () => {
    for (const permission of ['install', 'update', 'migrate', 'enable', 'disable', 'config', 'uninstall', 'purge', 'package-delete', 'history']) {
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
    for (const operation of ['updateLocalZip', 'operate', 'updatePlugin', 'installDiscovered', 'uninstall', 'purge', 'deletePackage', 'installMarket', 'installSelectedVersion']) {
      expect(page.match(new RegExp(`async function ${operation}\\([\\s\\S]*?confirmAction\\(`))?.[0]).toBeTruthy();
    }
    expect(page).toContain('if (!completed) return;');
  });

  it('uninstall 与 purge 是独立动作且 purge 必须精确输入插件名', () => {
    expect(actions).toContain("confirmation !== pluginName");
    expect(actions).toContain('purgeConfirm');
    expect(page).toContain('pluginApi.uninstall(row.name)');
    expect(page).toContain('pluginApi.purge(row.name, payload.purgeConfirm)');
    expect(page).not.toContain('pluginApi.uninstall(row.name,');
  });

  it('页面挂载当前 Account、Market、ConfigDialog、LifecycleDrawer 与 InstallDialog', () => {
    expect(page).not.toContain('<PluginAccountDrawer');
    expect(page).not.toContain('<PluginMarketDrawer');
    expect(page).not.toContain('<PluginConfigDrawer');
    expect(page).not.toContain('<PluginHistoryDrawer');
    for (const component of ['Account', 'Market', 'ConfigDialog', 'LifecycleDrawer', 'InstallDialog']) {
      expect(page).toContain(`<${component}`);
      expect(page).toContain(`./components/${component}.vue`);
    }
    expect(readFileSync(resolve(componentDirectory, 'InstallDialog.vue'), 'utf8')).toContain('pluginApi.installLocal');
    expect(configDialog).toContain('PluginConfigDrawer');
    expect(market).toContain('PluginMarketDrawer');
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

  it('生命周期动作后立即同步动态插件路由', () => {
    expect(page).toContain("import { loadPluginModulesSafely } from '@/router/pluginStartup'");
    for (const operation of ['operate', 'updatePlugin', 'uninstall']) {
      expect(page.match(new RegExp(`async function ${operation}\\([\\s\\S]*?syncPluginRoutes\\(\\)`))?.[0]).toBeTruthy();
    }
  });

  it('生产源码不再保留插件旧旁路和旧配置', () => {
    for (const path of [
      'app/common/service/AuthCloudService.php',
      'extend/fun/plugins/command/Config.php',
      'extend/fun/plugins/config.php'
    ]) {
      expect(existsSync(resolve(projectRoot, path))).toBe(false);
    }

    const source = ['app', 'config', 'extend'].map(productionSource).join('\n') + readFileSync(resolve(projectRoot, 'composer.json'), 'utf8');
    expect(source).not.toContain('AuthCloudService');
    expect(source).not.toContain('plugins:config');

    const pluginConfig = readFileSync(resolve(projectRoot, 'config/plugins.php'), 'utf8');
    for (const legacyKey of ["'autoload'", "'hooks'", "'route'", "'service'"]) {
      expect(pluginConfig).not.toContain(legacyKey);
    }
  });
});
