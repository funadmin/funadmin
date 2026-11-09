<?php

declare(strict_types=1);

namespace app\admin\plugins\document\controller;

use app\admin\plugins\document\service\DocumentApiController;
use app\document\service\FeedbackService;
use support\Response;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;

#[RouteGroup('/admin/plugins/document/feedback')]
final class FeedbackController extends DocumentApiController
{
    /** 读者反馈列表，可按文档（documentId）、是否有帮助（helpful=1/0）与是否填写了说明（withComment）筛选。 */
    #[Get('')]
    public function index(): Response
    {
        return $this->action(fn () => (new FeedbackService())->list($this->input()));
    }
}
