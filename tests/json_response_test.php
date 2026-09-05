<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';

use app\common\service\BearerTokenExtractor;
use app\common\traits\JsonResponse;
use think\App;
use think\Request;

function responseExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$app = new App();

$plainResponder = new class {
    use JsonResponse;

    public function successResponse(): \think\Response
    {
        return $this->ok(['id' => 1]);
    }

    public function failureResponse(): \think\Response
    {
        return $this->fail('参数错误', 422, ['field' => 'name']);
    }
};

$success = $plainResponder->successResponse();
$successData = json_decode((string) $success->getContent(), true, 512, JSON_THROW_ON_ERROR);
responseExpect($success->getCode() === 200, 'ok HTTP 状态必须为 200');
responseExpect($successData['code'] === 200, 'ok 响应 code 必须为 200');
responseExpect($successData['msg'] === '操作成功', 'ok 必须使用默认成功消息');
responseExpect($successData['data'] === ['id' => 1], 'ok 必须保留响应数据');

$failure = $plainResponder->failureResponse();
$failureData = json_decode((string) $failure->getContent(), true, 512, JSON_THROW_ON_ERROR);
responseExpect($failure->getCode() === 422, 'fail HTTP 状态必须与 code 一致');
responseExpect($failureData['code'] === 422, 'fail 响应 code 必须为 422');
responseExpect($failureData['data'] === ['field' => 'name'], 'fail 必须支持错误详情');

$headerResponder = new class {
    use JsonResponse;

    protected function responseHeaders(): array
    {
        return ['X-CSRF-TOKEN' => 'test-token'];
    }

    public function response(): \think\Response
    {
        return $this->ok();
    }
};
responseExpect($headerResponder->response()->getHeader('X-CSRF-TOKEN') === 'test-token', '调用方必须能统一扩展响应头');

$plainResponseFiles = [
    dirname(__DIR__) . '/app/common/controller/Api.php',
    dirname(__DIR__) . '/app/common/middleware/MApi.php',
];
foreach ($plainResponseFiles as $file) {
    $source = (string) file_get_contents($file);
    responseExpect(str_contains($source, 'use JsonResponse;'), basename($file) . ' 必须复用 JsonResponse');
    responseExpect(!preg_match('/function\s+(ok|fail|apiResponse|responseHeaders)\s*\(/', $source), basename($file) . ' 不得重复实现响应方法');
}

$adminResponseFiles = [
    dirname(__DIR__) . '/app/backend/controller/base/AdminApiController.php',
    dirname(__DIR__) . '/app/backend/controller/auth/AdminAuth.php',
];
foreach ($adminResponseFiles as $file) {
    $source = (string) file_get_contents($file);
    responseExpect(str_contains($source, 'use AdminJsonResponse;'), basename($file) . ' 必须复用后台 JSON 响应 Trait');
    responseExpect(!preg_match('/function\s+(ok|fail|apiResponse|responseHeaders)\s*\(/', $source), basename($file) . ' 不得重复实现响应方法');
}

$adminApiSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/controller/base/AdminApiController.php');
responseExpect(str_contains($adminApiSource, 'protected function normalizeIds('), '后台基类必须提供统一 ID 规范化');
responseExpect(str_contains($adminApiSource, 'protected function paginationData('), '后台基类必须提供统一分页数据组装');
$adminUtilities = new class($app) extends \app\backend\controller\base\AdminApiController {
    public function normalizedIds(mixed $ids): array
    {
        return $this->normalizeIds($ids);
    }

    public function pageResult(array $list, int $total, int $page, int $pageSize): array
    {
        return $this->paginationData($list, $total, $page, $pageSize);
    }
};
responseExpect($adminUtilities->normalizedIds('3,1,3,0,-2') === [3, 1], 'ID 规范化必须过滤无效值并去重');
responseExpect($adminUtilities->pageResult([['id' => 1]], 3, 2, 10) === [
    'list' => [['id' => 1]],
    'total' => 3,
    'page' => 2,
    'pageSize' => 10,
], '分页数据结构必须保持统一');

$systemDictSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/controller/system/SystemDict.php');
responseExpect(str_contains($systemDictSource, 'extends AdminApiController'), 'SystemDict 必须复用后台 API 基类');
responseExpect(!preg_match('/private function (ids|pageSize|formatTime)\s*\(/', $systemDictSource), 'SystemDict 不得重复实现后台基础方法');

$systemAdminSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/controller/system/SystemAdmin.php');
$systemRoleSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/controller/system/SystemRole.php');
responseExpect(!str_contains($systemAdminSource, 'private function arrayIds('), 'SystemAdmin 不得重复实现 ID 规范化');
responseExpect(!str_contains($systemRoleSource, 'private function arrayIds('), 'SystemRole 不得重复实现 ID 规范化');

$responseExtractorSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/service/BearerTokenExtractor.php');
responseExpect(str_contains($responseExtractorSource, 'class BearerTokenExtractor'), '必须提供统一 Bearer Token 解析器');
$extractor = new BearerTokenExtractor();
responseExpect($extractor->extract((new Request())->withHeader(['Authorization' => 'Bearer token-value'])) === 'token-value', 'Bearer Token 解析必须返回令牌值');
responseExpect($extractor->extract((new Request())->withHeader(['Authorization' => 'Basic token-value'])) === null, '非 Bearer 认证必须被拒绝');

$middlewareSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/middleware/MApi.php');
$tokenSource = (string) file_get_contents(dirname(__DIR__) . '/app/api/controller/v2/Token.php');
responseExpect(str_contains($middlewareSource, 'BearerTokenExtractor'), 'MApi 必须复用 Bearer Token 解析器');
responseExpect(str_contains($tokenSource, 'BearerTokenExtractor'), 'Token 刷新必须复用 Bearer Token 解析器');

 echo "json response tests: PASS\n";
