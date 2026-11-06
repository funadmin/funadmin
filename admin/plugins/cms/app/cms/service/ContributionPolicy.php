<?php

declare (strict_types=1);
namespace app\cms\service;

use InvalidArgumentException;
use support\Db;
final class ContributionPolicy
{
    public function read(string $site): array
    {
        $row = (new SiteService())->get($site);
        $saved = $row['settings']['member_content'] ?? [];
        $models = $this->models($site);
        $codes = is_array($saved['model_codes'] ?? null) ? $saved['model_codes'] : [];
        return ['enabled' => ($saved['enabled'] ?? false) === true, 'model_codes' => array_values(array_intersect($codes, array_column($models, 'code'))), 'models' => $models];
    }
    public function save(string $site, string $actor, array $input): array
    {
        return Db::transaction(function () use ($site, $actor, $input): array {
            $row = (new SiteService())->lock($site);
            (new SiteAuthorization())->assert($site, $actor, 'cms:site:edit');
            if (!is_bool($input['enabled'] ?? null) || !is_array($input['model_codes'] ?? null) || !array_is_list($input['model_codes'])) {
                throw new InvalidArgumentException('投稿设置格式无效', 422);
            }
            $codes = array_map(Input::code(...), $input['model_codes']);
            $models = $this->models($site);
            if (array_diff($codes, array_column($models, 'code'))) {
                throw new InvalidArgumentException('投稿模型必须为本站启用且不含附件字段的模型', 422);
            }
            $settings = $row['settings'] ?? [];
            $settings['member_content'] = ['enabled' => $input['enabled'], 'model_codes' => array_values(array_unique($codes))];
            Db::table('cms_site')->where('id', $site)->update(['settings' => Input::json($settings), 'updated_at' => Input::now()]);
            return $this->read($site);
        });
    }
    public function context(): array
    {
        foreach (array_merge(Schema::tables(), ContributionSchema::tables()) as $table) {
            if (!Db::schema()->hasTable($table)) {
                return ['sites' => [], 'ready' => false];
            }
        }
        $sites = [];
        foreach (Input::rows(Db::table('cms_site')->where('status', 1)->whereNull('deleted_at')->orderBy('id')->get()) as $site) {
            $policy = $this->read($site['id']);
            $locales = [];
            foreach ((new SiteService())->locales($site['id'], true) as $locale) {
                $locales[] = ['locale' => $locale['locale'], 'name' => $locale['name']];
            }
            $models = array_values(array_filter($policy['models'], static fn($model) => in_array($model['code'], $policy['model_codes'], true)));
            $sites[] = ['id' => $site['id'], 'name' => $site['name'], 'default_locale' => $site['default_locale'], 'locales' => $locales, 'enabled' => $policy['enabled'], 'models' => $models];
        }
        return ['sites' => $sites, 'ready' => true];
    }
    private function models(string $site): array
    {
        $models = [];
        $rows = Db::table('cms_content_model as m')->join('cms_site_model as s', 's.model_id', '=', 'm.id')->where('s.site_id', Input::id($site))->where('s.status', 1)->where('m.status', 1)->orderBy('m.id')->select('m.id', 'm.code', 'm.name', 'm.current_version')->get();
        foreach ($rows as $row) {
            $version = Input::row(Db::table('cms_model_version')->where('model_id', $row->id)->where('version', $row->current_version)->first());
            if (!$version) {
                continue;
            }
            $fields = $version['field_schema'];
            if (array_intersect(array_column($fields, 'type'), ['image', 'file', 'gallery'])) {
                continue;
            }
            $safe = [];
            foreach ($fields as $field) {
                $safe[] = array_intersect_key($field, array_flip(['name', 'label', 'type', 'required', 'options', 'default']));
            }
            $models[] = ['code' => $row->code, 'name' => $row->name, 'fields' => $safe];
        }
        return $models;
    }
}
