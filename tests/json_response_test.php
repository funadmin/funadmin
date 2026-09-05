<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';

use app\backend\traits\AdminCrudRequest;
use app\backend\traits\AdminDataFormat;
use app\backend\traits\AdminDataScope;
use app\backend\traits\AdminPagination;
use app\backend\traits\AdminTree;
use app\common\service\BearerTokenExtractor;
use app\common\traits\Curd;
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
        return $this->ok('查询成功', ['id' => 1]);
    }

    public function failureResponse(): \think\Response
    {
        return $this->fail('参数错误', ['field' => 'name'], 422);
    }
};

$success = $plainResponder->successResponse();
$successData = json_decode((string) $success->getContent(), true, 512, JSON_THROW_ON_ERROR);
responseExpect($success->getCode() === 200, 'ok HTTP 状态必须为 200');
responseExpect($successData['code'] === 200, 'ok 响应 code 必须为 200');
responseExpect($successData['msg'] === '查询成功', 'ok 必须保留成功消息');
responseExpect($successData['data'] === ['id' => 1], 'ok 必须保留响应数据');

$failure = $plainResponder->failureResponse();
$failureData = json_decode((string) $failure->getContent(), true, 512, JSON_THROW_ON_ERROR);
responseExpect($failure->getCode() === 422, 'fail HTTP 状态必须与 code 一致');
responseExpect($failureData['code'] === 422, 'fail 响应 code 必须为 422');
responseExpect($failureData['data'] === ['field' => 'name'], 'fail 必须支持错误详情');

$reflection = new ReflectionClass($plainResponder);
foreach (['ok', 'fail'] as $methodName) {
    $parameters = $reflection->getMethod($methodName)->getParameters();
    responseExpect(array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $parameters) === ['msg', 'data', 'code'], "{$methodName} 必须统一使用 msg、data、code 参数顺序");
}

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
foreach (['AdminCrudRequest', 'AdminPagination', 'AdminTree', 'AdminDataFormat', 'AdminDataScope'] as $traitName) {
    responseExpect(str_contains($adminApiSource, "use {$traitName};"), "后台 API 基类必须组合 {$traitName} Trait");
}
responseExpect(!preg_match('/protected function (page|pageSize|ids|normalizeIds|paginationData|buildTree|formatTime)\s*\(/', $adminApiSource), '后台 API 基类不得直接实现公共 CRUD 操作');
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

$traitUtilities = new class {
    use AdminCrudRequest;
    use AdminPagination;
    use AdminTree;
    use AdminDataFormat;

    public function flag(mixed $value): int
    {
        return $this->binaryStatus($value);
    }

    public function boolean(mixed $value): bool
    {
        return $this->booleanValue($value);
    }

    public function tree(array $rows): array
    {
        return $this->buildTree($rows);
    }
};
responseExpect($traitUtilities->flag('1') === 1 && $traitUtilities->flag('true') === 0, '数据库状态转换必须保持严格二值语义');
responseExpect($traitUtilities->boolean('true') && !$traitUtilities->boolean('false'), '前端布尔值转换必须复用数据格式化 Trait');
responseExpect($traitUtilities->tree([
    ['id' => 1, 'parentId' => 0],
    ['id' => 2, 'parentId' => 1],
]) === [['id' => 1, 'parentId' => 0, 'children' => [['id' => 2, 'parentId' => 1]]]], '树构建 Trait 必须正确组装父子节点');

$adminAuthSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/controller/auth/AdminAuth.php');
responseExpect(str_contains($adminAuthSource, 'use AdminTree;'), '后台认证菜单必须复用树构建 Trait');
responseExpect(str_contains($adminAuthSource, 'use AdminDataFormat;'), '后台认证菜单必须复用数据格式化 Trait');
responseExpect(!str_contains($adminAuthSource, 'private function menuTree('), '后台认证不得重复实现菜单树');

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
responseExpect(!str_contains($tokenSource, 'BearerTokenExtractor'), 'Refresh Token 必须只通过请求体传递');

$jumpSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/traits/Jump.php');
responseExpect(!preg_match('/protected function result\s*\(/', $jumpSource), 'Jump 只负责页面跳转，不得重复实现 JSON result');
responseExpect(!preg_match('/public function __construct\s*\(/', $jumpSource), 'Trait 不得覆盖宿主类构造方法');

$installerSource = (string) file_get_contents(dirname(__DIR__) . '/app/install/controller/Index.php');
responseExpect(str_contains($installerSource, 'use JsonResponse;'), '安装器 JSON 接口必须复用 JsonResponse');
responseExpect(!str_contains($installerSource, 'use Jump;'), '安装器不应再依赖页面跳转 Trait');
responseExpect(!preg_match('/\$this->result\s*\(/', $installerSource), '安装器不得继续调用旧 result 响应');
responseExpect(!preg_match('/\$this->error\s*\(/', $installerSource), '安装器不得继续调用异常式 error 响应');

$curdSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/traits/Curd.php');
responseExpect(str_contains($curdSource, 'declare(strict_types=1);'), 'Curd 必须仅面向 PHP 8.1+ 新 API');
foreach (['index', 'detail', 'create', 'update', 'status', 'recycle', 'restore', 'destroy', 'export'] as $methodName) {
    responseExpect(preg_match("/public function {$methodName}\\s*\\([^)]*\\): Response/", $curdSource) === 1, "Curd 必须统一实现 {$methodName} REST 动作");
}
responseExpect(!preg_match('/public function (add|edit|copy)\s*\(/', $curdSource), 'Curd 不得保留旧页面表单动作');
responseExpect(!str_contains($curdSource, 'buildParames'), 'Curd 不得保留旧 Layui 搜索协议');
responseExpect(!str_contains($curdSource, "save('php://output')"), 'Curd 不得直接输出文件并退出进程');

$backendSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/controller/Backend.php');
responseExpect(!str_contains($backendSource, 'use Curd;'), '旧 Backend 页面基类不得继续组合新 API Curd');
responseExpect(!str_contains($adminApiSource, 'use Curd;'), '后台 API 基类不得让所有控制器隐式暴露通用 CRUD 动作');

foreach (['SystemMemberGroup.php', 'SystemMemberLevel.php'] as $controllerFile) {
    $source = (string) file_get_contents(dirname(__DIR__) . '/app/backend/controller/system/' . $controllerFile);
    responseExpect(str_contains($source, 'use Curd;'), "{$controllerFile} 必须显式组合 Curd Trait");
    responseExpect(!preg_match('/public function (index|detail|create|update|status|recycle|restore|destroy|export)\s*\(/', $source), "{$controllerFile} 必须复用 Curd 公共动作");
    foreach (['crudModelClass', 'crudSearchFields', 'crudPayload', 'crudValidate', 'crudData'] as $hookName) {
        responseExpect(str_contains($source, "function {$hookName}("), "{$controllerFile} 必须实现 {$hookName} 扩展点");
    }
}

$curdReflection = new ReflectionClass(Curd::class);
responseExpect($curdReflection->hasMethod('index') && $curdReflection->hasMethod('destroy'), 'Curd Trait 必须可被 PHP 正常加载');

echo "json response tests: PASS\n";
