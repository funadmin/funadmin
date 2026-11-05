<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\admin\controller\base\AdminApiController;
use app\admin\system\model\AttachGroup;
use app\common\model\Attach;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Db;
use support\Response;

/** Admin Web 附件分组管理；保留模型供旧文件选择器兼容使用。 */
#[RouteGroup('/admin/system/attachment-group')]
class SystemAttachmentGroup extends AdminApiController
{
    /**
     * 获取附件分组树形数据。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/attachment-group/tree",
     *     operationId="admin_system_controller_SystemAttachmentGroup_tree_get",
     *     tags={"附件分组"},
     *     summary="获取附件分组树形数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/tree')]
    public function tree(): Response { $groups = AttachGroup::orderBy('sort_order')->orderBy('id')->get(); return $this->ok(data: $this->buildTree($groups->map(fn (AttachGroup $group): array => $this->groupData($group))->all())); }
    /**
     * 获取指定附件分组的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/attachment-group/{id}",
     *     operationId="admin_system_controller_SystemAttachmentGroup_detail_get",
     *     tags={"附件分组"},
     *     summary="获取指定附件分组的详情。",
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
    public function detail(int $id): Response { $group = AttachGroup::find($id); return $group ? $this->ok(data: $this->groupData($group)) : $this->fail(msg: '附件分组不存在', code: 404); }
    /**
     * 创建附件分组，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/attachment-group",
     *     operationId="admin_system_controller_SystemAttachmentGroup_create_post",
     *     tags={"附件分组"},
     *     summary="创建附件分组，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(allOf={@OA\Schema(ref="#/components/schemas/AttachmentGroupInput"), @OA\Schema(type="object", required={"name"})})
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function create(): Response { $data = $this->payload(); if ($error = $this->validatePayload($data)) return $this->fail(msg: $error, code: 422); $group = new AttachGroup(); $group->forceFill($data)->save(); return $this->ok('创建成功', $this->groupData($group)); }
    /**
     * 更新指定附件分组。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/attachment-group/{id}",
     *     operationId="admin_system_controller_SystemAttachmentGroup_update_put",
     *     tags={"附件分组"},
     *     summary="更新指定附件分组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/AttachmentGroupInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}')]
    public function update(int $id): Response { $group = AttachGroup::find($id); if (!$group) return $this->fail(msg: '附件分组不存在', code: 404); $data = $this->payload($group); if ($error = $this->validatePayload($data, $id)) return $this->fail(msg: $error, code: 422); $group->forceFill($data)->save(); return $this->ok('保存成功', $this->groupData($group)); }
    /**
     * 删除指定附件分组。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/attachment-group/{id}",
     *     operationId="admin_system_controller_SystemAttachmentGroup_delete_delete",
     *     tags={"附件分组"},
     *     summary="删除指定附件分组。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
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
        if ($id === 1) return $this->fail(msg: '默认附件分组不能删除', code: 422);
        $group = AttachGroup::find($id); if (!$group) return $this->fail(msg: '附件分组不存在', code: 404);
        if (AttachGroup::where('pid', $id)->count() > 0) return $this->fail(msg: '请先删除下级附件分组', code: 422);
        Db::transaction(function () use ($group, $id): void { Attach::where('group_id', $id)->update(['group_id' => 0]); $group->delete(); });
        return $this->ok('删除成功，组内附件已移至未分组');
    }
    private function payload(?AttachGroup $group = null): array { return ['pid' => (int) $this->request->post('parentId', $group?->pid ?? 0), 'name' => trim((string) $this->request->post('name', $group?->name ?? '')), 'thumb' => trim((string) $this->request->post('thumb', $group?->thumb ?? '')), 'status' => (int) $this->request->post('status', $group?->status ?? 1), 'sort_order' => max(0, (int) $this->request->post('sort', $group?->sort_order ?? 999))]; }
    private function validatePayload(array $data, int $currentId = 0): ?string
    {
        $nameLength = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        if ($data['name'] === '') return '附件分组名称不能为空'; if ($nameLength > 100) return '附件分组名称不能超过 100 个字符'; if (strlen($data['thumb']) > 255) return '分组图片地址不能超过 255 个字符'; if ($data['pid'] > 0 && !AttachGroup::whereKey($data['pid'])->first()) return '上级附件分组不存在'; if ($currentId > 0 && ($data['pid'] === $currentId || $this->isDescendant($data['pid'], $currentId))) return '不能将附件分组移动到自身或下级'; return null;
    }
    private function isDescendant(int $candidateId, int $currentId): bool { $visited = []; while ($candidateId > 0 && !isset($visited[$candidateId])) { if ($candidateId === $currentId) return true; $visited[$candidateId] = true; $candidateId = (int) (AttachGroup::where('id', $candidateId)->value('pid') ?? 0); } return false; }
    private function groupData(AttachGroup $group): array { return ['id' => (int) $group->id, 'parentId' => (int) $group->pid, 'name' => (string) $group->name, 'thumb' => (string) ($group->thumb ?? ''), 'status' => (int) $group->status, 'sort' => (int) $group->sort_order, 'isDefault' => (int) $group->id === 1 ? 1 : 0, 'createdAt' => $this->formatTime($group->created_at), 'updatedAt' => $this->formatTime($group->updated_at)]; }
}
