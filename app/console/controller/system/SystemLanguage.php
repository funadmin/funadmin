<?php

declare(strict_types=1);

namespace app\console\controller\system;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\common\model\Language;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;
use think\facade\Cache;

/**
 * Admin Web 多语言注册管理。
 */
#[Group('system/language')]
class SystemLanguage extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = Language::order('is_default', 'desc')->order('id', 'asc');
        $name = trim((string) $this->request->get('name', ''));
        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }
        $result = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);

        return $this->ok(data: $this->paginationData(
            array_map(fn (Language $language): array => $this->languageData($language), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    #[Get(':id')]
    #[Pattern('id', '\\d+')]
    public function detail(int $id): Response
    {
        $language = Language::find($id);
        return $language
            ? $this->ok(data: $this->languageData($language))
            : $this->fail(msg: '语言不存在', code: 404);
    }

    #[Post('')]
    public function create(): Response
    {
        $name = $this->name();
        if ($error = $this->validateName($name)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Language::withTrashed()->where('name', $name)->find()) {
            return $this->fail(msg: '语言名称已存在', code: 422);
        }

        $language = Language::create([
            'name' => $name,
            'is_default' => 0,
            'status' => 1,
        ]);
        Cache::clear();
        return $this->ok('创建成功', $this->languageData($language));
    }

    #[Put(':id')]
    #[Pattern('id', '\\d+')]
    public function update(int $id): Response
    {
        $language = Language::find($id);
        if (!$language) {
            return $this->fail(msg: '语言不存在', code: 404);
        }
        if ($this->isDefault($language)) {
            return $this->fail(msg: '默认语言不能重命名', code: 422);
        }

        $name = $this->name((string) $language->name);
        if ($error = $this->validateName($name)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Language::withTrashed()->where('name', $name)->where('id', '<>', $id)->find()) {
            return $this->fail(msg: '语言名称已存在', code: 422);
        }

        $language->save(['name' => $name]);
        Cache::clear();
        return $this->ok('保存成功', $this->languageData($language));
    }

    #[Delete(':id')]
    #[Pattern('id', '\\d+')]
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

        $languages = Language::withTrashed()->whereIn('id', $ids)->select();
        if (count($languages) !== count($ids)) {
            return $this->fail(msg: '部分语言不存在', code: 404);
        }
        foreach ($languages as $language) {
            if ($this->isDefault($language)) {
                return $this->fail(msg: '默认语言不能删除', code: 422);
            }
        }
        foreach ($languages as $language) {
            $language->force()->delete();
        }
        Cache::clear();
        return $this->ok('删除成功', ['removed' => count($languages)]);
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

    private function isDefault(Language $language): bool
    {
        return (int) $language->is_default === 1 || strtolower((string) $language->name) === 'zh-cn';
    }

    private function languageData(Language $language): array
    {
        return [
            'id' => (int) $language->id,
            'name' => (string) $language->name,
            'isDefault' => (int) $language->is_default,
            'status' => (int) $language->status,
            'createdAt' => $this->formatTime($language->created_at),
            'updatedAt' => $this->formatTime($language->updated_at),
        ];
    }
}
