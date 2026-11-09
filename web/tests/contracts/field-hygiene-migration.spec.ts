import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { backendJson } from './backend-runtime';

const fieldVerifyModel = readFileSync(resolve(process.cwd(), '../admin/app/common/model/FieldVerify.php'), 'utf8');
const projectRoot = resolve(process.cwd(), '..');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');

describe('013 字段规范化迁移契约', () => {
  it('FieldVerify 模型使用 id 主键', () => {
    expect(fieldVerifyModel).toMatch(/protected \$pk\s*=\s*['"]id['"]/);
    expect(fieldVerifyModel).not.toMatch(/protected \$pk\s*=\s*['"]verify['"]/);
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

  it('会员注册限制邮箱 60 字符，规范空值并把软删除邮箱纳入查重', () => {
    const result = backendJson(String.raw`
      \support\Db::schema()->create('member', function ($table) {
        $table->id(); $table->string('username'); $table->string('email')->nullable(); $table->softDeletes();
      });
      \support\Db::table('member')->insert(['username'=>'retired', 'email'=>'used@example.com', 'deleted_at'=>'2026-01-01 00:00:00']);
      $service = new \app\common\service\member\MemberAccountService();
      $errors = [];
      foreach (['used@example.com', str_repeat('a', 50).'@example.com'] as $index => $email) {
        try {
          $service->register(['username'=>'emailtest'.$index, 'email'=>$email,
            'password'=>'safe-password-91', 'password_confirm'=>'safe-password-91'], 'email-fixture-'.bin2hex(random_bytes(5)));
          $errors[] = null;
        } catch (\RuntimeException $error) { $errors[] = $error->getMessage(); }
      }
      echo json_encode(['errors'=>$errors,
        'empty'=>\app\common\service\member\MemberInput::normalizeEmail('  '),
        'email'=>\app\common\service\member\MemberInput::normalizeEmail(' User@Example.com ')]);
    `);
    expect(result).toEqual({ errors: ['邮箱已被注册', '请输入有效的邮箱（不超过 60 个字符）'], empty: null, email: 'user@example.com' });
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

  it('SystemRole 对 name/code 使用 withTrashed 判重并捕获数据库唯一冲突', () => {
    const source = readProjectFile('admin/app/admin/authorization/controller/SystemRole.php');
    const writes = source.match(/public function create\(\): Response[\s\S]*?(?=\n    public function delete)/)?.[0] ?? '';

    expect(writes).toMatch(/AuthGroup::withTrashed\(\)[\s\S]*['"]name['"]/);
    expect(writes).toMatch(/AuthGroup::withTrashed\(\)[\s\S]*['"]code['"]/);
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
