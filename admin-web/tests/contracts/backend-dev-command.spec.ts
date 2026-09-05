import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const packageJson = JSON.parse(readFileSync(resolve(process.cwd(), 'package.json'), 'utf8'));

describe('本地后端启动命令', () => {
  it('从项目根目录启动 ThinkPHP，避免 router.php 相对路径失效', () => {
    expect(packageJson.scripts['dev:backend']).toBe('cd .. && php think run -H 0.0.0.0 -p 8000');
  });
});
