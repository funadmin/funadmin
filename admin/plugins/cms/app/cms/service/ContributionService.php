<?php

declare (strict_types=1);
namespace app\cms\service;

use InvalidArgumentException;
use support\Db;
final class ContributionService
{
    public const KINDS = ['content', 'question', 'answer'];
    public const STATUSES = ['pending', 'published', 'rejected', 'withdrawn', 'hidden'];
    public function submit(string $site, string $locale, string $member, string $kind, array $input, ?string $parent = null): array
    {
        $this->ready();
        $site = Input::id($site);
        $member = Input::id($member);
        $locale = Input::locale($locale);
        if (!in_array($kind, self::KINDS, true) || ($kind === 'answer') !== ($parent !== null)) {
            throw new InvalidArgumentException('投稿类型或父级无效', 422);
        }
        $parent = $parent === null ? null : Input::id($parent);
        $payload = $this->payload($kind, $input);
        $key = Input::text($input['request_key'] ?? '', 64);
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/D', $key)) {
            throw new InvalidArgumentException('request_key 必须为8至64位字母、数字、下划线或短横线', 422);
        }
        $hash = hash('sha256', Input::json($this->canonical(['kind' => $kind, 'locale' => $locale, 'parent_id' => $parent] + $payload)));
        return Db::transaction(function () use ($site, $locale, $member, $kind, $parent, $payload, $key, $hash): array {
            (new SiteService())->lock($site);
            $this->scope($site, $locale);
            $this->activeMember($member);
            $existing = Db::table('cms_member_submission')->where('site_id', $site)->where('member_id', $member)->where('request_key', $key)->first();
            if ($existing) {
                if (!hash_equals($existing->payload_hash, $hash)) {
                    throw new InvalidArgumentException('请求标识已用于其他投稿', 409);
                }
                return $this->normalize($existing);
            }
            $policy = (new ContributionPolicy())->read($site);
            if (!$policy['enabled']) {
                throw new InvalidArgumentException('本站未开放会员投稿', 403);
            }
            $data = $kind === 'content' ? $this->contentData($site, $payload, $policy) : [];
            if ($payload['body_html'] === '' && ($kind !== 'content' || !$this->hasFields($data['fields']))) {
                throw new InvalidArgumentException('正文不能为空', 422);
            }
            if ($kind === 'answer') {
                $this->parent($site, $locale, $parent);
            }
            $recent = Db::table('cms_member_submission')->where('site_id', $site)->where('member_id', $member)->where('created_at', '>=', gmdate('Y-m-d H:i:s', time() - 60))->count();
            if ($recent >= 10) {
                throw new InvalidArgumentException('投稿过于频繁，请稍后重试', 429);
            }
            $now = Input::now();
            $id = (string) Db::table('cms_member_submission')->insertGetId(['site_id' => $site, 'locale' => $locale, 'member_id' => $member, 'kind' => $kind, 'parent_id' => $parent, 'title' => $payload['title'], 'body_html' => $payload['body_html'], 'data' => Input::json($data), 'result' => Input::json([]), 'request_key' => $key, 'payload_hash' => $hash, 'status' => 'pending', 'lock_version' => 0, 'reviewer_admin_id' => null, 'review_reason' => '', 'reviewed_at' => null, 'created_at' => $now, 'updated_at' => $now]);
            return $this->get($site, $id);
        });
    }
    public function get(string $site, string $id): array
    {
        $this->ready();
        return $this->normalize(Db::table('cms_member_submission')->where('site_id', Input::id($site))->where('id', Input::id($id))->first() ?? throw new InvalidArgumentException('投稿不存在', 404));
    }
    public function mine(string $site, string $locale, string $member, int $page = 1): array
    {
        $this->ready();
        $this->scope($site, $locale);
        $this->activeMember($member);
        return $this->paginate(Db::table('cms_member_submission')->where('site_id', Input::id($site))->where('locale', Input::locale($locale))->where('member_id', Input::id($member)), $page);
    }
    public function listing(string $site, string $locale, string $kind = 'content', string $status = 'pending', int $page = 1): array
    {
        $this->ready();
        $this->scope($site, $locale);
        if (!in_array($kind, self::KINDS, true) || !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('投稿筛选无效', 422);
        }
        return $this->paginate(Db::table('cms_member_submission')->where('site_id', Input::id($site))->where('locale', Input::locale($locale))->where('kind', $kind)->where('status', $status), $page);
    }
    public function withdraw(string $site, string $member, string $id, int $version): array
    {
        $this->ready();
        $member = Input::id($member);
        return Db::transaction(function () use ($site, $member, $id, $version): array {
            (new SiteService())->lock($site);
            $this->activeMember($member);
            $row = $this->locked($site, $id);
            if ($row['member_id'] !== $member) {
                throw new InvalidArgumentException('只能撤回自己的投稿', 403);
            }
            if ($row['lock_version'] !== $version || !in_array($row['status'], ['pending', 'rejected'], true)) {
                throw new InvalidArgumentException('投稿状态已变化', 409);
            }
            $this->update($row, ['status' => 'withdrawn']);
            return $this->get($site, $id);
        });
    }
    public function review(string $site, string $actor, string $id, int $version, string $decision, string $reason = '', ?callable $publisher = null): array
    {
        $this->ready();
        $actor = Input::id($actor);
        return Db::transaction(function () use ($site, $actor, $id, $version, $decision, $reason, $publisher): array {
            (new SiteService())->lock($site);
            (new SiteAuthorization())->assert($site, $actor, 'cms:submission:review');
            $row = $this->locked($site, $id);
            if ($row['lock_version'] !== $version) {
                throw new InvalidArgumentException('投稿版本已变化', 409);
            }
            $target = match ($decision) {
                'publish' => 'published',
                'reject' => 'rejected',
                'hide' => 'hidden',
                default => throw new InvalidArgumentException('审核决定无效', 422),
            };
            $expected = $decision === 'hide' ? 'published' : 'pending';
            if ($row['status'] !== $expected) {
                throw new InvalidArgumentException('投稿状态不允许此操作', 409);
            }
            $reason = Input::text($reason, 1000);
            if ($decision !== 'publish' && $reason === '') {
                throw new InvalidArgumentException('请填写审核原因', 422);
            }
            $changes = ['status' => $target, 'reviewer_admin_id' => $actor, 'review_reason' => $reason, 'reviewed_at' => Input::now()];
            if ($decision === 'publish') {
                $this->scope($site, $row['locale']);
                if ($publisher !== null) {
                    $result = $publisher($row);
                    if (!is_array($result)) {
                        throw new \UnexpectedValueException('Publisher result must be an array');
                    }
                    $changes['result'] = Input::json($result);
                }
            }
            $this->update($row, $changes);
            return $this->get($site, $id);
        });
    }
    public function reviewContent(string $site, string $actor, string $id, int $version, string $decision, string $reason = ''): array
    {
        return Db::transaction(function () use ($site, $actor, $id, $version, $decision, $reason): array {
            (new SiteService())->lock($site);
            foreach (['cms:content:create', 'cms:content:publish'] as $operation) {
                (new SiteAuthorization())->assert($site, $actor, $operation);
            }
            $row = $this->get($site, $id);
            if ($row['kind'] !== 'content') {
                throw new InvalidArgumentException('不是内容投稿', 422);
            }
            $reviewed = $this->review($site, $actor, $id, $version, $decision, $reason, fn($submission) => $this->publishContent($submission, $actor));
            if ($decision === 'hide' && isset($row['result']['locale_id'])) {
                $content = new ContentService();
                $editor = $content->editor($site, $row['result']['locale_id']);
                $content->offline($site, $editor['id'], (int) $editor['lock_version']);
            }
            return $reviewed;
        });
    }
    private function payload(string $kind, array $input): array
    {
        $title = Input::text($input['title'] ?? '', 255);
        if ($kind !== 'answer' && $title === '') {
            throw new InvalidArgumentException('标题不能为空', 422);
        }
        $raw = Input::text($input['body_html'] ?? '', 40000);
        $body = HtmlSanitizer::clean($raw);
        if (trim(strip_tags($body)) === '' && !preg_match('/<(?:img|video|audio)\b/i', $body)) {
            $body = '';
        }
        $payload = ['title' => $title, 'body_html' => $body];
        if ($kind === 'content') {
            if (!is_array($input['data'] ?? [])) {
                throw new InvalidArgumentException('模型字段格式无效', 422);
            }
            $payload['model_code'] = Input::code($input['model_code'] ?? '');
            $payload['fields'] = $input['data'] ?? [];
        }
        return $payload;
    }
    private function contentData(string $site, array $payload, array $policy): array
    {
        if (!in_array($payload['model_code'], $policy['model_codes'], true)) {
            throw new InvalidArgumentException('本站未开放此投稿模型', 422);
        }
        $model = Db::table('cms_content_model')->where('code', $payload['model_code'])->where('status', 1)->first() ?? throw new InvalidArgumentException('投稿模型不可用', 422);
        $schema = Db::table('cms_model_version')->where('model_id', $model->id)->where('version', $model->current_version)->first() ?? throw new InvalidArgumentException('模型版本不存在', 422);
        return ['model_code' => $model->code, 'model_id' => (string) $model->id, 'model_version_id' => (string) $schema->id, 'fields' => (new ModelService())->validate((string) $schema->id, $payload['fields'])];
    }
    private function publishContent(array $row, string $actor): array
    {
        $data = $row['data'];
        $service = new ContentService();
        $draft = $service->save($row['site_id'], $row['locale'], ['model_id' => $data['model_id'], 'model_version_id' => $data['model_version_id'], 'title' => $row['title'], 'body_html' => $row['body_html'], 'data' => $data['fields'], 'desired_path' => '/community/articles/' . $row['id']], $actor);
        $published = $service->publish($row['site_id'], $draft['id'], $draft['draft_revision_id'], (int) $draft['lock_version']);
        return ['content_id' => $published['content_id'], 'locale_id' => $published['id'], 'revision_id' => $published['published_revision_id'], 'url' => '/community/articles/' . $row['id']];
    }
    private function ready(): void
    {
        if (!Db::schema()->hasTable('cms_member_submission')) {
            throw new InvalidArgumentException('请完成 CMS 会员投稿升级', 409);
        }
    }
    private function scope(string $site, string $locale): void
    {
        (new SiteService())->get($site, true);
        (new SiteService())->language($site, $locale);
    }
    private function activeMember(string $member): void
    {
        if (!Db::schema()->hasTable('member') || !Db::table('member')->where('id', Input::id($member))->where('status', 1)->whereNull('deleted_at')->exists()) {
            throw new InvalidArgumentException('会员未登录或已停用', 401);
        }
    }
    private function parent(string $site, string $locale, string $parent): void
    {
        $row = $this->get($site, $parent);
        if ($row['locale'] !== $locale || $row['kind'] !== 'question' || $row['status'] !== 'published') {
            throw new InvalidArgumentException('问题不可回答', 422);
        }
    }
    private function locked(string $site, string $id): array
    {
        return $this->normalize(Db::table('cms_member_submission')->where('site_id', Input::id($site))->where('id', Input::id($id))->lockForUpdate()->first() ?? throw new InvalidArgumentException('投稿不存在', 404));
    }
    private function update(array $row, array $changes): void
    {
        $changed = Db::table('cms_member_submission')->where('site_id', $row['site_id'])->where('id', $row['id'])->where('lock_version', $row['lock_version'])->update($changes + ['lock_version' => $row['lock_version'] + 1, 'updated_at' => Input::now()]);
        if ($changed !== 1) {
            throw new InvalidArgumentException('投稿版本已变化', 409);
        }
    }
    private function normalize(object|array $row): array
    {
        $row = (array) $row;
        foreach (['id', 'site_id', 'member_id', 'parent_id', 'reviewer_admin_id'] as $key) {
            if ($row[$key] !== null) {
                $row[$key] = (string) $row[$key];
            }
        }
        foreach (['data', 'result'] as $key) {
            if (is_string($row[$key])) {
                $row[$key] = json_decode($row[$key], true, 512, JSON_THROW_ON_ERROR);
            }
        }
        $row['lock_version'] = (int) $row['lock_version'];
        return $row;
    }
    private function paginate(\Illuminate\Database\Query\Builder $query, int $page): array
    {
        $page = max(1, min(10000, $page));
        $total = (clone $query)->count();
        $items = [];
        foreach ($query->orderByDesc('id')->offset(($page - 1) * 20)->limit(20)->get() as $row) {
            $items[] = $this->normalize($row);
        }
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => 20];
    }
    private function canonical(array $data): array
    {
        if (!array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->canonical($value);
            }
        }
        unset($value);
        return $data;
    }
    private function hasFields(array $fields): bool
    {
        foreach ($fields as $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                return true;
            }
        }
        return false;
    }
}
