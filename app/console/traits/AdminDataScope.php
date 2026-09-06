<?php

declare(strict_types=1);

namespace app\console\traits;

use app\console\service\DataScopeService;

/**
 * 后台 CRUD 数据范围应用。
 */
trait AdminDataScope
{
    protected function applyDataScope($query, string $adminField = 'admin_id', string $departmentField = 'dept_id')
    {
        return (new DataScopeService())->apply($query, $adminField, $departmentField);
    }
}
