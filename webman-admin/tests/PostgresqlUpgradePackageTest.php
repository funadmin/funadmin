<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\service\UpgradePackageVerifier;
use app\admin\system\service\UpgradeManifestVerifier;
use PHPUnit\Framework\TestCase;
use support\Db;

final class PostgresqlUpgradePackageTest extends TestCase
{
    public function test_signed_upgrade_accepts_only_matching_postgresql_migration_inventory(): void
    {
        if (getenv('TEST_PGSQL') !== '1') $this->markTestSkipped('需要隔离 PostgreSQL');
        $root = sys_get_temp_dir() . '/pg-package-' . bin2hex(random_bytes(5));
        mkdir($root . '/migrations/pgsql', 0700, true);
        $previous = getenv('CORE_DATABASE_PATH');
        putenv('CORE_DATABASE_PATH=' . $root);
        $sql = 'SELECT 1;';
        file_put_contents($root . '/migrations/pgsql/001_baseline.sql', $sql);
        $keypair = sodium_crypto_sign_keypair();
        $verifier = new UpgradeManifestVerifier(base64_encode(sodium_crypto_sign_publickey($keypair)));
        $manifest = ['version' => '1.1.0', 'databaseDriver' => 'pgsql', 'coreMigrations' => ['001_baseline.sql' => hash('sha256', $sql)], 'files' => ['database/migrations/pgsql/001_baseline.sql' => hash('sha256', $sql), 'app/pg_fixture.php' => hash('sha256', '<?php // safe')]];
        $manifest['signature'] = ['algorithm' => 'ed25519', 'value' => base64_encode(sodium_crypto_sign_detached($verifier->canonicalPayload($manifest), sodium_crypto_sign_secretkey($keypair)))];
        $zip = new \ZipArchive();
        $zip->open($root . '/upgrade.zip', \ZipArchive::CREATE);
        $zip->addFromString('upgrade.json', json_encode($manifest));
        $zip->addFromString('database/migrations/pgsql/001_baseline.sql', $sql);
        $zip->addFromString('app/pg_fixture.php', '<?php // safe');
        $zip->close();
        try {
            $package = new UpgradePackageVerifier(manifestVerifier: $verifier);
            self::assertSame('1.1.0', $package->verify($root . '/upgrade.zip', '')['version']);
            self::assertSame(realpath($root . '/migrations/pgsql'), $package->mountedCoreDirectory($manifest));
            $manifest['databaseDriver'] = 'mysql';
            try {
                $package->coreMigrationManifest($manifest);
                self::fail('错误驱动必须拒绝');
            } catch (\RuntimeException $error) { self::assertStringContainsString('驱动', $error->getMessage()); }
            $manifest['databaseDriver'] = 'pgsql';
            $manifest['coreMigrations']['001_baseline.sql'] = str_repeat('0', 64);
            $this->expectException(\RuntimeException::class);
            $package->coreMigrationManifest($manifest);
        } finally {
            putenv($previous === false ? 'CORE_DATABASE_PATH' : 'CORE_DATABASE_PATH=' . $previous);
            unlink($root . '/upgrade.zip'); unlink($root . '/migrations/pgsql/001_baseline.sql');
            rmdir($root . '/migrations/pgsql'); rmdir($root . '/migrations'); rmdir($root);
        }
    }
}
