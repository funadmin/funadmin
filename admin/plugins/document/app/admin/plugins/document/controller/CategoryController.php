<?php

declare(strict_types=1);

namespace app\admin\plugins\document\controller;

use app\admin\plugins\document\service\DocumentApiController;
use app\document\service\DocumentCategoryService;
use support\Response;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;

#[RouteGroup('/admin/plugin/document/categories')]
final class CategoryController extends DocumentApiController
{
    #[Get('')]
    public function tree(): Response
    {
        return $this->action(fn () => (new DocumentCategoryService())->tree());
    }

    #[Post('')]
    public function create(): Response
    {
        return $this->action(fn () => (new DocumentCategoryService())->create($this->input()), '创建成功');
    }

    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        return $this->action(fn () => (new DocumentCategoryService())->update($id, $this->input()), '保存成功');
    }

    #[Delete('/{id:\d+}')]
    public function delete(int $id): Response
    {
        return $this->action(function () use ($id): array {
            (new DocumentCategoryService())->delete($id);
            return ['id' => $id];
        }, '删除成功');
    }
}
