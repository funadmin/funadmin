<?php

declare(strict_types=1);

namespace app\cms\service;

use app\cms\model\{Form,FormEvent,FormFile,FormSubmission};
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use support\Db;
use Throwable;

/**
 * 访客提交与后台跟进：提交（防刷 → 校验 → 落库与附件）、列表、详情、备注、状态流转、批量无效、删除、附件定位。
 * 所有查询都限定站点。文件字段不写入 `values`，附件以 `cms_form_file.field` 关联到字段。
 */
final class FormSubmissionService
{
    public const STATUSES = ['new', 'processing', 'done', 'invalid'];
    /** submit 在 FormValueValidator::ERRORS 之外可能产生的错误键。 */
    public const ERRORS = ['token_invalid', 'too_fast', 'token_expired', 'too_many_requests', 'captcha_invalid', 'invalid_file_type', 'file_too_large', 'files_too_large'];
    public const MAX_TOTAL_UPLOAD = 50 * 1024 * 1024;
    public const PAGE_SIZE = 20;
    /** 同一来源对同一表单提交完全相同的内容，在该秒数内只保存一次并照常返回成功（双击、刷新重发、网络重试）。 */
    public const DUPLICATE_WINDOW = 600;
    /** 参与关键词搜索与列表摘要的文本类字段。 */
    private const TEXT_TYPES = ['text', 'textarea', 'email', 'phone', 'hidden'];
    private const SEARCH_LIMIT = 20000;
    private const NOTE_LIMIT = 2000;
    private const BATCH_LIMIT = 1000;
    /** 导出用固定文字：表头前四列、状态名、同意框的是/否。键为导出语言。 */
    private const EXPORT_LABELS = [
        'zh-cn' => ['columns' => ['提交时间', '表单', '状态', '来源页面'], 'statuses' => ['new' => '新提交', 'processing' => '跟进中', 'done' => '已完成', 'invalid' => '无效'], 'yes' => '是', 'no' => '否'],
        'en-us' => ['columns' => ['Submitted at', 'Form', 'Status', 'Source page'], 'statuses' => ['new' => 'New', 'processing' => 'In progress', 'done' => 'Done', 'invalid' => 'Invalid'], 'yes' => 'Yes', 'no' => 'No'],
    ];

    private readonly FormService $forms;
    private readonly FormFileStorage $storage;

    public function __construct(?FormService $forms = null, ?FormFileStorage $storage = null, private ?FormGuard $guard = null, private ?FormCaptcha $captcha = null)
    {
        $this->forms = $forms ?? new FormService();
        $this->storage = $storage ?? new FormFileStorage();
    }

    /**
     * @param array<string,list<array{tmp:string,name:string,size:int}>> $uploads 字段名 → 上传文件
     * @param array{ip?:string,user_agent?:string,source_url?:string,member_id?:?string,token?:string,captcha_id?:string,captcha?:string} $meta
     * @return array{status:string,message:string}
     * @throws FormValidationException 校验未通过；表单不存在或已停用时抛 404 InvalidArgumentException
     */
    public function submit(array $site, string $locale, string $code, array $input, array $uploads, array $meta): array
    {
        $siteId = Input::id($site['id'] ?? '');
        try {
            $locale = Input::locale($locale);
        } catch (InvalidArgumentException) {
            $locale = Input::locale($site['default_locale'] ?? '');
        }
        $form = $this->forms->resolve($siteId, $code, $locale) ?? throw new InvalidArgumentException('表单不存在', 404);
        $ok = ['status' => 'ok', 'message' => $form['settings']['success_message']];
        $guard = $this->guard ??= new FormGuard();
        if ($guard->isHoneypot($input)) {
            \support\Log::warning('cms.form.rejected', ['reason' => 'honeypot', 'site_id' => $siteId, 'form' => $form['code'] ?? $code, 'source' => FormGuard::bucket(self::meta($meta, 'ip'))]);
            return $ok;
        }
        $ip = mb_substr(self::meta($meta, 'ip'), 0, 45);
        try {
            $guard->verify(self::meta($meta, 'token'), $siteId, $form['id']);
            $guard->throttle($siteId, $form['id'], $ip, $form['settings']['rate_limit']);
        } catch (InvalidArgumentException $e) {
            throw new FormValidationException(['_form' => $e->getMessage()], $e->getCode());
        }
        if ($form['settings']['captcha'] && !($this->captcha ??= new FormCaptcha())->verify(self::meta($meta, 'captcha_id'), self::meta($meta, 'captcha'))) {
            throw new FormValidationException(['captcha' => 'captcha_invalid']);
        }
        $fields = array_values(array_filter($form['fields'], static fn(array $field): bool => in_array($field['type'], FormFieldCatalog::DATA_TYPES, true)));
        $files = [];
        foreach ($fields as $field) {
            if (!in_array($field['type'], FormFieldCatalog::FILE_TYPES, true)) continue;
            $list = $uploads[$field['name']] ?? [];
            $files[$field['name']] = is_array($list) ? array_map(static fn(mixed $item): array => is_array($item) ? $item : [], array_values($list)) : [[]];
        }
        ['values' => $values, 'errors' => $errors] = FormValueValidator::validate($fields, $input, array_map('count', $files));
        [$inspected, $fileErrors, $total] = $this->inspect($fields, $files, $errors);
        $errors = array_replace(array_intersect_key(array_fill_keys(array_column($fields, 'name'), ''), $errors + $fileErrors), $errors + $fileErrors);
        if ($total > self::MAX_TOTAL_UPLOAD) $errors['_form'] = 'files_too_large';
        if ($errors) throw new FormValidationException($errors);

        $search = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], self::TEXT_TYPES, true) && isset($values[$field['name']])) $search[] = (string)$values[$field['name']];
        }
        $memberId = $meta['member_id'] ?? null;
        $memberId = $memberId === null || $memberId === '' ? null : Input::id($memberId);
        $fingerprint = self::fingerprint($siteId, $form['id'], $memberId, $ip, $values, $inspected);
        $row = [
            'site_id' => $siteId,
            'form_id' => $form['id'],
            'locale' => $locale,
            'fields' => Input::json($fields),
            'values' => $values === [] ? '{}' : Input::json($values),
            'search_text' => mb_substr(implode("\n", $search), 0, self::SEARCH_LIMIT),
            'status' => 'new',
            'source_url' => mb_substr(self::meta($meta, 'source_url'), 0, 1024),
            'ip' => $ip,
            'user_agent' => mb_substr(self::meta($meta, 'user_agent'), 0, 255),
            'member_id' => $memberId,
            'fingerprint' => $fingerprint,
            'lock_version' => 0,
            'handled_at' => null,
        ];
        $stored = [];
        try {
            Db::transaction(function () use ($siteId, $form, $row, $inspected, &$stored): void {
                // 与 FormService::delete 抢同一行锁，避免删除表单与新提交交错留下孤儿提交。
                $current = Form::query()->where('site_id', $siteId)->where('id', $form['id'])->lockForUpdate()->first();
                if (!$current || (int)$current->status !== 1) throw new InvalidArgumentException('表单不存在', 404);
                $now = Input::now();
                // 已持有表单行锁，同表单的并发提交在此串行，查重与写入之间不会插入同指纹记录。
                if (FormSubmission::query()->where('site_id', $siteId)->where('form_id', $form['id'])->where('fingerprint', $row['fingerprint'])
                    ->where('created_at', '>=', gmdate('Y-m-d H:i:s', time() - self::DUPLICATE_WINDOW))->exists()) return;
                $submission = new FormSubmission();
                $submission->fill($row + ['created_at' => $now, 'updated_at' => $now])->save();
                $id = (string)$submission->getKey();
                $this->event($siteId, $id, 'created', null, $now);
                foreach ($inspected as [$name, $file]) {
                    $saved = $this->storage->store($siteId, $file);
                    $stored[] = $saved['path'];
                    (new FormFile())->fill([
                        'site_id' => $siteId, 'submission_id' => $id, 'field' => $name, 'original_name' => $file['name'], 'mime' => $file['mime'],
                        'size' => $file['size'], 'sha256' => $saved['sha256'], 'path' => $saved['path'], 'created_at' => $now,
                    ])->save();
                }
            });
        } catch (Throwable $e) {
            $this->unlink($siteId, $stored);
            throw $e;
        }
        return $ok;
    }

    /** @return array{list:list<array>,total:int,page:int,pageSize:int} */
    public function list(string $siteId, array $filters, int $page): array
    {
        $siteId = Input::id($siteId);
        $query = $this->filtered($siteId, $filters);
        $page = max(1, min(10000, $page));
        $total = (clone $query)->count();
        $forms = $this->formNames($siteId);
        $list = [];
        foreach ($query->orderByDesc('id')->offset(($page - 1) * self::PAGE_SIZE)->limit(self::PAGE_SIZE)->get() as $row) {
            $item = $this->format($row);
            $summary = [];
            foreach ($item['fields'] as $field) {
                $value = $item['values'][$field['name']] ?? null;
                if (count($summary) < 2 && in_array($field['type'], self::TEXT_TYPES, true) && $value !== null && $value !== '') $summary[] = ['label' => $field['label'], 'value' => (string)$value];
            }
            unset($item['fields'], $item['values']);
            $list[] = $item + ($forms[$item['form_id']] ?? ['form_code' => '', 'form_name' => '']) + ['summary' => $summary];
        }
        return ['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => self::PAGE_SIZE];
    }

    /**
     * 字段按提交时的快照排列；每个字段带原值 `value`（同意框为布尔）与 `text`（只含来自快照的文字：选项标签、文件原名，
     * 其余类型为原值文本，同意框为空串，由后台界面按语言翻译），文件字段另带 `files`。状态返回键名。
     */
    public function detail(string $siteId, string $id): array
    {
        $siteId = Input::id($siteId);
        $row = FormSubmission::query()->where('site_id', $siteId)->where('id', Input::id($id))->first();
        $item = $this->format($row ?? throw self::notFound());
        $files = array_map(static fn(object $file): array => [
            'id' => (string)$file->id, 'field' => $file->field, 'name' => $file->original_name, 'mime' => $file->mime, 'size' => (int)$file->size, 'created_at' => $file->created_at,
        ], FormFile::query()->where('site_id', $siteId)->where('submission_id', $item['id'])->orderBy('id')->get()->all());
        $byField = [];
        foreach ($files as $file) $byField[$file['field']][] = $file;
        $fields = [];
        foreach ($item['fields'] as $field) {
            $entry = ['name' => $field['name'], 'type' => $field['type'], 'label' => $field['label']];
            if (in_array($field['type'], FormFieldCatalog::FILE_TYPES, true)) {
                $entry += ['value' => null, 'files' => $byField[$field['name']] ?? []];
                $entry['text'] = implode(', ', array_column($entry['files'], 'name'));
            } else {
                $entry['value'] = $item['values'][$field['name']] ?? null;
                $entry['text'] = self::display($field, $entry['value']);
            }
            $fields[] = $entry;
        }
        $events = array_map(static fn(object $event): array => [
            'id' => (string)$event->id, 'type' => $event->type, 'admin_id' => $event->admin_id === null ? null : (string)$event->admin_id, 'body' => $event->body,
            'from_status' => $event->from_status, 'to_status' => $event->to_status, 'created_at' => $event->created_at,
        ], FormEvent::query()->where('site_id', $siteId)->where('submission_id', $item['id'])->orderBy('id')->get()->all());
        unset($item['values']);
        return ['fields' => $fields, 'files' => $files, 'events' => $events] + $item + ($this->formNames($siteId)[$item['form_id']] ?? ['form_code' => '', 'form_name' => '']);
    }

    /** 备注只增不改，不改变乐观锁版本。 */
    public function note(string $siteId, string $id, string $adminId, string $body): array
    {
        $siteId = Input::id($siteId);
        $adminId = Input::id($adminId);
        $body = FormValueValidator::clean($body, true);
        if ($body === null || $body === '' || mb_strlen($body) > self::NOTE_LIMIT) throw new InvalidArgumentException('备注须为 1–' . self::NOTE_LIMIT . ' 字', 422);
        $id = Db::transaction(function () use ($siteId, $id, $adminId, $body): string {
            $row = $this->locked($siteId, $id);
            $now = Input::now();
            $this->event($siteId, $row['id'], 'note', $adminId, $now, $body);
            FormSubmission::query()->where('site_id', $siteId)->where('id', $row['id'])->update(['updated_at' => $now]);
            return $row['id'];
        });
        return $this->detail($siteId, $id);
    }

    public function transition(string $siteId, string $id, string $adminId, string $status, int $version): array
    {
        $siteId = Input::id($siteId);
        $adminId = Input::id($adminId);
        if (!in_array($status, self::STATUSES, true)) throw new InvalidArgumentException('提交状态无效', 422);
        $id = Db::transaction(function () use ($siteId, $id, $adminId, $status, $version): string {
            $row = $this->locked($siteId, $id);
            if ($row['lock_version'] !== $version) throw new InvalidArgumentException('提交已被他人处理，请刷新后重试', 409);
            if ($row['status'] === $status) throw new InvalidArgumentException('提交已是该状态', 422);
            $this->changeStatus($siteId, $row, $adminId, $status);
            return $row['id'];
        });
        return $this->detail($siteId, $id);
    }

    /** 批量标记为无效；跳过已无效与不属于本站的提交，返回实际变更数。 */
    public function invalidate(string $siteId, array $ids, string $adminId): int
    {
        $siteId = Input::id($siteId);
        $adminId = Input::id($adminId);
        $ids = self::ids($ids);
        if ($ids === []) return 0;
        return Db::transaction(function () use ($siteId, $ids, $adminId): int {
            $rows = FormSubmission::query()->where('site_id', $siteId)->whereIn('id', $ids)->where('status', '<>', 'invalid')->orderBy('id')->lockForUpdate()->get(['id', 'status', 'lock_version']);
            foreach ($rows as $row) $this->changeStatus($siteId, ['id' => (string)$row->id, 'status' => $row->status, 'lock_version' => (int)$row->lock_version], $adminId, 'invalid');
            return count($rows);
        });
    }

    /** 删除提交及其附件记录与时间线；事务提交后再删磁盘文件（已不存在的文件忽略）。返回删除数。 */
    public function delete(string $siteId, array $ids): int
    {
        $siteId = Input::id($siteId);
        $ids = self::ids($ids);
        if ($ids === []) return 0;
        [$count, $paths] = Db::transaction(function () use ($siteId, $ids): array {
            $found = FormSubmission::query()->where('site_id', $siteId)->whereIn('id', $ids)->lockForUpdate()->pluck('id')->all();
            if ($found === []) return [0, []];
            $paths = FormFile::query()->where('site_id', $siteId)->whereIn('submission_id', $found)->pluck('path')->all();
            foreach (['cms_form_file', 'cms_form_event'] as $table) Db::table($table)->where('site_id', $siteId)->whereIn('submission_id', $found)->delete();
            FormSubmission::query()->where('site_id', $siteId)->whereIn('id', $found)->delete();
            return [count($found), $paths];
        });
        $this->unlink($siteId, $paths);
        return $count;
    }

    /**
     * 按筛选逐行产出 CSV 行（第一行为表头）。列：提交时间、表单、状态、来源页面 + 所有涉及快照合并的字段（按字段标识合并，
     * 标签取最新快照，较新快照的字段在前）。所有单元格做公式注入防护。筛选条件在调用时立即校验。
     * `$locale` 决定固定表头、状态名与是/否的语言：以 `en` 开头用英文，其余用中文。
     */
    public function export(string $siteId, array $filters, string $locale = 'zh-cn'): \Generator
    {
        $siteId = Input::id($siteId);
        $labels = self::EXPORT_LABELS[str_starts_with(strtolower($locale), 'en') ? 'en-us' : 'zh-cn'];
        return $this->rows($siteId, $this->filtered($siteId, $filters), $labels);
    }

    /** @return array{absolute:string,name:string,mime:string} */
    public function file(string $siteId, string $fileId): array
    {
        $siteId = Input::id($siteId);
        $row = FormFile::query()->where('site_id', $siteId)->where('id', Input::id($fileId))->first() ?? throw new InvalidArgumentException('附件不存在', 404);
        $absolute = $this->storage->absolute($siteId, $row->path);
        if (!is_file($absolute)) throw new InvalidArgumentException('附件不存在', 404);
        return ['absolute' => $absolute, 'name' => $row->original_name, 'mime' => $row->mime];
    }

    private function rows(string $siteId, Builder $query, array $labels): \Generator
    {
        $columns = [];
        foreach ($this->batches((clone $query)->select(['id', 'fields'])) as $batch) {
            foreach ($batch as $row) {
                foreach (self::decode($row->fields) as $field) {
                    if (!isset($columns[$field['name']]) && in_array($field['type'], FormFieldCatalog::DATA_TYPES, true)) $columns[$field['name']] = $field['label'];
                }
            }
        }
        yield array_map(self::cell(...), array_merge($labels['columns'], array_values($columns)));
        $forms = $this->formNames($siteId);
        foreach ($this->batches($query) as $batch) {
            $names = [];
            foreach (Db::table('cms_form_file')->where('site_id', $siteId)->whereIn('submission_id', array_column($batch, 'id'))->orderBy('id')->get(['submission_id', 'field', 'original_name']) as $file) {
                $names[(string)$file->submission_id][$file->field][] = $file->original_name;
            }
            foreach ($batch as $row) {
                $item = $this->format($row);
                $fields = array_column($item['fields'], null, 'name');
                $line = [$item['created_at'], $forms[$item['form_id']]['form_name'] ?? '', $labels['statuses'][$item['status']] ?? $item['status'], $item['source_url']];
                foreach (array_keys($columns) as $name) {
                    $field = $fields[$name] ?? null;
                    if ($field === null) $line[] = '';
                    elseif (in_array($field['type'], FormFieldCatalog::FILE_TYPES, true)) $line[] = implode(', ', $names[$item['id']][$name] ?? []);
                    else $line[] = self::display($field, $item['values'][$name] ?? null, [$labels['yes'], $labels['no']]);
                }
                yield array_map(self::cell(...), $line);
            }
        }
    }

    /** 按 id 倒序分批读取，避免一次性加载全部提交。 */
    private function batches(Builder $query): \Generator
    {
        $last = null;
        do {
            $batch = (clone $query)->when($last !== null, static fn(Builder $q) => $q->where('id', '<', $last))->orderByDesc('id')->limit(500)->get()->all();
            if ($batch !== []) {
                $last = end($batch)->id;
                yield $batch;
            }
        } while (count($batch) === 500);
    }

    private function filtered(string $siteId, array $filters): Builder
    {
        $query = Db::table('cms_form_submission')->where('site_id', $siteId);
        if (($filters['form_id'] ?? '') !== '' && $filters['form_id'] !== null) $query->where('form_id', Input::id($filters['form_id']));
        $statuses = $filters['status'] ?? [];
        $statuses = is_array($statuses) ? array_values($statuses) : [$statuses];
        $statuses = array_values(array_filter($statuses, static fn(mixed $status): bool => $status !== '' && $status !== null));
        if ($statuses === []) $statuses = ['new', 'processing'];
        foreach ($statuses as $status) {
            if (!in_array($status, self::STATUSES, true)) throw new InvalidArgumentException('提交状态无效', 422);
        }
        $query->whereIn('status', array_values(array_unique($statuses)));
        foreach (['from' => false, 'to' => true] as $key => $end) {
            if (($bound = self::date($filters[$key] ?? null, $end)) !== null) $query->where('created_at', $bound[0], $bound[1]);
        }
        $keyword = FormValueValidator::clean(is_scalar($filters['keyword'] ?? null) ? (string)$filters['keyword'] : '', false);
        if ($keyword !== null && $keyword !== '') {
            // 关键词按字面匹配：转义 LIKE 通配符。转义符用 `!`，MySQL 与 SQLite 对它的字面量写法一致（反斜杠在两者中不同）。
            $pattern = '%' . strtr(mb_substr($keyword, 0, 100), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $query->whereRaw("search_text LIKE ? ESCAPE '!'", [$pattern]);
        }
        return $query;
    }

    /** @return ?array{0:string,1:string} [比较符, 边界时间]；只写日期的截止条件包含当天全天。 */
    private static function date(mixed $value, bool $end): ?array
    {
        if ($value === null || $value === '') return null;
        if (is_string($value) && ($date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value)) && $date->format('Y-m-d') === $value) {
            return $end ? ['<', $date->modify('+1 day')->format('Y-m-d H:i:s')] : ['>=', $date->format('Y-m-d H:i:s')];
        }
        if (is_string($value) && ($time = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value)) && $time->format('Y-m-d H:i:s') === $value) return [$end ? '<=' : '>=', $value];
        throw new InvalidArgumentException('时间格式无效', 422);
    }

    /** 状态变更：写事件、版本 +1；done/invalid 记录最后处理时间。 */
    private function changeStatus(string $siteId, array $row, string $adminId, string $status): void
    {
        $now = Input::now();
        $changes = ['status' => $status, 'lock_version' => $row['lock_version'] + 1, 'updated_at' => $now];
        if (in_array($status, ['done', 'invalid'], true)) $changes['handled_at'] = $now;
        $changed = FormSubmission::query()->where('site_id', $siteId)->where('id', $row['id'])->where('lock_version', $row['lock_version'])->update($changes);
        if ($changed !== 1) throw new InvalidArgumentException('提交已被他人处理，请刷新后重试', 409);
        $this->event($siteId, $row['id'], 'status', $adminId, $now, '', $row['status'], $status);
    }

    private function event(string $siteId, string $submissionId, string $type, ?string $adminId, string $now, string $body = '', string $from = '', string $to = ''): void
    {
        (new FormEvent())->fill(['site_id' => $siteId, 'submission_id' => $submissionId, 'type' => $type, 'admin_id' => $adminId, 'body' => $body, 'from_status' => $from, 'to_status' => $to, 'created_at' => $now])->save();
    }

    private function locked(string $siteId, string $id): array
    {
        $row = FormSubmission::query()->where('site_id', $siteId)->where('id', Input::id($id))->lockForUpdate()->first(['id', 'status', 'lock_version']);
        if (!$row) throw self::notFound();
        return ['id' => (string)$row->id, 'status' => $row->status, 'lock_version' => (int)$row->lock_version];
    }

    /**
     * 校验通过字段的附件逐个 inspect；单个字段遇到第一个错误即停。
     * @return array{0:list<array{0:string,1:array}>,1:array<string,string>,2:int} [[字段名, inspect 结果], 字段错误, 合计字节]
     */
    private function inspect(array $fields, array $files, array $errors): array
    {
        $inspected = [];
        $fileErrors = [];
        $total = 0;
        foreach ($fields as $field) {
            $name = $field['name'];
            if (!isset($files[$name]) || isset($errors[$name])) continue;
            foreach ($files[$name] as $upload) {
                try {
                    $file = $this->storage->inspect($field, $upload);
                } catch (InvalidArgumentException $e) {
                    if ($e->getCode() !== 422) throw $e;
                    $fileErrors[$name] = $e->getMessage();
                    break;
                }
                $total += $file['size'];
                $inspected[] = [$name, $file];
            }
        }
        return [$inspected, $fileErrors, $total];
    }

    /**
     * 来源（会员优先，否则按限流同样的 IP 归并）+ 表单 + 规范化后的值 + 附件字段与内容哈希；文件名不参与，改名重传仍视为同一提交。
     * @param list<array{0:string,1:array{tmp:string}}> $inspected
     */
    private static function fingerprint(string $siteId, string $formId, ?string $memberId, string $ip, array $values, array $inspected): string
    {
        $files = array_map(static fn(array $item): array => [$item[0], (string)hash_file('sha256', $item[1]['tmp'])], $inspected);
        $source = $memberId !== null ? 'member:' . $memberId : 'ip:' . FormGuard::bucket($ip);
        return hash('sha256', json_encode([$siteId, $formId, $source, $values, $files], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @param list<string> $paths */
    private function unlink(string $siteId, array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->storage->delete($siteId, $path);
            } catch (Throwable) {
                // 清理尽力而为：数据库状态已确定，单个文件删不掉不应让整个操作失败。
            }
        }
    }

    /** @return array<string,array{form_code:string,form_name:string}> */
    private function formNames(string $siteId): array
    {
        $forms = [];
        foreach (Form::query()->where('site_id', $siteId)->get(['id', 'code', 'name']) as $form) $forms[(string)$form->id] = ['form_code' => $form->code, 'form_name' => $form->name];
        return $forms;
    }

    private function format(object $row): array
    {
        $row = Input::row($row);
        return [
            'id' => $row['id'], 'form_id' => $row['form_id'], 'locale' => $row['locale'], 'status' => $row['status'],
            'fields' => self::decode($row['fields']), 'values' => self::decode($row['values']),
            'source_url' => $row['source_url'], 'ip' => $row['ip'], 'user_agent' => $row['user_agent'], 'member_id' => $row['member_id'],
            'lock_version' => (int)$row['lock_version'], 'handled_at' => $row['handled_at'], 'created_at' => $row['created_at'],
        ];
    }

    /**
     * 展示文本：选项值显示为快照里的标签，多选用逗号连接。同意框只在给出 [是, 否] 文字时（导出）转成文字，否则为空串。
     * @param ?array{0:string,1:string} $yesNo
     */
    private static function display(array $field, mixed $value, ?array $yesNo = null): string
    {
        if ($value === null) return '';
        if ($field['type'] === 'consent') return $yesNo === null ? '' : ($value ? $yesNo[0] : $yesNo[1]);
        $labels = array_column($field['options'] ?? [], 'label', 'value');
        $items = array_map(static fn(mixed $item): string => (string)($labels[(string)$item] ?? $item), is_array($value) ? $value : [$value]);
        return implode(', ', $items);
    }

    /** CSV 公式注入防护：以 = + - @ 制表符或回车开头的单元格前加单引号。 */
    private static function cell(mixed $value): string
    {
        $value = (string)$value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    private static function decode(mixed $value): array
    {
        return is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : (array)$value;
    }

    /** @return list<string> */
    private static function ids(array $ids): array
    {
        if (count($ids) > self::BATCH_LIMIT) throw new InvalidArgumentException('一次最多处理 ' . self::BATCH_LIMIT . ' 条', 422);
        return array_values(array_unique(array_map(Input::id(...), array_values($ids))));
    }

    private static function meta(array $meta, string $key): string
    {
        $value = $meta[$key] ?? '';
        if (!is_scalar($value)) return '';
        return FormValueValidator::clean(mb_scrub((string)$value, 'UTF-8'), false) ?? '';
    }

    private static function notFound(): InvalidArgumentException
    {
        return new InvalidArgumentException('提交不存在', 404);
    }
}
