<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\admin\system\model;


use app\common\model\BaseModel;
use app\admin\system\model\MemberTag;
use app\admin\system\model\MemberGroup;
use app\admin\system\model\MemberLevel;
use app\admin\system\model\MemberGroupRelation;
use app\admin\system\model\MemberTagRelation;
use app\common\traits\DataFormat;
use app\common\service\member\MemberIdentityGuard;
use app\common\service\member\MemberIdentityTakenException;
use app\common\model\concern\LaravelSoftDelete;
use app\common\model\concern\CascadesMemberDeletion;

class Member extends BaseModel {
    protected $table = 'member';


    /**
     * @var bool
     */
    use LaravelSoftDelete;
    use CascadesMemberDeletion;
    use DataFormat;


    


    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    public function groups()
    {
        // 关联表软删除：只读有效的中间表行。
        return $this->belongsToMany(MemberGroup::class, 'member_group_relation', 'member_id', 'group_id')->wherePivotNull('deleted_at');
    }

    public function tags()
    {
        return $this->belongsToMany(MemberTag::class, 'member_tag_relation', 'member_id', 'tag_id')->wherePivotNull('deleted_at');
    }

    public function level()
    {
        return $this->belongsTo(MemberLevel::class, 'level_id', 'id');
    }

    /**
     * 会员列表查询边界：回收站/关键词/状态/组/等级筛选由控制器传入。
     */
    public static function filteredQuery(bool $recycled, string $keyword = '', $status = null, int $groupId = 0, int $levelId = 0)
    {
        $query = $recycled ? self::onlyTrashed() : self::where('id', '>', 0);
        if ($keyword !== '') {
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('username', '%' . $keyword . '%')
                    ->orWhere('mobile', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($groupId > 0) {
            $query->whereIn('id', function ($relationQuery) use ($groupId): void {
                $relationQuery->from((new MemberGroupRelation())->getTable())
                    ->select('member_id')->where('group_id', $groupId)->whereNull('deleted_at');
            });
        }
        if ($levelId > 0) {
            $query->where('level_id', $levelId);
        }
        return $query;
    }

    public static function validateAttributes(array $data): ?string
    {
        $usernameLength = function_exists('mb_strlen') ? mb_strlen($data['username']) : strlen($data['username']);
        if ($usernameLength < 2 || $usernameLength > 80) {
            return '用户名长度必须为 2 至 80 个字符';
        }
        if (preg_match('/[\x00-\x1F\x7F<>]/u', $data['username'])) {
            return '用户名包含非法字符';
        }
        if ($data['mobile'] === null || !preg_match('/^[0-9+\- ]{6,20}$/', $data['mobile'])) {
            return '请输入 6 至 20 位有效手机号';
        }
        if ($data['email'] !== null && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 60)) {
            return '邮箱格式不正确或超过 60 个字符';
        }
        if (!in_array($data['sex'], ['0', '1', '2'], true)) {
            return '性别参数无效';
        }
        if (!$data['groupIds'] || count($data['groupIds']) > 32) {
            return '请选择有效会员组，且最多选择 32 个会员组';
        }
        if (MemberGroup::whereIn('id', $data['groupIds'])->where('status', 1)->count() !== count($data['groupIds'])) {
            return '会员组不存在、已删除或已停用';
        }
        if (count($data['tagIds']) > 32 || ($data['tagIds'] && MemberTag::whereIn('id', $data['tagIds'])->where('status', 1)->count() !== count($data['tagIds']))) {
            return '会员标签不存在、已删除或已停用，且最多选择 32 个标签';
        }
        if ($data['level_id'] <= 0 || !MemberLevel::where('id', $data['level_id'])->where('status', 1)->first()) {
            return '会员等级不存在、已删除或已停用';
        }
        if (strlen($data['avatar']) > 255) {
            return '头像地址不能超过 255 个字符';
        }
        return null;
    }

    /**
     * 用户名 / 手机号 / 邮箱唯一性：未删除会员与冷静期内删除的会员都占用（MemberIdentityGuard）。
     */
    public static function validateUniqueAttributes(array $data, int $excludeId = 0): ?string
    {
        try {
            (new MemberIdentityGuard())->assertAvailable(array_intersect_key($data, MemberIdentityGuard::FIELDS), $excludeId);
        } catch (MemberIdentityTakenException $exception) {
            return $exception->getMessage();
        }
        return null;
    }

    public static function duplicateError(\Throwable $exception): ?string
    {
        $message = $exception->getMessage();
        if (!str_contains($message, '1062') && !str_contains($message, 'Duplicate entry')) {
            return null;
        }
        foreach (['username' => '用户名已存在', 'mobile' => '手机号已存在', 'email' => '邮箱已存在'] as $field => $error) {
            if (str_contains($message, $field)) {
                return $error;
            }
        }
        return '用户名、手机号或邮箱已存在';
    }

    /**
     * 批量预加载组/等级/标签映射，避免列表 N+1。
     */
    public static function relationMaps(array $members): array
    {
        $memberIds = [];
        $memberGroups = [];
        $levelIds = [];
        foreach ($members as $member) {
            $memberIds[] = (int) $member->id;
            $memberGroups[(int) $member->id] = [];
            $levelIds[] = (int) $member->level_id;
        }
        $relations = self::relationRows(MemberGroupRelation::class, 'group_id', $members);
        $groupIds = [];
        foreach ($relations as $relation) {
            $memberId = (int) $relation['member_id'];
            $groupId = (int) $relation['group_id'];
            $memberGroups[$memberId][] = $groupId;
            $groupIds[] = $groupId;
        }
        $groupIds = array_values(array_unique($groupIds));
        $levelIds = array_values(array_unique(array_filter($levelIds)));
        $groups = $groupIds ? MemberGroup::withTrashed()->whereIn('id', $groupIds)->pluck('name', 'id')->all() : [];
        $levels = $levelIds ? MemberLevel::withTrashed()->whereIn('id', $levelIds)->pluck('name', 'id')->all() : [];
        $memberTags = array_fill_keys($memberIds, []);
        $tagRelations = self::relationRows(MemberTagRelation::class, 'tag_id', $members);
        $tagIds = [];
        foreach ($tagRelations as $relation) {
            $memberTags[(int) $relation['member_id']][] = (int) $relation['tag_id'];
            $tagIds[] = (int) $relation['tag_id'];
        }
        $tagIds = array_values(array_unique($tagIds));
        $tags = $tagIds ? MemberTag::withTrashed()->whereIn('id', $tagIds)->pluck('name', 'id')->all() : [];
        return [$memberGroups, $groups, $levels, $memberTags, $tags];
    }

    /**
     * 列表展示用的关联行：未删除会员取有效关联；回收站中的会员取随它一起删除的关联
     * （deleted_at 与会员相同，见 MemberCascade），这样回收站里仍能看到恢复后会带回的分组 / 标签。
     *
     * @param class-string $relation
     * @return list<array<string, int>>
     */
    private static function relationRows(string $relation, string $key, array $members): array
    {
        $live = [];
        $trashed = [];
        foreach ($members as $member) {
            $deletedAt = $member->getRawOriginal('deleted_at');
            if ($deletedAt === null) {
                $live[] = (int) $member->id;
            } else {
                $trashed[(int) $member->id] = (string) $deletedAt;
            }
        }
        $rows = $live ? $relation::whereIn('member_id', $live)->select(['member_id', $key])->orderBy($key)->get()->toArray() : [];
        if ($trashed) {
            $seen = [];
            $candidates = $relation::onlyTrashed()->whereIn('member_id', array_keys($trashed))
                ->select(['member_id', $key, 'deleted_at'])->orderBy($key)->get();
            foreach ($candidates as $row) {
                $memberId = (int) $row->member_id;
                $value = (int) $row->getAttribute($key);
                if ((string) $row->getRawOriginal('deleted_at') !== $trashed[$memberId] || isset($seen[$memberId][$value])) {
                    continue;
                }
                $seen[$memberId][$value] = true;
                $rows[] = ['member_id' => $memberId, $key => $value];
            }
        }
        return $rows;
    }

    public static function groupIdsFor(int $memberId): array
    {
        return array_map('intval', MemberGroupRelation::where('member_id', $memberId)
            ->orderBy('group_id')->pluck('group_id')->all());
    }

    public static function tagIdsFor(int $memberId): array
    {
        return array_map('intval', MemberTagRelation::where('member_id', $memberId)->orderBy('tag_id')->pluck('tag_id')->all());
    }

    public function toApiData(array $memberGroups, array $groups, array $levels, array $memberTags, array $tags): array
    {
        $groupIds = $memberGroups[(int) $this->id] ?? [];
        $tagIds = $memberTags[(int) $this->id] ?? [];
        return [
            'id' => (int) $this->id,
            'username' => (string) $this->username,
            'mobile' => (string) $this->mobile,
            'email' => (string) $this->email,
            'sex' => (string) $this->sex,
            'groupIds' => $groupIds,
            'groupNames' => array_values(array_map(static fn (int $id): string => (string) ($groups[$id] ?? ('#' . $id)), $groupIds)),
            'tagIds' => $tagIds,
            'tagNames' => array_values(array_map(static fn (int $id): string => (string) ($tags[$id] ?? ('#' . $id)), $tagIds)),
            'levelId' => (int) $this->level_id,
            'levelName' => (string) ($levels[(int) $this->level_id] ?? ''),
            'avatar' => (string) ($this->avatar ?? ''),
            'status' => (int) $this->status,
            'loginCount' => (int) $this->login_num,
            'lastLoginAt' => $this->formatTime($this->last_login),
            'lastLoginIp' => (string) $this->last_ip,
            'createdAt' => $this->formatTime($this->created_at),
            'updatedAt' => $this->formatTime($this->updated_at),
            'deletedAt' => $this->formatTime($this->deleted_at),
        ];
    }

}
