<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\controller\SystemMember;
use app\admin\system\controller\SystemMemberGroup;
use app\admin\system\controller\SystemMemberLevel;
use app\admin\system\model\Member;
use app\admin\system\model\MemberTag;
use app\common\model\concern\RestoreConflictException;
use app\common\service\database\MigrationService;
use app\common\service\member\MemberAccountService;
use app\common\service\member\MemberApiSessionService;
use app\common\service\member\MemberIdentityGuard;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

/**
 * 会员注销冷静期：冷静期内被删会员的用户名 / 手机号 / 邮箱仍被占用；删除会员时会话与分组标签关联一起软删除，
 * 恢复时只恢复这次删除带走的关联；还被未删除会员使用的等级 / 分组 / 标签不能删除。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MemberCoolingPeriodTest extends TestCase
{
    private int $levelId = 0;

    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        Context::destroy();
        putenv('MEMBER_JWT_SECRET=' . bin2hex(random_bytes(32)));
        putenv('MEMBER_REFRESH_JWT_SECRET=' . bin2hex(random_bytes(32)));
        \Webman\Config::load(config_path(), ['route']);
        Db::schema()->table('member', static function (Blueprint $table): void {
            $table->string('nickname')->default(''); $table->string('motto')->default(''); $table->string('real_name')->default('');
            $table->integer('birthday')->default(0); $table->integer('scores')->default(0);
        });
        $this->levelId = (int) Db::table('member_level')->where('status', 1)->orderBy('id')->value('id');
        foreach ([71, 72, 73] as $id) Db::table('member_group')->insert(['id' => $id, 'name' => '冷静期组' . $id, 'status' => 1]);
        foreach ([81, 82] as $id) Db::table('member_tag')->insert(['id' => $id, 'name' => '冷静期标签' . $id, 'status' => 1]);
        Db::table('member_level')->insert(['id' => 61, 'name' => '冷静期等级', 'status' => 1]);
    }

    protected function tearDown(): void
    {
        if (Db::transactionLevel() > 0) Db::rollBack();
        Context::destroy();
        \Carbon\Carbon::setTestNow();
    }

    public function test_cooling_days_config_defaults_to_thirty(): void
    {
        self::assertSame(30, config('soft_delete.member_cooling_days'));
    }

    public function test_identity_of_member_deleted_within_cooling_period_cannot_be_reused(): void
    {
        $id = $this->createMember(['username' => 'coolone', 'mobile' => '13800000001', 'email' => 'coolone@example.com']);
        $this->member('DELETE', '/admin/system/member', ['ids' => [$id]], static fn ($c) => $c->recycle());

        foreach ([['username' => 'coolone'], ['mobile' => '13800000001'], ['email' => 'coolone@example.com']] as $taken) {
            $payload = $this->member('POST', '/admin/system/member', $taken + $this->input('fresh' . key($taken)), static fn ($c) => $c->create());
            self::assertSame(422, $payload['code'], json_encode($taken));
            self::assertStringContainsString('注销', $payload['msg']);
            self::assertStringContainsString('30', $payload['msg']);
        }

        // 编辑其他会员占用冷静期内的手机号同样拒绝。
        $other = $this->createMember($this->input('coolother'));
        $payload = $this->member('PUT', '/admin/system/member/' . $other, ['mobile' => '13800000001'] + $this->input('coolother'), static fn ($c) => $c->update($other));
        self::assertSame(422, $payload['code']);
        self::assertStringContainsString('注销', $payload['msg']);

        // 导入也走同一检查。
        $payload = $this->member('POST', '/admin/system/member/import', ['rows' => [$this->input('coolone')]], static fn ($c) => $c->import());
        self::assertSame(0, $payload['data']['created']);
        self::assertStringContainsString('注销', $payload['data']['errors'][0]);

        // 会员前台注册。
        $accounts = new MemberAccountService();
        foreach ([['coolone', 'reg1@example.com'], ['regfresh', 'coolone@example.com']] as $index => [$username, $email]) {
            try {
                $accounts->register(['username' => $username, 'email' => $email, 'password' => 'cool-password-1', 'password_confirm' => 'cool-password-1'], '10.1.0.' . $index);
                self::fail('冷静期内的账号标识不能注册：' . $username);
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('注销', $exception->getMessage());
            }
        }

        // 未删除会员占用时仍是普通的重复提示。
        $payload = $this->member('POST', '/admin/system/member', ['username' => 'coolother'] + $this->input('x'), static fn ($c) => $c->create());
        self::assertSame(422, $payload['code']);
        self::assertSame('用户名已存在', $payload['msg']);
        $this->expectExceptionMessage('用户名已被注册');
        $accounts->register(['username' => 'coolother', 'email' => 'reg9@example.com', 'password' => 'cool-password-1', 'password_confirm' => 'cool-password-1'], '10.1.0.9');
    }

    public function test_identity_is_released_after_cooling_period(): void
    {
        $id = $this->createMember(['username' => 'expired', 'mobile' => '13800000002', 'email' => 'expired@example.com']);
        $this->member('DELETE', '/admin/system/member', ['ids' => [$id]], static fn ($c) => $c->recycle());
        $deletedAt = new \DateTimeImmutable((string) Db::table('member')->where('id', $id)->value('deleted_at'));

        $guard = static fn (string $offset): MemberIdentityGuard => new MemberIdentityGuard(now: $deletedAt->modify($offset));
        try {
            $guard('+29 days')->assertAvailable(['username' => 'expired']);
            self::fail('第 29 天仍在冷静期内');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(422, $exception->getCode());
        }
        $guard('+31 days')->assertAvailable(['username' => 'expired', 'mobile' => '13800000002', 'email' => 'expired@example.com']);

        // 删除时间推到 31 天前：后台新建与前台注册都可以使用这些值，旧账号仍保留为软删除。
        Db::table('member')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s', time() - 31 * 86400)]);
        $payload = $this->member('POST', '/admin/system/member', ['username' => 'expired', 'mobile' => '13800000002'] + $this->input('expired'), static fn ($c) => $c->create());
        self::assertSame(200, $payload['code'], $payload['msg']);
        (new MemberAccountService())->register(['username' => 'regexpired', 'email' => 'expired@example.com', 'password' => 'cool-password-1', 'password_confirm' => 'cool-password-1'], '10.2.0.1');
        self::assertNotNull(Member::onlyTrashed()->find($id));
        self::assertSame(2, Db::table('member')->where('username', 'expired')->count());
    }

    public function test_empty_mobile_and_email_are_stored_as_null_and_do_not_collide(): void
    {
        self::assertSame(['username' => null, 'mobile' => null, 'email' => null, 'nickname' => ''],
            MemberIdentityGuard::normalize(['username' => '  ', 'mobile' => '', 'email' => ' ', 'nickname' => '']));
        self::assertSame(['username' => 'abc', 'mobile' => '138', 'email' => 'a@b.c'], MemberIdentityGuard::normalize(['username' => ' abc ', 'mobile' => '138 ', 'email' => ' a@b.c']));

        $first = $this->createMember(['username' => 'noemail1', 'mobile' => '13800000011', 'email' => '']);
        $second = $this->createMember(['username' => 'noemail2', 'mobile' => '13800000012', 'email' => '   ']);
        $payload = $this->member('POST', '/admin/system/member/import', ['rows' => [['email' => ''] + $this->input('noemail3'), ['email' => ''] + $this->input('noemail4')]], static fn ($c) => $c->import());
        self::assertSame(2, $payload['data']['created'], json_encode($payload['data']['errors']));
        $payload = $this->member('PUT', '/admin/system/member/' . $first, ['email' => ''] + $this->input('noemail1'), static fn ($c) => $c->update($first));
        self::assertSame(200, $payload['code'], $payload['msg']);
        self::assertSame(0, Db::table('member')->where('email', '')->count());
        self::assertSame(0, Db::table('member')->where('mobile', '')->count());
        self::assertSame(4, Db::table('member')->whereIn('username', ['noemail1', 'noemail2', 'noemail3', 'noemail4'])->whereNull('email')->count());
        self::assertNotSame($first, $second);

        $registered = (new MemberAccountService())->register(['username' => 'regnull', 'email' => 'regnull@example.com', 'password' => 'cool-password-1', 'password_confirm' => 'cool-password-1'], '10.3.0.1');
        self::assertNull(Db::table('member')->where('id', $registered)->value('mobile'));
    }

    public function test_deleting_member_soft_deletes_sessions_and_relations(): void
    {
        $id = $this->createMember(['username' => 'cascade', 'groupIds' => [71, 72], 'tagIds' => [81]]);
        $sessions = new MemberApiSessionService();
        $first = $sessions->open($id, '127.0.0.1');
        $second = $sessions->open($id, '127.0.0.2');
        self::assertNotNull($sessions->authenticate($first['access_token']));

        $payload = $this->member('DELETE', '/admin/system/member', ['ids' => [$id]], static fn ($c) => $c->recycle());
        self::assertSame(200, $payload['code'], $payload['msg']);

        $deletedAt = (string) Db::table('member')->where('id', $id)->value('deleted_at');
        self::assertNotSame('', $deletedAt);
        foreach (['member_group_relation', 'member_tag_relation', 'member_api_session'] as $table) {
            $rows = Db::table($table)->where('member_id', $id)->get();
            self::assertGreaterThan(0, count($rows), $table);
            foreach ($rows as $row) {
                self::assertNotNull($row->deleted_at, $table);
                self::assertSame((int) $row->id, (int) $row->deleted_token, $table);
            }
        }
        self::assertSame([$deletedAt], Db::table('member_group_relation')->where('member_id', $id)->distinct()->pluck('deleted_at')->map('strval')->all());
        self::assertSame([$deletedAt], Db::table('member_tag_relation')->where('member_id', $id)->distinct()->pluck('deleted_at')->map('strval')->all());

        // 已登录的设备立即失效。
        self::assertNull($sessions->authenticate($first['access_token']));
        self::assertNull($sessions->authenticate($second['access_token']));
        self::assertNull($sessions->rotate($second['refresh_token']));
    }

    public function test_restoring_member_restores_relations_but_not_sessions(): void
    {
        $id = $this->createMember(['username' => 'restorer', 'groupIds' => [71, 72, 73], 'tagIds' => [81, 82]]);
        // 编辑时移除的分组 73、标签 82：删除时间早于会员删除，恢复会员时不能带回。
        $this->member('PUT', '/admin/system/member/' . $id, ['groupIds' => [71, 72], 'tagIds' => [81]] + $this->input('restorer'), static fn ($c) => $c->update($id));
        Db::table('member_group_relation')->where('member_id', $id)->where('group_id', 73)->update(['deleted_at' => '2026-01-01 00:00:00']);
        Db::table('member_tag_relation')->where('member_id', $id)->where('tag_id', 82)->update(['deleted_at' => '2026-01-01 00:00:00']);
        $sessions = new MemberApiSessionService();
        $tokens = $sessions->open($id, '127.0.0.1');

        $this->member('DELETE', '/admin/system/member', ['ids' => [$id]], static fn ($c) => $c->recycle());
        // 删除后又有一条相同的有效分组关联（例如数据修复写入）：恢复时跳过冲突的那条，不报错。
        Db::table('member_group_relation')->insert(['member_id' => $id, 'group_id' => 72, 'created_at' => date('Y-m-d H:i:s')]);

        // 回收站列表仍显示这次删除带走的分组 / 标签（编辑时移除的不显示）。
        $recycled = $this->member('GET', '/admin/system/member?recycled=1', [], static fn ($c) => $c->index());
        self::assertSame([[71, 72]], array_column($recycled['data']['list'], 'groupIds'));
        self::assertSame([[81]], array_column($recycled['data']['list'], 'tagIds'));

        $payload = $this->member('POST', '/admin/system/member/restore', ['ids' => [$id]], static fn ($c) => $c->restore());
        self::assertSame(200, $payload['code'], $payload['msg']);
        self::assertNull(Db::table('member')->where('id', $id)->value('deleted_at'));
        self::assertSame([71, 72], Member::groupIdsFor($id));
        self::assertSame([81], Member::tagIdsFor($id));
        self::assertSame(1, Db::table('member_group_relation')->where('member_id', $id)->where('group_id', 72)->whereNull('deleted_at')->count());
        self::assertSame('2026-01-01 00:00:00', (string) Db::table('member_group_relation')->where('member_id', $id)->where('group_id', 73)->value('deleted_at'));
        self::assertSame('2026-01-01 00:00:00', (string) Db::table('member_tag_relation')->where('member_id', $id)->where('tag_id', 82)->value('deleted_at'));

        // 会话不恢复：需要重新登录。
        self::assertSame(0, Db::table('member_api_session')->where('member_id', $id)->whereNull('deleted_at')->count());
        self::assertNull($sessions->authenticate($tokens['access_token']));
        self::assertNull($sessions->rotate($tokens['refresh_token']));
        self::assertNotNull($sessions->authenticate($sessions->open($id, '127.0.0.1')['access_token']));
    }

    public function test_restore_conflicting_with_new_member_returns_conflict_message(): void
    {
        $old = $this->createMember(['username' => 'samename', 'mobile' => '13800000021', 'groupIds' => [71]]);
        $this->member('DELETE', '/admin/system/member', ['ids' => [$old]], static fn ($c) => $c->recycle());
        Db::table('member')->where('id', $old)->update(['deleted_at' => date('Y-m-d H:i:s', time() - 40 * 86400)]);
        Db::table('member_group_relation')->where('member_id', $old)->update(['deleted_at' => date('Y-m-d H:i:s', time() - 40 * 86400)]);
        $new = $this->createMember(['username' => 'samename', 'mobile' => '13800000022']);

        $payload = $this->member('POST', '/admin/system/member/restore', ['ids' => [$old]], static fn ($c) => $c->restore());
        self::assertSame(409, $payload['code']);
        self::assertSame(RestoreConflictException::MESSAGE, $payload['msg']);
        self::assertNotNull(Db::table('member')->where('id', $old)->value('deleted_at'));
        self::assertNull(Db::table('member')->where('id', $new)->value('deleted_at'));
        self::assertSame(0, Db::table('member_group_relation')->where('member_id', $old)->whereNull('deleted_at')->count(), '恢复失败时关联保持删除');
    }

    public function test_member_level_group_or_tag_in_use_cannot_be_deleted(): void
    {
        $id = $this->createMember(['username' => 'refholder', 'groupIds' => [72], 'tagIds' => [82], 'levelId' => 61]);

        $level = $this->crud(SystemMemberLevel::class, 'DELETE', '/admin/system/member-level', ['ids' => [61]], static fn ($c) => $c->recycle());
        self::assertSame(422, $level['code']);
        self::assertStringContainsString('会员', $level['msg']);
        $group = $this->crud(SystemMemberGroup::class, 'DELETE', '/admin/system/member-group', ['ids' => [72]], static fn ($c) => $c->recycle());
        self::assertSame(422, $group['code']);
        self::assertStringContainsString('会员', $group['msg']);
        try {
            MemberTag::query()->findOrFail(82)->delete();
            self::fail('标签仍被会员使用时不能删除');
        } catch (\DomainException $exception) {
            self::assertSame(422, $exception->getCode());
            self::assertStringContainsString('会员', $exception->getMessage());
        }
        self::assertNull(Db::table('member_level')->where('id', 61)->value('deleted_at'));
        self::assertNull(Db::table('member_group')->where('id', 72)->value('deleted_at'));
        self::assertNull(Db::table('member_tag')->where('id', 82)->value('deleted_at'));

        // 会员进入回收站后，等级 / 分组 / 标签不再被未删除会员使用，可以软删除。
        $this->member('DELETE', '/admin/system/member', ['ids' => [$id]], static fn ($c) => $c->recycle());
        self::assertSame(200, $this->crud(SystemMemberLevel::class, 'DELETE', '/admin/system/member-level', ['ids' => [61]], static fn ($c) => $c->recycle())['code']);
        self::assertSame(200, $this->crud(SystemMemberGroup::class, 'DELETE', '/admin/system/member-group', ['ids' => [72]], static fn ($c) => $c->recycle())['code']);
        MemberTag::query()->findOrFail(82)->delete();
        self::assertNotNull(Db::table('member_tag')->where('id', 82)->value('deleted_at'));

        // 彻底删除等级：回收站中的会员仍引用它（fk_member_level），拒绝。
        $destroy = $this->crud(SystemMemberLevel::class, 'DELETE', '/admin/system/member-level/destroy', ['ids' => [61]], static fn ($c) => $c->destroy());
        self::assertSame(422, $destroy['code']);
        self::assertNotNull(Db::table('member_level')->where('id', 61)->first());
    }

    public function test_destroying_trashed_group_tag_and_member_purges_relation_rows_under_foreign_keys(): void
    {
        $this->recreateRelationTablesWithForeignKeys();
        self::assertSame(1, (int) (Db::select('PRAGMA foreign_keys')[0]->foreign_keys ?? 0), 'SQLite 外键约束已开启');
        try {
            Db::transaction(static fn () => Db::table('member_group_relation')->insert(['member_id' => 1, 'group_id' => 999999]));
            self::fail('外键应拒绝不存在的分组');
        } catch (\Illuminate\Database\QueryException $exception) {
            self::assertStringContainsString('FOREIGN KEY', $exception->getMessage());
        }

        $keep = $this->createMember(['username' => 'fkkeep', 'mobile' => '13800000031', 'groupIds' => [71], 'tagIds' => [81]]);
        $gone = $this->createMember(['username' => 'fkgone', 'mobile' => '13800000032', 'groupIds' => [71, 72], 'tagIds' => [81, 82]]);
        $this->member('DELETE', '/admin/system/member', ['ids' => [$gone]], static fn ($c) => $c->recycle());
        // 编辑移除的历史关联同样引用分组 / 标签。
        $this->member('PUT', '/admin/system/member/' . $keep, ['mobile' => '13800000031', 'groupIds' => [71, 72], 'tagIds' => [81, 82]] + $this->input('fkkeep'), static fn ($c) => $c->update($keep));
        $this->member('PUT', '/admin/system/member/' . $keep, ['mobile' => '13800000031', 'groupIds' => [71], 'tagIds' => [81]] + $this->input('fkkeep'), static fn ($c) => $c->update($keep));
        self::assertGreaterThan(0, Db::table('member_group_relation')->where('group_id', 72)->whereNotNull('deleted_at')->count());

        $recycle = $this->crud(SystemMemberGroup::class, 'DELETE', '/admin/system/member-group', ['ids' => [72]], static fn ($c) => $c->recycle());
        self::assertSame(200, $recycle['code'], $recycle['msg']);
        $destroy = $this->crud(SystemMemberGroup::class, 'DELETE', '/admin/system/member-group/destroy', ['ids' => [72]], static fn ($c) => $c->destroy());
        self::assertSame(200, $destroy['code'], $destroy['msg']);
        self::assertNull(Db::table('member_group')->where('id', 72)->first());
        self::assertSame(0, Db::table('member_group_relation')->where('group_id', 72)->count());

        MemberTag::query()->findOrFail(82)->delete();
        MemberTag::withTrashed()->findOrFail(82)->forceDelete();
        self::assertNull(Db::table('member_tag')->where('id', 82)->first());
        self::assertSame(0, Db::table('member_tag_relation')->where('tag_id', 82)->count());

        // 其他关联不受影响。
        self::assertSame([71], Member::groupIdsFor($keep));
        self::assertSame(2, Db::table('member_group_relation')->where('group_id', 71)->count());

        $payload = $this->member('DELETE', '/admin/system/member/destroy', ['ids' => [$gone]], static fn ($c) => $c->destroy());
        self::assertSame(200, $payload['code'], $payload['msg']);
        self::assertNull(Db::table('member')->where('id', $gone)->first());
        self::assertSame(0, Db::table('member_group_relation')->where('member_id', $gone)->count());
        self::assertSame(0, Db::table('member_tag_relation')->where('member_id', $gone)->count());
    }

    public function test_member_belongs_to_many_reads_only_live_pivot_rows(): void
    {
        $id = $this->createMember(['username' => 'pivotlive', 'groupIds' => [71, 72], 'tagIds' => [81, 82]]);
        $this->member('PUT', '/admin/system/member/' . $id, ['groupIds' => [71], 'tagIds' => [82]] + $this->input('pivotlive'), static fn ($c) => $c->update($id));
        $member = Member::query()->findOrFail($id);
        self::assertSame([71], array_map('intval', $member->groups()->pluck('member_group.id')->all()));
        self::assertSame([82], array_map('intval', $member->tags()->pluck('member_tag.id')->all()));
    }

    /** @return array<string, mixed> */
    private function input(string $username): array
    {
        return ['username' => $username, 'mobile' => '139' . str_pad((string) (crc32($username) % 100000000), 8, '0', STR_PAD_LEFT),
            'email' => '', 'sex' => '0', 'levelId' => $this->levelId, 'groupIds' => [71], 'tagIds' => [], 'status' => 1];
    }

    private function createMember(array $overrides): int
    {
        $payload = $this->member('POST', '/admin/system/member', $overrides + $this->input((string) $overrides['username']), static fn ($c) => $c->create());
        self::assertSame(200, $payload['code'], (string) $payload['msg']);
        return (int) $payload['data']['id'];
    }

    private function member(string $method, string $path, array $data, callable $action): array
    {
        return $this->crud(SystemMember::class, $method, $path, $data, $action);
    }

    private function crud(string $controller, string $method, string $path, array $data, callable $action): array
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $request = new Request("$method $path HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
        return json_decode($action(new $controller())->rawBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** 迁移链路的 SQLite 关联表没有外键；按 MySQL 基线重建带外键的关联表，验证彻底删除不会撞 ON DELETE RESTRICT。 */
    private function recreateRelationTablesWithForeignKeys(): void
    {
        foreach (['member_group_relation' => ['group_id', 'member_group'], 'member_tag_relation' => ['tag_id', 'member_tag']] as $name => [$column, $related]) {
            Db::schema()->drop($name);
            Db::schema()->create($name, static function (Blueprint $table) use ($column, $related): void {
                $table->id();
                $table->unsignedBigInteger('member_id');
                $table->unsignedBigInteger($column);
                $table->dateTime('created_at')->nullable();
                $table->softDeletes();
                $table->unsignedBigInteger('deleted_token')->default(0);
                $table->unique(['member_id', $column, 'deleted_token']);
                $table->foreign('member_id')->references('id')->on('member')->cascadeOnDelete();
                $table->foreign($column)->references('id')->on($related)->restrictOnDelete();
            });
        }
        \app\common\model\concern\TokenSoftDeletingScope::flushDeletedTokenCache();
    }
}
