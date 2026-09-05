import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const readProject = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');

const consoleConfig = readProject('config/console.php');

const commands: Record<string, string> = {
  curd: 'extend/fun/crud/AdminWebCrud.php',
  'crud:inspect': 'extend/fun/command/CrudInspect.php',
  'crud:validate': 'extend/fun/command/CrudValidate.php',
  'crud:preview': 'extend/fun/command/CrudPreview.php',
  'crud:generate': 'extend/fun/command/CrudGenerate.php',
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

  it('旧 curd 命令明确弃用且仅提供只读预览入口', () => {
    const command = readProject('extend/fun/crud/AdminWebCrud.php');
    expect(command).toContain("setName('curd')");
    expect(command).toContain('已弃用');
    expect(command).toContain('只读');
    expect(command).not.toContain("getOption('write')");
    expect(existsSync(resolve(projectRoot, 'app/common/traits/Curd.php'))).toBe(false);
  });

  it('已删除旧工具链命令不再注册', () => {
    expect(consoleConfig).not.toMatch(/'(?:menu|plugin|install)'\s*=>\s*'fun\\crud\\/);
  });

  it('MCP plugin 工具识别中文成功输出', () => {
    expect(readProject('app/common/service/McpService.php')).toContain("strpos($content, '成功')");
  });
});
