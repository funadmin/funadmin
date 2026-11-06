import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { backendJson, runBackendPhp, runBackendTests } from './backend-runtime';

const projectRoot = resolve(process.cwd(), '..');
const readProject = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');
const commands: Record<string, string> = {
  'crud:inspect': 'app\\admin\\development\\command\\CrudInspect',
  'crud:validate': 'app\\admin\\development\\command\\CrudValidate',
  'crud:preview': 'app\\admin\\development\\command\\CrudPreview',
  'crud:generate': 'app\\admin\\development\\command\\CrudGenerate',
  mcp: 'app\\admin\\common\\command\\McpServer'
};

describe('Webman console 命令发现契约', () => {
  it('模块目录命令由真实 CLI 发现且 Symfony 名称与 Attribute 一致', () => {
    const list = runBackendPhp(['webman', 'list', '--raw', '--no-ansi']);
    const classes = JSON.stringify(Object.values(commands));
    const metadata = backendJson<Record<string, string[]>>(String.raw`
      $classes = json_decode(base64_decode('${Buffer.from(classes).toString('base64')}'), true, flags: JSON_THROW_ON_ERROR);
      $result = [];
      foreach ($classes as $class) {
          $command = new $class();
          $attribute = (new ReflectionClass($class))->getAttributes(\Symfony\Component\Console\Attribute\AsCommand::class)[0]->newInstance();
          $result[$class] = [$command->getName(), $attribute->name];
      }
      echo json_encode($result, JSON_THROW_ON_ERROR);
    `);
    for (const [name, className] of Object.entries(commands)) {
      expect(list, `CLI 缺少命令 ${name}`).toMatch(new RegExp(`^${name}\\s`, 'm'));
      expect(metadata[className]).toEqual([name, name]);
    }
    expect(existsSync(resolve(projectRoot, 'admin/config/console.php'))).toBe(false);
  }, 60_000);

  it('mcp 命令帮助文案使用真实命令名', () => {
    const help = runBackendPhp(['webman', 'mcp', '--help', '--no-ansi']);
    expect(help).toMatch(/mcp\s+\[options\]/);
    expect(help).not.toContain('mcp:server');
    expect(readProject('admin/app/admin/common/command/McpServer.php')).not.toContain('mcp:server');
  }, 60_000);

  it('旧工具链命令不再注册', () => {
    const list = runBackendPhp(['webman', 'list', '--raw', '--no-ansi']);
    expect(list).not.toMatch(/^(?:curd|menu|plugin)\s/m);
    expect(readProject('admin/webman')).not.toContain('fun\\crud\\');
    // Webman's own install command remains valid; only the legacy FunAdmin installer is retired.
    expect(list).toMatch(/^install\s+Execute webman installation script$/m);
  }, 60_000);

  it('MCP 仅按真实退出码判断命令成功并拒绝写入命令和命令注入', () => {
    expect(runBackendTests('Console/McpToolsExecutionTest.php', 'test_command_tool_runs_only_side_effect_free_commands')).toContain('OK (1 test');
  }, 60_000);
});
