import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { backendJson, runBackendTests } from './backend-runtime';

const projectRoot = resolve(process.cwd(), '..');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');

describe('013 字段规范化迁移契约', () => {
  it('FieldVerify 模型使用 id 主键', () => {
    expect(backendJson(String.raw`
      echo json_encode((new \app\common\model\FieldVerify())->getKeyName());
    `)).toBe('id');
  });
});

describe('字段类型、会员与唯一冲突源码契约', () => {
  it('模型使用 Eloquent 的 created_at、updated_at 和 deleted_at 日期列', () => {
    const fields = backendJson(String.raw`
      $model = new \app\common\model\BaseModel();
      $member = new \app\common\model\Member();
      echo json_encode([
        'timestamps' => $model->usesTimestamps(),
        'created' => $model->getCreatedAtColumn(),
        'updated' => $model->getUpdatedAtColumn(),
        'deleted' => $member->getDeletedAtColumn(),
        'format' => $model->getDateFormat(),
      ]);
    `);
    expect(fields).toEqual({ timestamps: true, created: 'created_at', updated: 'updated_at', deleted: 'deleted_at', format: 'Y-m-d H:i:s' });
  });

  it('迁移后的注册夹具验证邮箱长度、空值、活跃重复、冷静期拒绝及过期可重用', () => {
    // Reuse the real migration (including login_attempts), not an incomplete hand-built member table.
    expect(runBackendTests('RemainingSoftDeleteGapTest.php',
      'test_registration_email_active_cooling_and_expired_uniqueness')).toContain('OK (1 test');
  });

  it('IP 规范化保留 IPv4/IPv6 并拒绝非法值，不依赖 $_SERVER', () => {
    const result = backendJson(String.raw`
      echo json_encode(array_map(
        [\app\common\service\member\MemberInput::class, 'normalizeIp'],
        [' 127.0.0.1 ', '2001:db8::1', '999.0.0.1', '127.0.0.1, 8.8.8.8', '']
      ));
    `);
    expect(result).toEqual(['127.0.0.1', '2001:db8::1', '', '', '']);
    expect(readProjectFile('admin/app/common/service/member/MemberInput.php')).not.toContain('$_SERVER');
  });

  it('SystemRole 拒绝活跃 name/code 重复、允许软删后复用并保留数据库冲突处理', () => {
    const source = readProjectFile('admin/app/admin/authorization/controller/SystemRole.php');
    const writes = source.match(/public function create\(\): Response[\s\S]*?(?=\n    public function delete)/)?.[0] ?? '';

    expect(runBackendTests('RemainingSoftDeleteGapTest.php',
      'test_role_active_name_and_code_duplicates_rejected_and_deleted_values_reusable')).toContain('OK (1 test');
    expect(writes).toMatch(/catch\s*\(\\Throwable\s+\$\w+\)/);
    expect(writes).toMatch(/(?:1062|Duplicate entry)[\s\S]*(?:角色名称或标识已存在|duplicateError)/);
  });

  it('SystemConfig 配置分组写入捕获数据库唯一冲突', () => {
    const source = readProjectFile('admin/app/admin/system/controller/SystemConfig.php');
    const groupWrites = source.match(/public function createGroup\(\): Response[\s\S]*?(?=\n    public function deleteGroup)/)?.[0] ?? '';

    expect(groupWrites).toMatch(/catch\s*\(\\Throwable\s+\$\w+\)/);
    expect(groupWrites).toMatch(/(?:1062|Duplicate entry)[\s\S]*(?:配置分组编码已存在|duplicateError)/);
  });
});
