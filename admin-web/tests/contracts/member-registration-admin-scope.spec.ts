import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const readProjectFile = (path: string) => readFileSync(resolve(process.cwd(), '..', path), 'utf8');

const memberSource = readProjectFile('app/common/model/Member.php');
const memberReg = memberSource.match(
  /public function reg\(\)[\s\S]*?(?=\n    public function )/
)?.[0] ?? '';
const systemAdminSource = readProjectFile('app/backend/controller/system/SystemAdmin.php');
const systemAdminUpdate = systemAdminSource.match(
  /public function update\(int \$id\): Response[\s\S]*?(?=\n    public function )/
)?.[0] ?? '';

describe('会员注册默认分组源码契约', () => {
  it('注册后通过公共关系模型写入服务端默认组且不信任请求 group_id', () => {
    expect(memberSource).toContain('use app\\common\\model\\MemberGroupRelation;');
    expect(memberReg).toMatch(/\$member\s*=\s*self::create\(/);
    expect(memberReg).toMatch(
      /MemberGroupRelation::create\(\s*\[[\s\S]*['"]member_id['"]\s*=>\s*\(int\)\s*\$member->id[\s\S]*['"]group_id['"]\s*=>/
    );
    expect(memberReg).not.toMatch(/['"]group_id['"]\s*=>\s*\$data\[['"]group_id['"]\]/);
    expect(memberReg).toMatch(/unset\(\$data\[['"]group_id['"]\]\)/);
  });
});

describe('管理员更新数据范围源码契约', () => {
  it('角色层级校验后校验目标管理员数据范围并在保存前拒绝越权', () => {
    const roleGuardAt = systemAdminUpdate.indexOf('assertManageAdmin($admin)');
    const dataScopeAt = systemAdminUpdate.indexOf('isInDataScope($admin)');
    const rejectionAt = systemAdminUpdate.indexOf('throw new InvalidArgumentException', dataScopeAt);
    const saveAt = systemAdminUpdate.indexOf('$admin->save(');

    expect(roleGuardAt).toBeGreaterThan(-1);
    expect(dataScopeAt).toBeGreaterThan(roleGuardAt);
    expect(rejectionAt).toBeGreaterThan(dataScopeAt);
    expect(saveAt).toBeGreaterThan(rejectionAt);
  });
});