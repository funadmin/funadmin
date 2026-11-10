<?php

declare(strict_types=1);
namespace Tests;

use app\admin\system\controller\{SystemConfig, SystemLanguage, SystemAttachment};
use app\admin\authentication\service\AuthPruneService;
use app\common\model\{LanguageLine, Attach};
use PHPUnit\Framework\TestCase;
use support\{Context, Db, Request};

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class RemainingSoftDeleteGapTest extends TestCase
{
    protected function setUp(): void { (new \app\common\service\database\MigrationService())->run(); Db::beginTransaction(); }
    protected function tearDown(): void { Db::rollBack(); Context::destroy(); }
    private function call(string $class, string $method, array $post = [], array $args = []): array
    {
        $request = new Request("POST /admin/system/test HTTP/1.1\r\n\r\n");
        $request->setPost($post);
        Context::set(\Webman\Http\Request::class, $request);
        return json_decode((new $class())->$method(...$args)->rawBody(), true);
    }
    private function trashed(string $table, int $id): void
    {
        $row = Db::table($table)->where('id', $id)->first();
        self::assertNotNull($row);
        self::assertNotNull($row->deleted_at);
        self::assertSame($id, (int) $row->deleted_token);
    }
    public function test_config_and_group_delete_keep_rows_and_allow_same_keys(): void
    {
        $group = $this->call(SystemConfig::class, 'createGroup', ['name'=>'round9', 'title'=>'Round 9'])['data'];
        $payload = ['code'=>'round9_key', 'group'=>'round9', 'type'=>'text', 'value'=>'old'];
        $item = $this->call(SystemConfig::class, 'create', $payload)['data'];
        self::assertSame(422, $this->call(SystemConfig::class, 'deleteGroup', args: [$group['id']])['code']);
        self::assertSame(200, $this->call(SystemConfig::class, 'delete', ['ids'=>[$item['id']]])['code']);
        $this->trashed('config', $item['id']);
        self::assertSame(200, $this->call(SystemConfig::class, 'deleteGroup', args: [$group['id']])['code']);
        $this->trashed('config_group', $group['id']);
        self::assertSame(200, $this->call(SystemConfig::class, 'createGroup', ['name'=>'round9', 'title'=>'New'])['code']);
        self::assertSame(200, $this->call(SystemConfig::class, 'create', $payload)['code']);
    }
    public function test_language_and_lines_soft_delete_and_keys_can_be_recreated(): void
    {
        $language = $this->call(SystemLanguage::class, 'create', ['name'=>'fr-fr'])['data'];
        $line = LanguageLine::forceCreate(['locale'=>'fr-fr', 'key'=>'round9.test', 'value'=>'old']);
        self::assertSame(200, $this->call(SystemLanguage::class, 'delete', args: [$language['id']])['code']);
        $this->trashed('language', $language['id']); $this->trashed('language_line', $line->id);
        self::assertSame(200, $this->call(SystemLanguage::class, 'create', ['name'=>'fr-fr'])['code']);
        $new = LanguageLine::forceCreate(['locale'=>'fr-fr', 'key'=>'round9.test', 'value'=>'new']);
        self::assertNotSame($line->id, $new->id);
        // saveLine must update the live duplicate, not try restoring an older deleted record.
        $saved = $this->call(SystemLanguage::class, 'saveLine', ['locale'=>'fr-fr','key'=>'round9.test','value'=>'updated']);
        self::assertSame(200, $saved['code']); self::assertSame($new->id, $saved['data']['id']);
    }
    public function test_attachment_delete_preserves_file_and_metadata_even_without_driver(): void
    {
        $path = public_path() . '/storage/round9-' . uniqid() . '.txt';
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'must survive');
        try {
            $item = Attach::forceCreate(['group_id'=>1,'name'=>basename($path),'path'=>'/storage/'.basename($path), 'storage_key'=>basename($path),'driver'=>'local','size'=>1,'status'=>1]);
            self::assertSame(200, $this->call(SystemAttachment::class, 'delete', ['ids'=>[$item->id]])['code']);
            $this->trashed('attach', $item->id); self::assertSame('must survive', file_get_contents($path));
            $cloud = Attach::forceCreate(['group_id'=>1,'name'=>'cloud','path'=>'https://example.test/file','driver'=>'missing_cloud','size'=>1,'status'=>1]);
            self::assertSame(200, $this->call(SystemAttachment::class, 'delete', ['ids'=>[$cloud->id]])['code']);
            $this->trashed('attach', $cloud->id);
        } finally { unlink($path); }
    }
    public function test_plugin_controller_wrappers_preserve_restore_conflict_409(): void
    {
        $request = new Request("POST /admin/system/plugin HTTP/1.1\r\n\r\n");
        Context::set(\Webman\Http\Request::class, $request);
        foreach ([\app\admin\plugin\controller\DevPlugin::class, \app\admin\plugin\controller\SystemPlugin::class] as $class) {
            $method = new \ReflectionMethod($class, 'execute');
            $response = $method->invoke(new $class(), static function (): never {
                throw new \app\common\model\concern\RestoreConflictException();
            });
            $body = json_decode($response->rawBody(), true);
            self::assertSame(409, $body['code']);
            self::assertSame(\app\common\model\concern\RestoreConflictException::MESSAGE, $body['msg']);
        }
    }

    public function test_auth_prune_marks_only_expired_live_rows_and_is_idempotent(): void
    {
        $now = time();
        foreach (['admin_auth_tokens','browser_sessions','login_attempts'] as $table) {
            $base = match($table) {
                'admin_auth_tokens' => ['token_hash'=>str_repeat('a',64),'admin_id'=>1,'purpose'=>'test','credential_version'=>'test','expires_at'=>date('Y-m-d H:i:s',$now),'created_at'=>date('Y-m-d H:i:s',$now)],
                'browser_sessions' => ['session_hash'=>str_repeat('a',64),'csrf_token'=>'test','expires_at'=>date('Y-m-d H:i:s',$now)],
                default => ['key'=>'round9','attempts'=>1,'window_start'=>$now-900],
            };
            $id = Db::table($table)->insertGetId($base);
            $future = $base;
            $key = array_key_first($future); $future[$key] = 'future';
            $future[$table==='login_attempts'?'window_start':'expires_at'] = $table==='login_attempts'?$now-899:date('Y-m-d H:i:s',$now+1);
            $futureId = Db::table($table)->insertGetId($future);
            $old = $base; $old[$key]='old'; $old['deleted_at']='2020-01-01 00:00:00';
            $oldId = Db::table($table)->insertGetId($old); Db::table($table)->where('id',$oldId)->update(['deleted_token'=>$oldId]);
            (new AuthPruneService())->prune($now);
            $this->trashed($table,$id);
            self::assertNull(Db::table($table)->where('id',$futureId)->value('deleted_at'));
            self::assertSame('2020-01-01 00:00:00',Db::table($table)->where('id',$oldId)->value('deleted_at'));
        }
        self::assertSame(['tokens'=>0,'sessions'=>0,'attempts'=>0], (new AuthPruneService())->prune($now));
    }
}
