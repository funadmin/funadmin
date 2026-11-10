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
        $request->context['admin'] = ['id' => 1];
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
    public function test_language_rename_does_not_move_or_revive_deleted_translation_history(): void
    {
        $old = $this->call(SystemLanguage::class, 'create', ['name'=>'fr-fr'])['data'];
        $line = LanguageLine::forceCreate(['locale'=>'fr-fr', 'key'=>'round9.history', 'value'=>'historical']);
        self::assertSame(200, $this->call(SystemLanguage::class, 'delete', args: [$old['id']])['code']);
        $before = (array) Db::table('language_line')->where('id', $line->id)->first();
        $replacement = $this->call(SystemLanguage::class, 'create', ['name'=>'fr-fr'])['data'];
        $live = LanguageLine::forceCreate(['locale'=>'fr-fr', 'key'=>'round9.live', 'value'=>'current']);
        self::assertSame(200, $this->call(SystemLanguage::class, 'update', ['name'=>'de-de'], [$replacement['id']])['code']);
        self::assertSame('de-de', $live->fresh()->locale);
        self::assertSame($before, (array) Db::table('language_line')->where('id', $line->id)->first());
        $saved = $this->call(SystemLanguage::class, 'saveLine', ['locale'=>'de-de', 'key'=>'round9.history', 'value'=>'new']);
        self::assertSame(200, $saved['code']);
        self::assertNotSame($line->id, $saved['data']['id']);
        self::assertSame($before, (array) Db::table('language_line')->where('id', $line->id)->first());
    }

    private function fixtureMember(string $username, ?string $email = null): \app\admin\system\model\Member
    {
        return \app\admin\system\model\Member::forceCreate([
            'username'=>$username, 'email'=>$email, 'mobile'=>null, 'password'=>'fixture-only',
            'level_id'=>Db::table('member_level')->where('status', 1)->value('id'), 'status'=>1,
        ]);
    }

    private function assertMemberRelationDiff(string $kind): void
    {
        $model = $kind === 'group' ? \app\common\model\MemberGroupRelation::class : \app\common\model\MemberTagRelation::class;
        $table = 'member_'.$kind.'_relation';
        $key = $kind.'_id';
        $member = $this->fixtureMember('relationfixture');
        $targets = [];
        foreach (['kept', 'removed', 'added'] as $name) {
            $targets[] = Db::table('member_'.$kind)->insertGetId(['name'=>'round9-'.$name, 'status'=>1]);
        }
        $attributes = ['member_id'=>$member->id, 'created_at'=>'2020-01-01 01:02:03'];
        $kept = $model::forceCreate($attributes + [$key=>$targets[0]]);
        $removed = $model::forceCreate($attributes + [$key=>$targets[1]]);
        $history = $model::forceCreate($attributes + [$key=>$targets[2]]);
        $history->delete();
        $keptBefore = (array) Db::table($table)->where('id', $kept->id)->first();
        $historyBefore = (array) Db::table($table)->where('id', $history->id)->first();
        $selected = [$targets[0], $targets[2]];
        // Exercise the controller's real integration with RelationRowSync, not a parallel implementation.
        Context::set(\Webman\Http\Request::class, new Request("POST /admin/system/member HTTP/1.1\r\n\r\n"));
        $method = new \ReflectionMethod(\app\admin\system\controller\SystemMember::class, 'syncRelations');
        $controller = new \app\admin\system\controller\SystemMember();
        $started = date('Y-m-d H:i:s');
        $sync = static fn () => $method->invoke($controller, $member, $kind === 'group' ? $selected : [], $kind === 'tag' ? $selected : []);
        $sync();
        self::assertSame($keptBefore, (array) Db::table($table)->where('id', $kept->id)->first());
        $this->trashed($table, $removed->id);
        self::assertSame('2020-01-01 01:02:03', Db::table($table)->where('id', $removed->id)->value('created_at'));
        self::assertSame($historyBefore, (array) Db::table($table)->where('id', $history->id)->first());
        $added = $model::where('member_id', $member->id)->where($key, $targets[2])->firstOrFail();
        self::assertNotSame($history->id, $added->id);
        self::assertNotNull($added->created_at);
        self::assertGreaterThanOrEqual($started, $added->getRawOriginal('created_at'));
        self::assertLessThanOrEqual(date('Y-m-d H:i:s'), $added->getRawOriginal('created_at'));
        self::assertSame(0, (int) $added->deleted_token);
        self::assertSame(4, Db::table($table)->where('member_id', $member->id)->count());
        self::assertSame(2, $model::where('member_id', $member->id)->count());
        $liveIds = $model::where('member_id', $member->id)->orderBy('id')->pluck('id')->all();
        $sync();
        self::assertSame($liveIds, $model::where('member_id', $member->id)->orderBy('id')->pluck('id')->all());
        self::assertSame(4, Db::table($table)->where('member_id', $member->id)->count());
        self::assertSame($historyBefore, (array) Db::table($table)->where('id', $history->id)->first());
    }

    public function test_member_group_relation_sync_preserves_ids_timestamps_and_deleted_history(): void
    {
        $this->assertMemberRelationDiff('group');
    }

    public function test_member_tag_relation_sync_preserves_ids_timestamps_and_deleted_history(): void
    {
        $this->assertMemberRelationDiff('tag');
    }

    public function test_member_group_reference_guard_protects_live_members_only(): void
    {
        $group = \app\admin\system\model\MemberGroup::forceCreate(['name'=>'round9-guard', 'status'=>1]);
        $member = $this->fixtureMember('guardfixture');
        \app\common\model\MemberGroupRelation::forceCreate(['member_id'=>$member->id, 'group_id'=>$group->id, 'created_at'=>date('Y-m-d H:i:s')]);
        $guard = \app\common\service\member\MemberReferenceGuard::class;
        self::assertSame('会员组仍被会员使用，不能删除', $guard::blockingMessage($guard::GROUP, [$group->id], false));
        $blocked = $this->call(\app\admin\system\controller\SystemMemberGroup::class, 'recycle', ['ids'=>[$group->id]]);
        self::assertSame(422, $blocked['code']);
        self::assertSame('会员组仍被会员使用，不能删除', $blocked['msg']);
        self::assertNotNull($group->fresh());
        try {
            $group->delete();
            self::fail('Model deletion must also enforce MemberReferenceGuard');
        } catch (\DomainException $error) {
            self::assertSame(422, $error->getCode());
        }
        $member->delete();
        self::assertNull($guard::blockingMessage($guard::GROUP, [$group->id], false));
        self::assertSame(200, $this->call(\app\admin\system\controller\SystemMemberGroup::class, 'recycle', ['ids'=>[$group->id]])['code']);
        $this->trashed('member_group', $group->id);
        $this->trashed('member', $member->id);
    }

    public function test_registration_email_active_cooling_and_expired_uniqueness(): void
    {
        $service = new \app\common\service\member\MemberAccountService();
        $holder = $this->fixtureMember('emailholder', 'used@example.com');
        $register = static fn (string $username, string $email) => $service->register([
            'username'=>$username, 'email'=>$email, 'password'=>'safe-password-91', 'password_confirm'=>'safe-password-91',
        ], '127.0.0.91');
        try { $register('activeemail', 'used@example.com'); self::fail('Active email duplicate allowed'); }
        catch (\RuntimeException $error) { self::assertSame('邮箱已被注册', $error->getMessage()); }
        try { $register('longemail', str_repeat('a', 50).'@example.com'); self::fail('Overlong email allowed'); }
        catch (\RuntimeException $error) { self::assertSame('请输入有效的邮箱（不超过 60 个字符）', $error->getMessage()); }
        $holder->delete();
        try { $register('coolingemail', 'used@example.com'); self::fail('Cooling email duplicate allowed'); }
        catch (\RuntimeException $error) { self::assertStringContainsString('冷静期', $error->getMessage()); self::assertStringContainsString('已注销', $error->getMessage()); }
        $expired = date('Y-m-d H:i:s', time() - ((int) config('soft_delete.member_cooling_days', 30) + 1) * 86400);
        Db::table('member')->where('id', $holder->id)->update(['deleted_at'=>$expired]);
        $newId = $register('expiredemail', 'used@example.com');
        self::assertNotSame($holder->id, $newId);
        self::assertSame('used@example.com', \app\common\model\Member::findOrFail($newId)->email);
        $this->trashed('member', $holder->id);
        self::assertSame($expired, Db::table('member')->where('id', $holder->id)->value('deleted_at'));
        self::assertNull(\app\common\service\member\MemberInput::normalizeEmail('  '));
        self::assertSame('user@example.com', \app\common\service\member\MemberInput::normalizeEmail(' User@Example.com '));
    }

    public function test_role_active_name_and_code_duplicates_rejected_and_deleted_values_reusable(): void
    {
        $class = \app\admin\authorization\controller\SystemRole::class;
        $payload = ['name'=>'Round nine role', 'code'=>'round_nine_role', 'level'=>100];
        $created = $this->call($class, 'create', $payload);
        self::assertSame(200, $created['code'], $created['msg']);
        self::assertSame(422, $this->call($class, 'create', array_replace($payload, ['code'=>'other_code']))['code']);
        self::assertSame(422, $this->call($class, 'create', array_replace($payload, ['name'=>'Other name']))['code']);
        $role = \app\admin\authorization\model\AuthGroup::findOrFail($created['data']['id']);
        $role->delete();
        $this->trashed('auth_group', $role->id);
        $replacement = $this->call($class, 'create', $payload);
        self::assertSame(200, $replacement['code'], $replacement['msg']);
        self::assertNotSame($role->id, $replacement['data']['id']);
        // The migrated database still rejects duplicates independently of the controller's precheck.
        try {
            \app\admin\authorization\model\AuthGroup::forceCreate(['name'=>$payload['name'], 'code'=>'different_again']);
            self::fail('Database name uniqueness no longer enforced');
        } catch (\Illuminate\Database\QueryException $error) {
            self::assertTrue(\app\common\model\concern\RestoreConflictException::isUniqueViolation($error));
        }
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
