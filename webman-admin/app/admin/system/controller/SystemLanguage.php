<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use app\admin\controller\base\AdminApiController;
use app\common\model\Language;
use app\common\model\LanguageLine;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Cache;
use support\Db;
use support\Response;

/**
 * Admin Web 多语言注册管理。
 */
#[RouteGroup('/admin/system/language')]
class SystemLanguage extends AdminApiController
{

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

    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        $language = Language::find($id);
        return $language
            ? $this->ok(data: $language->toApiData())
            : $this->fail(msg: '语言不存在', code: 404);
    }

    #[Post('')]
    public function create(): Response
    {
        $name = $this->name();
        if ($error = Language::validateAttributes($name)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Language::withTrashed()->where('name', $name)->first()) {
            return $this->fail(msg: '语言名称已存在', code: 422);
        }

        $language = Language::forceCreate([
            'name' => $name,
            'is_default' => 0,
            'status' => 1,
        ]);
        Cache::clear();
        return $this->ok('创建成功', $language->toApiData());
    }

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
        if (Language::withTrashed()->where('name', $name)->where('id', '<>', $id)->first()) {
            return $this->fail(msg: '语言名称已存在', code: 422);
        }

        Db::transaction(static function () use ($language, $name): void {
            $previous = (string) $language->getAttribute('name');
            LanguageLine::withTrashed()->where('locale', $previous)->update(['locale' => $name]);
            $language->forceFill(['name' => $name])->save();
        });
        Cache::clear();
        return $this->ok('保存成功', $language->toApiData());
    }

    #[Delete('/{id:\d+}')]
    public function deleteById(int $id): Response
    {
        return $this->delete($id);
    }

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

        $languages = Language::withTrashed()->whereIn('id', $ids)->get();
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
                LanguageLine::withTrashed()->where('locale', (string) $language->getAttribute('name'))->forceDelete();
                $language->forceDelete();
            }
        });
        Cache::clear();
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
        $line = LanguageLine::withTrashed()->where('locale', $locale)->where('key', $key)->first();
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
