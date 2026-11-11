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

#[RouteGroup('/admin/plugins/document/categories')]
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

    /** 回收站：已删除的目录。 */
    #[Get('/recycle')]
    public function recycle(): Response
    {
        return $this->action(fn () => (new DocumentCategoryService())->trashed());
    }

    /** 从回收站恢复目录；上级目录仍在回收站或标识已被占用时返回 409。 */
    #[Post('/{id:\d+}/restore')]
    public function restore(int $id): Response
    {
        return $this->action(fn () => (new DocumentCategoryService())->restore($id), '已恢复');
    }

    /** 彻底删除回收站中的目录，不可恢复；未删除的目录返回 404。 */
    #[Delete('/{id:\d+}/destroy')]
    public function destroy(int $id): Response
    {
        return $this->action(function () use ($id): array {
            (new DocumentCategoryService())->destroy($id);
            return ['id' => $id];
        }, '已彻底删除');
    }
}
