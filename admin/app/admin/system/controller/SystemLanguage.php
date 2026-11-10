<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\common\model\Language;
use app\common\model\LanguageLine;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Db;
use support\Response;

/**
 * Admin Web 多语言注册管理。
 */
#[RouteGroup('/admin/system/language')]
class SystemLanguage extends AdminController
{

    /**
     * 获取语言列表。
     *
     * 查询参数：name、page、pageSize。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/language",
     *     operationId="admin_system_controller_SystemLanguage_index_get",
     *     tags={"语言"},
     *     summary="获取语言列表。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="name", in="query", required=false, description="name 参数。", @OA\Schema(type="string")),
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
        $query = Language::orderBy('is_default', 'desc')->orderBy('id', 'asc');
        $name = trim((string) $this->request->get('name', ''));
        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }
        $result = $query->paginate($pageSize, ['*'], 'page', $page);

        return $this->ok(data: $this->paginationData(
            array_map(fn (Language $language): array => $language->toApiData(), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    /**
     * 获取指定语言的详情。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/language/{id}",
     *     operationId="admin_system_controller_SystemLanguage_detail_get",
     *     tags={"语言"},
     *     summary="获取指定语言的详情。",
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
    public function detail(int $id): Response
    {
        $language = Language::find($id);
        return $language
            ? $this->ok(data: $language->toApiData())
            : $this->fail(msg: '语言不存在', code: 404);
    }

    /**
     * 创建语言，校验提交字段后保存。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Post(
     *     path="/admin/system/language",
     *     operationId="admin_system_controller_SystemLanguage_create_post",
     *     tags={"语言"},
     *     summary="创建语言，校验提交字段后保存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", @OA\Property(property="name", type="string", description="name 参数。"))),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('')]
    public function create(): Response
    {
        $name = $this->name();
        if ($error = Language::validateAttributes($name)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Language::where('name', $name)->first()) {
            return $this->fail(msg: '语言名称已存在', code: 422);
        }

        $language = Language::forceCreate([
            'name' => $name,
            'is_default' => 0,
            'status' => 1,
        ]);
        return $this->ok('创建成功', $language->toApiData());
    }

    /**
     * 重命名语言并同步译文 locale，默认语言不允许重命名。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/language/{id}",
     *     operationId="admin_system_controller_SystemLanguage_update_put",
     *     tags={"语言"},
     *     summary="重命名语言并同步译文 locale，默认语言不允许重命名。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", @OA\Property(property="name", type="string", description="name 参数。"))),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        $language = Language::find($id);
        if (!$language) {
            return $this->fail(msg: '语言不存在', code: 404);
        }
        if ($language->isDefaultLanguage()) {
            return $this->fail(msg: '默认语言不能重命名', code: 422);
        }

        $name = $this->name((string) $language->getAttribute('name'));
        if ($error = Language::validateAttributes($name)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Language::where('name', $name)->where('id', '<>', $id)->first()) {
            return $this->fail(msg: '语言名称已存在', code: 422);
        }

        Db::transaction(static function () use ($language, $name): void {
            $previous = (string) $language->getAttribute('name');
            // 只迁移当前语言的有效译文；同名旧语言的墓碑永久保留在原 locale，不能被新语言复活。
            LanguageLine::where('locale', $previous)->update(['locale' => $name]);
            $language->forceFill(['name' => $name])->save();
        });
        return $this->ok('保存成功', $language->toApiData());
    }

    /**
     * 按 ID 删除单条语言。
     *
     * @param int $id 路由中的目标记录 ID。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/language/{id}",
     *     operationId="admin_system_controller_SystemLanguage_deleteById_delete",
     *     tags={"语言"},
     *     summary="按 ID 删除单条语言。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="路由中的目标记录 ID。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", @OA\Property(property="ids", type="array", description="目标记录 ID 集合。", @OA\Items(type="integer")))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/{id:\d+}')]
    public function deleteById(int $id): Response
    {
        return $this->delete($id);
    }

    /**
     * 删除语言及其译文，默认语言不允许删除。
     *
     * 请求字段：ids。
     *
     * @param int $id 路由中的目标记录 ID；默认值 0 时由请求中的 ID 集合确定目标。
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/language",
     *     operationId="admin_system_controller_SystemLanguage_delete_delete",
     *     tags={"语言"},
     *     summary="删除语言及其译文，默认语言不允许删除。",
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
    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的语言', code: 422);
        }

        $languages = Language::whereIn('id', $ids)->get();
        if (count($languages) !== count($ids)) {
            return $this->fail(msg: '部分语言不存在', code: 404);
        }
        foreach ($languages as $language) {
            if ($language->isDefaultLanguage()) {
                return $this->fail(msg: '默认语言不能删除', code: 422);
            }
        }
        Db::transaction(static function () use ($languages): void {
            foreach ($languages as $language) {
                // 软删除语言及其未删除译文（写 deleted_at 与 deleted_token），数据保留可追溯。
                LanguageLine::where('locale', (string) $language->getAttribute('name'))->delete();
                $language->delete();
            }
        });
        return $this->ok('删除成功', ['removed' => count($languages)]);
    }

    private function name(string $default = ''): string
    {
        $name = $this->request->post('name', $default);
        return is_string($name) ? strtolower(trim($name)) : '';
    }

    /**
     * 译文包：前端启动/切语言时拉取并合并覆盖静态语言包。
     * version 覆盖 INSERT/UPDATE/软删三种变化（同秒连续修改除外，见 packVersion 注释）；
     * 客户端带匹配 version 时返回 unchanged 空包，前端复用 localStorage 缓存。
     * ns 参数按命名空间等值过滤（idx_locale_ns），为将来按模块分包加载预留。
     *
     * @OA\Get(
     *     path="/admin/system/language/pack",
     *     operationId="admin_system_controller_SystemLanguage_pack_get",
     *     tags={"语言"},
     *     summary="译文包：前端启动/切语言时拉取并合并覆盖静态语言包。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="locale", in="query", required=true, description="locale 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="ns", in="query", required=false, description="ns 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="version", in="query", required=false, description="version 参数。", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/pack')]
    public function pack(): Response
    {
        $locale = $this->request->get('locale', '');
        if (!is_string($locale) || !preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})*$/Di', $locale)) {
            return $this->fail(msg: 'locale 不合法', code: 422);
        }
        $locale = strtolower($locale);
        $ns = trim((string) $this->request->get('ns', ''));
        if ($ns !== '' && (mb_strlen($ns) > 190 || preg_match('/\A[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)?\z/', $ns) !== 1)) {
            return $this->fail(msg: 'ns 不合法', code: 422);
        }
        $clientVersion = (int) $this->request->get('version', 0);
        $messages = [];
        foreach (LanguageLine::where('locale', $locale)->orderBy('key')->get() as $line) {
            if ($ns === '' || self::namespaceFor((string) $line->key) === $ns) {
                $messages[(string) $line->key] = (string) $line->value;
            }
        }
        $version = (int) hexdec(substr(hash('sha256', json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 0, 12));
        if ($clientVersion > 0 && $clientVersion === $version) {
            return $this->ok(data: ['locale' => $locale, 'version' => $version, 'unchanged' => true, 'messages' => (object) []]);
        }
        return $this->ok(data: ['locale' => $locale, 'version' => $version, 'messages' => (object) $messages]);
    }

    /**
     * 译文包版本：含软删行的 GREATEST(MAX id, MAX updated_at, MAX deleted_at)。
     * 物理行保留（软删）保证版本单调不回退；已知边界：同秒内的连续 UPDATE 版本号相同，
     * 后一次修改要等下一次任意写入才会被客户端感知（秒级时间戳分辨率所致）。
     */

    /**
     * 命名空间列表：语言管理页译文筛选下拉的数据源（ns 为生成列，去重返回）。
     *
     * @OA\Get(
     *     path="/admin/system/language/ns",
     *     operationId="admin_system_controller_SystemLanguage_namespaces_get",
     *     tags={"语言"},
     *     summary="命名空间列表：语言管理页译文筛选下拉的数据源（ns 为生成列，去重返回）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="locale", in="query", required=true, description="locale 参数。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/ns')]
    public function namespaces(): Response
    {
        $locale = $this->request->get('locale', '');
        if (!is_string($locale) || !preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})*$/Di', $locale)) {
            return $this->fail(msg: 'locale 不合法', code: 422);
        }
        $locale = strtolower($locale);
        $list = LanguageLine::where('locale', $locale)->pluck('key')->map(static fn (string $key): string => self::namespaceFor($key))->unique()->all();
        sort($list);
        return $this->ok(data: array_values($list));
    }

    /**
     * 译文条目列表：按 locale 分页，支持命名空间与 key/译文关键字过滤。
     *
     * @OA\Get(
     *     path="/admin/system/language/lines",
     *     operationId="admin_system_controller_SystemLanguage_lines_get",
     *     tags={"语言"},
     *     summary="译文条目列表：按 locale 分页，支持命名空间与 key/译文关键字过滤。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="locale", in="query", required=true, description="locale 参数。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="ns", in="query", required=false, description="ns 参数。", @OA\Schema(type="string")),
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
    #[Get('/lines')]
    public function lines(): Response
    {
        $locale = $this->request->get('locale', '');
        if (!is_string($locale) || !preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})*$/Di', $locale)) {
            return $this->fail(msg: 'locale 不合法', code: 422);
        }
        $locale = strtolower($locale);
        $keyword = trim((string) $this->request->get('keyword', ''));
        $ns = trim((string) $this->request->get('ns', ''));
        $query = LanguageLine::where('locale', $locale);
        if ($ns !== '') {
            $query->where(function ($scoped) use ($ns): void {
                                $scoped->where('key', $ns)->orWhere('key', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $ns) . '.%');
                            });
        }
        if ($keyword !== '') {
            $query->where(static function ($sub) use ($keyword): void {
                $sub->whereLike('key', '%' . $keyword . '%')->orWhere('value', 'like', '%' . $keyword . '%');
            });
        }
        $result = $query->orderBy('id', 'desc')->paginate($this->pageSize(), ['*'], 'page', $this->page());
        return $this->ok(data: $this->paginationData(array_map(
            static fn ($line): array => [
                'id' => (int) $line->id,
                'locale' => (string) $line->locale,
                'key' => (string) $line->key,
                'ns' => self::namespaceFor((string) $line->key),
                'value' => (string) $line->value,
                'updatedAt' => (string) ($line->updated_at ?? ''),
            ],
            $result->items()
        ), $result->total(), $this->page(), $this->pageSize()));
    }

    /**
     * 译文保存：locale+key 存在则覆盖，不存在则新增。
     *
     * @OA\Post(
     *     path="/admin/system/language/lines",
     *     operationId="admin_system_controller_SystemLanguage_saveLine_post",
     *     tags={"语言"},
     *     summary="译文保存：locale+key 存在则覆盖，不存在则新增。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="locale", type="string", description="locale 参数。"),
     *             @OA\Property(property="key", type="string", description="key 参数。"),
     *             @OA\Property(property="value", description="结构由具体业务、插件或已发布 Schema 决定；不推断未声明的字段。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/lines')]
    public function saveLine(): Response
    {
        $locale = $this->request->post('locale', '');
        $key = $this->request->post('key', '');
        $value = $this->request->post('value', '');
        if (!is_string($locale) || !is_string($key) || !is_string($value)) {
            return $this->fail(msg: '译文参数必须为字符串', code: 422);
        }
        $locale = strtolower(trim($locale));
        $key = trim($key);
        if (!preg_match('/^[a-z]{2}(?:-[a-z0-9]{2,8})*$/D', $locale) || !Language::where('name', $locale)->where('status', 1)->exists()
            || !preg_match('/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/D', $key) || mb_strlen($key) > 190
            || array_intersect(explode('.', $key), ['__proto__', 'constructor', 'prototype'])) {
            return $this->fail(msg: 'locale/key 不合法', code: 422);
        }
        // 优先取未删除行；同 key 可能有多条已删除行（删除后重建再删除），取最近一条复活。
        $line = LanguageLine::where('locale', $locale)->where('key', $key)->first()
            ?? LanguageLine::onlyTrashed()->where('locale', $locale)->where('key', $key)->orderByDesc('id')->first();
        if ($line) {
            // 软删行同 key 复活：恢复而非新增，避免唯一键冲突。
            if ($line->trashed()) {
                $line->restore();
            }
            $line->value = $value;
            $line->save();
        } else {
            $line = LanguageLine::forceCreate(['locale' => $locale, 'key' => $key, 'value' => $value]);
        }
        return $this->ok('保存成功', ['id' => (int) $line->id]);
    }

    private static function namespaceFor(string $key): string
    {
        $parts = explode('.', $key);
        return implode('.', array_slice($parts, 0, in_array($parts[0], ['crud', 'plugin'], true) ? 2 : 1));
    }

    /**
     * 删除请求中 id 指定的译文条目。
     *
     * 查询参数：id。
     * 请求字段：id。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Delete(
     *     path="/admin/system/language/lines",
     *     operationId="admin_system_controller_SystemLanguage_removeLine_delete",
     *     tags={"语言"},
     *     summary="删除请求中 id 指定的译文条目。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\Parameter(name="id", in="query", required=false, description="id 参数。", @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", @OA\Property(property="id", type="integer", description="id 参数。"))),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Delete('/lines')]
    public function removeLine(): Response
    {
        $id = (int) $this->request->post('id', $this->request->get('id', 0));
        $line = LanguageLine::find($id);
        if (!$line) {
            return $this->fail(msg: '译文条目不存在', code: 404);
        }
        $line->delete();
        return $this->ok('删除成功', ['removed' => 1]);
    }
}
