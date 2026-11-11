<?php

declare(strict_types=1);

namespace app\cms\service;

use app\cms\model\Form;
use app\cms\model\FormFile;
use app\cms\model\FormSubmission;
use app\cms\model\Site;
use app\cms\model\SiteLocale;
use InvalidArgumentException;
use support\Db;

/** 表单配置：增删改查、多语言文字（请求语言 → 站点默认语言 → 字段原值）与新提交计数。所有查询都限定站点。 */
final class FormService
{
    public const RATE_LIMIT_DEFAULT = 5;
    public const RATE_LIMIT_MAX = 60;
    private const FIELD_TEXTS = ['label' => 100, 'placeholder' => 255, 'help' => 500];

    public function list(string $siteId): array
    {
        $siteId = Input::id($siteId);
        $total = $this->counts($siteId, null);
        $fresh = $this->counts($siteId, 'new');
        $forms = [];
        foreach (Form::query()->where('site_id', $siteId)->orderBy('id')->get() as $row) {
            $form = $this->format($row);
            $form['submission_count'] = $total[$form['id']] ?? 0;
            $form['new_count'] = $fresh[$form['id']] ?? 0;
            $forms[] = $form;
        }
        return $forms;
    }

    public function get(string $siteId, string $formId): array
    {
        $row = Form::query()->where('site_id', Input::id($siteId))->where('id', Input::id($formId))->first();
        return $this->format($row ?? throw new InvalidArgumentException('表单不存在', 404));
    }

    public function save(string $siteId, array $input): array
    {
        $siteId = Input::id($siteId);
        $formId = empty($input['id']) ? null : Input::id($input['id']);
        $result = Db::transaction(function () use ($siteId, $formId, $input): array {
            (new SiteService())->lock($siteId);
            $old = $formId === null ? null : $this->locked($siteId, $formId);
            if ($old && (!array_key_exists('lock_version', $input) || (int)$input['lock_version'] !== $old['lock_version'])) {
                throw new InvalidArgumentException('表单已被修改，请刷新后重试', 409);
            }
            $code = Input::code($input['code'] ?? $old['code'] ?? '');
            $taken = Form::query()->where('site_id', $siteId)->where('code', $code)->when($old, fn($q) => $q->where('id', '<>', $old['id']))->exists();
            if ($taken) throw new InvalidArgumentException('表单调用代码已被使用', 409);
            $name = self::text($input['name'] ?? $old['name'] ?? '', 100);
            if ($name === '') throw new InvalidArgumentException('表单名称不能为空', 422);
            $enabled = $input['enabled'] ?? $old['enabled'] ?? true;
            if (!is_bool($enabled)) throw new InvalidArgumentException('启用状态必须为布尔值', 422);
            $fields = FormFieldCatalog::normalize($input['fields'] ?? $old['fields'] ?? []);
            $data = [
                'code' => $code,
                'name' => $name,
                'status' => $enabled ? 1 : 0,
                'fields' => Input::json($fields),
                'texts' => Input::json($this->texts($siteId, $input['texts'] ?? $old['texts'] ?? [], $fields)),
                'settings' => Input::json(self::settings($input['settings'] ?? $old['settings'] ?? [])),
                'updated_at' => Input::now(),
            ];
            if ($old) {
                Form::query()->where('site_id', $siteId)->where('id', $old['id'])->update($data + ['lock_version' => $old['lock_version'] + 1]);
                return $this->get($siteId, $old['id']);
            }
            $form = new Form();
            $form->fill($data + ['site_id' => $siteId, 'lock_version' => 0, 'created_at' => $data['updated_at']])->save();
            $id = (string)$form->getKey();
            return $this->get($siteId, $id);
        });
        // 事务提交后再刷新公开页缓存：附带该表单的页面需重新渲染。
        (new PublicPageCache())->invalidateSite($siteId);
        return $result;
    }

    public function setStatus(string $siteId, string $formId, bool $enabled, int $version): array
    {
        $siteId = Input::id($siteId);
        $formId = Input::id($formId);
        $result = Db::transaction(function () use ($siteId, $formId, $enabled, $version): array {
            (new SiteService())->lock($siteId);
            $form = $this->locked($siteId, $formId);
            if ($form['lock_version'] !== $version) throw new InvalidArgumentException('表单已被修改，请刷新后重试', 409);
            Form::query()->where('site_id', $siteId)->where('id', $formId)->update(['status' => $enabled ? 1 : 0, 'lock_version' => $version + 1, 'updated_at' => Input::now()]);
            return $this->get($siteId, $formId);
        });
        // 事务提交后再刷新公开页缓存：附带该表单的页面需重新渲染。
        (new PublicPageCache())->invalidateSite($siteId);
        return $result;
    }

    /**
     * 删除表单（软删除，进入表单回收站）：它的未删除提交与附件记录一起软删除，共用一个删除时间，恢复表单时一并带回。
     * 时间线（cms_form_event）是只追加的历史表，保留；磁盘附件只在回收站彻底删除时清理。删除后表单代码可以重新使用。
     */
    public function delete(string $siteId, string $formId): void
    {
        $siteId = Input::id($siteId);
        $formId = Input::id($formId);
        Db::transaction(function () use ($siteId, $formId): void {
            (new SiteService())->lock($siteId);
            $this->locked($siteId, $formId);
            $at = RecycleBin::now();
            $submissions = FormSubmission::query()->where('site_id', $siteId)->where('form_id', $formId)->select('id');
            RecycleBin::trash(FormFile::query()->where('site_id', $siteId)->whereIn('submission_id', $submissions), $at);
            RecycleBin::trash(FormSubmission::query()->where('site_id', $siteId)->where('form_id', $formId), $at);
            RecycleBin::trash(Form::query()->where('site_id', $siteId)->where('id', $formId), $at);
        });
        (new PublicPageCache())->invalidateSite($siteId);
    }

    /** 前台用：只返回启用的表单；字段与文字按“请求语言 → 站点默认语言 → 字段原值”合并，不含 `texts`。 */
    public function resolve(string $siteId, string $code, string $locale): ?array
    {
        $siteId = Input::id($siteId);
        try {
            $code = Input::code($code);
        } catch (InvalidArgumentException) {
            return null;
        }
        $row = Form::query()->where('site_id', $siteId)->where('code', $code)->where('status', 1)->first();
        $default = Site::query()->where('id', $siteId)->value('default_locale');
        if (!$row || $default === null) return null;
        $form = $this->format($row);
        try {
            $locale = Input::locale($locale);
        } catch (InvalidArgumentException) {
            $locale = (string)$default;
        }
        // 默认语言先覆盖字段原值，请求语言再覆盖默认语言。
        $layers = array_values(array_filter([$form['texts'][$default] ?? null, $locale === $default ? null : ($form['texts'][$locale] ?? null)]));
        $settings = $form['settings'];
        $name = $form['name'];
        $fields = $form['fields'];
        foreach ($layers as $texts) {
            $name = $texts['name'] ?? $name;
            $settings['success_message'] = $texts['success_message'] ?? $settings['success_message'];
            $settings['submit_text'] = $texts['submit_text'] ?? $settings['submit_text'];
            foreach ($fields as &$field) {
                $override = $texts['fields'][$field['name']] ?? [];
                foreach (array_keys(self::FIELD_TEXTS) as $key) {
                    if (isset($override[$key])) $field[$key] = $override[$key];
                }
                foreach ($field['options'] ?? [] as $index => $option) {
                    if (isset($override['options'][$option['value']])) $field['options'][$index]['label'] = $override['options'][$option['value']];
                }
            }
            unset($field);
        }
        return ['id' => $form['id'], 'site_id' => $form['site_id'], 'code' => $form['code'], 'name' => $name, 'fields' => $fields, 'settings' => $settings];
    }

    /**
     * 供内容、栏目“附带表单”下拉框使用：本站全部表单的代码、名称与启用状态，按名称排序。
     * @return list<array{code:string,name:string,status:int}>
     */
    public function options(string $siteId): array
    {
        return array_map(static fn(object $row): array => ['code' => (string)$row->code, 'name' => (string)$row->name, 'status' => (int)$row->status],
            Form::query()->where('site_id', Input::id($siteId))->orderBy('name')->orderBy('code')->get(['code', 'name', 'status'])->all());
    }

    /** 校验要附带的表单代码：空串表示不附带；否则必须是本站已有的表单（停用的也可选，前台不显示）。 */
    public static function attachable(string $siteId, mixed $code): string
    {
        if ($code === null || $code === '') return '';
        try {
            $code = Input::code($code);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('附带的表单不存在', 422);
        }
        if (!Form::query()->where('site_id', Input::id($siteId))->where('code', $code)->exists()) throw new InvalidArgumentException('附带的表单不存在', 422);
        return $code;
    }

    public function newCount(string $siteId): int
    {
        return FormSubmission::query()->where('site_id', Input::id($siteId))->where('status', 'new')->count();
    }

    /** @return array{success_message:string,submit_text:string,captcha:bool,rate_limit:int} */
    private static function settings(mixed $settings): array
    {
        if (!is_array($settings)) throw new InvalidArgumentException('表单设置无效', 422);
        $captcha = $settings['captcha'] ?? false;
        if (!is_bool($captcha)) throw new InvalidArgumentException('验证码设置必须为布尔值', 422);
        $limit = $settings['rate_limit'] ?? self::RATE_LIMIT_DEFAULT;
        if ($limit === '' || $limit === null) $limit = self::RATE_LIMIT_DEFAULT;
        if (!is_int($limit) && !(is_string($limit) && preg_match('/^-?\d{1,9}$/D', $limit))) throw new InvalidArgumentException('提交频率必须为整数', 422);
        return [
            'success_message' => self::text($settings['success_message'] ?? '', 500),
            'submit_text' => self::text($settings['submit_text'] ?? '', 50),
            'captcha' => $captcha,
            'rate_limit' => max(1, min(self::RATE_LIMIT_MAX, (int)$limit)),
        ];
    }

    /** 语言必须属于站点；未知字段名、未知选项值与空白文字直接丢弃。 */
    private function texts(string $siteId, mixed $texts, array $fields): array
    {
        if (!is_array($texts)) throw new InvalidArgumentException('多语言文字配置无效', 422);
        $locales = SiteLocale::query()->where('site_id', $siteId)->pluck('locale')->all();
        $known = array_column($fields, null, 'name');
        $result = [];
        foreach ($texts as $locale => $entry) {
            $locale = Input::locale($locale);
            if (!in_array($locale, $locales, true)) throw new InvalidArgumentException('语言不属于本站：' . $locale, 422);
            if (isset($result[$locale])) throw new InvalidArgumentException('语言重复：' . $locale, 422);
            if (!is_array($entry)) throw new InvalidArgumentException('多语言文字配置无效', 422);
            $out = self::filled(['name' => self::text($entry['name'] ?? '', 100), 'success_message' => self::text($entry['success_message'] ?? '', 500), 'submit_text' => self::text($entry['submit_text'] ?? '', 50)]);
            $overrides = [];
            foreach (self::map($entry['fields'] ?? []) as $name => $override) {
                if (!isset($known[$name])) continue;
                $override = self::map($override);
                $field = $known[$name];
                $item = [];
                foreach (self::FIELD_TEXTS as $key => $max) {
                    if (array_key_exists($key, $field)) $item[$key] = self::text($override[$key] ?? '', $max);
                }
                $item = self::filled($item);
                $values = array_column($field['options'] ?? [], 'value');
                $options = [];
                foreach (self::map($override['options'] ?? []) as $value => $label) {
                    if (in_array((string)$value, $values, true)) $options[(string)$value] = self::text($label, 255);
                }
                if ($options = self::filled($options)) $item['options'] = $options;
                if ($item) $overrides[$name] = $item;
            }
            if ($overrides) $out['fields'] = $overrides;
            if ($out) $result[$locale] = $out;
        }
        return $result;
    }

    private function locked(string $siteId, string $formId): array
    {
        $row = Form::query()->where('site_id', $siteId)->where('id', $formId)->lockForUpdate()->first();
        return $this->format($row ?? throw new InvalidArgumentException('表单不存在', 404));
    }

    /** @return array<string,int> form_id → 提交数 */
    private function counts(string $siteId, ?string $status): array
    {
        $query = Db::table('cms_form_submission')->where('site_id', $siteId)->whereNull('deleted_at');
        if ($status !== null) $query->where('status', $status);
        $counts = [];
        foreach ($query->selectRaw('form_id, count(*) as aggregate')->groupBy('form_id')->get() as $row) $counts[(string)$row->form_id] = (int)$row->aggregate;
        return $counts;
    }

    private function format(object $row): array
    {
        $row = Input::row($row);
        $decode = static fn(mixed $value): array => is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : (array)$value;
        return [
            'id' => $row['id'],
            'site_id' => $row['site_id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'enabled' => (int)$row['status'] === 1,
            'fields' => $decode($row['fields']),
            'texts' => $decode($row['texts']),
            'settings' => $decode($row['settings']),
            'lock_version' => (int)$row['lock_version'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private static function map(mixed $value): array
    {
        if (!is_array($value)) throw new InvalidArgumentException('多语言文字配置无效', 422);
        return $value;
    }

    private static function filled(array $values): array
    {
        return array_filter($values, static fn(string $value): bool => $value !== '');
    }

    private static function text(mixed $value, int $max): string
    {
        if ($value === null) return '';
        if (!is_string($value) && !is_int($value) && !is_float($value)) throw new InvalidArgumentException('文本格式无效', 422);
        $text = FormValueValidator::clean((string)$value, false);
        if ($text === null || mb_strlen($text) > $max) throw new InvalidArgumentException('文本无效或超过 ' . $max . ' 字', 422);
        return $text;
    }
}
