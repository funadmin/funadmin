<?php
namespace Tests;
use app\common\plugin\sdk\Manifest;
use PHPUnit\Framework\TestCase;
final class PluginHookManifestTest extends TestCase
{
    private string $root;
    protected function setUp(): void {
        $this->root = sys_get_temp_dir().'/hookmanifest-'.bin2hex(random_bytes(5)).'/demo';
        mkdir($this->root.'/hooks',0700,true);
        mkdir($this->root.'/web/src/admin/plugins/demo',0700,true);
        file_put_contents($this->root.'/Plugin.php','<?php namespace plugins\\demo; class Plugin {}');
        file_put_contents($this->root.'/hooks/test.php','<?php throw new \\RuntimeException("must never execute");');
        file_put_contents($this->root.'/web/src/admin/plugins/demo/hooks.ts','export const run = () => false');
    }
    protected function tearDown(): void {
        $parent=dirname($this->root);
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($parent,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir()&&!$item->isLink()?rmdir($item->getPathname()):unlink($item->getPathname());
        rmdir($parent);
    }
    private function manifest(array $extra): Manifest {
        file_put_contents($this->root.'/plugin.json',json_encode(array_merge(['schema_version'=>2,'code'=>'demo','name'=>'Demo','version'=>'1.0.0','requires'=>['plugins'=>(object)[]],'entry'=>['class'=>'plugins\\demo\\Plugin','file'=>'Plugin.php']],$extra)));
        return Manifest::fromDirectory($this->root);
    }
    public function test_pure_backend_does_not_execute_sources(): void {
        $m=$this->manifest(['serverHooks'=>[['id'=>'test','point'=>'demo.run','type'=>'action','file'=>'hooks/test.php','handler'=>'run']]]);
        self::assertSame('demo',$m->code());
    }
    public function test_functions_only_frontend_with_empty_components(): void {
        $m=$this->manifest(['adminWeb'=>['source'=>'web/src/admin/plugins/demo','components'=>(object)[],'minFrontendVersion'=>'1','permissions'=>[],'menu'=>[],'routes'=>[]], 'clientHooks'=>[['id'=>'test','point'=>'demo.run','type'=>'filter','file'=>'hooks.ts','export'=>'run']]]);
        self::assertSame('demo',$m->code());
    }
    public function test_duplicate_backend_ids_rejected(): void {
        $this->expectException(\RuntimeException::class);
        $h=['id'=>'test','point'=>'demo.run','type'=>'action','file'=>'hooks/test.php','handler'=>'run'];
        $this->manifest(['serverHooks'=>[$h,$h]]);
    }
    public function test_permission_on_public_hook_rejected(): void {
        $this->expectException(\RuntimeException::class);
        $this->manifest(['adminWeb'=>['source'=>'web/src/admin/plugins/demo','components'=>(object)[],'minFrontendVersion'=>'1','permissions'=>[],'menu'=>[],'routes'=>[]], 'clientHooks'=>[['id'=>'test','point'=>'demo.run','type'=>'action','file'=>'hooks.ts','export'=>'run','permission'=>'system:plugin:list','audiences'=>['public']]]]);
    }
    public function test_invalid_hook_type_and_priority_rejected(): void {
        $this->expectException(\RuntimeException::class);
        $this->manifest(['serverHooks'=>[['id'=>'test','point'=>'demo.run','type'=>'component','file'=>'hooks/test.php','handler'=>'run','priority'=>'high']]]);
    }
    public function test_backend_path_outside_hooks_rejected(): void {
        $this->expectException(\RuntimeException::class);
        $this->manifest(['serverHooks'=>[['id'=>'test','point'=>'demo.run','type'=>'action','file'=>'Plugin.php','handler'=>'run']]]);
    }
    public function test_hook_symlink_rejected(): void {
        symlink($this->root.'/Plugin.php',$this->root.'/hooks/linked.php');
        $this->expectException(\RuntimeException::class);
        $this->manifest(['serverHooks'=>[['id'=>'test','point'=>'demo.run','type'=>'action','file'=>'hooks/linked.php','handler'=>'run']]]);
    }
    public function test_retired_hook_fields_rejected_with_rename_hint(): void {
        foreach (['backendHooks'=>'serverHooks','frontendHooks'=>'clientHooks'] as $retired=>$current) {
            try { $this->manifest([$retired=>[]]); self::fail($retired.' must be rejected'); }
            catch (\RuntimeException $e) { self::assertStringContainsString($current, $e->getMessage()); }
        }
    }
    public function test_missing_component_reference_rejected(): void {
        $this->expectException(\RuntimeException::class);
        $this->manifest(['adminWeb'=>['source'=>'web/src/admin/plugins/demo','components'=>(object)[],'minFrontendVersion'=>'1','permissions'=>[],'menu'=>[],'routes'=>[]], 'clientHooks'=>[['id'=>'test','point'=>'demo.run','type'=>'component','component'=>'Missing']]]);
    }
}
