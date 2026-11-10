import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { backendJson } from './backend-runtime';

const readProjectFile = (path: string) => readFileSync(resolve(process.cwd(), '..', path), 'utf8');
const systemAdminSource = readProjectFile('admin/app/admin/authorization/controller/SystemAdmin.php');
const systemAdminUpdate = systemAdminSource.match(
  /public function update\(int \$id\): Response[\s\S]*?(?=\n    public function )/
)?.[0] ?? '';

describe('会员注册默认分组契约', () => {
  it('真实注册仅绑定服务端默认启用组且忽略请求的分组、等级和状态', () => {
    const result = backendJson(String.raw`
      (new \app\common\service\database\MigrationService())->run();
      \support\Cache::$instances[config('cache.default')] = new \Symfony\Component\Cache\Psr16Cache(new \Symfony\Component\Cache\Adapter\ArrayAdapter());
      // The full migration already provides all member account columns (including nickname,
      // motto, real_name, birthday and scores); exercise that schema without recreating columns.
      \support\Db::table('member_group')->update(['status' => 0]);
      foreach ([
          [9001, 'disabled', 0, null], [9002, 'deleted', 1, date('Y-m-d H:i:s')],
          [9003, 'default', 1, null], [9004, 'requested', 1, null],
      ] as [$id, $name, $status, $deletedAt]) {
          \support\Db::table('member_group')->insert(['id' => $id, 'name' => $name, 'status' => $status, 'deleted_at' => $deletedAt]);
      }
      $level = (int) \support\Db::table('member_level')->where('status', 1)->whereNull('deleted_at')->orderBy('id')->value('id');
      $request = new \support\Request("POST /api/v2/member HTTP/1.1\r\n\r\n");
      \support\Context::set(\Webman\Http\Request::class, $request);
      $service = new \app\common\service\member\MemberAccountService();
      $id = $service->register([
          'username' => 'contractbuyer', 'email' => 'contract-buyer@example.com',
          'password' => 'contract-password-123', 'password_confirm' => 'contract-password-123',
          'group_id' => 9004, 'level_id' => 99999, 'status' => 0, 'scores' => 777,
      ], '198.51.100.31');
      $member = \app\common\model\Member::findOrFail($id);
      echo json_encode([
          'retiredModelRegistration' => method_exists($member, 'reg'),
          'groupIds' => \support\Db::table('member_group_relation')->where('member_id', $id)->pluck('group_id')->map('intval')->all(),
          'levelId' => (int) $member->level_id, 'defaultLevelId' => $level,
          'status' => (int) $member->status, 'scores' => (int) $member->scores,
          'identityTable' => \support\Db::schema()->hasTable('identity_member_link'),
          'sessionLogin' => method_exists($service, 'currentId'),
      ], JSON_THROW_ON_ERROR);
    `);
    expect(result).toMatchObject({
      retiredModelRegistration: false, groupIds: [9003], status: 1, scores: 0,
      identityTable: false, sessionLogin: false
    });
    const levels = result as { levelId: number; defaultLevelId: number };
    expect(levels.defaultLevelId).toBeGreaterThan(0);
    expect(levels.levelId).toBe(levels.defaultLevelId);
  }, 60_000);
});

describe('管理员更新数据范围契约', () => {
  it('角色层级校验后校验目标管理员数据范围并在写入前拒绝越权', () => {
    const roleGuardAt = systemAdminUpdate.indexOf('assertManageAdmin($admin)');
    const dataScopeAt = systemAdminUpdate.indexOf('isInDataScope($admin)');
    const rejectionAt = systemAdminUpdate.indexOf('throw new InvalidArgumentException', dataScopeAt);
    const writeAt = systemAdminUpdate.indexOf('$admin->update(');
    expect(roleGuardAt).toBeGreaterThan(-1);
    expect(dataScopeAt).toBeGreaterThan(roleGuardAt);
    expect(rejectionAt).toBeGreaterThan(dataScopeAt);
    expect(writeAt).toBeGreaterThan(rejectionAt);
  });

  it('即使目标属于下级角色且新部门可分配，范围外更新仍返回403且不修改数据', () => {
    const result = backendJson(String.raw`
      (new \app\common\service\database\MigrationService())->run();
      \support\Cache::$instances[config('cache.default')] = new \Symfony\Component\Cache\Psr16Cache(new \Symfony\Component\Cache\Adapter\ArrayAdapter());
      foreach ([9001, 9002] as $id) {
          \support\Db::table('department')->insert(['id' => $id, 'name' => 'department-' . $id, 'pid' => 0, 'status' => 1]);
          \support\Db::table('auth_group')->insert(['id' => $id, 'pid' => $id === 9002 ? 9001 : 0, 'name' => 'role-' . $id, 'code' => 'contract_' . $id, 'level' => $id === 9001 ? 10 : 20, 'data_scope' => 'dept', 'status' => 1]);
          \support\Db::table('admin')->insert(['id' => $id, 'username' => 'admin' . $id, 'real_name' => 'original-' . $id, 'password' => 'hidden', 'dept_id' => $id, 'status' => 1]);
      }
      $casbin = new \app\admin\authorization\service\CasbinService();
      $casbin->syncAdminRoles(9001, [9001]);
      $casbin->syncAdminRoles(9002, [9002]);
      $request = new \support\Request("PUT /admin/system/user/9002 HTTP/1.1\r\n\r\n");
      $request->setPost(['nickname' => 'changed', 'deptId' => 9001, 'departmentIds' => [], 'roleIds' => [9002], 'status' => 1]);
      $request->context['admin'] = ['id' => 9001];
      \support\Context::set(\Webman\Http\Request::class, $request);
      $target = \app\admin\authentication\model\Admin::findOrFail(9002);
      (new \app\admin\authorization\service\RoleGuardService())->assertManageAdmin($target);
      $before = $target->getAttributes();
      $response = (new \app\admin\authorization\controller\SystemAdmin())->update(9002);
      $after = $target->fresh()->getAttributes();
      $denied = json_decode($response->rawBody(), true, flags: JSON_THROW_ON_ERROR);
      $rolesAfterDenied = (new \app\admin\authorization\service\RoleScopeService())->adminRoleIds(9002);
      $departmentsAfterDenied = \support\Db::table('admin_department')->where('admin_id', 9002)->count();
      // A positive control distinguishes target data-scope denial from a broken fixture.
      \support\Db::table('admin')->where('id', 9002)->update(['dept_id' => 9001]);
      $allowed = json_decode((new \app\admin\authorization\controller\SystemAdmin())->update(9002)->rawBody(), true, flags: JSON_THROW_ON_ERROR);
      echo json_encode([
          'denied' => $denied['code'], 'reason' => $denied['msg'], 'unchanged' => $before === $after,
          'rolesAfterDenied' => $rolesAfterDenied, 'departmentsAfterDenied' => $departmentsAfterDenied,
          'allowed' => $allowed['code'], 'nicknameAfterAllowed' => $target->fresh()->real_name,
      ], JSON_THROW_ON_ERROR);
    `);
    expect(result).toEqual({
      denied: 403, reason: '管理员不在当前数据范围内', unchanged: true,
      rolesAfterDenied: [9002], departmentsAfterDenied: 0, allowed: 200, nicknameAfterAllowed: 'changed'
    });
  }, 60_000);
});
