import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const readProject = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');

const consoleConfig = readProject('config/console.php');

const commands: Record<string, string> = {
  curd: 'extend/fun/curd/AdminWebCrud.php',
  menu: 'extend/fun/curd/Menu.php',
  plugin: 'extend/fun/curd/Plugin.php',
  install: 'extend/fun/curd/Install.php',
  mcp: 'extend/fun/mcp/McpServer.php'
};

describe('console 命令注册契约', () => {
  it('config/console.php 的每个命令都指向存在且 setName 一致的类', () => {
    for (const [name, file] of Object.entries(commands)) {
      expect(consoleConfig, `config 缺少命令 ${name}`).toContain(`'${name}' =>`);
      const source = readProject(file);
      expect(source, `${file} 的 setName 与配置键不一致`).toMatch(new RegExp(`setName\\('${name}'\\)`));
    }
  });

  it('mcp 命令帮助文案使用真实命令名', () => {
    expect(readProject('extend/fun/mcp/McpServer.php')).not.toContain('mcp:server');
  });

  it('plugin 命令生成新架构要求的 plugin.json', () => {
    expect(readProject('extend/fun/curd/Plugin.php')).toContain("'plugin.json'");
    expect(existsSync(resolve(projectRoot, 'extend/fun/curd/tpl/plugin/json.tpl'))).toBe(true);
  });

  it('menu 与 plugin 命令失败时返回非零退出码', () => {
    const menu = readProject('extend/fun/curd/Menu.php');
    const plugin = readProject('extend/fun/curd/Plugin.php');
    expect(menu).toContain('return 1;');
    expect(plugin).toContain('return 1;');
    expect(menu).not.toContain('return false;');
    expect(plugin).not.toContain('return false;');
  });

  it('MCP plugin 工具识别中文成功输出', () => {
    expect(readProject('app/common/service/McpService.php')).toContain("strpos($content, '成功')");
  });
});
