<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\common\model\Languages;
use think\Response;
use think\facade\Cache;

/**
 * Admin Web 多语言注册管理。
 */
class SystemLanguage extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = Languages::order('is_default', 'desc')->order('id', 'asc');
        $name = trim((string) $this->request->get('name', ''));
        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }
        $result = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);

        return $this->ok($this->paginationData(
            array_map(fn (Languages $language): array => $this->languageData($language), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    public function detail(int $id): Response
    {
        $language = Languages::find($id);
        return $language
            ? $this->ok($this->languageData($language))
            : $this->fail('语言不存在', 404);
    }

    public function create(): Response
    {
        $name = $this->name();
        if ($error = $this->validateName($name)) {
            return $this->fail($error, 422);
        }
        if (Languages::withTrashed()->where('name', $name)->find()) {
            return $this->fail('语言名称已存在', 422);
        }

        $language = Languages::create([
            'name' => $name,
            'is_default' => 0,
            'status' => 1,
        ]);
        Cache::clear();
        return $this->ok($this->languageData($language), '创建成功');
    }

    public function update(int $id): Response
    {
        $language = Languages::find($id);
        if (!$language) {
            return $this->fail('语言不存在', 404);
        }
        if ($this->isDefault($language)) {
            return $this->fail('默认语言不能重命名', 422);
        }

        $name = $this->name((string) $language->name);
        if ($error = $this->validateName($name)) {
            return $this->fail($error, 422);
        }
        if (Languages::withTrashed()->where('name', $name)->where('id', '<>', $id)->find()) {
            return $this->fail('语言名称已存在', 422);
        }

        $language->save(['name' => $name]);
        Cache::clear();
        return $this->ok($this->languageData($language), '保存成功');
    }

    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail('请选择要删除的语言', 422);
        }

        $languages = Languages::withTrashed()->whereIn('id', $ids)->select();
        if (count($languages) !== count($ids)) {
            return $this->fail('部分语言不存在', 404);
        }
        foreach ($languages as $language) {
            if ($this->isDefault($language)) {
                return $this->fail('默认语言不能删除', 422);
            }
        }
        foreach ($languages as $language) {
            $language->force()->delete();
        }
        Cache::clear();
        return $this->ok(['removed' => count($languages)], '删除成功');
    }

    private function name(string $default = ''): string
    {
        return trim((string) $this->request->post('name', $default));
    }

    private function validateName(string $name): ?string
    {
        $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($name === '') {
            return '语言名称不能为空';
        }
        if ($length > 20) {
            return '语言名称不能超过 20 个字符';
        }
        return null;
    }

    private function isDefault(Languages $language): bool
    {
        return (int) $language->is_default === 1 || strtolower((string) $language->name) === 'zh-cn';
    }

    private function languageData(Languages $language): array
    {
        return [
            'id' => (int) $language->id,
            'name' => (string) $language->name,
            'isDefault' => (int) $language->is_default,
            'status' => (int) $language->status,
            'createdAt' => $this->formatTime($language->create_time),
            'updatedAt' => $this->formatTime($language->update_time),
        ];
    }
}
