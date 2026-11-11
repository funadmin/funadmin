<?php

declare(strict_types=1);

namespace app\admin\plugins\document\controller;

use app\admin\plugins\document\service\DocumentApiController;
use app\document\service\DocumentService;
use app\document\service\DocumentVersionService;
use support\Response;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;

#[RouteGroup('/admin/plugins/document/documents')]
final class DocumentController extends DocumentApiController
{
    #[Get('')]
    public function index(): Response
    {
        return $this->action(fn () => (new DocumentService())->list($this->input()));
    }

    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        return $this->action(fn () => (new DocumentService())->find($id));
    }

    #[Post('')]
    public function create(): Response
    {
        return $this->action(fn () => (new DocumentService())->create($this->input()), '创建成功');
    }

    #[Put('/{id:\d+}')]
    public function updateMeta(int $id): Response
    {
        return $this->action(function () use ($id): array {
            $input = $this->input();
            if (!array_key_exists('metaVersion', $input)) throw new \InvalidArgumentException('metaVersion 无效', 422);
            $current = (new DocumentService())->row($id);
            if (array_key_exists('visibility', $input) && $input['visibility'] !== $current->visibility
                && !(new \app\admin\authorization\service\AdminAuthorizationService())->can('document:document:publish')) {
                throw new \InvalidArgumentException('更改文档可见性需要发布权限', 403);
            }
            return (new DocumentService())->updateMeta($id, $input);
        }, '保存成功');
    }

    #[Put('/{id:\d+}/draft')]
    public function saveDraft(int $id): Response
    {
        return $this->action(function () use ($id): array {
            $input = $this->input();
            return (new DocumentVersionService())->saveDraft($id, $input['content'] ?? null, $this->lockVersion($input));
        }, '已保存');
    }

    #[Delete('/{id:\d+}')]
    public function delete(int $id): Response
    {
        return $this->action(function () use ($id): array {
            (new DocumentService())->delete($id);
            return ['id' => $id];
        }, '删除成功');
    }

    /** 回收站：已删除的文档（可按 keyword 搜索标题或标识，page / pageSize 分页）。 */
    #[Get('/recycle')]
    public function recycle(): Response
    {
        return $this->action(fn () => (new DocumentService())->trashed($this->input()));
    }

    /** 从回收站恢复文档，连同这次删除带走的目录关联；标识已被占用时返回 409。（restore 已用于恢复历史版本。） */
    #[Post('/{id:\d+}/recover')]
    public function recover(int $id): Response
    {
        // 有目录关联因目录已删除或换了范围而未恢复时，在提示里说明数量（data.skippedLinks）。
        return $this->action(fn () => (new DocumentService())->restore($id), static fn (array $restored): string => $restored['skippedLinks'] > 0
            ? "已恢复，{$restored['skippedLinks']} 个目录关联因目录已删除或范围变化未恢复" : '已恢复');
    }

    /** 彻底删除回收站中的文档，不可恢复；未删除的文档返回 404。 */
    #[Delete('/{id:\d+}/destroy')]
    public function destroy(int $id): Response
    {
        return $this->action(function () use ($id): array {
            (new DocumentService())->destroy($id);
            return ['id' => $id];
        }, '已彻底删除');
    }

    #[Post('/{id:\d+}/publish')]
    public function publish(int $id): Response
    {
        return $this->action(function () use ($id): array {
            $input = $this->input();
            return (new DocumentVersionService())->publish($id, (string) ($input['changeSummary'] ?? ''), $this->lockVersion($input));
        }, '发布成功');
    }

    #[Post('/{id:\d+}/archive')]
    public function archive(int $id): Response
    {
        return $this->action(fn () => (new DocumentVersionService())->archive($id, $this->lockVersion($this->input())), '已归档');
    }

    #[Get('/{id:\d+}/revisions')]
    public function revisions(int $id): Response
    {
        $input = $this->input();
        return $this->action(fn () => (new DocumentVersionService())->revisions($id, (int) ($input['page'] ?? 1), (int) ($input['pageSize'] ?? 20)));
    }

    #[Get('/{id:\d+}/revisions/{revisionNo:\d+}')]
    public function revision(int $id, int $revisionNo): Response
    {
        return $this->action(fn () => (new DocumentVersionService())->revision($id, $revisionNo));
    }

    #[Post('/{id:\d+}/revisions/{revisionNo:\d+}/restore')]
    public function restore(int $id, int $revisionNo): Response
    {
        return $this->action(fn () => (new DocumentVersionService())->restore($id, $revisionNo, $this->lockVersion($this->input())), '已恢复');
    }
}
