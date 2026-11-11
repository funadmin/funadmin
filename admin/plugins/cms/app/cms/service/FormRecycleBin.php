<?php
declare(strict_types=1);
namespace app\cms\service;
use app\cms\model\Form;
use app\cms\model\FormEvent;
use app\cms\model\FormFile;
use app\cms\model\FormSubmission;
use InvalidArgumentException;
use support\Db;
use Throwable;

/**
 * 表单与提交的回收站（1.6.0）：列表、恢复、彻底删除。所有操作限定站点，先锁站点行（所属锁）再锁回收站中的行。
 *
 * - 表单回收站：恢复表单时带回与它一起删除（deleted_at 相同）的提交与附件记录；表单代码已被新表单占用时 409。
 *   彻底删除表单连同它的全部已删除提交、附件记录与时间线，事务提交后删除磁盘文件；仍有未删除的提交时 409。
 * - 提交回收站：只列出表单仍存在的提交（表单已删除时，提交随表单在表单回收站中）。恢复时带回一起删除的附件；
 *   表单已删除时 409（先恢复表单）。彻底删除连同附件记录、时间线与磁盘文件。
 */
final class FormRecycleBin
{
    private readonly FormFileStorage $storage;

    public function __construct(?FormFileStorage $storage = null)
    {
        $this->storage = $storage ?? new FormFileStorage();
    }

    /**
     * 表单回收站（分页）；submission_count 为与表单一起删除（恢复时会带回）的提交数。
     * @return array{list:list<array>,total:int,page:int,pageSize:int}
     */
    public function trashedForms(string $siteId, mixed $page = 1, mixed $pageSize = RecycleBin::PAGE_SIZE): array
    {
        $siteId = Input::id($siteId);
        [$page, $pageSize] = RecycleBin::paging($page, $pageSize);
        $query = Form::onlyTrashed()->where('site_id', $siteId);
        $total = (clone $query)->count();
        $forms = $query->orderByDesc('deleted_at')->orderByDesc('id')->forPage($page, $pageSize)->get();
        $counts = RecycleBin::childCounts(FormSubmission::onlyTrashed()->where('site_id', $siteId), 'form_id', array_map(static fn($f): string => (string)$f->getRawOriginal('id'), $forms->all()));
        $rows = [];
        foreach ($forms as $form) {
            $at = RecycleBin::deletedAt($form);
            $id = (string)$form->getRawOriginal('id');
            $rows[] = ['id' => $id, 'code' => (string)$form->code, 'name' => (string)$form->name, 'deleted_at' => $at, 'submission_count' => $counts[$id . '|' . $at] ?? 0];
        }
        return RecycleBin::result($rows, $total, $page, $pageSize);
    }

    /** @return array{count:int,submissions:int} */
    public function restoreForms(string $siteId, mixed $ids): array
    {
        $siteId = Input::id($siteId);
        $ids = RecycleBin::ids($ids);
        $result = Db::transaction(function () use ($siteId, $ids): array {
            (new SiteService())->lock($siteId);
            $forms = RecycleBin::locked(Form::class, $siteId, $ids, '表单不在回收站或不属于本站');
            $submissions = 0;
            foreach ($forms as $form) {
                $at = RecycleBin::deletedAt($form);
                $id = (string)$form->getRawOriginal('id');
                $form->restore();
                $restored = FormSubmission::onlyTrashed()->where('site_id', $siteId)->where('form_id', $id)->where('deleted_at', $at)->pluck('id')->all();
                if ($restored === []) continue;
                FormFile::onlyTrashed()->where('site_id', $siteId)->whereIn('submission_id', $restored)->where('deleted_at', $at)->restore();
                FormSubmission::onlyTrashed()->whereKey($restored)->restore();
                $submissions += count($restored);
            }
            return ['count' => count($forms), 'submissions' => $submissions];
        });
        (new PublicPageCache())->invalidateSite($siteId);
        return $result;
    }

    /** @return array{count:int} */
    public function destroyForms(string $siteId, mixed $ids): array
    {
        $siteId = Input::id($siteId);
        $ids = RecycleBin::ids($ids);
        [$count, $paths] = Db::transaction(function () use ($siteId, $ids): array {
            (new SiteService())->lock($siteId);
            $forms = RecycleBin::locked(Form::class, $siteId, $ids, '表单不在回收站或不属于本站');
            $paths = [];
            foreach ($forms as $form) {
                $id = (string)$form->getRawOriginal('id');
                if (FormSubmission::query()->where('site_id', $siteId)->where('form_id', $id)->exists()) throw new InvalidArgumentException('表单仍有未删除的提交，不能彻底删除', 409);
                $submissions = FormSubmission::onlyTrashed()->where('site_id', $siteId)->where('form_id', $id)->pluck('id')->all();
                array_push($paths, ...$this->purge($siteId, $submissions));
                $form->forceDelete();
            }
            return [count($forms), $paths];
        });
        $this->unlink($siteId, $paths);
        return ['count' => $count];
    }

    /** @return array{list:list<array>,total:int,page:int,pageSize:int} */
    public function trashedSubmissions(string $siteId, mixed $page = 1, mixed $pageSize = RecycleBin::PAGE_SIZE): array
    {
        $siteId = Input::id($siteId);
        [$page, $pageSize] = RecycleBin::paging($page, $pageSize);
        $forms = [];
        foreach (Form::query()->where('site_id', $siteId)->get(['id', 'code', 'name']) as $form) $forms[(string)$form->id] = ['form_code' => (string)$form->code, 'form_name' => (string)$form->name];
        $query = FormSubmission::onlyTrashed()->where('site_id', $siteId)->whereIn('form_id', array_keys($forms) ?: ['0']);
        $total = (clone $query)->count();
        $list = [];
        foreach ($query->orderByDesc('deleted_at')->orderByDesc('id')->forPage($page, $pageSize)->get() as $row) {
            $item = Input::row($row);
            $values = is_string($item['values']) ? (json_decode($item['values'], true) ?: []) : (array)$item['values'];
            $fields = is_string($item['fields']) ? (json_decode($item['fields'], true) ?: []) : (array)$item['fields'];
            $summary = [];
            foreach ($fields as $field) {
                $value = $values[$field['name'] ?? ''] ?? null;
                if (count($summary) < 2 && is_scalar($value) && $value !== '') $summary[] = ['label' => (string)($field['label'] ?? ''), 'value' => (string)$value];
            }
            $list[] = ['id' => $item['id'], 'form_id' => $item['form_id'], 'status' => $item['status'], 'created_at' => $item['created_at'], 'deleted_at' => (string)$row->getRawOriginal('deleted_at'), 'summary' => $summary] + $forms[$item['form_id']];
        }
        return RecycleBin::result($list, $total, $page, $pageSize);
    }

    /** @return array{count:int} */
    public function restoreSubmissions(string $siteId, mixed $ids): array
    {
        $siteId = Input::id($siteId);
        $ids = RecycleBin::ids($ids);
        return Db::transaction(function () use ($siteId, $ids): array {
            (new SiteService())->lock($siteId);
            $rows = RecycleBin::locked(FormSubmission::class, $siteId, $ids, '提交不在回收站或不属于本站');
            foreach ($rows as $row) {
                if (!Form::query()->where('site_id', $siteId)->where('id', $row->form_id)->exists()) throw new InvalidArgumentException('表单已删除，请先在表单回收站中恢复表单', 409);
                $at = RecycleBin::deletedAt($row);
                $id = (string)$row->getRawOriginal('id');
                $row->restore();
                FormFile::onlyTrashed()->where('site_id', $siteId)->where('submission_id', $id)->where('deleted_at', $at)->restore();
            }
            return ['count' => count($rows)];
        });
    }

    /** @return array{count:int} */
    public function destroySubmissions(string $siteId, mixed $ids): array
    {
        $siteId = Input::id($siteId);
        $ids = RecycleBin::ids($ids);
        [$count, $paths] = Db::transaction(function () use ($siteId, $ids): array {
            (new SiteService())->lock($siteId);
            $rows = RecycleBin::locked(FormSubmission::class, $siteId, $ids, '提交不在回收站或不属于本站');
            $found = array_map(static fn(FormSubmission $row): string => (string)$row->getRawOriginal('id'), $rows);
            return [count($rows), $this->purge($siteId, $found)];
        });
        $this->unlink($siteId, $paths);
        return ['count' => $count];
    }

    /**
     * 物理删除已删除的提交及其附件记录与时间线（时间线只随提交彻底删除）。返回要在事务提交后删除的磁盘路径。
     * 已删除提交仍挂着未删除的附件记录时 409（不应出现，防御）。
     * @param list<string> $submissions
     * @return list<string>
     */
    private function purge(string $siteId, array $submissions): array
    {
        if ($submissions === []) return [];
        if (FormFile::query()->where('site_id', $siteId)->whereIn('submission_id', $submissions)->exists()) throw new InvalidArgumentException('提交仍有未删除的附件，不能彻底删除', 409);
        $paths = array_map('strval', FormFile::onlyTrashed()->where('site_id', $siteId)->whereIn('submission_id', $submissions)->pluck('path')->all());
        FormFile::onlyTrashed()->where('site_id', $siteId)->whereIn('submission_id', $submissions)->forceDelete();
        FormEvent::query()->where('site_id', $siteId)->whereIn('submission_id', $submissions)->delete();
        FormSubmission::onlyTrashed()->where('site_id', $siteId)->whereIn('id', $submissions)->forceDelete();
        return $paths;
    }

    /** @param list<string> $paths */
    private function unlink(string $siteId, array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->storage->delete($siteId, $path);
            } catch (Throwable) {
                // 数据库已提交，单个文件删不掉不应让整个操作失败。
            }
        }
    }
}
