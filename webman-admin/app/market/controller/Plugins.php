<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketCatalogService;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Route;
use think\Response;

/**
 * 规范定义查询接口为 GET，而现有客户端传输层统一 POST JSON，两种方法都接受。
 * categories 必须先于 :code 注册，否则会被当作插件标识匹配。
 */
#[Group('api/v3/plugins', ['complete_match' => true])]
final class Plugins extends ApiController
{
    #[Route('GET|POST', 'categories')]
    public function categories(): Response
    {
        if ($this->member() === null) {
            return $this->unauthorized();
        }
        return $this->success(['items' => (new MarketCatalogService())->categories()]);
    }

    #[Post('check-updates')]
    public function checkUpdates(): Response
    {
        if ($this->member() === null) {
            return $this->unauthorized();
        }
        $installed = $this->request->param('installed', []);
        if (!is_array($installed) || !array_is_list($installed) || count($installed) > 500) {
            return $this->error(422, 'installed 必须是不超过 500 项的列表');
        }
        $entries = [];
        foreach ($installed as $item) {
            if (!is_array($item) || !is_string($item['code'] ?? null) || !is_string($item['code_version'] ?? null)
                || preg_match('/^[a-z][a-z0-9]*$/', $item['code']) !== 1) {
                return $this->error(422, 'installed 项必须包含有效的 code 与 code_version');
            }
            $entries[] = [
                'code' => $item['code'],
                'code_version' => $item['code_version'],
                'db_version' => is_string($item['db_version'] ?? null) ? $item['db_version'] : '',
                'modified' => (bool) ($item['modified'] ?? false),
            ];
        }
        return $this->success(['items' => (new MarketCatalogService())->checkUpdates($entries, $this->context())]);
    }

    #[Route('GET|POST', '')]
    public function search(): Response
    {
        if ($this->member() === null) {
            return $this->unauthorized();
        }
        $page = max(1, (int) $this->request->param('page', 1));
        $limit = (int) $this->request->param('limit', 20);
        if ($limit < 1 || $limit > 100) {
            return $this->error(422, 'limit 必须在 1 到 100 之间');
        }
        return $this->success((new MarketCatalogService())->search(
            $this->context(),
            (string) $this->request->param('keyword', ''),
            (int) $this->request->param('category_id', 0),
            $page,
            $limit
        ));
    }

    #[Route('GET|POST', ':code')]
    #[Pattern('code', '[a-z][a-z0-9]*')]
    public function detail(string $code): Response
    {
        if ($this->member() === null) {
            return $this->unauthorized();
        }
        $detail = (new MarketCatalogService())->detail($code, $this->context());
        return $detail === null ? $this->error(404, '插件不存在或已下架') : $this->success($detail);
    }

    #[Route('GET|POST', ':code/versions')]
    #[Pattern('code', '[a-z][a-z0-9]*')]
    public function versions(string $code): Response
    {
        if ($this->member() === null) {
            return $this->unauthorized();
        }
        $versions = (new MarketCatalogService())->versions($code, $this->context());
        return $versions === null ? $this->error(404, '插件不存在或已下架') : $this->success(['items' => $versions]);
    }

    #[Post(':code/authorize')]
    #[Pattern('code', '[a-z][a-z0-9]*')]
    public function authorize(string $code): Response
    {
        $member = $this->member();
        if ($member === null) {
            return $this->unauthorized();
        }
        $version = $this->requestedVersion();
        if ($version === null) {
            return $this->error(422, 'code_version 必须是语义化版本');
        }
        return $this->success((new MarketCatalogService())->authorize(
            $member['id'],
            $code,
            $version,
            (string) $this->request->param('db_version', ''),
            $this->context()
        ));
    }

    #[Post(':code/download')]
    #[Pattern('code', '[a-z][a-z0-9]*')]
    public function download(string $code): Response
    {
        $member = $this->member();
        if ($member === null) {
            return $this->unauthorized();
        }
        $version = $this->requestedVersion();
        if ($version === null) {
            return $this->error(422, 'code_version 必须是语义化版本');
        }
        [$descriptor, $message] = (new MarketCatalogService())->download(
            $member['id'],
            $code,
            $version,
            (string) $this->request->param('db_version', ''),
            $this->context()
        );
        return $descriptor === null ? $this->error(403, $message) : $this->success($descriptor);
    }

    private function requestedVersion(): ?string
    {
        $version = (string) $this->request->param('code_version', '');
        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1 ? $version : null;
    }
}
