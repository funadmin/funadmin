<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use app\cms\service\{FormService,FormSubmissionService,Input,SiteService};
use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
/**
 * 自定义表单后台接口：表单配置与提交跟进。每个接口先校验本站操作权限（403），再按站点查询，他站 ID 一律 404。
 * 附件下载与 CSV 导出直接返回文件响应，出错时仍返回统一 JSON。
 */
#[RouteGroup('/admin/plugins/cms/forms')]
final class Forms extends Base
{
    /** 只有这些图片类型在 preview=1 时内联显示，其余一律作为附件下载。 */
    private const PREVIEW_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
    private const FILTERS = ['form_id', 'status', 'from', 'to', 'keyword'];

    #[Get('')]
    public function index(): Response
    {
        return $this->action(fn(): array => (new FormService())->list($this->site('cms:form:list')));
    }
    #[Get('/summary')]
    public function summary(): Response
    {
        return $this->action(fn(): array => ['new_count' => (new FormService())->newCount($this->site('cms:form:list'))]);
    }
    #[Post('/save')]
    public function save(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:manage');
            $input = $this->input();
            if (isset($input['enabled'])) $input['enabled'] = self::bool($input['enabled']);
            return (new FormService())->save($site, $input);
        });
    }
    #[Post('/status')]
    public function status(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:manage');
            $input = $this->input();
            return (new FormService())->setStatus($site, Input::id($input['id'] ?? ''), self::bool($input['enabled'] ?? null), self::version($input));
        });
    }
    #[Post('/delete')]
    public function delete(): Response
    {
        return $this->action(function (): void {
            $site = $this->site('cms:form:manage');
            (new FormService())->delete($site, Input::id($this->input()['id'] ?? ''));
        });
    }
    #[Get('/submissions')]
    public function submissions(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:submissions');
            return (new FormSubmissionService())->list($site, $this->filters(), (int)($this->input()['page'] ?? 1));
        });
    }
    #[Get('/submissions/detail')]
    public function detail(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:submissions');
            return (new FormSubmissionService())->detail($site, Input::id($this->input()['id'] ?? ''));
        });
    }
    #[Get('/files/download')]
    public function file(): Response
    {
        return $this->raw(function (): Response {
            $site = $this->site('cms:form:submissions');
            $input = $this->input();
            $file = (new FormSubmissionService())->file($site, Input::id($input['id'] ?? ''));
            $mime = $file['mime'];
            $inline = ($input['preview'] ?? null) === '1' && in_array($mime, self::PREVIEW_MIMES, true);
            // 文本类型（含被误存为文本的 HTML）一律按二进制下载，浏览器不会渲染。
            if ($mime === '' || str_starts_with(strtolower($mime), 'text/')) $mime = 'application/octet-stream';
            return response('', 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => self::disposition($inline ? 'inline' : 'attachment', $file['name']),
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'Cache-Control' => 'private, no-store',
            ])->withFile($file['absolute']);
        });
    }
    #[Post('/submissions/note')]
    public function note(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:handle');
            $input = $this->input();
            return (new FormSubmissionService())->note($site, Input::id($input['id'] ?? ''), $this->actor(), self::string($input['body'] ?? ''));
        });
    }
    #[Post('/submissions/transition')]
    public function transition(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:handle');
            $input = $this->input();
            return (new FormSubmissionService())->transition($site, Input::id($input['id'] ?? ''), $this->actor(), self::string($input['status'] ?? ''), self::version($input));
        });
    }
    #[Post('/submissions/invalidate')]
    public function invalidate(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:handle');
            return ['count' => (new FormSubmissionService())->invalidate($site, $this->batchIds(), $this->actor())];
        });
    }
    /** 按当前筛选导出 CSV：UTF-8 BOM + 逐行 fputcsv。先在临时流中生成完整内容，再一次性返回。 */
    #[Get('/submissions/export')]
    public function export(): Response
    {
        return $this->raw(function (): Response {
            $site = $this->site('cms:form:export');
            $rows = (new FormSubmissionService())->export($site, $this->filters(), $this->locale());
            $code = (new SiteService())->get($site)['code'];
            $stream = fopen('php://temp/maxmemory:' . (8 * 1024 * 1024), 'w+b');
            try {
                fwrite($stream, "\xEF\xBB\xBF");
                foreach ($rows as $row) fputcsv($stream, $row, ',', '"', '');
                rewind($stream);
                $csv = (string)stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="forms-' . $code . '-' . date('Ymd') . '.csv"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        });
    }
    #[Post('/submissions/remove')]
    public function remove(): Response
    {
        return $this->action(function (): array {
            $site = $this->site('cms:form:delete');
            return ['count' => (new FormSubmissionService())->delete($site, $this->batchIds())];
        });
    }

    /** 返回非 JSON 响应的接口：成功时原样返回构造的响应，出错时沿用 action() 的 JSON 错误规则。 */
    private function raw(callable $build): Response
    {
        $response = null;
        $error = $this->action(function () use ($build, &$response): void {
            $response = $build();
        });
        return $response ?? $error;
    }
    private function filters(): array
    {
        return array_intersect_key($this->input(), array_flip(self::FILTERS));
    }
    /** @return list<mixed> */
    private function batchIds(): array
    {
        $ids = $this->input()['ids'] ?? [];
        if (!is_array($ids)) throw new \InvalidArgumentException('ids 必须为数组', 422);
        return array_values($ids);
    }
    private static function version(array $input): int
    {
        $version = filter_var($input['lock_version'] ?? null, FILTER_VALIDATE_INT);
        if ($version === false || $version < 0) throw new \InvalidArgumentException('lock_version 必须为非负整数', 422);
        return $version;
    }
    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (in_array($value, [1, '1', 'true'], true)) return true;
        if (in_array($value, [0, '0', 'false'], true)) return false;
        throw new \InvalidArgumentException('启用状态必须为布尔值', 422);
    }
    private static function string(mixed $value): string
    {
        if ($value !== null && !is_scalar($value)) throw new \InvalidArgumentException('文本格式无效', 422);
        return (string)$value;
    }
    /** RFC 6266/5987：ASCII 回退名（非 ASCII 字符替换为 `_`，去掉引号与反斜杠）+ UTF-8 原名。 */
    private static function disposition(string $type, string $name): string
    {
        $fallback = str_replace(['"', '\\'], '', (string)preg_replace('/[^\x20-\x7e]/u', '_', $name));
        if (trim($fallback, " ._") === '') $fallback = 'download';
        return $type . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }
}
