import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const source = readFileSync(
  resolve(process.cwd(), 'src/views/system/menu/components/MenuFormDialog.vue'),
  'utf8'
);

describe('菜单表单图标字段', () => {
  it('图标选择器不能放在无指令的原生 template 中', () => {
    expect(source).toContain('<el-form-item label="图标" prop="icon">');
    expect(source).toContain('<IconSelect v-model="form.icon" />');
    expect(source).not.toMatch(/<template>\s*<el-row[\s\S]*?<IconSelect v-model="form\.icon" \/>[\s\S]*?<\/template>/);
  });
});
