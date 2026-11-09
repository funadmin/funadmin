<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
/** 自定义表单后台接口骨架：路由与权限码先行登记，接口实现随后补齐。 */
#[RouteGroup('/admin/plugins/cms/forms')]
final class Forms extends Base
{
    #[Get('')]
    public function index(): Response { return $this->pending(); }
    #[Get('/summary')]
    public function summary(): Response { return $this->pending(); }
    #[Post('/save')]
    public function save(): Response { return $this->pending(); }
    #[Post('/status')]
    public function status(): Response { return $this->pending(); }
    #[Post('/delete')]
    public function delete(): Response { return $this->pending(); }
    #[Get('/submissions')]
    public function submissions(): Response { return $this->pending(); }
    #[Get('/submissions/detail')]
    public function detail(): Response { return $this->pending(); }
    #[Get('/files/download')]
    public function file(): Response { return $this->pending(); }
    #[Post('/submissions/note')]
    public function note(): Response { return $this->pending(); }
    #[Post('/submissions/transition')]
    public function transition(): Response { return $this->pending(); }
    #[Post('/submissions/invalidate')]
    public function invalidate(): Response { return $this->pending(); }
    #[Get('/submissions/export')]
    public function export(): Response { return $this->pending(); }
    #[Post('/submissions/remove')]
    public function remove(): Response { return $this->pending(); }
    private function pending(): Response { return $this->fail(msg:'表单接口尚未实现',code:501); }
}
