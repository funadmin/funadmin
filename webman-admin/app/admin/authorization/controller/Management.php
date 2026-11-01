<?php

declare(strict_types=1);

namespace app\admin\authorization\controller;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\authorization\service\ManagementService;
use DomainException;
use support\Request;
use support\Response;

final class Management
{
    public function listing(Request $request, string $resource): Response
    {
        return $this->execute($request, fn (int $actor): array => (new ManagementService())->listing($actor, $resource, $request->get()));
    }

    public function create(Request $request, string $resource): Response
    {
        return $this->execute($request, fn (int $actor): array => (new ManagementService())->save($actor, $resource, null, $request->post()));
    }

    public function update(Request $request, string $resource, string $id): Response
    {
        return $this->execute($request, fn (int $actor): array => (new ManagementService())->save($actor, $resource, (int) $id, $request->post()));
    }

    public function remove(Request $request, string $resource, string $id): Response
    {
        return $this->execute($request, function (int $actor) use ($resource, $id): ?array {
            (new ManagementService())->remove($actor, $resource, (int) $id);
            return null;
        });
    }

    private function execute(Request $request, callable $callback): Response
    {
        try {
            $auth = new BrowserAuthService();
            $cookie = (string) $request->cookie(BrowserAuthService::COOKIE, '');
            $admin = $auth->user($cookie);
            return json(['code' => 200, 'msg' => '成功', 'data' => $callback((int) $admin['id'])])->withHeader('Cache-Control', 'no-store');
        } catch (DomainException $error) {
            return json(['code' => $error->getCode(), 'msg' => $error->getMessage(), 'data' => null])
                ->withStatus($error->getCode())->withHeader('Cache-Control', 'no-store');
        }
    }
}
