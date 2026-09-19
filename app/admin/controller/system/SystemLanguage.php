<?php

declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckAdminApiRole;
use app\admin\middleware\SystemLog;
use app\common\model\Language;
use app\common\model\LanguageLine;
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
            array_map(fn (Language $language): array => $language->toApiData(), $result->items()),
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
        if (Language::withTrashed()->where('name', $name)->find()) {
            return $this->fail(msg: '语言名称已存在', code: 422);
        }

        $language = Language::create([
            'name' => $name,
            'is_default' => 0,
            'status' => 1,
        ]);
        Cache::clear();
        return $this->ok('创建成功', $language->toApiData());
    }

    #[Put(':id')]
    #[Pattern('id', '\\d+')]
    public function update(int $id): Response
    {
        $language = Language::find($id);
        if (!$language) {
            return $this->fail(msg: '语言不存在', code: 404);
        }
        if ($language->isDefaultLanguage()) {
            return $this->fail(msg: '默认语言不能重命名', code: 422);
        }

        $name = $this->name((string) $language->name);
        if ($error = Language::validateAttributes($name)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Language::withTrashed()->where('name', $name)->where('id', '<>', $id)->find()) {
            return $this->fail(msg: '语言名称已存在', code: 422);
        }

        $language->save(['name' => $name]);
        Cache::clear();
        return $this->ok('保存成功', $language->toApiData());
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
            if ($language->isDefaultLanguage()) {
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

    /**
     * 译文包：前端启动/切语言时拉取并合并覆盖静态语言包。
     */
    #[Get('pack')]
    #[Pattern('locale', '[a-z]{2}-[a-z]{2,4}')]
    public function pack(): Response
    {
        $locale = strtolower(trim((string) $this->request->get('locale', '')));
        if ($locale === '') {
            return $this->fail(msg: 'locale 不合法', code: 422);
        }
        $messages = [];
        $version = 0;
        foreach (LanguageLine::where('locale', $locale)->order('id', 'asc')->select() as $line) {
            $messages[(string) $line->key] = (string) $line->value;
            $version = max($version, (int) $line->id);
        }
        return $this->ok(data: ['locale' => $locale, 'version' => $version, 'messages' => (object) $messages]);
    }

    /**
     * 译文条目列表：按 locale 分页，支持 key/译文关键字搜索。
     */
    #[Get('lines')]
    public function lines(): Response
    {
        $locale = strtolower(trim((string) $this->request->get('locale', '')));
        if ($locale === '') {
            return $this->fail(msg: 'locale 不合法', code: 422);
        }
        $keyword = trim((string) $this->request->get('keyword', ''));
        $query = LanguageLine::where('locale', $locale);
        if ($keyword !== '') {
            $query->where(static function ($sub) use ($keyword): void {
                $sub->whereLike('key', '%' . $keyword . '%')->whereOr('value', 'like', '%' . $keyword . '%');
            });
        }
        $result = $query->order('id', 'desc')->paginate(['list_rows' => $this->pageSize(), 'page' => $this->page()]);
        return $this->ok(data: $this->paginationData(array_map(
            static fn ($line): array => [
                'id' => (int) $line->id,
                'locale' => (string) $line->locale,
                'key' => (string) $line->key,
                'value' => (string) $line->value,
                'updatedAt' => (string) ($line->updated_at ?? ''),
            ],
            $result->items()
        ), $result->total(), $this->page(), $this->pageSize()));
    }

    /**
     * 译文保存：locale+key 存在则覆盖，不存在则新增。
     */
    #[Post('lines')]
    public function saveLine(): Response
    {
        $locale = strtolower(trim((string) $this->request->post('locale', '')));
        $key = trim((string) $this->request->post('key', ''));
        $value = (string) $this->request->post('value', '');
        if ($locale === '' || $key === '' || mb_strlen($key) > 190) {
            return $this->fail(msg: 'locale/key 不合法', code: 422);
        }
        $line = LanguageLine::where('locale', $locale)->where('key', $key)->find();
        if ($line) {
            $line->value = $value;
            $line->save();
        } else {
            $line = LanguageLine::create(['locale' => $locale, 'key' => $key, 'value' => $value]);
        }
        return $this->ok('保存成功', ['id' => (int) $line->id]);
    }

    #[Delete('lines')]
    public function removeLine(): Response
    {
        $id = (int) $this->request->delete('id', 0);
        $line = LanguageLine::find($id);
        if (!$line) {
            return $this->fail(msg: '译文条目不存在', code: 404);
        }
        $line->delete();
        return $this->ok('删除成功', ['removed' => 1]);
    }
}
