<?php

declare(strict_types=1);

namespace Tests\Support;

/** 表单/业务模块测试使用与正式安装同源的完整核心结构，不再手写三张最小元数据表。 */
trait FormMetadataTables
{
    private function createMetadataTables(): void
    {
        TestDatabase::migrate();
    }
}
