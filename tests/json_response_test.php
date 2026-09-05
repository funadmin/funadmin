<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';

use app\common\traits\JsonResponse;
use think\App;

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

$filesWithoutLocalResponse = [
    dirname(__DIR__) . '/app/common/controller/Api.php',
    dirname(__DIR__) . '/app/backend/controller/base/AdminApiController.php',
    dirname(__DIR__) . '/app/backend/controller/auth/AdminAuth.php',
    dirname(__DIR__) . '/app/backend/controller/system/SystemDict.php',
    dirname(__DIR__) . '/app/common/middleware/MApi.php',
];
foreach ($filesWithoutLocalResponse as $file) {
    $source = (string) file_get_contents($file);
    responseExpect(str_contains($source, 'use JsonResponse;'), basename($file) . ' 必须复用 JsonResponse');
    responseExpect(!preg_match('/function\s+(ok|fail|apiResponse)\s*\(/', $source), basename($file) . ' 不得重复实现响应方法');
}

echo "json response tests: PASS\n";
