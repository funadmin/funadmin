<?php

declare(strict_types=1);

namespace app\admin\plugins\document\controller;

use OpenApi\Annotations as OA;

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
    /**
     * 获取文档目录树。
     *
     * @OA\Get(
     *     path="/admin/plugins/document/categories",
     *     operationId="admin_plugins_document_controller_CategoryController_tree_get",
     *     tags={"文档目录"},
     *     summary="获取文档目录树。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function tree(): Response
    {
        return $this->action(fn () => (new DocumentCategoryService())->tree());
    }

    /**
     * 创建文档目录。
     *
     * @OA\Post(
     *     path="/admin/plugins/document/categories",
     *     operationId="admin_plugins_document_controller_CategoryController_create_post",
     *     tags={"文档目录"},
     *     summary="创建文档目录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"scope", "slug", "name"},
     *             @OA\Property(property="scope", type="string", description="文档范围。"),
     *             @OA\Property(property="slug", type="string", description="标识。"),
     *             @OA\Property(property="name", type="string", description="名称。"),
     *             @OA\Property(property="parentId", type="integer", description="上级目录 ID，0 为顶级。"),
     *             @OA\Property(property="sortOrder", type="integer", description="排序。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function create(): Response
    {
        return $this->action(fn () => (new DocumentCategoryService())->create($this->input()), '创建成功');
    }

    /**
     * 更新文档目录；缺省字段保留原值。
     *
     * @OA\Put(
     *     path="/admin/plugins/document/categories/{id}",
     *     operationId="admin_plugins_document_controller_CategoryController_update_put",
     *     tags={"文档目录"},
     *     summary="更新文档目录；缺省字段保留原值。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="目录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="scope", type="string", description="文档范围；有子目录或文档时不能修改。"),
     *             @OA\Property(property="slug", type="string", description="标识。"),
     *             @OA\Property(property="name", type="string", description="名称。"),
     *             @OA\Property(property="parentId", type="integer", description="上级目录 ID。"),
     *             @OA\Property(property="sortOrder", type="integer", description="排序。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        return $this->action(fn () => (new DocumentCategoryService())->update($id, $this->input()), '保存成功');
    }

    /**
     * 删除文档目录（进入回收站）。
     *
     * @OA\Delete(
     *     path="/admin/plugins/document/categories/{id}",
     *     operationId="admin_plugins_document_controller_CategoryController_delete_delete",
     *     tags={"文档目录"},
     *     summary="删除文档目录（进入回收站）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="目录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\d+}')]
    public function delete(int $id): Response
    {
        return $this->action(function () use ($id): array {
            (new DocumentCategoryService())->delete($id);
            return ['id' => $id];
        }, '删除成功');
    }

    /**
     * 回收站：已删除的目录。
     *
     * @OA\Get(
     *     path="/admin/plugins/document/categories/recycle",
     *     operationId="admin_plugins_document_controller_CategoryController_recycle_get",
     *     tags={"文档目录"},
     *     summary="目录回收站：已删除的目录。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/recycle')]
    public function recycle(): Response
    {
        return $this->action(fn () => (new DocumentCategoryService())->trashed());
    }

    /**
     * 从回收站恢复目录；上级目录仍在回收站或标识已被占用时返回 409。
     *
     * @OA\Post(
     *     path="/admin/plugins/document/categories/{id}/restore",
     *     operationId="admin_plugins_document_controller_CategoryController_restore_post",
     *     tags={"文档目录"},
     *     summary="从回收站恢复目录；上级目录仍在回收站或标识已被占用时返回 409。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="目录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/restore')]
    public function restore(int $id): Response
    {
        return $this->action(fn () => (new DocumentCategoryService())->restore($id), '已恢复');
    }

    /**
     * 彻底删除回收站中的目录，不可恢复；未删除的目录返回 404。
     *
     * @OA\Delete(
     *     path="/admin/plugins/document/categories/{id}/destroy",
     *     operationId="admin_plugins_document_controller_CategoryController_destroy_delete",
     *     tags={"文档目录"},
     *     summary="彻底删除回收站中的目录，不可恢复；未删除的目录返回 404。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="目录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\d+}/destroy')]
    public function destroy(int $id): Response
    {
        return $this->action(function () use ($id): array {
            (new DocumentCategoryService())->destroy($id);
            return ['id' => $id];
        }, '已彻底删除');
    }
}
