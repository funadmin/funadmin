<?php

declare(strict_types=1);

namespace app\admin\plugins\document\controller;

use OpenApi\Annotations as OA;

use app\admin\plugins\document\service\DocumentApiController;
use app\document\service\DocumentService;
use app\document\service\DocumentVersionService;
use support\Response;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;

#[RouteGroup('/admin/plugins/document/documents')]
final class DocumentController extends DocumentApiController
{
    /**
     * 获取文档列表（默认不含已归档）。
     *
     * @OA\Get(
     *     path="/admin/plugins/document/documents",
     *     operationId="admin_plugins_document_controller_DocumentController_index_get",
     *     tags={"文档管理"},
     *     summary="获取文档列表（默认不含已归档）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="scope", in="query", required=false, description="文档范围。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="type", in="query", required=false, description="文档类型。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="pluginCode", in="query", required=false, description="关联插件代码。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="文档状态；为 archived 时列出已归档文档。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="categoryId", in="query", required=false, description="目录 ID。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="标题、摘要或正文关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数，默认 20。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        return $this->action(fn () => (new DocumentService())->list($this->input()));
    }

    /**
     * 获取文档详情（含正文与纯文本）。
     *
     * @OA\Get(
     *     path="/admin/plugins/document/documents/{id}",
     *     operationId="admin_plugins_document_controller_DocumentController_detail_get",
     *     tags={"文档管理"},
     *     summary="获取文档详情（含正文与纯文本）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        return $this->action(fn () => (new DocumentService())->find($id));
    }

    /**
     * 创建文档草稿。
     *
     * @OA\Post(
     *     path="/admin/plugins/document/documents",
     *     operationId="admin_plugins_document_controller_DocumentController_create_post",
     *     tags={"文档管理"},
     *     summary="创建文档草稿。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"title", "slug", "scope"},
     *             @OA\Property(property="title", type="string", description="标题。"),
     *             @OA\Property(property="slug", type="string", description="标识。"),
     *             @OA\Property(property="scope", type="string", description="文档范围。"),
     *             @OA\Property(property="type", type="string", description="文档类型。"),
     *             @OA\Property(property="visibility", type="string", description="可见性。"),
     *             @OA\Property(property="summary", type="string", description="摘要。"),
     *             @OA\Property(property="pluginCode", type="string", description="关联插件代码。"),
     *             @OA\Property(property="appliesTo", type="string", description="适用版本。"),
     *             @OA\Property(property="sortOrder", type="integer", description="排序。"),
     *             @OA\Property(property="categoryIds", description="目录 ID 列表。", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="content", type="object", additionalProperties=true, description="ProseMirror 文档 JSON，默认空文档。")
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
        return $this->action(fn () => (new DocumentService())->create($this->input()), '创建成功');
    }

    /**
     * 更新文档元数据（标题、范围、目录等），不改正文；更改可见性需要发布权限。
     *
     * @OA\Put(
     *     path="/admin/plugins/document/documents/{id}",
     *     operationId="admin_plugins_document_controller_DocumentController_updateMeta_put",
     *     tags={"文档管理"},
     *     summary="更新文档元数据（标题、范围、目录等），不改正文；更改可见性需要发布权限。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"metaVersion"},
     *             @OA\Property(property="metaVersion", type="integer", description="元数据版本，与当前不一致返回 409。"),
     *             @OA\Property(property="title", type="string", description="标题。"),
     *             @OA\Property(property="slug", type="string", description="标识。"),
     *             @OA\Property(property="scope", type="string", description="文档范围。"),
     *             @OA\Property(property="type", type="string", description="文档类型。"),
     *             @OA\Property(property="visibility", type="string", description="可见性。"),
     *             @OA\Property(property="summary", type="string", description="摘要。"),
     *             @OA\Property(property="pluginCode", type="string", description="关联插件代码。"),
     *             @OA\Property(property="appliesTo", type="string", description="适用版本。"),
     *             @OA\Property(property="sortOrder", type="integer", description="排序。"),
     *             @OA\Property(property="categoryIds", description="目录 ID 列表。", type="array", @OA\Items(type="integer"))
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
    public function updateMeta(int $id): Response
    {
        return $this->action(function () use ($id): array {
            $input = $this->input();
            if (!array_key_exists('metaVersion', $input)) throw new \InvalidArgumentException('metaVersion 无效', 422);
            $current = (new DocumentService())->row($id);
            if (array_key_exists('visibility', $input) && $input['visibility'] !== $current->visibility
                && !(new \app\admin\authorization\service\AdminAuthorizationService())->can('document:document:publish')) {
                throw new \InvalidArgumentException('更改文档可见性需要发布权限', 403);
            }
            return (new DocumentService())->updateMeta($id, $input);
        }, '保存成功');
    }

    /**
     * 保存文档草稿正文。
     *
     * @OA\Put(
     *     path="/admin/plugins/document/documents/{id}/draft",
     *     operationId="admin_plugins_document_controller_DocumentController_saveDraft_put",
     *     tags={"文档管理"},
     *     summary="保存文档草稿正文。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"content", "lockVersion"},
     *             @OA\Property(property="content", type="object", additionalProperties=true, description="ProseMirror 文档 JSON。"),
     *             @OA\Property(property="lockVersion", type="integer", description="正文乐观锁版本。")
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
    #[Put('/{id:\d+}/draft')]
    public function saveDraft(int $id): Response
    {
        return $this->action(function () use ($id): array {
            $input = $this->input();
            return (new DocumentVersionService())->saveDraft($id, $input['content'] ?? null, $this->lockVersion($input));
        }, '已保存');
    }

    /**
     * 删除文档（进入回收站，目录关联一并软删除）。
     *
     * @OA\Delete(
     *     path="/admin/plugins/document/documents/{id}",
     *     operationId="admin_plugins_document_controller_DocumentController_delete_delete",
     *     tags={"文档管理"},
     *     summary="删除文档（进入回收站，目录关联一并软删除）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\d+}')]
    public function delete(int $id): Response
    {
        return $this->action(function () use ($id): array {
            (new DocumentService())->delete($id);
            return ['id' => $id];
        }, '删除成功');
    }

    /**
     * 回收站：已删除的文档（可按 keyword 搜索标题或标识，page / pageSize 分页）。
     *
     * @OA\Get(
     *     path="/admin/plugins/document/documents/recycle",
     *     operationId="admin_plugins_document_controller_DocumentController_recycle_get",
     *     tags={"文档管理"},
     *     summary="文档回收站：已删除的文档。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="keyword", in="query", required=false, description="标题或标识关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数，默认 20。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/recycle')]
    public function recycle(): Response
    {
        return $this->action(fn () => (new DocumentService())->trashed($this->input()));
    }

    /**
     * 从回收站恢复文档，连同这次删除带走的目录关联；标识已被占用时返回 409。（restore 已用于恢复历史版本。）
     *
     * @OA\Post(
     *     path="/admin/plugins/document/documents/{id}/recover",
     *     operationId="admin_plugins_document_controller_DocumentController_recover_post",
     *     tags={"文档管理"},
     *     summary="从回收站恢复文档及其目录关联；标识已被占用时返回 409。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 目录已删除或范围变化而未恢复的关联数在 data.skippedLinks 中返回。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/recover')]
    public function recover(int $id): Response
    {
        // 有目录关联因目录已删除或换了范围而未恢复时，在提示里说明数量（data.skippedLinks）。
        return $this->action(fn () => (new DocumentService())->restore($id), static fn (array $restored): string => $restored['skippedLinks'] > 0
            ? "已恢复，{$restored['skippedLinks']} 个目录关联因目录已删除或范围变化未恢复" : '已恢复');
    }

    /**
     * 彻底删除回收站中的文档，不可恢复；未删除的文档返回 404。
     *
     * @OA\Delete(
     *     path="/admin/plugins/document/documents/{id}/destroy",
     *     operationId="admin_plugins_document_controller_DocumentController_destroy_delete",
     *     tags={"文档管理"},
     *     summary="彻底删除回收站中的文档，不可恢复；未删除的文档返回 404。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
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
            (new DocumentService())->destroy($id);
            return ['id' => $id];
        }, '已彻底删除');
    }

    /**
     * 发布文档当前草稿为新修订。
     *
     * @OA\Post(
     *     path="/admin/plugins/document/documents/{id}/publish",
     *     operationId="admin_plugins_document_controller_DocumentController_publish_post",
     *     tags={"文档管理"},
     *     summary="发布文档当前草稿为新修订。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"lockVersion"},
     *             @OA\Property(property="lockVersion", type="integer", description="正文乐观锁版本。"),
     *             @OA\Property(property="changeSummary", type="string", description="变更说明。")
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
    #[Post('/{id:\d+}/publish')]
    public function publish(int $id): Response
    {
        return $this->action(function () use ($id): array {
            $input = $this->input();
            return (new DocumentVersionService())->publish($id, (string) ($input['changeSummary'] ?? ''), $this->lockVersion($input));
        }, '发布成功');
    }

    /**
     * 归档文档。
     *
     * @OA\Post(
     *     path="/admin/plugins/document/documents/{id}/archive",
     *     operationId="admin_plugins_document_controller_DocumentController_archive_post",
     *     tags={"文档管理"},
     *     summary="归档文档。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"lockVersion"},
     *             @OA\Property(property="lockVersion", type="integer", description="正文乐观锁版本。")
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
    #[Post('/{id:\d+}/archive')]
    public function archive(int $id): Response
    {
        return $this->action(fn () => (new DocumentVersionService())->archive($id, $this->lockVersion($this->input())), '已归档');
    }

    /**
     * 获取文档修订列表。
     *
     * @OA\Get(
     *     path="/admin/plugins/document/documents/{id}/revisions",
     *     operationId="admin_plugins_document_controller_DocumentController_revisions_get",
     *     tags={"文档管理"},
     *     summary="获取文档修订列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="pageSize", in="query", required=false, description="每页记录数，默认 20。", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}/revisions')]
    public function revisions(int $id): Response
    {
        $input = $this->input();
        return $this->action(fn () => (new DocumentVersionService())->revisions($id, (int) ($input['page'] ?? 1), (int) ($input['pageSize'] ?? 20)));
    }

    /**
     * 获取文档的指定修订。
     *
     * @OA\Get(
     *     path="/admin/plugins/document/documents/{id}/revisions/{revisionNo}",
     *     operationId="admin_plugins_document_controller_DocumentController_revision_get",
     *     tags={"文档管理"},
     *     summary="获取文档的指定修订。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="revisionNo", in="path", required=true, description="修订号。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}/revisions/{revisionNo:\d+}')]
    public function revision(int $id, int $revisionNo): Response
    {
        return $this->action(fn () => (new DocumentVersionService())->revision($id, $revisionNo));
    }

    /**
     * 将文档草稿恢复为指定修订。
     *
     * @OA\Post(
     *     path="/admin/plugins/document/documents/{id}/revisions/{revisionNo}/restore",
     *     operationId="admin_plugins_document_controller_DocumentController_restore_post",
     *     tags={"文档管理"},
     *     summary="将文档草稿恢复为指定修订。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="文档 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="revisionNo", in="path", required=true, description="修订号。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"lockVersion"},
     *             @OA\Property(property="lockVersion", type="integer", description="正文乐观锁版本。")
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
    #[Post('/{id:\d+}/revisions/{revisionNo:\d+}/restore')]
    public function restore(int $id, int $revisionNo): Response
    {
        return $this->action(fn () => (new DocumentVersionService())->restore($id, $revisionNo, $this->lockVersion($this->input())), '已恢复');
    }
}
