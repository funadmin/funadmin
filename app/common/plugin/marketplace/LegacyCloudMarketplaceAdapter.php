<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

use app\common\plugin\marketplace\dto\AuthorizationDto;
use app\common\plugin\marketplace\dto\CategoryDto;
use app\common\plugin\marketplace\dto\CloudAccountDto;
use app\common\plugin\marketplace\dto\DownloadDescriptorDto;
use app\common\plugin\marketplace\dto\LoginRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchResultDto;
use app\common\plugin\marketplace\dto\PluginDetailDto;
use app\common\plugin\marketplace\dto\PluginVersionDto;
use app\common\plugin\marketplace\dto\UpdateCheckDto;

/**
 * 旧云 API 防腐层；所有旧 endpoint 与字段名仅允许出现在此类。
 */
final class LegacyCloudMarketplaceAdapter implements PluginMarketplaceGateway
{
    private string $loginToken = '';

    /** @param callable(string, array, string): array $request */
    public function __construct(private readonly mixed $request, private readonly CloudAccountSession $session)
    {
    }

    public function setLoginToken(string $token): void
    {
        $this->loginToken = $token;
    }

    public function login(LoginRequestDto $request): CloudAccountDto
    {
        $tokenData = $this->call('/api/v2.token/build', ['username' => $request->account, 'password' => $request->password], '');
        $token = (string) ($tokenData['access_token'] ?? '');
        if ($token === '') {
            throw new MarketplaceException('云登录响应缺少 access token');
        }
        $member = $this->call('/api/v2.member/get', [], $token);
        $account = $this->accountDto($member);
        $this->session->login($account, $token);
        return $account;
    }

    public function logout(): void
    {
        $this->session->logout();
    }

    public function currentAccount(): ?CloudAccountDto
    {
        return $this->session->account();
    }

    public function categories(): array
    {
        return array_map(
            static fn (array $item): CategoryDto => new CategoryDto((int) ($item['id'] ?? $item['cateid'] ?? 0), (string) ($item['name'] ?? $item['title'] ?? '')),
            $this->call('/api/v2.plugins/cateList', [], $this->token())
        );
    }

    public function search(MarketplaceSearchRequestDto $request): MarketplaceSearchResultDto
    {
        $data = $this->call('/api/v2.plugins/getList', [
            'keywords' => $request->keyword,
            'page' => $request->page,
            'limit' => $request->limit,
            'cateid' => $request->categoryId ?? 0,
            'app_version' => $request->platformVersion,
        ], $this->token());
        $items = array_map(fn (array $item): PluginDetailDto => $this->detailDto($item), $data['list'] ?? []);
        return new MarketplaceSearchResultDto($items, (int) ($data['total'] ?? $data['count'] ?? count($items)), $request->page, $request->limit);
    }

    public function detail(string $name): PluginDetailDto
    {
        return $this->detailDto($this->call('/api/v2.plugins/detail', ['name' => $name], $this->token()));
    }

    public function versions(string $name): array
    {
        $data = $this->call('/api/v2.plugins/versionList', ['name' => $name], $this->token());
        return array_map(fn (array $item): PluginVersionDto => $this->versionDto($item, $name), $data['list'] ?? $data);
    }

    public function checkUpdates(array $installed): array
    {
        $data = $this->call('/api/v2.plugins/checkUpdate', ['plugins' => $installed], $this->token());
        return array_map(static fn (array $item): UpdateCheckDto => new UpdateCheckDto(
            (string) $item['name'],
            (string) ($item['current_version'] ?? $installed[$item['name']] ?? ''),
            (string) ($item['latest_version'] ?? $item['version'] ?? ''),
            (bool) ($item['update_available'] ?? true)
        ), $data['list'] ?? $data);
    }

    public function authorize(string $name, string $version): AuthorizationDto
    {
        $data = $this->call('/api/v2.plugins/auth', ['name' => $name, 'version' => $version], $this->token());
        return new AuthorizationDto($name, $version, (bool) ($data['authorized'] ?? $data['auth'] ?? false), (string) ($data['message'] ?? $data['msg'] ?? ''));
    }

    public function download(string $name, string $version): DownloadDescriptorDto
    {
        $data = $this->call('/api/v2.plugins/down', ['name' => $name, 'version' => $version], $this->token());
        return new DownloadDescriptorDto(
            (string) ($data['url'] ?? $data['file_url'] ?? ''),
            $name,
            (string) ($data['version'] ?? $version),
            strtolower((string) ($data['sha256'] ?? $data['hash'] ?? '')),
            isset($data['signature']) ? (string) $data['signature'] : null,
            isset($data['algorithm']) ? (string) $data['algorithm'] : null,
            (int) ($data['size'] ?? 0)
        );
    }

    private function call(string $endpoint, array $params, string $token): array
    {
        try {
            $response = ($this->request)($endpoint, $params, $token);
        } catch (\Throwable $exception) {
            throw new MarketplaceException('插件市场请求失败：' . $exception->getMessage(), 502, $exception);
        }
        $code = (int) ($response['code'] ?? 0);
        if ($code !== 200) {
            if ($code === 401) {
                $this->session->logout();
            }
            throw new MarketplaceException((string) ($response['msg'] ?? '插件市场响应错误'), $code ?: 502);
        }
        $data = $response['data'] ?? null;
        if (!is_array($data)) {
            throw new MarketplaceException('插件市场响应格式无效');
        }
        return $data;
    }

    private function token(): string
    {
        return $this->loginToken !== '' ? $this->loginToken : $this->session->token();
    }

    private function accountDto(array $item): CloudAccountDto
    {
        return new CloudAccountDto((int) ($item['id'] ?? $item['member_id'] ?? 0), (string) ($item['username'] ?? $item['account'] ?? ''), (string) ($item['nickname'] ?? $item['name'] ?? ''), (string) ($item['avatar'] ?? ''));
    }

    private function detailDto(array $item): PluginDetailDto
    {
        $name = (string) ($item['name'] ?? $item['plugin_name'] ?? '');
        $versions = array_map(fn (array $version): PluginVersionDto => $this->versionDto($version, $name), $item['versions'] ?? []);
        return new PluginDetailDto((int) ($item['id'] ?? $item['plugins_id'] ?? 0), $name, (string) ($item['title'] ?? $name), (string) ($item['description'] ?? ''), (string) ($item['author'] ?? ''), $versions);
    }

    private function versionDto(array $item, string $name): PluginVersionDto
    {
        return new PluginVersionDto((int) ($item['id'] ?? $item['version_id'] ?? 0), $name, (string) ($item['version'] ?? ''), (string) ($item['changelog'] ?? $item['content'] ?? ''), (bool) ($item['compatible'] ?? true));
    }
}
