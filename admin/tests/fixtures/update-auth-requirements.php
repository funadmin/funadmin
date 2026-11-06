<?php

declare(strict_types=1);

// 人工确认接口鉴权变化后运行：php tests/fixtures/update-auth-requirements.php
require dirname(__DIR__) . '/bootstrap.php';

(new \app\common\service\database\MigrationService())->run();
\support\Db::beginTransaction();
try {
    $data = \Tests\Support\AuthRequirementSnapshot::collect();
} finally {
    \support\Db::rollBack();
}
file_put_contents(__DIR__ . '/auth-requirements.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo count($data), " routes recorded\n";
