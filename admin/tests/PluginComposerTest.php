<?php

declare(strict_types=1);

use app\common\plugin\sdk\{HostPackageRepository, ComposerConflictDetector, DependencyValidator, Manifest};
use PHPUnit\Framework\TestCase;

final class PluginComposerTest extends TestCase
{
    private string $dir;
    protected function setUp(): void { $this->dir = sys_get_temp_dir() . '/plugin-composer-' . bin2hex(random_bytes(8)); mkdir($this->dir); }
    protected function tearDown(): void {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); } rmdir($this->dir);
    }
    private function host(array $versions): HostPackageRepository {
        $file = $this->dir . '/installed.php';
        file_put_contents($file, '<?php return ' . var_export(['versions' => array_map(fn($v) => ['pretty_version' => $v], $versions)], true) . ';');
        return new HostPackageRepository($file);
    }
    private function vendor(string $code, array $packages, bool $legacy = false): string {
        $dir = $this->dir . '/' . $code; mkdir($dir . '/vendor/composer', 0755, true);
        file_put_contents($dir . '/vendor/composer/installed.json', json_encode($legacy ? $packages : ['packages' => $packages])); return $dir;
    }
    public function testHostRepositoryNeverUsesAggregatedPluginPackages(): void {
        self::assertSame(['test/lib' => 'v1.2.0'], $this->host(['test/lib' => 'v1.2.0'])->packages());
    }
    public function testDetectsHostAndPluginConflictsWithComposerOneAndTwoMetadata(): void {
        $detector = new ComposerConflictDetector($this->host(['test/lib' => '1.0.0', 'test/zero' => '0.2.0', 'test/dev' => 'dev-main', 'test/same' => '2.0.0']));
        $dir = $this->vendor('alpha', [
            ['name'=>'test/lib','version'=>'2.0.0'], ['name'=>'test/zero','version'=>'0.3.0'],
            ['name'=>'test/dev','version'=>'1.0.0'], ['name'=>'test/same','version'=>'2.1.0'], ['name'=>'test/peer','version'=>'1.0.0']]);
        $other = $this->vendor('beta', [['name'=>'test/peer','version'=>'2.0.0']], true);
        $conflicts = $detector->detect($dir, ['beta' => $other, 'alpha' => $dir]);
        self::assertSame(['test/dev','test/lib','test/peer','test/zero'], array_column($conflicts, 'package'));
        self::assertSame('plugin:beta', $conflicts[2]['source']);
        self::assertSame('2.0.0', $conflicts[2]['otherVersion']);
        self::assertSame(ComposerConflictDetector::fingerprint($conflicts), ComposerConflictDetector::fingerprint(array_reverse($conflicts)));
    }
    public function testHostRequirementsDoNotAcceptPluginCarriedPackagesAndReportAllFailures(): void {
        $manifest = Manifest::fromCompiled($this->dir . '/alpha', ['code'=>'alpha', 'requires'=>['composer'=>['test/missing'=>'^1.0', 'test/lib'=>'>=2.0']]]);
        try { (new DependencyValidator('1.0', PHP_VERSION, $this->host(['test/lib'=>'1.0.0'])))->assertComposerSatisfied($manifest); self::fail('Expected missing requirements'); }
        catch (RuntimeException $e) { self::assertStringContainsString('test/missing:^1.0', $e->getMessage()); self::assertStringContainsString('test/lib:>=2.0', $e->getMessage()); self::assertStringContainsString('composer require', $e->getMessage()); }
    }
    public function testSupportedHostRequirementMatchesVersionAndUnsupportedSyntaxIsRejected(): void {
        $host = $this->host(['test/lib'=>'v2.3.4']);
        $validator = new DependencyValidator('1.0', PHP_VERSION, $host);
        $validator->assertComposerSatisfied(Manifest::fromCompiled($this->dir . '/alpha', ['code'=>'alpha','requires'=>['composer'=>['test/lib'=>'^2.0, <3.0']]]));
        $this->expectException(RuntimeException::class);
        $validator->assertComposerSatisfied(Manifest::fromCompiled($this->dir . '/alpha', ['code'=>'alpha','requires'=>['composer'=>['test/lib'=>'~2.0']]]));
    }
    public function testMalformedVendorMetadataIsNotSilentlyIgnored(): void {
        $dir = $this->vendor('alpha', []); file_put_contents($dir . '/vendor/composer/installed.json', '{bad');
        $this->expectException(RuntimeException::class); (new ComposerConflictDetector($this->host([])))->detect($dir);
    }
    public function testVendorLoaderAppendsAndLoadsOnlyOnce(): void {
        $dir = $this->dir . '/alpha'; mkdir($dir . '/vendor', 0755, true);
        file_put_contents($dir . '/vendor/autoload.php', '<?php $GLOBALS["composer_test_loads"] = ($GLOBALS["composer_test_loads"] ?? 0) + 1; $l = new \\Composer\\Autoload\\ClassLoader(__DIR__); $l->register(true); return $l;');
        unset($GLOBALS['composer_test_loads']);
        \app\common\plugin\sdk\PluginVendorAutoloader::ensure($dir);
        $loaders = spl_autoload_functions();
        $last = end($loaders)[0]; self::assertInstanceOf(\Composer\Autoload\ClassLoader::class, $last);
        \app\common\plugin\sdk\PluginVendorAutoloader::ensure($dir);
        self::assertSame(1, $GLOBALS['composer_test_loads']);
        $last->unregister(); unset($GLOBALS['composer_test_loads']);
    }

    public function testWarningAuditSupportsCoreMigratedSchemaWithoutLosingConflicts(): void {
        (new \app\common\service\database\MigrationService())->run();
        \support\Db::beginTransaction();
        try {
            (new \app\admin\plugin\service\PluginOperationAuditService())->composerWarning('alpha', 'operation-token', [['package'=>'test/lib']], '42');
            $row = \support\Db::table('plugin_operation')->where('plugin_code', 'alpha')->first();
            self::assertNotNull($row);
            $metadata = json_decode($row->error_message, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('test/lib', $metadata['conflicts'][0]['package']);
            self::assertSame('42', $metadata['actor']);
        } finally { \support\Db::rollBack(); }
    }

    public function testZeroMajorHostConstraintRejectsNextMinorRelease(): void {
        $validator = new DependencyValidator('1.0', PHP_VERSION, $this->host(['test/lib'=>'0.3.0']));
        $this->expectException(RuntimeException::class);
        $validator->assertComposerSatisfied(Manifest::fromCompiled($this->dir . '/alpha', ['code'=>'alpha','requires'=>['composer'=>['test/lib'=>'^0.2.1']]]));
    }

    public function testSchemaAllowsComposerPackagesButRejectsPlatformPackages(): void {
        $directory = (new \app\common\plugin\sdk\PluginScaffolder($this->dir))->scaffold('schemafixture');
        // Scaffolder returns metadata; its output directory follows the plugin code.
        $directory = $this->dir . '/schemafixture'; $file = $directory . '/plugin.json';
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $data['requires']['composer'] = ['vendor/package'=>'^1.0']; file_put_contents($file, json_encode($data));
        self::assertSame(['vendor/package'=>'^1.0'], Manifest::fromDirectory($directory)->composerRequirements());
        $data['requires']['composer'] = ['ext-json'=>'^1.0']; file_put_contents($file, json_encode($data));
        $this->expectException(RuntimeException::class); Manifest::fromDirectory($directory);
    }
    public function testValidateCommandFailsHostRequirementsButOnlyWarnsForBundledConflict(): void {
        (new \app\common\service\database\MigrationService())->run();
        (new \app\common\plugin\sdk\PluginScaffolder($this->dir . '/plugins'))->scaffold('commandfixture');
        $directory = $this->dir . '/plugins/commandfixture'; $file = $directory . '/plugin.json';
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $data['requires']['composer'] = ['test/missing'=>'^1.0']; file_put_contents($file, json_encode($data));
        mkdir($this->dir . '/vendor/composer', 0755, true);
        file_put_contents($this->dir . '/vendor/composer/installed.php', '<?php return ["versions"=>["test/lib"=>["pretty_version"=>"1.0.0"]]];');
        $command = new \Symfony\Component\Console\Tester\CommandTester(new \app\admin\plugin\command\PluginValidate($this->dir));
        self::assertSame(1, $command->execute(['name'=>'commandfixture']));
        self::assertMatchesRegularExpression('/composer\s+require/', $command->getDisplay());
        unset($data['requires']['composer']); file_put_contents($file, json_encode($data));
        mkdir($directory . '/vendor/composer', 0755, true);
        file_put_contents($directory . '/vendor/composer/installed.json', '{"packages":[{"name":"test/lib","version":"2.0.0"}]}');
        self::assertSame(0, $command->execute(['name'=>'commandfixture']), $command->getDisplay());
        $result = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($result['valid']); self::assertSame('test/lib', $result['warnings'][0]['package']);
    }
    public function testHostClassLoaderWinsOverBundledClass(): void {
        $namespace = 'VendorPrecedence' . bin2hex(random_bytes(5));
        mkdir($this->dir . '/host'); file_put_contents($this->dir . '/host/Thing.php', '<?php namespace ' . $namespace . '; class Thing { const ORIGIN = "host"; }');
        $dir = $this->dir . '/precedence'; mkdir($dir . '/vendor', 0755, true);
        file_put_contents($dir . '/vendor/Thing.php', '<?php namespace ' . $namespace . '; class Thing { const ORIGIN = "plugin"; }');
        file_put_contents($dir . '/vendor/autoload.php', '<?php $l = new \\Composer\\Autoload\\ClassLoader(__DIR__); $l->addPsr4(' . var_export($namespace . "\\", true) . ', __DIR__); $l->register(true); return $l;');
        $host = new \Composer\Autoload\ClassLoader(); $host->addPsr4($namespace . "\\", $this->dir . '/host'); $host->register(false);
        try {
            \app\common\plugin\sdk\PluginVendorAutoloader::ensure($dir);
            self::assertSame('host', constant($namespace . '\\Thing::ORIGIN'));
        } finally {
            $host->unregister();
            foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $path=>$loader) {
                if ($path === $dir . '/vendor') $loader->unregister();
            }
        }
    }

    public function testStartupLoadsOnlyEnabledPluginsFromTrustedSnapshotInStableOrder(): void {
        $base = ['enabled'=>true, 'state'=>'enabled', 'operation_token'=>null, 'dependencies'=>[]];
        foreach (['beta','alpha','disabledfixture'] as $code) {
            mkdir($this->dir . '/' . $code . '/vendor', 0755, true);
            file_put_contents($this->dir . '/' . $code . '/vendor/autoload.php', '<?php $GLOBALS["startup_vendor_codes"][] = ' . var_export($code, true) . '; $l = new \\Composer\\Autoload\\ClassLoader(__DIR__); $l->register(true); return $l;');
        }
        $reader = new class extends \app\common\plugin\sdk\PluginActivationReader {
            public \app\common\plugin\sdk\PluginActivationSnapshot $snapshot;
            public function __construct() { $this->snapshot = \app\common\plugin\sdk\PluginActivationSnapshot::untrusted(); }
            public function read(): \app\common\plugin\sdk\PluginActivationSnapshot { return $this->snapshot; }
        };
        $GLOBALS['startup_vendor_codes'] = [];
        \app\common\plugin\sdk\PluginVendorAutoloader::loadEnabled($reader, $this->dir);
        self::assertSame([], $GLOBALS['startup_vendor_codes']);
        $reader->snapshot = \app\common\plugin\sdk\PluginActivationSnapshot::trusted([
            'beta'=>['code'=>'beta']+$base, 'alpha'=>['code'=>'alpha']+$base,
            'disabledfixture'=>['code'=>'disabledfixture', 'enabled'=>false]+$base,
        ]);
        try {
            \app\common\plugin\sdk\PluginVendorAutoloader::loadEnabled($reader, $this->dir);
            self::assertSame(['alpha','beta'], $GLOBALS['startup_vendor_codes']);
        } finally {
            foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $path=>$loader) {
                if (str_starts_with($path, $this->dir . '/')) $loader->unregister();
            }
            unset($GLOBALS['startup_vendor_codes']);
        }
    }

}
