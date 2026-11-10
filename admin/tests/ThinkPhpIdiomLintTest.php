<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * 核心代码运行在 Eloquent 上：ThinkPHP 专有的查询构造器方法、操作符和模型属性会被 Eloquent
 * 静默忽略（whereOr、$autoWriteTimestamp）、误解（where(x, 'in', ...) 变成等值比较）或运行时报错
 * （hasWhere、无参 find()）。这里按源码扫描，阻止它们回到核心。cms 插件不在本扫描范围内。
 */
final class ThinkPhpIdiomLintTest extends TestCase
{
    private const PATTERNS = [
        'ThinkPHP builder method' => '/(->|::)(whereOr|hasWhere|whereTime|whereBetweenTime|whereExp|insertAll|setInc|setDec|findOrEmpty|fetchSql|buildSql|getLastSql|allowField|withAttr|withJoin|withoutField|failException)\s*\(/',
        'ThinkPHP field/column/order on a builder' => '/(?<!\$this)->(field|column|order)\s*\(/',
        'find() without a key (ThinkPHP fetch-first)' => '/->find\(\s*\)/',
        'select() used as a fetch' => '/->select\(\s*\)/',
        'ThinkPHP where operator' => '/(->|::)(where|orWhere)\([^;]*?,\s*[\'"](in|not in|between|not between|exp|null|not null)[\'"]\s*,/i',
        'ThinkPHP Db::name()' => '/\bDb::name\s*\(/',
        'ThinkPHP model property' => '/(protected|public)\s+\$(pk|autoWriteTimestamp|createTime|updateTime|deleteTime|jsonAssoc|defaultSoftDelete)\b/',
    ];

    public function test_core_code_uses_no_thinkphp_only_query_idioms(): void
    {
        $root = dirname(__DIR__);
        $violations = [];
        foreach (['app', 'support', 'plugins'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $path = substr($file->getPathname(), strlen($root) + 1);
                if ($file->getExtension() !== 'php' || str_contains($path, '/vendor/') || str_starts_with($path, 'app/cms/') || str_starts_with($path, 'plugins/cms/')) continue;
                foreach (file($file->getPathname()) ?: [] as $index => $line) {
                    foreach (self::PATTERNS as $label => $pattern) {
                        if (preg_match($pattern, $line) === 1) $violations[] = $label . ': ' . $path . ':' . ($index + 1) . ': ' . trim($line);
                    }
                }
            }
        }
        self::assertSame([], $violations);
    }

    public function test_patterns_catch_the_known_regressions(): void
    {
        $samples = [
            "\$query->whereNull('a')->whereOr('a', '<=', \$now)",
            "AiTask::hasWhere('conversation', \$scope)",
            "Plugin::where('code', \$code)->find()",
            "self::where('pid', 'in', \$pid)->where('status', 1)->select();",
            "self::where('pid', 'in', \$pid)->get();",
            "    protected \$updateTime = false;",
        ];
        foreach ($samples as $sample) {
            $matched = array_filter(self::PATTERNS, static fn (string $pattern): bool => preg_match($pattern, $sample) === 1);
            self::assertNotSame([], $matched, $sample);
        }
        foreach (["\$this->field(\$name)", "Member::find(\$id)", "\$query->select(['id'])", "->where('id', '<>', 1)"] as $allowed) {
            $matched = array_filter(self::PATTERNS, static fn (string $pattern): bool => preg_match($pattern, $allowed) === 1);
            self::assertSame([], $matched, $allowed);
        }
    }
}
