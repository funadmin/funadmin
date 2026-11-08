<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** 生成模块的 PHP 契约文件（tests/business/*GeneratedTest.php）是独立脚本而非 PHPUnit 类，不能被测试套件收集。 */
final class PhpUnitSuiteScopeTest extends TestCase
{
    public function test_generated_contract_script_directories_are_excluded_from_suite(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__) . '/phpunit.xml');
        self::assertNotFalse($xml);
        $excluded = array_map('strval', $xml->xpath('/phpunit/testsuites/testsuite/exclude') ?: []);
        self::assertContains('tests/business', $excluded);
        self::assertContains('tests/generated', $excluded);
    }

    public function test_suite_does_not_list_generated_contract_scripts(): void
    {
        $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/vendor/bin/phpunit') . ' --filter ZzPhpUnitSuiteScopeNoSuchTest 2>&1');
        self::assertStringContainsString('No tests executed', $output);
        self::assertStringNotContainsString('PHP contract', $output, '契约脚本不得在收集测试时被执行');
        self::assertStringNotContainsString('cannot be found', $output);
    }
}
