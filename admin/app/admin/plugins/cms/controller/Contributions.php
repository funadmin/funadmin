<?php

declare (strict_types=1);
namespace app\admin\plugins\cms\controller;

use app\cms\service\{ContributionPolicy, ContributionService};
use support\annotation\route\{Get, Post, RouteGroup};
use support\{Db, Response};
#[RouteGroup('/admin/plugin/cms/contributions')]
final class Contributions extends Base
{
    #[Get('')]
    public function index(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:submission:list');
            $input = $this->input();
            return (new ContributionService())->listing($site, $this->locale(), 'content', $input['status'] ?? 'pending', (int) ($input['page'] ?? 1));
        });
    }
    #[Post('/review')]
    public function review(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:submission:review');
            $input = $this->input();
            $version = filter_var($input['lock_version'] ?? null, FILTER_VALIDATE_INT);
            if ($version === false || $version === null || $version < 0) {
                throw new \InvalidArgumentException('lock_version 必须为非负整数', 422);
            }
            return (new ContributionService())->reviewContent($site, $this->actor(), \app\cms\service\Input::id($input['id'] ?? ''), $version, \app\cms\service\Input::text($input['decision'] ?? '', 20), \app\cms\service\Input::text($input['reason'] ?? '', 1000));
        });
    }
    #[Get('/settings')]
    public function settings(): Response
    {
        return $this->action(fn() => $this->policy()->read($this->site('cms:site:detail')));
    }
    #[Post('/settings/save')]
    public function saveSettings(): Response
    {
        return $this->action(fn() => $this->policy()->save($this->site('cms:site:edit'), $this->actor(), $this->input()));
    }
    private function policy(): ContributionPolicy
    {
        if (!Db::schema()->hasTable('cms_member_submission')) {
            throw new \InvalidArgumentException('请完成 CMS 会员投稿升级', 409);
        }
        return new ContributionPolicy();
    }
}
