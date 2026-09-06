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
    /**
     * @param callable(string, array, string): array $request
     * @param null|callable(): int $clock
     */
    public function __construct(
        private readonly mixed $request,
        private readonly CloudAccountSession $session,
        private readonly mixed $clock = null
    ) {
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
        $this->session->login(
            $account,
            $token,
            (string) ($tokenData['refresh_token'] ?? ''),
            $this->expiresAt($tokenData)
        );
        return $account;
    }

    public function refreshToken(): CloudAccountDto
    {
        $refreshToken = $this->session->refreshToken();
        $account = $this->session->account();
        if ($refreshToken === '' || $account === null) {
            $this->session->logout();
            throw new MarketplaceException('云账号会话缺少 refresh token', 401);
        }
        $tokenData = $this->call('/api/v2.token/refresh', ['refresh_token' => $refreshToken], '');
        $accessToken = (string) ($tokenData['access_token'] ?? '');
        if ($accessToken === '') {
            $this->session->logout();
            throw new MarketplaceException('云刷新响应缺少 access token', 401);
        }
        $this->session->rotate(
            $accessToken,
            (string) ($tokenData['refresh_token'] ?? $refreshToken),
            $this->expiresAt($tokenData)
        );
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

    public function detail(string $code): PluginDetailDto
    {
        return $this->detailDto($this->call('/api/v2.plugins/detail', ['name' => $code], $this->token()));
    }

    public function versions(string $code): array
    {
        $data = $this->call('/api/v2.plugins/versionList', ['name' => $code], $this->token());
        return array_map(fn (array $item): PluginVersionDto => $this->versionDto($item, $code), $data['list'] ?? $data);
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

    public function authorize(string $code, string $version): AuthorizationDto
    {
        $data = $this->call('/api/v2.plugins/auth', ['name' => $code, 'version' => $version], $this->token());
        return new AuthorizationDto($code, $version, (bool) ($data['authorized'] ?? $data['auth'] ?? false), (string) ($data['message'] ?? $data['msg'] ?? ''));
    }

    public function download(string $code, string $version): DownloadDescriptorDto
    {
        $data = $this->call('/api/v2.plugins/down', ['name' => $code, 'version' => $version], $this->token());
        $descriptorVersion = (string) ($data['version'] ?? '');
        if ($descriptorVersion === '' || $descriptorVersion !== $version) {
            throw new MarketplaceException('云下载描述版本与请求版本不一致');
        }
        return new DownloadDescriptorDto(
            (string) ($data['url'] ?? $data['file_url'] ?? ''),
            $code,
            $descriptorVersion,
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
        return $this->session->token();
    }

    private function accountDto(array $item): CloudAccountDto
    {
        return new CloudAccountDto((int) ($item['id'] ?? $item['member_id'] ?? 0), (string) ($item['username'] ?? $item['account'] ?? ''), (string) ($item['nickname'] ?? $item['name'] ?? ''), (string) ($item['avatar'] ?? ''));
    }

    private function detailDto(array $item): PluginDetailDto
    {
        $code = (string) ($item['name'] ?? $item['plugin_name'] ?? '');
        $versions = array_map(fn (array $version): PluginVersionDto => $this->versionDto($version, $code), $item['versions'] ?? []);
        return new PluginDetailDto((int) ($item['id'] ?? $item['plugins_id'] ?? 0), $code, (string) ($item['title'] ?? $code), (string) ($item['description'] ?? ''), (string) ($item['author'] ?? ''), $versions);
    }

    private function versionDto(array $item, string $code): PluginVersionDto
    {
        $requires = $item['requires'] ?? [];
        if (is_string($requires)) {
            $decoded = json_decode($requires, true);
            $requires = is_array($decoded) ? $decoded : ['funadmin' => $requires];
        }
        return new PluginVersionDto(
            (int) ($item['id'] ?? $item['version_id'] ?? 0),
            $code,
            (string) ($item['version'] ?? ''),
            (string) ($item['changelog'] ?? $item['content'] ?? ''),
            (bool) ($item['compatible'] ?? true),
            is_array($requires) ? $requires : [],
            (string) ($item['compatible_range'] ?? $item['funadmin_range'] ?? ''),
            (string) ($item['published_at'] ?? $item['create_time'] ?? ''),
            strtolower((string) ($item['sha256'] ?? $item['hash'] ?? '')),
            isset($item['signature']) ? (string) $item['signature'] : null,
            isset($item['signature_algorithm']) || isset($item['algorithm'])
                ? (string) ($item['signature_algorithm'] ?? $item['algorithm'])
                : null,
            (int) ($item['size'] ?? 0)
        );
    }

    private function expiresAt(array $tokenData): int
    {
        $expiresAt = (int) ($tokenData['expires_at'] ?? 0);
        $now = is_callable($this->clock) ? (int) ($this->clock)() : time();
        return $expiresAt > $now ? $expiresAt : $now + max(1, (int) ($tokenData['expires_in'] ?? 3600));
    }
}
