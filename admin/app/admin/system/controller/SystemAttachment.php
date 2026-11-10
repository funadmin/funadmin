<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\system\model\AttachGroup;
use app\common\model\Attach;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

/** Admin Web 附件库管理。 */
#[RouteGroup('/admin/system/attachment')]
class SystemAttachment extends AdminController
{
    /**
     * 获取附件列表。
     *
     * 查询参数：keyword、mimeType、groupId、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/attachment",
     *     operationId="admin_system_controller_SystemAttachment_index_get",
     *     tags={"附件"},
     *     summary="获取附件列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="mimeType", in="query", required=false, description="mimeType 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="groupId", in="query", required=false, description="groupId 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = Attach::orderByDesc('id');
        $keyword = trim((string) $this->request->get('keyword', ''));
        $mimeType = trim((string) $this->request->get('mimeType', ''));
        $groupId = $this->request->get('groupId', null);
        if ($keyword !== '') $query->where(function ($where) use ($keyword): void { $where->where('original_name', 'like', '%' . addcslashes($keyword, '%_') . '%')->orWhere('name', 'like', '%' . addcslashes($keyword, '%_') . '%'); });
        if ($groupId !== null && $groupId !== '') $query->where('group_id', (int) $groupId);
        if ($mimeType !== '') $this->applyMimeFilter($query, $mimeType);
        $total = (clone $query)->count();
        $items = $query->forPage($page, $pageSize)->get();
        return $this->ok(data: $this->paginationData($items->map(fn (Attach $attach): array => $this->attachmentData($attach))->all(), $total, $page, $pageSize));
    }

    /**
     * 获取指定附件的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/attachment/{id}",
     *     operationId="admin_system_controller_SystemAttachment_detail_get",
     *     tags={"附件"},
     *     summary="获取指定附件的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}')]
    public function detail(int $id): Response { $attach = Attach::find($id); return $attach ? $this->ok(data: $this->attachmentData($attach)) : $this->fail(msg: '附件不存在', code: 404); }

    /**
     * 更新附件显示名称，保留底层存储路径。
     *
     * 请求字段：name。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/attachment/{id}/name",
     *     operationId="admin_system_controller_SystemAttachment_rename_put",
     *     tags={"附件"},
     *     summary="更新附件显示名称，保留底层存储路径。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", required={"name"}, @OA\Property(property="name", type="string", description="name 参数。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}/name')]
    public function rename(int $id): Response
    {
        $attach = Attach::find($id); if (!$attach) return $this->fail(msg: '附件不存在', code: 404);
        $name = trim((string) $this->request->post('name', '')); $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($name === '' || $length > 255) return $this->fail(msg: '文件名称不能为空且不能超过 255 个字符', code: 422);
        $attach->forceFill(['original_name' => $name])->save(); return $this->ok('重命名成功', $this->attachmentData($attach));
    }

    /**
     * 将选中的附件移动到指定分组。
     *
     * 请求字段：groupId、ids。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/attachment/move",
     *     operationId="admin_system_controller_SystemAttachment_move_post",
     *     tags={"附件"},
     *     summary="将选中的附件移动到指定分组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="groupId", type="integer", description="groupId 参数。"),
     *             @OA\Property(property="ids", type="array", description="目标记录 ID 集合。", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/move')]
    public function move(): Response
    {
        $ids = $this->ids(); if (!$ids) return $this->fail(msg: '请选择要移动的附件', code: 422);
        $groupId = (int) $this->request->post('groupId', 0); if ($groupId > 0 && !AttachGroup::find($groupId)) return $this->fail(msg: '目标附件分组不存在', code: 422);
        $attachments = Attach::whereIn('id', $ids)->get(); if (count($attachments) !== count($ids)) return $this->fail(msg: '部分附件不存在', code: 404);
        foreach ($attachments as $attach) $attach->forceFill(['group_id' => $groupId])->save();
        return $this->ok('移动成功', ['moved' => count($attachments)]);
    }

    /**
     * 软删除附件记录；存储中的文件保留不删除。
     *
     * 请求字段：ids。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/attachment",
     *     operationId="admin_system_controller_SystemAttachment_delete_delete",
     *     tags={"附件"},
     *     summary="软删除附件记录；存储中的文件保留不删除。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="ids", type="array", description="目标记录 ID 集合。", @OA\Items(type="integer")))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('')]
    public function delete(): Response
    {
        $ids = $this->ids(); if (!$ids) return $this->fail(msg: '请选择要删除的附件', code: 422);
        $attachments = Attach::whereIn('id', $ids)->get(); if (count($attachments) !== count($ids)) return $this->fail(msg: '部分附件不存在', code: 404);
        // 只软删除附件记录（写 deleted_at 与 deleted_token），存储中的文件一律保留：后台数据永不物理删除。
        $removed = []; $failed = [];
        foreach ($attachments as $attach) {
            try {
                if (!$attach->delete()) throw new \RuntimeException('attachment soft delete failed');
                $removed[] = $attach->id;
            } catch (\Throwable) {
                $failed[] = $attach->id;
            }
        }
        if ($failed) return $this->fail(msg: '部分附件删除失败：已删除 '.count($removed).' 条，失败 '.count($failed).' 条已保留', data: ['removed'=>$removed, 'failed'=>$failed], code: 422);
        return $this->ok('删除成功', ['removed' => count($removed)]);
    }

    private function applyMimeFilter($query, string $mimeType): void
    {
        match ($mimeType) { 'image' => $query->whereLike('mime', 'image/%'), 'video' => $query->whereLike('mime', 'video/%'), 'audio' => $query->whereLike('mime', 'audio/%'), 'document' => $query->whereIn('ext', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt']), 'archive' => $query->whereIn('ext', ['zip', 'rar', '7z', 'tar', 'gz']), default => null };
    }

    private function attachmentData(Attach $attach): array
    {
        return ['id' => (int) $attach->id, 'groupId' => (int) $attach->group_id, 'name' => (string) ($attach->name ?? ''), 'originalName' => (string) ($attach->original_name ?? ''), 'path' => (string) ($attach->path ?? ''), 'url' => (string) (($attach->url ?? '') ?: ($attach->path ?? '')), 'thumb' => (string) (($attach->thumb ?? '') ?: ($attach->path ?? '')), 'ext' => strtolower((string) ($attach->ext ?? '')), 'size' => (int) round((float) $attach->size * 1024), 'mime' => (string) ($attach->mime ?? ''), 'driver' => (string) ($attach->driver ?? ''), 'width' => (int) $attach->width, 'height' => (int) $attach->height, 'status' => (int) $attach->status, 'createdAt' => $this->formatTime($attach->created_at), 'updatedAt' => $this->formatTime($attach->updated_at)];
    }
}
