<?php

declare(strict_types=1);

namespace app\common\service\member;

use app\common\model\Member;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * 会员账号标识（用户名 / 手机号 / 邮箱）的可用性检查。
 *
 * 数据库唯一索引含 deleted_token，只约束未删除会员；冷静期（config soft_delete.member_cooling_days）内
 * 被删会员的标识仍要保留给原主人找回，所以注册、后台新建 / 编辑 / 导入都必须先经过这里。
 */
final class MemberIdentityGuard
{
    public const FIELDS = ['username' => '用户名', 'mobile' => '手机号', 'email' => '邮箱'];

    /**
     * @param int|null $coolingDays 冷静期天数，默认读配置
     * @param \DateTimeImmutable|null $now 当前时间（测试注入），默认 Carbon::now()
     */
    public function __construct(
        private readonly ?int $coolingDays = null,
        private readonly ?\DateTimeImmutable $now = null,
    ) {
    }

    /**
     * 选填的用户名 / 手机号 / 邮箱：去首尾空白，空值一律为 null（多个空字符串会在唯一索引上互相冲突）。
     * 其他键原样保留。
     */
    public static function normalize(array $identity): array
    {
        foreach (array_keys(self::FIELDS) as $field) {
            if (!array_key_exists($field, $identity)) {
                continue;
            }
            $value = $identity[$field] === null ? '' : trim((string) $identity[$field]);
            $identity[$field] = $value === '' ? null : $value;
        }
        return $identity;
    }

    /**
     * 标识被未删除会员或冷静期内删除的会员占用时抛 MemberIdentityTakenException（code 422）。
     *
     * @param array<string, mixed> $identity 只检查 username / mobile / email 中出现且非空的键
     * @param int|null $exceptId 编辑时排除会员自身
     */
    public function assertAvailable(array $identity, ?int $exceptId = null): void
    {
        $identity = self::normalize($identity);
        $since = $this->coolingStartsAt();
        foreach (self::FIELDS as $field => $label) {
            $value = $identity[$field] ?? null;
            if ($value === null) {
                continue;
            }
            $holder = Member::withTrashed()
                ->where($field, $value)
                ->when($exceptId !== null && $exceptId > 0, static fn ($query) => $query->where('id', '<>', $exceptId))
                ->where(static function ($query) use ($since): void {
                    $query->whereNull('deleted_at')->orWhere('deleted_at', '>=', $since);
                })
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                ->first(['id', 'deleted_at']);
            if ($holder === null) {
                continue;
            }
            $cooling = $holder->deleted_at !== null;
            throw new MemberIdentityTakenException(
                $field,
                $cooling,
                $cooling
                    ? sprintf('该%s所属账号已注销，%d 天冷静期内可由管理员恢复，暂不能使用', $label, $this->days())
                    : $label . '已存在'
            );
        }
    }

    public function days(): int
    {
        return max(0, $this->coolingDays ?? (int) config('soft_delete.member_cooling_days', 30));
    }

    /** 冷静期起点：在此时间及之后删除的会员仍占用标识。 */
    private function coolingStartsAt(): string
    {
        $now = $this->now ?? \DateTimeImmutable::createFromInterface(Carbon::now());
        return $now->modify('-' . $this->days() . ' days')->format('Y-m-d H:i:s');
    }
}
