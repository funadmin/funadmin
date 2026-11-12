<?php
declare(strict_types=1);
namespace app\qa\service;
use app\cms\model\MemberSubmission;
use app\cms\service\{FormFileStorage,Input,RecycleBin};
use app\qa\model\{QaAnswer,QaAttachment,QaQuestion};
use InvalidArgumentException;
use support\Response;
use Throwable;
use Webman\Http\UploadFile;
/**
 * 问答会员附件（1.3.0）：会员提问、回答时随表单上传图片或文件。
 *
 * 复用 CMS 会员表单附件的存储（FormFileStorage）：扩展名白名单与危险扩展名黑名单、MIME 嗅探、脚本内容拒绝、单个大小上限，
 * 私有目录（0700/0600，按站点分目录，不经过公开 URL），读取时校验路径格式与符号链接。根目录单独为 runtime/plugins-data/qa/attachments。
 * 数量与总大小限制在这里（总大小低于 webman 默认的 10MB 请求上限）。
 *
 * 下载鉴权：会员必须登录；附件所属问题（及回答）在问答侧与 CMS 侧都未删除；审核通过且未隐藏时任何会员可下载，否则只有上传者本人可下载。
 * 后台下载由后台接口按宿主权限与 CMS 投稿站点权限鉴权；所属问题或回答已删除时还需要问答回收站查看权限。
 *
 * 软删除：附件与所属问题或回答以同一删除时间软删除、按该时间一起恢复；磁盘文件只在回收站彻底删除时清理（QaRecycleBin）。
 */
final class AttachmentService
{
    public const FIELD = 'attachments';
    public const MAX_FILES = 5;
    public const MAX_FILE_SIZE = 5 * 1024 * 1024;
    public const MAX_TOTAL = 8 * 1024 * 1024;
    public const ACCEPT = ['image', 'document', 'archive'];
    private const MESSAGES = ['invalid_file_type' => '附件类型不允许', 'file_too_large' => '单个附件不能超过 5MB', 'files_too_large' => '附件总大小不能超过 8MB', 'too_many_files' => '附件最多 5 个', 'upload_failed' => '附件上传失败，请重新上传'];

    private readonly FormFileStorage $storage;

    public function __construct(?FormFileStorage $storage = null)
    {
        $this->storage = $storage ?? self::storage();
    }

    /** 问答附件的私有存储根目录（与 CMS 表单附件分开）。 */
    public static function storage(): FormFileStorage
    {
        return new FormFileStorage(runtime_path('plugins-data/qa/attachments'));
    }

    /**
     * 请求中的附件（字段 attachments，单个或 attachments[]）转成待校验的上传列表；未选择文件的空输入跳过。
     * @return list<array{tmp:string,name:string,size:int}>
     */
    public static function uploads(mixed $files): array
    {
        $result = [];
        foreach (is_array($files) ? $files : [$files] as $file) {
            if (!$file instanceof UploadFile) continue;
            $error = (int)$file->getUploadErrorCode();
            if ($error === UPLOAD_ERR_NO_FILE || ($error === UPLOAD_ERR_OK && (string)$file->getUploadName() === '' && (int)$file->getSize() === 0)) continue;
            if (count($result) >= self::MAX_FILES) throw self::rejected('too_many_files');
            // 大小超限提示限制；其他错误码（部分上传、无临时目录、写入失败、扩展中止等）是上传过程失败，与文件类型无关。
            if ($error !== UPLOAD_ERR_OK) throw self::rejected(in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'file_too_large' : 'upload_failed');
            $result[] = ['tmp' => $file->getPathname(), 'name' => (string)$file->getUploadName(), 'size' => (int)$file->getSize()];
        }
        return $result;
    }

    /**
     * 写入前校验全部附件：数量、类型、单个与总大小。任何一个不合格整次提交 422，不写任何数据。
     * @param list<array{tmp?:mixed,name?:mixed,size?:mixed}> $uploads
     * @return list<array{ext:string,mime:string,size:int,name:string,tmp:string}>
     */
    public function inspect(array $uploads): array
    {
        if (count($uploads) > self::MAX_FILES) throw self::rejected('too_many_files');
        $field = ['type' => 'file', 'accept' => self::ACCEPT, 'max_size' => self::MAX_FILE_SIZE];
        $result = [];
        $total = 0;
        foreach ($uploads as $upload) {
            try {
                $file = $this->storage->inspect($field, is_array($upload) ? $upload : []);
            } catch (InvalidArgumentException $e) {
                throw self::rejected($e->getMessage());
            }
            $total += $file['size'];
            $result[] = $file;
        }
        if ($total > self::MAX_TOTAL) throw self::rejected('files_too_large');
        return $result;
    }

    /**
     * 把校验过的附件落盘并登记（必须在事务内）。已落盘的路径追加到 $stored，事务失败时由调用方 unlink。
     * @param list<array{ext:string,mime:string,size:int,name:string,tmp:string}> $files
     * @param list<string> $stored
     */
    public function attach(string $site, string $question, string $submission, string $member, array $files, array &$stored): void
    {
        $now = Input::now();
        foreach ($files as $file) {
            $saved = $this->storage->store($site, $file);
            $stored[] = $saved['path'];
            (new QaAttachment())->fill(['site_id' => $site, 'question_id' => $question, 'submission_id' => $submission, 'member_id' => $member, 'original_name' => $file['name'], 'mime' => $file['mime'], 'size' => $file['size'], 'sha256' => $saved['sha256'], 'path' => $saved['path'], 'created_at' => $now, 'updated_at' => $now])->save();
        }
    }

    /**
     * 未删除的附件，按所属投稿分组（问题或回答 id => 附件列表）。只输出展示字段，不含存储路径与上传者。
     * @param list<string> $submissions
     * @return array<string,list<array{id:string,name:string,mime:string,size:int,created_at:mixed}>>
     */
    public function forSubmissions(string $site, array $submissions): array
    {
        if ($submissions === []) return [];
        $result = [];
        foreach (QaAttachment::query()->where('site_id', $site)->whereIn('submission_id', $submissions)->orderBy('id')->get() as $row) {
            $result[(string)$row->getRawOriginal('submission_id')][] = ['id' => (string)$row->getRawOriginal('id'), 'name' => (string)$row->original_name, 'mime' => (string)$row->mime, 'size' => (int)$row->size, 'created_at' => $row->getRawOriginal('created_at')];
        }
        return $result;
    }

    /** 以所属问题或回答的删除时间软删除它们的附件（必须在事务内）。 */
    public static function trash(string $site, array $submissions, string $at): void
    {
        if ($submissions === []) return;
        RecycleBin::trash(QaAttachment::query()->where('site_id', $site)->whereIn('submission_id', $submissions), $at);
    }

    /** 恢复与所属问题或回答同一时间删除的附件。 */
    public static function restore(string $site, string $submission, string $at): void
    {
        QaAttachment::onlyTrashed()->where('site_id', $site)->where('submission_id', $submission)->where('deleted_at', $at)->restore();
    }

    /** 删除磁盘文件（彻底删除提交之后调用）；单个文件删除失败不影响已提交的数据库操作。 @param list<string> $paths */
    public function unlink(string $site, array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->storage->delete($site, $path);
            } catch (Throwable) {
            }
        }
    }

    /**
     * 会员下载：附件所属问题（与回答）在问答侧与 CMS 侧都未删除；已公开（问题审核通过，回答审核通过且未隐藏）时任何登录会员可下载，
     * 否则只有上传者本人。不满足时一律 404，不暴露附件是否存在。
     * @return array{absolute:string,name:string,mime:string}
     */
    public function memberFile(string $site, string $member, string $id): array
    {
        $site = Input::id($site);
        $member = Input::id($member);
        $file = QaAttachment::query()->where('site_id', $site)->where('id', Input::id($id))->first() ?? throw self::missing();
        $questionId = (string)$file->getRawOriginal('question_id');
        $submissionId = (string)$file->getRawOriginal('submission_id');
        $state = QaQuestion::query()->where('id', $questionId)->where('site_id', $site)->first() ?? throw self::missing();
        $question = MemberSubmission::query()->where('id', $questionId)->where('site_id', $site)->where('locale', $state->locale)->where('kind', 'question')->first() ?? throw self::missing();
        $public = $question->status === 'published';
        if ($submissionId !== $questionId) {
            $answerState = QaAnswer::query()->where('id', $submissionId)->where('question_id', $questionId)->where('site_id', $site)->first() ?? throw self::missing();
            $answer = MemberSubmission::query()->where('id', $submissionId)->where('site_id', $site)->where('kind', 'answer')->where('parent_id', $questionId)->first() ?? throw self::missing();
            $public = $public && $answer->status === 'published' && !(bool)$answerState->hidden;
        }
        if (!$public && (string)$file->getRawOriginal('member_id') !== $member) throw self::missing();
        return $this->resolve($site, $file);
    }

    /**
     * 后台下载（接口权限与站点权限由后台接口完成）。附件、所属问题（及回答）在问答侧或 CMS 侧任一已软删除时，
     * 只有 $canViewTrashed 返回 true（管理员持有问答回收站查看权限 qa:questions:recycle）才可下载，否则 404；
     * 所属问题或回答的记录已不存在（彻底删除）时一律 404。$canViewTrashed 只在确有已删除的父级时调用。
     * @param callable():bool $canViewTrashed
     * @return array{absolute:string,name:string,mime:string}
     */
    public function adminFile(string $site, string $id, callable $canViewTrashed): array
    {
        $site = Input::id($site);
        $file = QaAttachment::withTrashed()->where('site_id', $site)->where('id', Input::id($id))->first() ?? throw self::missing();
        $questionId = (string)$file->getRawOriginal('question_id');
        $submissionId = (string)$file->getRawOriginal('submission_id');
        $state = QaQuestion::withTrashed()->where('id', $questionId)->where('site_id', $site)->first() ?? throw self::missing();
        $question = MemberSubmission::withTrashed()->where('id', $questionId)->where('site_id', $site)->where('locale', $state->locale)->where('kind', 'question')->first() ?? throw self::missing();
        $trashed = $file->trashed() || $state->trashed() || $question->trashed();
        if ($submissionId !== $questionId) {
            $answerState = QaAnswer::withTrashed()->where('id', $submissionId)->where('question_id', $questionId)->where('site_id', $site)->first() ?? throw self::missing();
            $answer = MemberSubmission::withTrashed()->where('id', $submissionId)->where('site_id', $site)->where('kind', 'answer')->where('parent_id', $questionId)->first() ?? throw self::missing();
            $trashed = $trashed || $answerState->trashed() || $answer->trashed();
        }
        if ($trashed && !$canViewTrashed()) throw self::missing();
        return $this->resolve($site, $file);
    }

    /**
     * 附件下载响应，与 CMS 表单附件下载相同的安全头：一律 attachment；文本类型按二进制下载；nosniff、沙箱 CSP、不缓存。
     * @param array{absolute:string,name:string,mime:string} $file
     */
    public static function response(array $file): Response
    {
        $mime = $file['mime'];
        if ($mime === '' || str_starts_with(strtolower($mime), 'text/')) $mime = 'application/octet-stream';
        $fallback = str_replace(['"', '\\'], '', (string)preg_replace('/[^\x20-\x7e]/u', '_', $file['name']));
        if (trim($fallback, ' ._') === '') $fallback = 'download';
        return (new Response(200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($file['name']),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], ''))->withFile($file['absolute']);
    }

    /** @return array{absolute:string,name:string,mime:string} */
    private function resolve(string $site, QaAttachment $file): array
    {
        $absolute = $this->storage->absolute($site, (string)$file->path);
        if (!is_file($absolute)) throw self::missing();
        return ['absolute' => $absolute, 'name' => (string)$file->original_name, 'mime' => (string)$file->mime];
    }

    private static function rejected(string $key): InvalidArgumentException
    {
        return new InvalidArgumentException(self::MESSAGES[$key] ?? self::MESSAGES['invalid_file_type'], 422);
    }

    private static function missing(): InvalidArgumentException
    {
        return new InvalidArgumentException('附件不存在', 404);
    }
}
