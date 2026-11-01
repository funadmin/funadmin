<?php

declare(strict_types=1);

namespace app\common\service\identity;

use app\common\model\identity\ApplicationDatabase;
use app\common\model\identity\EnterpriseApplication;
use DomainException;
use GuzzleHttp\Client;
use InvalidArgumentException;

final class ApplicationDatabaseService
{
    public function __construct(private readonly EnterpriseApplicationUrlPolicy $urlPolicy = new EnterpriseApplicationUrlPolicy())
    {
    }

    /** @return array{mode:string,credential_ref:?string,health_path:?string} */
    public static function validateConfiguration(array $input): array
    {
        foreach (['dsn', 'password', 'username', 'host', 'port', 'database'] as $secret) {
            if (array_key_exists($secret, $input)) {
                throw new InvalidArgumentException('禁止保存 DSN、密码或任意数据库连接参数');
            }
        }
        $mode = (string) ($input['mode'] ?? 'shared');
        $credentialRef = trim((string) ($input['credentialRef'] ?? $input['credential_ref'] ?? ''));
        if (!in_array($mode, ['shared', 'dedicated', 'external'], true)) {
            throw new InvalidArgumentException('数据模式无效');
        }
        if ($mode === 'shared' && $credentialRef !== '' || $mode !== 'shared' && ($credentialRef === '' || !preg_match('#^(vault|secret|kms)://[A-Za-z0-9._/-]+$#', $credentialRef))) {
            throw new InvalidArgumentException('dedicated/external 必须且只能引用安全凭证');
        }
        $healthPath = trim((string) ($input['healthPath'] ?? $input['health_path'] ?? ''));
        if ($healthPath !== '' && (!str_starts_with($healthPath, '/') || str_starts_with($healthPath, '//'))) {
            throw new InvalidArgumentException('健康检查只能使用相对路径');
        }
        return ['mode' => $mode, 'credential_ref' => $credentialRef ?: null, 'health_path' => $healthPath ?: null];
    }

    public function save(int $tenantId, int $applicationId, array $input): array
    {
        $this->application($tenantId, $applicationId);
        $row = self::validateConfiguration($input) + ['tenant_id' => $tenantId, 'application_id' => $applicationId];
        $model = ApplicationDatabase::forTenant($tenantId)->where('application_id', $applicationId)->find() ?? new ApplicationDatabase();
        $model->save($row);
        return $model->hidden(['credential_ref'])->toArray();
    }

    public function get(int $tenantId, int $applicationId): array
    {
        $this->application($tenantId, $applicationId);
        $model = ApplicationDatabase::forTenant($tenantId)->where('application_id', $applicationId)->find();
        return $model ? $model->hidden(['credential_ref'])->toArray() : ['mode' => 'shared', 'credentialConfigured' => false];
    }

    public function health(int $tenantId, int $applicationId, bool $development = false): array
    {
        $application = $this->application($tenantId, $applicationId);
        $database = ApplicationDatabase::forTenant($tenantId)->where('application_id', $applicationId)->find();
        if (!$database || !$database->health_path) {
            return ['status' => 'unknown', 'message' => '未配置健康检查路径'];
        }
        $base = $this->urlPolicy->normalizeLaunchUrl((string) $application->runtime_type, (string) $application->launch_url, $development);
        if (str_starts_with($base, '/')) {
            throw new DomainException('内部相对应用由平台健康状态负责，不发起网络请求');
        }
        $parts = parse_url($base);
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $outbound = $this->urlPolicy->normalizeOutboundUrl($origin . $database->health_path, $development);
        $resolve = array_map(
            static fn (string $address): string => $outbound['host'] . ':' . $outbound['port'] . ':' . $address,
            $outbound['addresses']
        );
        try {
            $response = (new Client(['timeout' => 2.0, 'connect_timeout' => 1.0, 'allow_redirects' => false, 'http_errors' => false]))->request('HEAD', $outbound['url'], [
                'curl' => [CURLOPT_RESOLVE => $resolve],
            ]);
            $status = $response->getStatusCode() < 500 ? 'healthy' : 'unhealthy';
        } catch (\Throwable) {
            $status = 'unhealthy';
        }
        $database->save(['health_status' => $status, 'last_checked_at' => date('Y-m-d H:i:s')]);
        return ['status' => $status];
    }

    private function application(int $tenantId, int $applicationId): EnterpriseApplication
    {
        $application = EnterpriseApplication::forTenant($tenantId)->where('id', $applicationId)->find();
        if (!$application) {
            throw new DomainException('应用不存在或不属于当前租户');
        }
        return $application;
    }
}
