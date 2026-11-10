<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use support\Context;
use support\Db;

/**
 * 只适用于不改 Container/Config/路由/常量的 SQLite 核心测试。
 * 在同一 PHP 进程里换一个全新的内存库；setUp 继续从模板恢复，tearDown 继续回滚各自的事务。
 * 前后均换库并销毁请求上下文，避免将本类的数据/结构交给下一个非隔离测试。
 */
trait RefreshCoreDatabase
{
    #[Before]
    protected function freshCoreDatabaseBeforeTest(): void
    {
        $this->reconnectCoreDatabase();
    }

    #[After]
    protected function freshCoreDatabaseAfterTest(): void
    {
        $this->reconnectCoreDatabase();
    }

    private function reconnectCoreDatabase(): void
    {
        $connection = Db::connection();
        self::assertSame('sqlite', $connection->getDriverName());
        self::assertSame(':memory:', $connection->getDatabaseName());
        while ($connection->transactionLevel() > 0) $connection->rollBack();
        $connection->disableQueryLog();
        $connection->flushQueryLog();
        $connection->disconnect();
        $connection->reconnect();
        Context::destroy();
        \app\common\model\concern\TokenSoftDeletingScope::flushDeletedTokenCache();
    }
}
