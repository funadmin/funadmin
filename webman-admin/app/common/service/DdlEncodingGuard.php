<?php
declare(strict_types=1);
namespace app\common\service;
use RuntimeException;

/** 只拒绝不安全的 DDL 编码，不修改应用或服务器的连接配置。 */
final class DdlEncodingGuard
{
    public static function assertUtf8(string $sql): void
    {
        if (str_starts_with($sql, "\xEF\xBB\xBF")) {
            throw new RuntimeException('Migration/DDL 不允许 UTF-8 BOM');
        }
        if (preg_match('//u', $sql) !== 1) {
            throw new RuntimeException('Migration/DDL 必须是有效 UTF-8');
        }
    }

    public static function assertSession(array $session): void
    {
        foreach (['client', 'connection_charset', 'results_charset'] as $key) {
            if (($session[$key] ?? null) !== 'utf8mb4') {
                throw new RuntimeException('Migration/DDL 实际连接必须使用 utf8mb4：' . $key);
            }
        }
    }

    public static function check(object $connection, string $sql): void
    {
        self::assertUtf8($sql);
        self::assertSession($connection->query('SELECT @@character_set_client client, @@character_set_connection connection_charset, @@character_set_results results_charset', [], true)[0] ?? []);
    }
}
