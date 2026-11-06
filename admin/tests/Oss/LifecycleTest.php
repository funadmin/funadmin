<?php
declare(strict_types=1);
namespace Tests\Oss;
require_once __DIR__.'/bootstrap.php';
use app\admin\plugin\service\PluginService;
use app\admin\plugins\oss\service\{Schema,ProfileService};
use app\common\service\database\MigrationService;
use support\Db;
use PHPUnit\Framework\TestCase;
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class LifecycleTest extends TestCase
{
    public function test_native_install_enable_upgrade_uninstall_and_reinstall_preserve_storage_profiles():void{
        (new MigrationService())->run();Schema::install();$root=sys_get_temp_dir().'/oss-lifecycle-'.bin2hex(random_bytes(8));mkdir($root.'/plugins/oss',0700,true);
        foreach (['system_migration','plugin_resource','plugin_operation','plugin','form_schema_version','form'] as $table) Db::schema()->dropIfExists($table);
        Db::schema()->create('system_migration', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->id(); $table->string('scope'); $table->string('version'); $table->string('checksum'); $table->unsignedBigInteger('executed_at'); });
        Db::schema()->create('plugin', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('name')->default(''); $table->string('version')->default('');
            foreach (['config', 'manifest', 'last_error', 'recovery_path'] as $name) $table->text($name)->nullable();
            foreach (['db_version', 'operation_token', 'package_hash', 'code_version', 'source', 'error_stage', 'requires'] as $name) $table->string($name)->nullable();
            $table->string('composer_conflict_ack', 64)->default(''); $table->string('lifecycle_state')->default('discovered'); $table->boolean('status')->default(false);
            $table->boolean('migration_pending')->default(false); $table->boolean('needs_reinstall')->default(false);
            $table->unsignedBigInteger('state_changed_at')->nullable(); $table->unsignedBigInteger('installed_at')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('plugin_operation', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); foreach (['plugin_code', 'operation', 'operation_token', 'stage', 'from_version', 'to_version', 'source', 'package_hash', 'result'] as $name) $table->string($name);
            $table->integer('progress'); $table->integer('status')->default(1); $table->text('error_message')->nullable(); $table->text('recovery_path')->nullable(); $table->timestamps();
        });
        Db::schema()->create('plugin_resource', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); foreach (['plugin_code', 'version', 'resource_type', 'operation_token', 'source_path', 'target_path', 'sha256'] as $name) $table->string($name);
            $table->string('publication_unit')->nullable(); $table->string('tree_hash')->nullable(); $table->timestamps();
        });
        Db::schema()->create('form', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->id(); $table->string('form_key'); $table->json('schema_document')->nullable(); $table->softDeletes(); });
        Db::schema()->create('form_schema_version', static function (\Illuminate\Database\Schema\Blueprint $table): void { $table->id(); $table->integer('form_id'); $table->integer('version'); $table->json('schema_document'); });
        $source=dirname(__DIR__,2).'/plugins/oss';
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source,\FilesystemIterator::SKIP_DOTS)) as $file){
            if(!$file->isFile())continue;$relative=substr($file->getPathname(),strlen($source)+1);if(str_starts_with($relative,'vendor/'))continue;
            $target=$root.'/plugins/oss/'.$relative;if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);copy($file->getPathname(),$target);
        }
        foreach(['public/plugin-assets/oss/public','storage'] as $dir)if(!is_dir($root.'/plugins/oss/'.$dir))mkdir($root.'/plugins/oss/'.$dir,0700,true);
        // SQLite cannot execute the MySQL migration runner. Schema itself is tested
        // separately; this fixture tests the real lifecycle/publication with its SQL removed.
        $manifestPath=$root.'/plugins/oss/plugin.json';$manifest=json_decode(file_get_contents($manifestPath),true);unset($manifest['migrations']);file_put_contents($manifestPath,json_encode($manifest));
        $service=new PluginService($root);
        try{
            self::assertTrue($service->installPlugin('oss'));
            self::assertFileExists($root.'/app/admin/plugins/oss/controller/Storage.php');self::assertFileExists($root.'/web/src/admin/modules/oss/pages/Index.vue');
            self::assertTrue($service->enablePlugin('oss'),'An unconfigured plugin must be enabled before its settings UI is accessible');
            $profiles=new ProfileService(\Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString());
            $profile=$profiles->save(['name'=>'History','provider'=>'qiniu','bucket'=>'history-bucket','domain'=>'https://cdn.example.com','region'=>'z0','access_key'=>'fixture-ak','secret_key'=>'fixture-sk']);
            self::assertTrue($service->disablePlugin('oss'));$manifest['version']='1.1.0';file_put_contents($manifestPath,json_encode($manifest));self::assertTrue($service->updatePlugin('oss'));
            self::assertSame($profile['id'],$profiles->listing()[0]['id']);self::assertTrue($service->uninstallPlugin('oss'));
            self::assertFileDoesNotExist($root.'/app/admin/plugins/oss/controller/Storage.php');self::assertFileDoesNotExist($root.'/web/src/admin/modules/oss/pages/Index.vue');
            self::assertSame($profile['id'],$profiles->listing()[0]['id']);self::assertTrue($service->installPlugin('oss'));self::assertTrue($service->enablePlugin('oss'));self::assertSame($profile['id'],$profiles->listing()[0]['id']);
        }finally{
            $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($files as $file)$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());rmdir($root);
        }
    }
}
