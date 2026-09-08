<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

use app\common\plugin\marketplace\dto\AuthorizationDto;
use app\common\plugin\marketplace\dto\CategoryDto;
use app\common\plugin\marketplace\dto\CloudAccountDto;
use app\common\plugin\marketplace\dto\DownloadDescriptorDto;
use app\common\plugin\marketplace\dto\LoginRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceProtocol;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchResultDto;
use app\common\plugin\marketplace\dto\PluginDetailDto;
use app\common\plugin\marketplace\dto\PluginVersionDto;
use app\common\plugin\marketplace\dto\UpdateCheckDto;
use app\common\plugin\marketplace\dto\UpdateCheckRequestDto;

/**
 * FunAdmin 原生插件市场 v3 客户端适配器。
 */
final class NativeMarketplaceAdapter implements PluginMarketplaceGateway
{
    /**
     * @param callable(string, array, string): array $request
     * @param null|callable(): int $clock
     */
    public function __construct(
        private readonly mixed $request,
        private readonly CloudAccountSession $session,
        private readonly string $platformVersion,
        private readonly string $phpVersion,
        private readonly mixed $clock = null
    ) {
    }

    public function login(LoginRequestDto $request): CloudAccountDto
    {
        $data = $this->call('/api/v3/auth/login', ['account' => $request->account, 'password' => $request->password], '');
        $accessToken = $this->requiredString($data, 'access_token');
        $refreshToken = $this->requiredString($data, 'refresh_token');
        $account = $this->accountDto($this->requiredArray($data, 'account'));
        $this->session->login($account, $accessToken, $refreshToken, $this->expiresAt($data));
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
        $data = $this->call('/api/v3/auth/refresh', ['refresh_token' => $refreshToken], '');
        $this->session->rotate(
            $this->requiredString($data, 'access_token'),
            $this->requiredString($data, 'refresh_token'),
            $this->expiresAt($data)
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
            fn (mixed $item): CategoryDto => $this->categoryDto($this->itemArray($item)),
            $this->items($this->call('/api/v3/plugins/categories', $this->context(), $this->token()))
        );
    }

    public function search(MarketplaceSearchRequestDto $request): MarketplaceSearchResultDto
    {
        $data = $this->call('/api/v3/plugins', $this->context([
            'keyword' => $request->keyword,
            'page' => $request->page,
            'limit' => $request->limit,
            'category_id' => $request->categoryId,
        ]), $this->token());
        $items = array_map(fn (mixed $item): PluginDetailDto => $this->detailDto($this->itemArray($item)), $this->items($data));
        return new MarketplaceSearchResultDto(
            $items,
            $this->requiredInt($data, 'total'),
            $this->requiredInt($data, 'page'),
            $this->requiredInt($data, 'limit')
        );
    }

    public function detail(string $code): PluginDetailDto
    {
        $this->assertCode($code);
        return $this->detailDto($this->call('/api/v3/plugins/' . $code, $this->context(['code' => $code]), $this->token()));
    }

    public function versions(string $code): array
    {
        $this->assertCode($code);
        $data = $this->call('/api/v3/plugins/' . $code . '/versions', $this->context(['code' => $code]), $this->token());
        return array_map(fn (mixed $item): PluginVersionDto => $this->versionDto($this->itemArray($item), $code), $this->items($data));
    }

    public function checkUpdates(UpdateCheckRequestDto $request): array
    {
        $data = $this->call('/api/v3/plugins/check-updates', $this->context(['installed' => $request->installed]), $this->token());
        return array_map(fn (mixed $item): UpdateCheckDto => $this->updateDto($this->itemArray($item)), $this->items($data));
    }

    public function authorize(string $code, string $version): AuthorizationDto
    {
        $this->assertCodeVersion($code, $version);
        $data = $this->call('/api/v3/plugins/' . $code . '/authorize', $this->context([
            'code' => $code,
            'code_version' => $version,
            'db_version' => '',
        ]), $this->token());
        if ($this->requiredString($data, 'code') !== $code || $this->requiredString($data, 'code_version') !== $version) {
            throw new MarketplaceException('云授权响应与请求插件版本不一致');
        }
        return new AuthorizationDto($code, $version, $this->requiredBool($data, 'authorized'), $this->requiredString($data, 'message', true));
    }

    public function download(string $code, string $version): DownloadDescriptorDto
    {
        $this->assertCodeVersion($code, $version);
        $data = $this->call('/api/v3/plugins/' . $code . '/download', $this->context([
            'code' => $code,
            'code_version' => $version,
            'db_version' => '',
        ]), $this->token());
        if ($this->requiredString($data, 'code') !== $code || $this->requiredString($data, 'code_version') !== $version) {
            throw new MarketplaceException('云下载描述与请求插件版本不一致');
        }
        return new DownloadDescriptorDto(
            $this->requiredString($data, 'url'),
            $code,
            $version,
            $this->requiredString($data, 'sha256'),
            $this->requiredString($data, 'signature'),
            $this->requiredString($data, 'signature_algorithm'),
            $this->requiredInt($data, 'size'),
            $this->requiredInt($data, 'manifest_schema'),
            $this->requiredString($data, 'package_format'),
            $this->requiredString($data, 'tree_hash'),
            $this->requiredString($data, 'database_capability', true)
        );
    }

    private function context(array $params = []): array
    {
        return $params + [
            'manifest_schema' => MarketplaceProtocol::MANIFEST_SCHEMA,
            'package_format' => MarketplaceProtocol::PACKAGE_FORMAT,
            'platform_version' => $this->platformVersion,
            'php_version' => $this->phpVersion,
        ];
    }

    private function call(string $endpoint, array $params, string $token): array
    {
        try {
            $response = ($this->request)($endpoint, $params, $token);
        } catch (\Throwable $exception) {
            throw new MarketplaceException('插件市场请求失败：' . $exception->getMessage(), 502, $exception);
        }
        if (!is_array($response) || !array_key_exists('code', $response) || !is_int($response['code'])) {
            throw new MarketplaceException('插件市场响应格式无效');
        }
        if ($response['code'] !== 200) {
            if ($response['code'] === 401) {
                $this->session->logout();
            }
            $message = isset($response['msg']) && is_string($response['msg']) ? $response['msg'] : '插件市场响应错误';
            throw new MarketplaceException($message, $response['code']);
        }
        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new MarketplaceException('插件市场响应 data 无效');
        }
        return $response['data'];
    }

    private function accountDto(array $item): CloudAccountDto
    {
        return new CloudAccountDto(
            $this->requiredInt($item, 'id'),
            $this->requiredString($item, 'username'),
            $this->requiredString($item, 'nickname'),
            $this->requiredString($item, 'avatar', true)
        );
    }

    private function categoryDto(array $item): CategoryDto
    {
        return new CategoryDto($this->requiredInt($item, 'id'), $this->requiredString($item, 'name'));
    }

    private function detailDto(array $item): PluginDetailDto
    {
        $code = $this->requiredString($item, 'code');
        $versions = array_map(
            fn (mixed $version): PluginVersionDto => $this->versionDto($this->itemArray($version), $code),
            $this->requiredList($item, 'versions')
        );
        return new PluginDetailDto(
            $this->requiredInt($item, 'id'),
            $code,
            $this->requiredString($item, 'name'),
            $this->requiredString($item, 'description', true),
            $this->requiredString($item, 'author', true),
            $versions
        );
    }

    private function versionDto(array $item, string $expectedCode): PluginVersionDto
    {
        $code = $this->requiredString($item, 'code');
        if ($code !== $expectedCode) {
            throw new MarketplaceException('插件版本响应 code 不一致');
        }
        return new PluginVersionDto(
            $this->requiredInt($item, 'id'),
            $code,
            $this->requiredString($item, 'code_version'),
            $this->requiredString($item, 'changelog', true),
            $this->requiredBool($item, 'compatible'),
            $this->requiredArray($item, 'requires'),
            $this->requiredString($item, 'compatible_range', true),
            $this->requiredString($item, 'published_at', true),
            $this->requiredString($item, 'sha256'),
            $this->requiredString($item, 'signature'),
            $this->requiredString($item, 'signature_algorithm'),
            $this->requiredInt($item, 'size'),
            $this->requiredInt($item, 'manifest_schema'),
            $this->requiredString($item, 'package_format'),
            $this->requiredString($item, 'tree_hash'),
            $this->requiredString($item, 'database_capability', true),
            $this->requiredArray($item, 'applications'),
            $this->requiredString($item, 'compatible_reason', true)
        );
    }

    private function updateDto(array $item): UpdateCheckDto
    {
        return new UpdateCheckDto(
            $this->requiredString($item, 'code'),
            $this->requiredString($item, 'installed_version'),
            $this->requiredString($item, 'latest_version'),
            $this->requiredBool($item, 'update_available'),
            $this->requiredBool($item, 'compatible'),
            $this->requiredBool($item, 'database_compatible'),
            $this->requiredBool($item, 'requires_manual_merge'),
            $this->requiredString($item, 'reason', true)
        );
    }

    private function items(array $data): array
    {
        return $this->requiredList($data, 'items');
    }

    private function requiredList(array $data, string $key): array
    {
        $value = $this->requiredArray($data, $key);
        if (!array_is_list($value)) {
            throw new MarketplaceException('插件市场响应字段 ' . $key . ' 必须是列表');
        }
        return $value;
    }

    private function requiredArray(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            throw new MarketplaceException('插件市场响应缺少有效字段 ' . $key);
        }
        return $data[$key];
    }

    private function requiredString(array $data, string $key, bool $allowEmpty = false): string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key]) || (!$allowEmpty && $data[$key] === '')) {
            throw new MarketplaceException('插件市场响应缺少有效字段 ' . $key);
        }
        return $data[$key];
    }

    private function requiredInt(array $data, string $key): int
    {
        if (!array_key_exists($key, $data) || !is_int($data[$key])) {
            throw new MarketplaceException('插件市场响应缺少有效字段 ' . $key);
        }
        return $data[$key];
    }

    private function requiredBool(array $data, string $key): bool
    {
        if (!array_key_exists($key, $data) || !is_bool($data[$key])) {
            throw new MarketplaceException('插件市场响应缺少有效字段 ' . $key);
        }
        return $data[$key];
    }

    private function itemArray(mixed $item): array
    {
        if (!is_array($item)) {
            throw new MarketplaceException('插件市场响应 item 无效');
        }
        return $item;
    }

    private function token(): string
    {
        return $this->session->token();
    }

    private function assertCode(string $code): void
    {
        try {
            \app\common\plugin\marketplace\dto\MarketplaceDtoValidator::pluginCode($code);
        } catch (\InvalidArgumentException $exception) {
            throw new MarketplaceException($exception->getMessage(), 422, $exception);
        }
    }

    private function assertCodeVersion(string $code, string $version): void
    {
        $this->assertCode($code);
        try {
            \app\common\plugin\marketplace\dto\MarketplaceDtoValidator::version($version);
        } catch (\InvalidArgumentException $exception) {
            throw new MarketplaceException($exception->getMessage(), 422, $exception);
        }
    }

    private function expiresAt(array $tokenData): int
    {
        $now = is_callable($this->clock) ? (int) ($this->clock)() : time();
        if (isset($tokenData['expires_at']) && is_int($tokenData['expires_at']) && $tokenData['expires_at'] > $now) {
            return $tokenData['expires_at'];
        }
        if (!isset($tokenData['expires_in']) || !is_int($tokenData['expires_in']) || $tokenData['expires_in'] < 1) {
            throw new MarketplaceException('云认证响应缺少有效 expires_in');
        }
        return $now + $tokenData['expires_in'];
    }
}
