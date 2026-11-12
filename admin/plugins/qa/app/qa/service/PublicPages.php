<?php
declare(strict_types=1);
namespace app\qa\service;
use app\cms\service\{HtmlSanitizer,Seo,SiteConfiguration,SiteService};
use app\qa\model\QaQuestion;
use InvalidArgumentException;
use support\{Request,Response};
/**
 * 问答前台 SEO 页面（1.3.0）：服务端渲染的问题列表与问题详情，含 title、description、canonical 与 QAPage 结构化数据。
 *
 * - 站点按请求域名识别，与 CMS 前台相同（SiteService::byHost）：未知或停用的域名 404；别名域名 301 到主域名。
 * - 内容与问答前台接口同一口径（QuestionService::listing/detail）：只显示审核通过、问答侧与 CMS 侧都未删除的问题，
 *   以及审核通过、未隐藏、未删除的回答。问题不存在、未审核通过或已删除时 404。
 * - canonical 等绝对地址一律用站点主域名（https），不用请求的 Host。
 * - 公开路径与内部路径相同：/qa/questions（列表，?page=N 分页，非默认语言带 ?locale=）与 /qa/questions/{id}（详情）。
 * - 模板为 Blade（app/qa/view/public/*.blade.php），文本一律 {{ }} 转义；正文先经 CMS HtmlSanitizer 清洗再原样输出；
 *   JSON-LD 用 JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT 编码，</script> 不会提前结束脚本。
 */
final class PublicPages
{
    public const PATH = '/qa/questions';
    private const JSON = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    private const MAX_PAGE = 10000;
    private const LABELS = [
        'zh' => ['questions' => '问答', 'description' => '%s 的会员问答：浏览已审核通过的问题和回答。', 'page' => '第 %d 页', 'answers' => '%d 个回答', 'accepted' => '已采纳', 'resolved' => '已解决', 'closed' => '已关闭', 'open' => '待解答', 'attachments' => '附件', 'empty' => '还没有公开的问题', 'previous' => '上一页', 'next' => '下一页', 'back' => '返回问题列表', 'noAnswers' => '还没有公开的回答', 'member' => '会员', 'pagination' => '分页', 'attachmentHint' => '登录会员可在问答社区下载附件'],
        'en' => ['questions' => 'Questions', 'description' => 'Member questions and answers on %s.', 'page' => 'Page %d', 'answers' => '%d answers', 'accepted' => 'Accepted', 'resolved' => 'Resolved', 'closed' => 'Closed', 'open' => 'Open', 'attachments' => 'Attachments', 'empty' => 'No public questions yet', 'previous' => 'Previous', 'next' => 'Next', 'back' => 'Back to questions', 'noAnswers' => 'No public answers yet', 'member' => 'Member', 'pagination' => 'Pagination', 'attachmentHint' => 'Signed-in members can download attachments in the Q&A community'],
    ];

    public function handle(Request $request, ?string $id = null): Response
    {
        try {
            $response = $this->dispatch($request, $id);
        } catch (InvalidArgumentException $error) {
            $response = $this->error(in_array($error->getCode(), [400, 404], true) ? $error->getCode() : 503);
        } catch (\Throwable) {
            $response = $this->error(503);
        }
        if ($request->method() === 'HEAD') $response->withBody('');
        return $response;
    }

    private function dispatch(Request $request, ?string $id): Response
    {
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) return new Response(405, ['Allow' => 'GET, HEAD'], '');
        $site = (new SiteService())->byHost((string)$request->host());
        if (!$site) return $this->error(404);
        if ($site['domain']['host'] !== $site['primary_host']) {
            $query = $request->queryString();
            return new Response(301, ['Location' => 'https://' . $site['primary_host'] . $request->path() . ($query !== '' ? '?' . $query : '')], '');
        }
        return $id === null ? $this->listing($request, $site) : $this->detail($site, $id);
    }

    private function listing(Request $request, array $site): Response
    {
        $sites = new SiteService();
        $requested = $request->get('locale');
        if ($requested !== null && !is_string($requested)) return $this->error(404);
        $locale = $requested === null || $requested === '' ? (string)$site['default_locale'] : strtolower($requested);
        if (!in_array($locale, array_column($sites->locales($site['id'], true), 'locale'), true)) return $this->error(404);
        $raw = $request->get('page', '1');
        if (!is_string($raw) || !preg_match('/^[1-9][0-9]{0,4}$/D', $raw) || (int)$raw > self::MAX_PAGE) return $this->error(404);
        $page = (int)$raw;
        $data = (new QuestionService())->listing((string)$site['id'], $locale, '', $page);
        if ($page > 1 && $data['items'] === []) return $this->error(404);
        $last = max(1, (int)ceil($data['total'] / max(1, $data['limit'])));
        $labels = self::labels($locale);
        $name = $this->siteName($site, $locale);
        $origin = 'https://' . $site['primary_host'];
        $query = $locale === (string)$site['default_locale'] ? [] : ['locale' => $locale];
        $url = static fn (int $number): string => $origin . self::PATH . self::query($query + ($number > 1 ? ['page' => $number] : []));
        $items = array_map(static fn (array $item): array => [
            'url' => $origin . self::PATH . '/' . $item['id'],
            'title' => $item['title'],
            'excerpt' => self::excerpt($item['body_html'], 200),
            'author' => $item['author_name'],
            'created_at' => (string)$item['created_at'],
            'state' => $item['accepted_answer_id'] !== null ? $labels['resolved'] : ($item['closed'] ? $labels['closed'] : $labels['open']),
        ], $data['items']);
        $title = $labels['questions'] . ($page > 1 ? ' · ' . sprintf($labels['page'], $page) : '') . ' - ' . $name;
        return $this->page('public/list', [
            'locale' => $locale, 'labels' => $labels, 'siteName' => $name, 'title' => $title, 'heading' => $labels['questions'],
            'description' => sprintf($labels['description'], $name), 'canonical' => $url($page),
            'prev' => $page > 1 ? $url($page - 1) : null, 'next' => $page < $last ? $url($page + 1) : null,
            'items' => $items, 'page' => $page, 'last' => $last, 'total' => $data['total'], 'jsonLd' => null, 'ogType' => 'website',
        ]);
    }

    private function detail(array $site, string $id): Response
    {
        $state = QaQuestion::query()->where('id', $id)->where('site_id', $site['id'])->first();
        if (!$state) return $this->error(404);
        $locale = (string)$state->locale;
        $question = (new QuestionService())->detail((string)$site['id'], $locale, $id);
        $labels = self::labels($locale);
        $name = $this->siteName($site, $locale);
        $canonical = 'https://' . $site['primary_host'] . self::PATH . '/' . $question['id'];
        $answers = array_map(static fn (array $answer): array => $answer + ['body_safe' => HtmlSanitizer::clean($answer['body_html']), 'accepted' => $answer['id'] === $question['accepted_answer_id'], 'anchor' => 'answer-' . $answer['id']], $question['answers']);
        $description = self::excerpt($question['body_html'], 160);
        return $this->page('public/detail', [
            'locale' => $locale, 'labels' => $labels, 'siteName' => $name, 'title' => $question['title'] . ' - ' . $name, 'heading' => $question['title'],
            'description' => $description !== '' ? $description : $question['title'], 'canonical' => $canonical, 'prev' => null, 'next' => null,
            'question' => $question + ['body_safe' => HtmlSanitizer::clean($question['body_html'])], 'answers' => $answers,
            'state' => $question['accepted_answer_id'] !== null ? $labels['resolved'] : ($question['closed'] ? $labels['closed'] : $labels['open']),
            'listUrl' => 'https://' . $site['primary_host'] . self::PATH . self::query($locale === (string)$site['default_locale'] ? [] : ['locale' => $locale]),
            'jsonLd' => json_encode(self::structured($question, $canonical), self::JSON), 'ogType' => 'article',
        ]);
    }

    /**
     * schema.org QAPage：mainEntity 为 Question（name、text、answerCount、dateCreated、author）；已采纳的回答放 acceptedAnswer，
     * 其余公开回答放 suggestedAnswer。answerCount 为公开回答总数（与详情 answers_total 相同）。
     */
    public static function structured(array $question, string $canonical): array
    {
        $person = static fn (string $name): array => ['@type' => 'Person', 'name' => $name];
        $answer = static fn (array $item): array => array_filter(['@type' => 'Answer', 'text' => self::excerpt($item['body_html'], 5000) ?: '-', 'dateCreated' => Seo::time($item['created_at'] ?? null), 'url' => $canonical . '#answer-' . $item['id'], 'author' => $person($item['author_name'])], static fn ($value): bool => $value !== null);
        $entity = array_filter(['@type' => 'Question', 'name' => $question['title'], 'text' => self::excerpt($question['body_html'], 5000) ?: $question['title'], 'answerCount' => (int)$question['answers_total'], 'dateCreated' => Seo::time($question['created_at'] ?? null), 'author' => $person($question['author_name'])], static fn ($value): bool => $value !== null);
        $suggested = [];
        foreach ($question['answers'] as $item) {
            if ($question['accepted_answer_id'] !== null && $item['id'] === $question['accepted_answer_id']) $entity['acceptedAnswer'] = $answer($item);
            else $suggested[] = $answer($item);
        }
        if ($suggested !== []) $entity['suggestedAnswer'] = $suggested;
        return ['@context' => 'https://schema.org', '@type' => 'QAPage', 'mainEntity' => $entity];
    }

    /** 正文转纯文本摘要：去标签、解码实体、合并空白，按字符截断。 */
    public static function excerpt(string $html, int $length): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));
        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }

    private static function labels(string $locale): array
    {
        return self::LABELS[str_starts_with($locale, 'en') ? 'en' : 'zh'];
    }

    private static function query(array $values): string
    {
        return $values === [] ? '' : '?' . http_build_query($values, '', '&', PHP_QUERY_RFC3986);
    }

    /** 站点名称：网站配置的品牌名，没有时用语言名称（与 CMS 页面一致）。 */
    private function siteName(array $site, string $locale): string
    {
        $language = (new SiteService())->language((string)$site['id'], $locale);
        try {
            $brand = (string)((new SiteConfiguration())->resolve((string)$site['id'], $locale)['brand_name'] ?? '');
        } catch (\Throwable) {
            $brand = '';
        }
        return $brand !== '' ? $brand : (string)$language['name'];
    }

    private function page(string $template, array $variables): Response
    {
        $html = \app\common\view\Blade::render($template, $variables, 'qa', '');
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache', 'X-Content-Type-Options' => 'nosniff'], $html);
    }

    private function error(int $status): Response
    {
        $message = match ($status) { 404 => '页面不存在', 400 => '请求无效', default => '服务暂不可用' };
        return new Response($status, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store'], '<!doctype html><html lang="zh-cn"><meta charset="utf-8"><meta name="robots" content="noindex"><title>' . $status . '</title><h1>' . $status . '</h1><p>' . $message . '</p></html>');
    }
}
