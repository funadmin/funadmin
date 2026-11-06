<?php

declare (strict_types=1);
namespace app\cms\controller;

use app\cms\service\{ContributionPolicy, ContributionService, Input};
use support\annotation\route\{Get, Post, RouteGroup};
use support\Response;
#[RouteGroup('/cms/member')]
final class MemberContent extends MemberEndpoint
{
    #[Get('/context')]
    public function context(): Response
    {
        return $this->action(fn() => (new ContributionPolicy())->context());
    }
    #[Get('/identity')]
    public function identity(): Response
    {
        return $this->action(fn() => ['member_id' => $this->member()]);
    }
    #[Get('/submissions')]
    public function submissions(): Response
    {
        return $this->action(function (): array {
            $member = $this->member();
            $input = $this->input();
            return (new ContributionService())->mine(Input::id($input['site_id'] ?? ''), Input::locale($input['locale'] ?? 'zh-cn'), $member, (int) ($input['page'] ?? 1));
        });
    }
    #[Post('/submissions')]
    public function submit(): Response
    {
        return $this->action(function (): array {
            $member = $this->member();
            $input = $this->input();
            return (new ContributionService())->submit(Input::id($input['site_id'] ?? ''), Input::locale($input['locale'] ?? 'zh-cn'), $member, 'content', $input);
        });
    }
    #[Post('/submissions/{id}/withdraw')]
    public function withdraw(string $id): Response
    {
        return $this->action(function () use ($id): array {
            $member = $this->member();
            $input = $this->input();
            $version = filter_var($input['lock_version'] ?? null, FILTER_VALIDATE_INT);
            if ($version === false || $version === null || $version < 0) {
                throw new \InvalidArgumentException('lock_version 必须为非负整数', 422);
            }
            return (new ContributionService())->withdraw(Input::id($input['site_id'] ?? ''), $member, Input::id($id), $version);
        });
    }
}
