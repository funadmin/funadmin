<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\admin\model;


use app\admin\traits\AdminDataFormat;
use app\common\model\concern\LaravelSoftDelete;

class Member extends BackendModel {


    /**
     * @var bool
     */
    use LaravelSoftDelete;
    use AdminDataFormat;


    


    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    public function groups()
    {
        return $this->belongsToMany(MemberGroup::class, 'member_group_relation', 'group_id', 'member_id');
    }

    public function tags()
    {
        return $this->belongsToMany(MemberTag::class, 'member_tag_relation', 'tag_id', 'member_id');
    }

    public function level()
    {
        return $this->belongsTo('MemberLevel', 'level_id', 'id', [], 'LEFT');
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
                    ->whereOr('mobile', 'like', '%' . $keyword . '%')
                    ->whereOr('email', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($groupId > 0) {
            $query->alias('member')->whereExists(function ($relationQuery) use ($groupId): void {
                $relationQuery->table((new MemberGroupRelation())->getTable() . ' member_group_relation')
                    ->field('member_group_relation.member_id')
                    ->whereColumn('member_group_relation.member_id', 'member.id')
                    ->where('member_group_relation.group_id', $groupId);
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
        if ($data['level_id'] <= 0 || !MemberLevel::where('id', $data['level_id'])->where('status', 1)->find()) {
            return '会员等级不存在、已删除或已停用';
        }
        if (strlen($data['avatar']) > 255) {
            return '头像地址不能超过 255 个字符';
        }
        return null;
    }

    public static function validateUniqueAttributes(array $data, int $excludeId = 0): ?string
    {
        $usernameQuery = self::withTrashed()->where('username', $data['username']);
        $mobileQuery = $data['mobile'] !== null ? self::withTrashed()->where('mobile', $data['mobile']) : null;
        $emailQuery = $data['email'] !== null ? self::withTrashed()->where('email', $data['email']) : null;
        if ($excludeId > 0) {
            $usernameQuery->where('id', '<>', $excludeId);
            $mobileQuery?->where('id', '<>', $excludeId);
            $emailQuery?->where('id', '<>', $excludeId);
        }
        if ($usernameQuery->find()) {
            return '用户名已存在';
        }
        if ($mobileQuery?->find()) {
            return '手机号已存在';
        }
        if ($emailQuery?->find()) {
            return '邮箱已存在';
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
        $relations = $memberIds ? MemberGroupRelation::whereIn('member_id', $memberIds)
            ->field('member_id,group_id')->order('group_id', 'asc')->select()->toArray() : [];
        $groupIds = [];
        foreach ($relations as $relation) {
            $memberId = (int) $relation['member_id'];
            $groupId = (int) $relation['group_id'];
            $memberGroups[$memberId][] = $groupId;
            $groupIds[] = $groupId;
        }
        $groupIds = array_values(array_unique($groupIds));
        $levelIds = array_values(array_unique(array_filter($levelIds)));
        $groups = $groupIds ? MemberGroup::withTrashed()->whereIn('id', $groupIds)->column('name', 'id') : [];
        $levels = $levelIds ? MemberLevel::withTrashed()->whereIn('id', $levelIds)->column('name', 'id') : [];
        $memberTags = array_fill_keys($memberIds, []);
        $tagRelations = $memberIds ? MemberTagRelation::whereIn('member_id', $memberIds)->field('member_id,tag_id')->order('tag_id', 'asc')->select()->toArray() : [];
        $tagIds = [];
        foreach ($tagRelations as $relation) {
            $memberTags[(int) $relation['member_id']][] = (int) $relation['tag_id'];
            $tagIds[] = (int) $relation['tag_id'];
        }
        $tagIds = array_values(array_unique($tagIds));
        $tags = $tagIds ? MemberTag::withTrashed()->whereIn('id', $tagIds)->column('name', 'id') : [];
        return [$memberGroups, $groups, $levels, $memberTags, $tags];
    }

    public static function groupIdsFor(int $memberId): array
    {
        return array_map('intval', MemberGroupRelation::where('member_id', $memberId)
            ->order('group_id', 'asc')->column('group_id'));
    }

    public static function tagIdsFor(int $memberId): array
    {
        return array_map('intval', MemberTagRelation::where('member_id', $memberId)->order('tag_id', 'asc')->column('tag_id'));
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
