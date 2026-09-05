import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const service = readFileSync(resolve(process.cwd(), '../app/common/service/UploadService.php'), 'utf8');

describe('上传落库扩展名契约', () => {
  it('attach 落库 ext 取自原始文件名净化值而非临时路径', () => {
    const attachBlock = service.match(/public function attach\([\s\S]*?Event::trigger\('afterUploadFile'/)?.[0] ?? '';

    expect(attachBlock).toMatch(/\$ext = \$this->safeExt\(\(string\) \$this->file->getOriginalExtension\(\)\)/);
    expect(attachBlock).toMatch(/'ext'\s*=>\s*\$ext,/);
    expect(attachBlock).not.toMatch(/'ext'\s*=>\s*\$this->file->getExtension\(\)/);
  });
});
