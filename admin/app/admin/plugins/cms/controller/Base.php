<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use app\common\controller\AdminController;
use app\cms\service\Input;
use app\cms\service\SiteAccess;
use support\Response;
use support\annotation\route\RouteGroup;
#[RouteGroup('/admin/plugins/cms')]
abstract class Base extends AdminController
{
    protected function actor(): string { return Input::id($this->request->context['admin']['id']??''); }
    protected function input(): array { return array_replace($this->request->get(),$this->request->post()); }
    protected function site(string $operation): string { $site=Input::id($this->input()['site_id']??'');(new SiteAccess())->assert($site,$this->actor());(new \app\cms\service\SiteAuthorization())->assert($site,$this->actor(),$operation);return $site; }
    protected function siteAny(array $operations): string
    {
        $site=Input::id($this->input()['site_id']??'');(new SiteAccess())->assert($site,$this->actor());
        if(!array_intersect($operations,(new \app\cms\service\SiteAuthorization())->effective($site,$this->actor())))throw new \InvalidArgumentException('没有本站操作权限',403);return $site;
    }
    protected function mutate(string $kind,callable $write): mixed
    {
        return \support\Db::transaction(function()use($kind,$write):mixed{
            $site=Input::id($this->input()['site_id']??'');(new \app\cms\service\SiteService())->lock($site);
            (new \app\cms\service\MutationAuthorization())->assert($site,$this->actor(),$kind,$this->locale(),$this->input());
            return $write($site);
        });
    }
    protected function locale(): string { return Input::locale($this->input()['locale']??'zh-cn'); }
    /** 写操作的事务已在回调内提交，此时再递增站点缓存版本，避免并发公开请求把提交前的页面写入新版本。 */
    protected function invalidatePublicPages(): void
    {
        $site=(string)($this->input()['site_id']??'');if($site==='')return;
        try{(new \app\cms\service\PublicPageCache())->invalidateSite($site);}catch(\Throwable){}
    }
    protected function action(callable $callback): Response
    {
        try{$data=$callback();if($this->request->method()!=='GET')$this->invalidatePublicPages();return $this->ok(data:$data);}
        catch(\InvalidArgumentException $e){return $this->fail(msg:$e->getMessage(),code:in_array($e->getCode(),[401,403,404,409,422],true)?$e->getCode():422);}
        catch(\Illuminate\Database\UniqueConstraintViolationException){return $this->fail(msg:'记录或访问路径已被占用，请刷新后重试',code:409);}
        catch(\Throwable){return $this->fail(msg:'CMS 操作失败，请检查插件安装及服务器日志',code:500);}
    }
}
