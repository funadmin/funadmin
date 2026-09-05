<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;
use app\common\validate\MemberValidate;
use fun\helper\IpHelper;
use think\exception\ValidateException;

function fieldGovernanceExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function emailWithLength(int $length): string
{
    $suffix = '@example.com';
    return str_repeat('a', $length - strlen($suffix)) . $suffix;
}

function registrationEmailAccepted(string $email): bool
{
    try {
        return (new MemberValidate())
            ->only(['email'])
            ->remove('email', 'unique')
            ->check(['email' => $email]);
    } catch (ValidateException) {
        return false;
    }
}

fieldGovernanceExpect(registrationEmailAccepted(emailWithLength(60)), '注册邮箱应接受 60 字符边界值');
fieldGovernanceExpect(!registrationEmailAccepted(emailWithLength(61)), '注册邮箱应拒绝超过 60 字符的值');

fieldGovernanceExpect((bool) IpHelper::is_ip('203.0.113.9'), 'IP helper 应接受合法 IPv4');
fieldGovernanceExpect((bool) IpHelper::is_ip('2001:db8::1'), 'IP helper 应接受合法 IPv6');
fieldGovernanceExpect(!(bool) IpHelper::is_ip('999.1.1.1'), 'IP helper 应拒绝非法 IPv4');
fieldGovernanceExpect(!(bool) IpHelper::is_ip('2001:db8:::1'), 'IP helper 应拒绝非法 IPv6');

$memberSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/model/Member.php');
$loginStart = strpos($memberSource, 'public function login()');
$registrationStart = strpos($memberSource, 'public function reg()');
$loginSource = $loginStart !== false && $registrationStart !== false
    ? substr($memberSource, $loginStart, $registrationStart - $loginStart)
    : '';

fieldGovernanceExpect(str_contains($loginSource, 'request()->ip()'), 'Member 登录必须从 request()->ip() 获取客户端 IP');
fieldGovernanceExpect(!str_contains($loginSource, '$_SERVER'), 'Member 登录不得直接读取 $_SERVER');

$migrationDirectory = dirname(__DIR__) . '/database/migrations';
$migrationFiles = glob($migrationDirectory . '/*.sql') ?: [];
$migrationService = new MigrationService();
$versionSequence = new ReflectionMethod($migrationService, 'assertVersionSequence');
$versionSequence->setAccessible(true);
$versionSequence->invoke($migrationService, $migrationFiles, 'core');

$migrationNames = array_map(static fn (string $file): string => basename($file), $migrationFiles);
fieldGovernanceExpect(in_array('021_time_columns_no_default.sql', $migrationNames, true), '时间列 migration 必须使用唯一版本 021');
fieldGovernanceExpect(in_array('022_laravel_field_cutover.sql', $migrationNames, true), '必须提供 forward-only expand/backfill/index migration');
$numericVersions = array_map(static function (string $name): string {
    preg_match('/^(\d+)_/', $name, $matches);
    return $matches[1] ?? '';
}, $migrationNames);
fieldGovernanceExpect(count($numericVersions) === count(array_unique($numericVersions)), '核心 migration 数字版本必须全局唯一');

$unknownDuplicateDirectory = sys_get_temp_dir() . '/funadmin-migration-sequence-' . bin2hex(random_bytes(6));
mkdir($unknownDuplicateDirectory);
$unknownDuplicateFiles = [
    $unknownDuplicateDirectory . '/023_alpha.sql',
    $unknownDuplicateDirectory . '/023_beta.sql',
];
foreach ($unknownDuplicateFiles as $file) {
    file_put_contents($file, 'SELECT 1;');
}
try {
    $versionSequence->invoke($migrationService, $unknownDuplicateFiles, 'core');
    throw new RuntimeException('未知核心 migration 重号必须被拒绝');
} catch (ReflectionException $exception) {
    throw $exception;
} catch (Throwable $exception) {
    fieldGovernanceExpect(str_contains($exception->getMessage(), '数字版本重复'), '未知核心 migration 重号应返回明确错误');
} finally {
    foreach ($unknownDuplicateFiles as $file) {
        unlink($file);
    }
    rmdir($unknownDuplicateDirectory);
}

$sortKey = new ReflectionMethod($migrationService, 'migrationSortKey');
$sortKey->setAccessible(true);
$orderedNames = $migrationFiles;
usort($orderedNames, static fn (string $left, string $right): int => strcmp(
    $sortKey->invoke($migrationService, $left),
    $sortKey->invoke($migrationService, $right)
));
$orderedNames = array_map(static fn (string $file): string => basename($file), $orderedNames);
fieldGovernanceExpect(
    array_search('020_schema_integrity_finalize.sql', $orderedNames, true) < array_search('021_time_columns_no_default.sql', $orderedNames, true),
    '时间列 migration 必须在 020 最终修复之后执行'
);
fieldGovernanceExpect(
    array_search('021_time_columns_no_default.sql', $orderedNames, true) < array_search('022_laravel_field_cutover.sql', $orderedNames, true),
    'Laravel 字段 cutover 必须在 021 时间列 migration 之后'
);

$softDeleteTraitSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/model/concern/LaravelSoftDelete.php');
fieldGovernanceExpect(str_contains($softDeleteTraitSource, "'deleted_at'"), '软删除必须写入 deleted_at');
fieldGovernanceExpect(str_contains($softDeleteTraitSource, "'deleted_at' => null"), '恢复必须清空 deleted_at');

$businessDirectories = [dirname(__DIR__) . '/app', dirname(__DIR__) . '/extend'];
$allowedCompatibilityFiles = [
    realpath(dirname(__DIR__) . '/app/common/model/BaseModel.php'),
    realpath(dirname(__DIR__) . '/app/common/model/concern/LaravelSoftDelete.php'),
];
foreach ($businessDirectories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if (!$item->isFile() || $item->getExtension() !== 'php' || in_array($item->getRealPath(), $allowedCompatibilityFiles, true)) {
            continue;
        }
        $source = (string) file_get_contents($item->getPathname());
        fieldGovernanceExpect(
            !preg_match('/\\b(?:create_time|update_time|delete_time)\\b/', $source),
            '业务源码不得读写 legacy 时间字段：' . $item->getPathname()
        );
    }
}

$fieldVerifySource = (string) file_get_contents(dirname(__DIR__) . '/app/common/model/FieldVerify.php');
fieldGovernanceExpect((bool) preg_match('/protected \\$pk\\s*=\\s*[\'\"]verify[\'\"]/', $fieldVerifySource), 'FieldVerify 必须使用 verify 字符串主键');
fieldGovernanceExpect((bool) preg_match('/protected \\$keyType\\s*=\\s*[\'\"]string[\'\"]/', $fieldVerifySource), 'FieldVerify 必须声明字符串主键类型');
$migration013 = (string) file_get_contents($migrationDirectory . '/013_field_hygiene.sql');
fieldGovernanceExpect(!str_contains($migration013, 'ADD COLUMN `id`'), '013 不得向 field_verify 添加第二主键');
fieldGovernanceExpect(str_contains($migration013, 'ADD PRIMARY KEY (`verify`)'), '013 必须在缺少主键时补齐 verify 主键');
$migration020 = (string) file_get_contents($migrationDirectory . '/020_schema_integrity_finalize.sql');
fieldGovernanceExpect((bool) preg_match('/COLUMN_NAME\\s*=\\s*[\'\"]verify[\'\"][^;]*COLUMN_TYPE\\s*=\\s*[\'\"]varchar\\(50\\)[\'\"]/', $migration020), '020 必须复核 verify varchar(50)');
fieldGovernanceExpect((bool) preg_match('/CONSTRAINT_NAME\\s*=\\s*[\'\"]PRIMARY[\'\"][^;]*HAVING COUNT\\(\\*\\) = 1[^;]*COLUMN_NAME = [\'\"]verify[\'\"]/', $migration020), '020 必须严格复核 verify 单列主键');
$fieldCutover = (string) file_get_contents($migrationDirectory . '/022_laravel_field_cutover.sql');
fieldGovernanceExpect(!preg_match('/\\b(?:DROP|TRUNCATE|RENAME)\\b/i', $fieldCutover), '字段 cutover migration 必须保留 legacy 列');
fieldGovernanceExpect(str_contains($fieldCutover, 'sort_order'), '字段 cutover migration 必须 expand/backfill sort_order');

$forwardOnly = new ReflectionMethod($migrationService, 'assertForwardOnly');
$forwardOnly->setAccessible(true);
$splitStatements = new ReflectionMethod($migrationService, 'statements');
$splitStatements->setAccessible(true);
foreach ($migrationFiles as $migrationFile) {
    $migrationSql = (string) file_get_contents($migrationFile);
    $forwardOnly->invoke($migrationService, $migrationSql, $migrationFile);
    fieldGovernanceExpect(
        count($splitStatements->invoke($migrationService, $migrationSql)) > 0,
        'Migration 必须能拆分出至少一条 SQL：' . basename($migrationFile)
    );
}

echo "field governance behavior test passed\n";
