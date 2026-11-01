<?php

declare(strict_types=1);

namespace app\admin\controller\base;

use app\BaseController;
use app\admin\traits\AdminCrudRequest;
use app\admin\traits\AdminDataFormat;
use app\admin\traits\AdminDataScope;
use app\admin\traits\AdminJsonResponse;
use app\admin\traits\AdminPagination;
use app\admin\traits\AdminTree;

/**
 * Admin Web CRUD API 基类：只提供协议与查询边界，不承载业务规则。
 */
abstract class AdminApiController extends BaseController
{
    use AdminCrudRequest;
    use AdminDataFormat;
    use AdminDataScope;
    use AdminJsonResponse;
    use AdminPagination;
    use AdminTree;

    /** 列表动作只公开精确协议码，不信任处理器提供的异常文本。 */
    protected function listActionFailure(\Throwable $error): \think\Response
    {
        if (!$error instanceof \InvalidArgumentException) {
            return $this->fail(msg: '服务器内部错误', code: 500);
        }
        $code = $error->getMessage();
        if (in_array($code, ['FORM_ACTION_FORBIDDEN', 'FORM_LIST_RECORD_FORBIDDEN', 'FORM_LIST_FIELD_FORBIDDEN'], true)) {
            return $this->fail(msg: $code, code: 403);
        }
        if ($code === 'FORM_SCHEMA_CONFLICT') {
            return $this->fail(msg: '表单发布版本已更新，请刷新后重试', data: ['code' => $code], code: 409);
        }
        $known = [
            'FORM_ACTION_NOT_REGISTERED', 'FORM_ACTION_NOT_DECLARED', 'FORM_ACTION_PARAMETER_NOT_ALLOWED',
            'FORM_ACTION_VERSION_MISMATCH', 'FORM_ACTION_IDEMPOTENCY_KEY_REQUIRED', 'FORM_ACTION_TIMEOUT',
            'FORM_ACTION_UNSAFE_RESULT', 'FORM_LIST_ACTION_ENTRY_REQUIRED', 'FORM_LIST_ACTION_ADAPTER_UNAVAILABLE',
            'FORM_LIST_REQUEST_INVALID', 'FORM_LIST_FILTER_INVALID', 'FORM_LIST_CATEGORY_UNAVAILABLE',
            'FORM_LIST_BUTTON_NOT_DECLARED', 'FORM_LIST_BUTTON_DISABLED', 'FORM_LIST_BINDING_UNSUPPORTED',
            'FORM_LIST_BATCH_UNSUPPORTED', 'FORM_LIST_SELECTION_COUNT_INVALID', 'FORM_LIST_PARAMETER_INVALID',
            'FORM_LIST_INPUT_INVALID', 'FORM_LIST_CONDITION_INVALID', 'FORM_LIST_CONFIRMATION_UNAVAILABLE',
            'FORM_LIST_CONFIRMATION_INVALID', 'FORM_LIST_ATOMIC_STORE_UNAVAILABLE', 'FORM_LIST_STORE_INVALID',
            'FORM_LIST_STORE_TRANSACTION_FORBIDDEN', 'FORM_LIST_STORE_UNAVAILABLE', 'FORM_LIST_STORE_STATE_CONFLICT',
            'FORM_LIST_IDEMPOTENCY_CONFLICT', 'FORM_LIST_RESULT_PENDING', 'FORM_LIST_RESULT_FAILED',
            'FORM_LIST_RESULT_UNKNOWN', 'FORM_LIST_RESULT_PROCESSING', 'FORM_LIST_RESOURCE_STALE', 'FORM_LIST_RESOURCE_FORBIDDEN',
        ];
        return $this->fail(msg: in_array($code, $known, true) ? $code : '列表动作执行失败', code: 422);
    }

}
