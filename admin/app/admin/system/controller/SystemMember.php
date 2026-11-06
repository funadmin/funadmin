<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\admin\system\model\Member;
use app\admin\system\model\MemberGroup;
use app\admin\system\model\MemberGroupRelation;
use app\admin\system\model\MemberLevel;
use app\admin\system\model\MemberTag;
use app\admin\system\model\MemberTagRelation;
use app\admin\system\service\MemberFormDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Db;
use support\Response;

/**
 * Admin Web 会员管理。
 */
#[RouteGroup('/admin/system/member')]
class SystemMember extends AdminController
{

    /**
     * 获取会员列表。
     *
     * 查询参数：recycled、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/member",
     *     operationId="admin_system_controller_SystemMember_index_get",
     *     tags={"会员"},
     *     summary="获取会员列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="recycled", in="query", required=false, description="recycled 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="状态筛选或启用状态，具体取值由当前资源校验。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="groupId", in="query", required=false, description="groupId 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="levelId", in="query", required=false, description="levelId 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(
     *         name="pageSize",
     *         in="query",
     *         required=false,
     *         description="每页记录数。",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberIndexResult")),
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
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = $this->filteredQuery($recycled);
        $total = (clone $query)->count();
        $result = $query->orderByDesc('id')->forPage($page, $pageSize);
        $items = $result->get()->all();
        [$memberGroups, $groups, $levels, $memberTags, $tags] = $this->relationMaps($items);

        return $this->ok(data: $this->paginationData(
            array_map(fn (Member $member): array => $this->memberData($member, $memberGroups, $groups, $levels, $memberTags, $tags), $items),
            $total,
            $page,
            $pageSize
        ));
    }

    /**
     * 获取指定会员的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/member/{id}",
     *     operationId="admin_system_controller_SystemMember_detail_get",
     *     tags={"会员"},
     *     summary="获取指定会员的详情。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberDetailResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        $member = Member::withTrashed()->find($id);
        if (!$member) {
            return $this->fail(msg: '会员不存在', code: 404);
        }
        [$memberGroups, $groups, $levels, $memberTags, $tags] = $this->relationMaps([$member]);
        return $this->ok(data: $this->memberData($member, $memberGroups, $groups, $levels, $memberTags, $tags));
    }

    /**
     * 获取会员表单定义及会员组、等级、标签选项。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/member/options",
     *     operationId="admin_system_controller_SystemMember_options_get",
     *     tags={"会员"},
     *     summary="获取会员表单定义及会员组、等级、标签选项。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="X-Locale", in="header", required=false, description="X-Locale 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/options')]
    public function options(): Response
    {
        $options = [
            'groups' => MemberGroup::where('status', 1)->orderBy('id')->get(['id', 'name'])->toArray(),
            'levels' => MemberLevel::where('status', 1)->orderBy('sort_order')->orderBy('id')->get(['id', 'name'])->toArray(),
            'tags' => MemberTag::where('status', 1)->orderBy('sort_order')->orderBy('id')->get(['id', 'name'])->toArray(),
        ];
        $locale = (string) $this->request->header('X-Locale', 'zh-cn');
        return $this->ok(data: $options + MemberFormDefinition::build($options, $locale) + [
            'page' => \app\admin\system\service\MemberPageDefinition::build($options, $locale),
        ]);
    }

    /**
     * 创建会员，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member",
     *     operationId="admin_system_controller_SystemMember_create_post",
     *     tags={"会员"},
     *     summary="创建会员，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/MemberInput"),
     *                 @OA\Schema(type="object", required={"username", "mobile", "groupIds", "levelId"})
     *             }
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberCreateResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($error = $this->validateUnique($data)) {
            return $this->fail(msg: $error, code: 422);
        }

        $groupIds = $data['groupIds'];
        $tagIds = $data['tagIds'];
        unset($data['groupIds'], $data['tagIds']);
        $data['password'] = '';
        try {
            $member = Db::transaction(function () use ($data, $groupIds, $tagIds): Member {
                $member = Member::create($data);
                $member->groups()->syncWithPivotValues($groupIds, ['created_at' => date('Y-m-d H:i:s')]);
                $member->tags()->syncWithPivotValues($tagIds, ['created_at' => date('Y-m-d H:i:s')]);
                return $member;
            });
        } catch (\Throwable $exception) {
            if ($message = $this->duplicateError($exception)) {
                return $this->fail(msg: $message, code: 422);
            }
            throw $exception;
        }
        [$memberGroups, $groups, $levels, $memberTags, $tags] = $this->relationMaps([$member]);
        return $this->ok('创建成功', $this->memberData($member, $memberGroups, $groups, $levels, $memberTags, $tags));
    }

    /**
     * 更新指定会员。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/member/{id}",
     *     operationId="admin_system_controller_SystemMember_update_put",
     *     tags={"会员"},
     *     summary="更新指定会员。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/MemberInput")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberUpdateResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        $member = Member::find($id);
        if (!$member) {
            return $this->fail(msg: '会员不存在', code: 404);
        }
        $data = $this->payload($member);
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($error = $this->validateUnique($data, $id)) {
            return $this->fail(msg: $error, code: 422);
        }

        $groupIds = $data['groupIds'];
        $tagIds = $data['tagIds'];
        unset($data['groupIds'], $data['tagIds']);
        try {
            Db::transaction(function () use ($member, $data, $groupIds, $tagIds): void {
                $member->forceFill($data)->save();
                $member->groups()->syncWithPivotValues($groupIds, ['created_at' => date('Y-m-d H:i:s')]);
                $member->tags()->syncWithPivotValues($tagIds, ['created_at' => date('Y-m-d H:i:s')]);
            });
        } catch (\Throwable $exception) {
            if ($message = $this->duplicateError($exception)) {
                return $this->fail(msg: $message, code: 422);
            }
            throw $exception;
        }
        [$memberGroups, $groups, $levels, $memberTags, $tags] = $this->relationMaps([$member]);
        return $this->ok('保存成功', $this->memberData($member, $memberGroups, $groups, $levels, $memberTags, $tags));
    }

    /**
     * 更新会员的启用状态。
     *
     * 请求字段：status。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member/{id}/status",
     *     operationId="admin_system_controller_SystemMember_status_post",
     *     tags={"会员"},
     *     summary="更新会员的启用状态。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="status", type="integer", description="状态筛选或启用状态，具体取值由当前资源校验。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/SystemMemberStatusResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/{id:\d+}/status')]
    public function status(int $id): Response
    {
        $member = Member::find($id);
        if (!$member) {
            return $this->fail(msg: '会员不存在', code: 404);
        }
        Db::transaction(function () use ($member): void {
            $member->forceFill(['status' => $this->binaryStatus($this->request->post('status', 0))])->save();
        });
        [$memberGroups, $groups, $levels, $memberTags, $tags] = $this->relationMaps([$member]);
        return $this->ok('状态更新成功', $this->memberData($member, $memberGroups, $groups, $levels, $memberTags, $tags));
    }

    /**
     * 将选中的会员移入回收站。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/member",
     *     operationId="admin_system_controller_SystemMember_recycle_delete",
     *     tags={"会员"},
     *     summary="将选中的会员移入回收站。",
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
    public function recycle(): Response
    {
        $members = $this->membersForAction(false);
        if ($members instanceof Response) {
            return $members;
        }
        foreach ($members as $member) {
            $member->delete();
        }
        return $this->ok('已移入回收站', ['removed' => count($members)]);
    }

    /**
     * 恢复回收站中的会员。
     *
     * 请求字段：ids。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member/restore",
     *     operationId="admin_system_controller_SystemMember_restore_post",
     *     tags={"会员"},
     *     summary="恢复回收站中的会员。",
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
    #[Post('/restore')]
    public function restore(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要恢复的会员', code: 422);
        }
        $members = Member::onlyTrashed()->whereIn('id', $ids)->get();
        if (count($members) !== count($ids)) {
            return $this->fail(msg: '部分会员不存在或不在回收站', code: 404);
        }
        foreach ($members as $member) {
            $member->restore();
        }
        return $this->ok('恢复成功', ['restored' => count($members)]);
    }

    /**
     * 永久删除会员，删除后不可通过回收站恢复。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/member/destroy",
     *     operationId="admin_system_controller_SystemMember_destroy_delete",
     *     tags={"会员"},
     *     summary="永久删除会员，删除后不可通过回收站恢复。",
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
    #[Delete('/destroy')]
    public function destroy(): Response
    {
        $members = $this->membersForAction(true);
        if ($members instanceof Response) {
            return $members;
        }
        Db::transaction(function () use ($members): void {
            foreach ($members as $member) {
                $member->groups()->detach();
                $member->tags()->detach();
                $member->forceDelete();
            }
        });
        return $this->ok('永久删除成功', ['removed' => count($members)]);
    }

    /**
     * 批量导入会员，返回导入结果。
     *
     * 请求字段：rows。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/member/import",
     *     operationId="admin_system_controller_SystemMember_import_post",
     *     tags={"会员"},
     *     summary="批量导入会员，返回导入结果。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="rows", type="array", description="待导入的记录数组。", @OA\Items()))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/import')]
    public function import(): Response
    {
        $rows = $this->request->post('rows', []);
        if (!is_array($rows) || !$rows) {
            return $this->fail(msg: '导入数据不能为空', code: 422);
        }
        if (count($rows) > 1000) {
            return $this->fail(msg: '单次最多导入 1000 条会员', code: 422);
        }

        $created = 0;
        $errors = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：数据格式错误';
                continue;
            }
            $data = $this->normalizePayload($row);
            if ($error = $this->validatePayload($data)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：' . $error;
                continue;
            }
            if ($error = $this->validateUnique($data)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：' . $error;
                continue;
            }
            try {
                $groupIds = $data['groupIds'];
                $tagIds = $data['tagIds'];
                unset($data['groupIds'], $data['tagIds']);
                $data['password'] = '';
                Db::transaction(function () use ($data, $groupIds, $tagIds): void {
                    $member = Member::create($data);
                    $member->groups()->syncWithPivotValues($groupIds, ['created_at' => date('Y-m-d H:i:s')]);
                    $member->tags()->syncWithPivotValues($tagIds, ['created_at' => date('Y-m-d H:i:s')]);
                });
                $created++;
            } catch (\Throwable $exception) {
                $errors[] = '第 ' . ($index + 2) . ' 行：' . ($this->duplicateError($exception) ?? '保存失败');
            }
        }

        return $this->ok($errors ? '导入完成，部分数据未导入' : '导入成功', [
            'created' => $created,
            'skipped' => count($rows) - $created,
            'errors' => $errors,
        ]);
    }

    /**
     * 按筛选条件导出会员数据。
     *
     * 查询参数：recycled。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/member/export",
     *     operationId="admin_system_controller_SystemMember_export_get",
     *     tags={"会员"},
     *     summary="按筛选条件导出会员数据。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="recycled", in="query", required=false, description="recycled 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="状态筛选或启用状态，具体取值由当前资源校验。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="groupId", in="query", required=false, description="groupId 参数。", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="levelId", in="query", required=false, description="levelId 参数。", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/export')]
    public function export(): Response
    {
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = $this->filteredQuery($recycled);
        if ((clone $query)->count() > 10000) {
            return $this->fail(msg: '导出数据超过 10000 条，请缩小筛选范围', code: 422);
        }
        $members = $query->orderByDesc('id')->get()->all();
        [$memberGroups, $groups, $levels, $memberTags, $tags] = $this->relationMaps($members);
        return $this->ok(data: array_map(fn (Member $member): array => $this->memberData($member, $memberGroups, $groups, $levels, $memberTags, $tags), $members));
    }

    private function filteredQuery(bool $recycled): Builder
    {
        $query = $recycled ? Member::onlyTrashed() : Member::where('id', '>', 0);
        $keyword = trim((string) $this->request->get('keyword', ''));
        $status = $this->request->get('status', null);
        $groupId = (int) $this->request->get('groupId', 0);
        $levelId = (int) $this->request->get('levelId', 0);
        if ($keyword !== '') {
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('username', '%' . $keyword . '%')
                    ->orWhere('mobile', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($groupId > 0) {
            $query->whereIn('id', function ($relationQuery) use ($groupId): void {
                $relationQuery->from((new MemberGroupRelation())->getTable())
                    ->select('member_id')->where('group_id', $groupId);
            });
        }
        if ($levelId > 0) {
            $query->where('level_id', $levelId);
        }
        return $query;
    }

    private function membersForAction(bool $onlyTrashed): array|Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要操作的会员', code: 422);
        }
        $query = $onlyTrashed ? Member::onlyTrashed() : Member::where('id', '>', 0);
        $members = $query->whereIn('id', $ids)->get()->all();
        if (count($members) !== count($ids)) {
            return $this->fail(msg: $onlyTrashed ? '部分会员不存在或不在回收站' : '部分会员不存在或已在回收站', code: 404);
        }
        return $members;
    }

    private function payload(?Member $member = null): array
    {
        return $this->normalizePayload([
            'username' => $this->request->post('username', $member?->username ?? ''),
            'mobile' => $this->request->post('mobile', $member?->mobile ?? ''),
            'email' => $this->request->post('email', $member?->email ?? ''),
            'sex' => $this->request->post('sex', $member?->sex ?? 0),
            'groupIds' => $this->request->post('groupIds', $member ? $this->memberGroupIds((int) $member->id) : []),
            'tagIds' => $this->request->post('tagIds', $member ? $this->memberTagIds((int) $member->id) : []),
            'levelId' => $this->request->post('levelId', $member?->level_id ?? 0),
            'avatar' => $this->request->post('avatar', $member?->avatar ?? ''),
            'status' => $this->request->post('status', $member?->status ?? 1),
        ]);
    }

    private function normalizePayload(array $row): array
    {
        $groupIds = $row['groupIds'] ?? $row['group_ids'] ?? $row['groupId'] ?? $row['group_id'] ?? [];
        $groupIds = $this->normalizeIds($groupIds);
        $tagIds = $this->normalizeIds($row['tagIds'] ?? $row['tag_ids'] ?? []);

        return [
            'username' => trim((string) ($row['username'] ?? '')),
            'mobile' => ($mobile = trim((string) ($row['mobile'] ?? ''))) !== '' ? $mobile : null,
            'email' => ($email = trim((string) ($row['email'] ?? ''))) !== '' ? $email : null,
            'sex' => (string) ($row['sex'] ?? '0'),
            'groupIds' => $groupIds,
            'tagIds' => $tagIds,
            'level_id' => (int) ($row['levelId'] ?? $row['level_id'] ?? 0),
            'avatar' => trim((string) ($row['avatar'] ?? '')),
            'status' => $this->binaryStatus($row['status'] ?? 1),
        ];
    }

    private function validatePayload(array $data): ?string
    {
        $usernameLength = function_exists('mb_strlen') ? mb_strlen($data['username']) : strlen($data['username']);
        if ($usernameLength < 2 || $usernameLength > 80) {
            return '用户名长度必须为 2 至 80 个字符';
        }
        if (preg_match('/[\x00-\x1F\x7F<>]/u', $data['username'])) {
            return '用户名包含非法字符';
        }
        if ($data['mobile'] === null || !preg_match('/^[0-9+\- ]{6,20}$/', $data['mobile'])) {
            return '请输入 6 至 20 位有效手机号';
        }
        if ($data['email'] !== null && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 60)) {
            return '邮箱格式不正确或超过 60 个字符';
        }
        if (!in_array($data['sex'], ['0', '1', '2'], true)) {
            return '性别参数无效';
        }
        if (!$data['groupIds'] || count($data['groupIds']) > 32) {
            return '请选择有效会员组，且最多选择 32 个会员组';
        }
        if (MemberGroup::whereIn('id', $data['groupIds'])->where('status', 1)->count() !== count($data['groupIds'])) {
            return '会员组不存在、已删除或已停用';
        }
        if (count($data['tagIds']) > 32 || ($data['tagIds'] && MemberTag::whereIn('id', $data['tagIds'])->where('status', 1)->count() !== count($data['tagIds']))) {
            return '会员标签不存在、已删除或已停用，且最多选择 32 个标签';
        }
        if ($data['level_id'] <= 0 || !MemberLevel::where('id', $data['level_id'])->where('status', 1)->first()) {
            return '会员等级不存在、已删除或已停用';
        }
        if (strlen($data['avatar']) > 255) {
            return '头像地址不能超过 255 个字符';
        }
        return null;
    }

    private function validateUnique(array $data, int $excludeId = 0): ?string
    {
        $usernameQuery = Member::withTrashed()->where('username', $data['username']);
        $mobileQuery = $data['mobile'] !== null ? Member::withTrashed()->where('mobile', $data['mobile']) : null;
        $emailQuery = $data['email'] !== null ? Member::withTrashed()->where('email', $data['email']) : null;
        if ($excludeId > 0) {
            $usernameQuery->where('id', '<>', $excludeId);
            $mobileQuery?->where('id', '<>', $excludeId);
            $emailQuery?->where('id', '<>', $excludeId);
        }
        if ($usernameQuery->first()) {
            return '用户名已存在';
        }
        if ($mobileQuery?->first()) {
            return '手机号已存在';
        }
        if ($emailQuery?->first()) {
            return '邮箱已存在';
        }
        return null;
    }

    private function duplicateError(\Throwable $exception): ?string
    {
        return Member::duplicateError($exception);
    }

    private function relationMaps(array $members): array
    {
        return Member::relationMaps($members);
    }

    private function memberGroupIds(int $memberId): array
    {
        return Member::groupIdsFor($memberId);
    }

    private function memberTagIds(int $memberId): array
    {
        return Member::tagIdsFor($memberId);
    }

    private function memberData(Member $member, array $memberGroups, array $groups, array $levels, array $memberTags, array $tags): array
    {
        $groupIds = $memberGroups[(int) $member->id] ?? [];
        $tagIds = $memberTags[(int) $member->id] ?? [];
        return [
            'id' => (int) $member->id,
            'username' => (string) $member->username,
            'mobile' => (string) $member->mobile,
            'email' => (string) $member->email,
            'sex' => (string) $member->sex,
            'groupIds' => $groupIds,
            'groupNames' => array_values(array_map(static fn (int $id): string => (string) ($groups[$id] ?? ('#' . $id)), $groupIds)),
            'tagIds' => $tagIds,
            'tagNames' => array_values(array_map(static fn (int $id): string => (string) ($tags[$id] ?? ('#' . $id)), $tagIds)),
            'levelId' => (int) $member->level_id,
            'levelName' => (string) ($levels[(int) $member->level_id] ?? ''),
            'avatar' => (string) ($member->avatar ?? ''),
            'status' => (int) $member->status,
            'loginCount' => (int) $member->login_num,
            'lastLoginAt' => $this->formatTime($member->last_login),
            'lastLoginIp' => (string) $member->last_ip,
            'createdAt' => $this->formatTime($member->created_at),
            'updatedAt' => $this->formatTime($member->updated_at),
            'deletedAt' => $this->formatTime($member->deleted_at),
        ];
    }
}
