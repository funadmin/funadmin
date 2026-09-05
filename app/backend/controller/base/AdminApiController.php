<?php

declare(strict_types=1);

namespace app\backend\controller\base;

use app\BaseController;
use app\backend\traits\AdminCrudRequest;
use app\backend\traits\AdminDataFormat;
use app\backend\traits\AdminDataScope;
use app\backend\traits\AdminJsonResponse;
use app\backend\traits\AdminPagination;
use app\backend\traits\AdminTree;

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

}
