<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;

final class CoreMigrationSourceTest extends TestCase
{
    public function test_default_source_is_admin_core_sql_not_webman_php_migrations(): void
    {
        $previous = getenv('CORE_DATABASE_PATH');
        putenv('CORE_DATABASE_PATH');
        try {
            self::assertTrue(method_exists(MigrationService::class, 'coreDirectory'), '缺少权威 SQL 源解析入口');
            self::assertSame(base_path() . '/database/core/migrations', MigrationService::coreDirectory());
            self::assertSame(MigrationService::coreDirectory(), \app\common\service\install\InstallSupport::paths(base_path())['migrations']);
        } finally {
            $previous === false ? putenv('CORE_DATABASE_PATH') : putenv('CORE_DATABASE_PATH=' . $previous);
        }
    }

    public function test_upgrade_manifest_requires_complete_signed_authoritative_core_sql(): void
    {
        $checksums = (new \app\common\service\database\CoreDatabaseAudit(MigrationService::coreDirectory()))->manifest()['checksums'];
        $files = [];
        foreach ($checksums as $name => $hash) $files['database/core/migrations/' . $name] = $hash;
        $manifest = ['files' => $files, 'coreMigrations' => $checksums];
        $verifier = new \app\admin\system\service\UpgradePackageVerifier();
        self::assertSame($checksums, $verifier->coreMigrationManifest($manifest));
        foreach (['declaration', 'baseline', 'checksum', 'historical', 'php'] as $case) {
            $changed = $manifest;
            if ($case === 'declaration') unset($changed['coreMigrations']);
            if ($case === 'baseline') {
                unset($changed['coreMigrations']['001_baseline.sql'], $changed['files']['database/core/migrations/001_baseline.sql']);
            }
            if ($case === 'checksum') $changed['coreMigrations']['001_baseline.sql'] = str_repeat('0', 64);
            if ($case === 'historical') {
                $changed['coreMigrations']['001_baseline.sql'] = str_repeat('0', 64);
                $changed['files']['database/core/migrations/001_baseline.sql'] = str_repeat('0', 64);
            }
            if ($case === 'php') $changed['files']['database/core/migrations/001_core.php'] = str_repeat('a', 64);
            try {
                $verifier->coreMigrationManifest($changed);
                self::fail('升级核心源异常未拒绝：' . $case);
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('核心 SQL', $error->getMessage());
            }
        }
    }

    public function test_signed_zip_is_rejected_without_core_contract_and_uses_mounted_sql_not_local_php(): void
    {
        $keys = sodium_crypto_sign_keypair();
        $signer = new \app\admin\system\service\UpgradeManifestVerifier(base64_encode(sodium_crypto_sign_publickey($keys)));
        $verifier = new \app\admin\system\service\UpgradePackageVerifier(manifestVerifier: $signer);
        $directory = MigrationService::coreDirectory();
        $checksums = (new \app\common\service\database\CoreDatabaseAudit($directory))->manifest()['checksums'];
        $files = [];
        foreach ($checksums as $name => $hash) $files['database/core/migrations/' . $name] = $hash;
        $archive = tempnam(sys_get_temp_dir(), 'core-upgrade-');
        try {
            foreach ([false, true] as $withContract) {
                $manifest = ['version' => '99.0.0', 'files' => $files];
                if ($withContract) $manifest['coreMigrations'] = $checksums;
                $manifest['signature'] = ['algorithm' => 'ed25519', 'value' => base64_encode(sodium_crypto_sign_detached($signer->canonicalPayload($manifest), sodium_crypto_sign_secretkey($keys)))];
                $zip = new \ZipArchive();
                self::assertTrue($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
                foreach ($checksums as $name => $hash) $zip->addFile($directory . '/' . $name, 'database/core/migrations/' . $name);
                $zip->addFromString('upgrade.json', json_encode($manifest, JSON_THROW_ON_ERROR));
                $zip->close();
                try {
                    $result = $verifier->verify($archive, '');
                    self::assertTrue($withContract, '有签名但缺核心 SQL 契约也必须拒绝');
                    self::assertSame($checksums, $result['manifest']['coreMigrations']);
                    self::assertSame($directory, $verifier->mountedCoreDirectory($result['manifest']));
                    $future = $result['manifest'];
                    $future['coreMigrations']['999_future.sql'] = str_repeat('b', 64);
                    $future['files']['database/core/migrations/999_future.sql'] = str_repeat('b', 64);
                    try {
                        $verifier->mountedCoreDirectory($future);
                        self::fail('只读根源未部署新 SQL 时禁止假升级成功');
                    } catch (\RuntimeException $error) {
                        self::assertStringContainsString('只读核心 SQL', $error->getMessage());
                    }
                } catch (\RuntimeException $error) {
                    if ($withContract) throw $error;
                    self::assertStringContainsString('核心 SQL', $error->getMessage());
                }
            }
        } finally { if (is_file($archive)) unlink($archive); }
    }

    public function test_missing_explicit_source_never_falls_back_to_php_migrations(): void
    {
        $previous = getenv('CORE_DATABASE_PATH');
        putenv('CORE_DATABASE_PATH=' . sys_get_temp_dir() . '/missing-core-' . bin2hex(random_bytes(8)));
        try {
            self::assertTrue(method_exists(MigrationService::class, 'coreDirectory'), '缺少权威 SQL 源解析入口');
            $this->expectException(\RuntimeException::class);
            MigrationService::coreDirectory();
        } finally {
            $previous === false ? putenv('CORE_DATABASE_PATH') : putenv('CORE_DATABASE_PATH=' . $previous);
        }
    }
}
