import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve(process.cwd(), '..');
const read = (path: string) => readFileSync(resolve(root, path), 'utf8');

describe('017 历史命名与会员标签治理', () => {
  it('应用代码切换到规范管理员字段', () => {
    for (const path of [
      'admin/app/admin/authentication/service/AdminSessionService.php',
      'admin/app/admin/authorization/service/AdminAuthorizationService.php',
      'admin/app/admin/authentication/controller/AdminAuth.php',
      'admin/app/admin/authentication/controller/AdminProfile.php',
      'admin/app/admin/authorization/controller/SystemAdmin.php',
      'admin/app/common/traits/Crud.php'
    ]) {
      const source = read(path);
      expect(source, path).not.toMatch(/\brealname\b|\blastloginip\b/);
    }
  });

  it('语言和地区模型显式绑定规范表', () => {
    expect(existsSync(resolve(root, 'admin/app/common/model/Language.php'))).toBe(true);
    expect(existsSync(resolve(root, 'admin/app/common/model/Region.php'))).toBe(true);
    expect(read('admin/app/common/model/Language.php')).toContain("protected $name = 'language';");
    expect(read('admin/app/common/model/Region.php')).toContain("protected $name = 'region';");
  });

  it('会员 API 与前端通过 tagIds 管理标签关系', () => {
    const controller = read('admin/app/admin/system/controller/SystemMember.php');
    const api = read('web/src/admin/api/system/member.ts');
    const form = read('web/src/admin/views/system/member/components/MemberFormDialog.vue');
    expect(controller).toContain('tagIds');
    expect(controller).toContain('syncWithPivotValues($tagIds');
    expect(controller).toContain("'tagNames'");
    expect(api).toContain('tagIds: number[]');
    expect(api).toContain('tagNames: string[]');
    expect(form).toContain('<SchemaRenderer');
    expect(form).toContain('tag_ids: [...row.tagIds]');
    expect(form).toContain('tagIds: [...value.tag_ids]');
    expect(form).toMatch(/<SchemaRenderer\b[^>]*:schema="definition.schema!"[^>]*:values="values"[^>]*:options="relationOptions"/);
    expect(form).toContain("const relationKeys = ['group_ids', 'tag_ids', 'level_id']");
    expect(form).toContain('return [node.id, Array.isArray(options) ? options : []]');
    expect(form).toContain('formRef.value?.validate()');
    expect(form).toContain('memberApi.update(id, payload)');
    expect(form).toContain('memberApi.create(payload)');
  });
});
