<?php
declare(strict_types=1);
namespace app\admin\plugins\qa\service;
use app\cms\service\Input;
use app\cms\service\SiteAccess;
use app\cms\service\SiteAuthorization;
use support\Response;
/**
 * 问答后台接口（/admin/plugins/qa/questions、/admin/plugins/qa/answers）的公共部分，由控制器 use。
 * 放在 service/ 而不是 controller/：插件清单要求 controller/ 下每个文件恰好声明一个 /admin/plugins/qa 路由组。
 * 每个接口有自己的宿主权限码（由控制器与方法名推导，登记在 plugin.json）；
 * 站点内复用 CMS 的投稿站点权限：读取需要 cms:submission:list，写操作（含删除、恢复、彻底删除）需要 cms:submission:review。
 */
trait AdminEndpoint
{
    protected function input(): array { return array_replace($this->request->get(),$this->request->post()); }
    protected function actor(): string { return Input::id(Input::text($this->request->context['admin']['id']??'')); }
    protected function locale(): string { return Input::locale($this->input()['locale']??'zh-cn'); }
    protected function id(): string { return Input::id(Input::text($this->input()['id']??'')); }
    protected function site(string $operation): string { $site=Input::id(Input::text($this->input()['site_id']??''));(new SiteAccess())->assert($site,$this->actor());(new SiteAuthorization())->assert($site,$this->actor(),$operation);return $site; }
    protected function flag(string $key): bool { $value=$this->input()[$key]??null;if(!is_bool($value))throw new \InvalidArgumentException($key.' 必须为布尔值',422);return $value; }
    protected function version(): int { $raw=Input::text($this->input()['question_version']??'',10);if(!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$raw)||(int)$raw>2147483647)throw new \InvalidArgumentException('问题版本无效',422);return (int)$raw; }
    protected function page(): int { $raw=Input::text($this->input()['page']??'1',10);if(!preg_match('/^[1-9][0-9]{0,8}$/D',$raw))throw new \InvalidArgumentException('页码无效',422);return (int)$raw; }
    protected function action(callable $callback): Response
    {
        try{return $this->ok(data:$callback());}
        catch(\InvalidArgumentException $e){return $this->fail(msg:$e->getMessage(),code:in_array($e->getCode(),[401,403,404,409,422,429],true)?$e->getCode():422);}
        catch(\app\common\model\concern\RestoreConflictException $e){return $this->fail(msg:$e->getMessage(),code:409);}
        catch(\Illuminate\Database\UniqueConstraintViolationException){return $this->fail(msg:'记录冲突，请刷新后重试',code:409);}
        catch(\Throwable){return $this->fail(msg:'问答管理暂时不可用',code:500);}
    }
}
