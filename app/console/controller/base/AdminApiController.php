<?php

declare(strict_types=1);

namespace app\console\controller\base;

use app\BaseController;
use app\console\traits\AdminCrudRequest;
use app\console\traits\AdminDataFormat;
use app\console\traits\AdminDataScope;
use app\console\traits\AdminJsonResponse;
use app\console\traits\AdminPagination;
use app\console\traits\AdminTree;

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
