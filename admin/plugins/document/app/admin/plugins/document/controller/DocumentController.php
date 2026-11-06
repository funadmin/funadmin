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

#[RouteGroup('/admin/plugin/document/documents')]
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
        return $this->action(fn () => (new DocumentService())->updateMeta($id, $this->input()), '保存成功');
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
