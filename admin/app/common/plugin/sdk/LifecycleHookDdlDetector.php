<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use app\common\service\database\MigrationService;
use PhpToken;
use RuntimeException;

/**
 * SDK 规则的静态检查（plugin:validate）：生命周期钩子 install / enabled / beforeUpdate / afterUpdate
 * 不得修改数据库结构；结构变更只能写在插件 migration 里，否则 migration 执行时会因“列已存在”失败。
 *
 * 从入口类的钩子方法出发，沿 self:: / static:: / $this-> / 类名:: / (new 类)-> 调用进入插件自带的类
 * （plugins\<code>\… 与 app\…，文件须位于插件目录内），在可达方法中查找：
 * - Schema Builder 的结构变更调用（create、table、drop*、rename 等）：接收者是 schema() / getSchemaBuilder()
 *   调用、由它们赋值的变量、类型为 Schema\Builder 的参数，或 Schema 门面；
 * - 使用 Schema Blueprint；
 * - statement() / unprepared() / exec() 执行的字面量 SQL 按 MigrationService 的 DDL 规则判定为 DDL；
 *   statement() / unprepared() 的 SQL 不是字面量时无法证明不是 DDL，同样拒绝。
 * 只读的结构探测（hasTable、hasColumn、getColumnListing 等）与数据读写不受限制。
 * 静态分析看不到的路径（变量接收者上的方法、插件外的类、动态调用）由 LifecycleDdlGuard 在执行钩子时兜底。
 */
final class LifecycleHookDdlDetector
{
    public const HOOKS = ['install', 'enabled', 'beforeUpdate', 'afterUpdate'];
    private const BUILDER_MUTATIONS = ['create', 'createdatabase', 'table', 'drop', 'dropifexists', 'dropcolumns', 'dropalltables', 'dropallviews', 'dropalltypes', 'dropdatabaseifexists', 'rename'];
    private const BUILDER_SOURCES = ['schema', 'getschemabuilder'];
    private const BUILDER_TYPES = ['illuminate\database\schema\builder', 'illuminate\database\schema\mysqlbuilder', 'illuminate\database\schema\sqlitebuilder', 'illuminate\database\schema\postgresbuilder', 'illuminate\database\schema\sqlserverbuilder', 'illuminate\database\schema\mariadbbuilder'];
    private const BLUEPRINT = 'illuminate\database\schema\blueprint';
    private const SCHEMA_FACADE = 'illuminate\support\facades\schema';
    private const RAW_SQL = ['statement', 'unprepared', 'exec'];
    private const MAX_DEPTH = 16;

    private string $directory = '';
    private string $code = '';
    /** @var array<string, array{namespace: string, uses: array<string, string>, parent: ?string, methods: array<string, array{params: array<string, string>, body: list<PhpToken>}>}|null> */
    private array $classes = [];
    /** @var array<string, true> */
    private array $parsedFiles = [];

    /**
     * @return list<string> 每条形如 “enabled() → app\x\Schema::install() → app\x\Schema::create()：Schema Builder create()”
     */
    public function violations(string $pluginDirectory, string $entryFile): array
    {
        $directory = realpath($pluginDirectory);
        $entry = realpath($pluginDirectory . DIRECTORY_SEPARATOR . $entryFile);
        if ($directory === false || $entry === false || !str_starts_with($entry, $directory . DIRECTORY_SEPARATOR) || !is_file($entry)) {
            throw new RuntimeException('插件入口文件不存在或越界');
        }
        $this->directory = $directory;
        $this->code = basename($directory);
        $this->classes = $this->parsedFiles = [];
        $declared = $this->parseFile($entry);
        $violations = [];
        foreach ($declared as $class) {
            foreach (self::HOOKS as $hook) {
                if (!isset($this->classes[$class]['methods'][strtolower($hook)])) continue;
                $visited = [];
                $this->walk($class, strtolower($hook), [$hook . '()'], $visited, $violations);
            }
        }
        return array_values(array_unique($violations));
    }

    /** @param array<string, true> $visited @param list<string> $violations */
    private function walk(string $class, string $method, array $frames, array &$visited, array &$violations): void
    {
        $key = $class . '::' . $method;
        if (isset($visited[$key]) || count($frames) > self::MAX_DEPTH) return;
        $visited[$key] = true;
        [$owner, $definition] = $this->method($class, $method) ?? [null, null];
        if ($definition === null) return;
        $context = $this->classes[$owner];
        $tokens = $definition['body'];
        $count = count($tokens);
        $builders = [];
        foreach ($definition['params'] as $variable => $type) {
            if (in_array(strtolower($this->resolve($type, $context)), self::BUILDER_TYPES, true)) $builders[$variable] = true;
        }
        $report = static function (string $reason) use ($frames, &$violations): void {
            $violations[] = implode(' → ', $frames) . '：' . $reason;
        };
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            $next = $tokens[$index + 1] ?? null;
            // $x = …schema(); / $x = …getSchemaBuilder();
            if ($token->is(T_VARIABLE) && $next?->text === '=' && $this->assignsBuilder($tokens, $index + 2)) {
                $builders[$token->text] = true;
            }
            if ($this->isName($token) && !in_array(strtolower($token->text), ['self', 'static', 'parent'], true)
                && strtolower($this->resolve($token->text, $context)) === self::BLUEPRINT) {
                $report('使用 Schema Blueprint');
            }
            $call = $this->callAt($tokens, $index);
            if ($call === null) continue;
            [$operator, $name, $open] = $call;
            $lower = strtolower($name);
            if ($operator === '->' && in_array($lower, self::BUILDER_MUTATIONS, true) && $this->builderReceiver($tokens, $index - 1, $builders)) {
                $report('Schema Builder ' . $name . '()');
            }
            if ($operator === '::' && in_array($lower, self::BUILDER_MUTATIONS, true) && $index >= 1 && $this->isName($tokens[$index - 1])
                && strtolower($this->resolve($tokens[$index - 1]->text, $context)) === self::SCHEMA_FACADE) {
                $report('Schema 门面 ' . $name . '()');
            }
            if (in_array($lower, self::RAW_SQL, true)) {
                $sql = $this->literalArgument($tokens, $open);
                if ($sql !== null) {
                    $keyword = self::ddlKeyword($sql);
                    if ($keyword !== null) $report($name . '() 执行 DDL：' . $keyword);
                } elseif ($lower !== 'exec') {
                    $report($name . '() 的 SQL 不是字面量，无法证明不是 DDL');
                }
            }
            $target = $this->callTarget($tokens, $index, $operator, $owner, $context);
            if ($target !== null && $this->method($target, $lower) !== null) {
                $this->walk($target, $lower, [...$frames, $target . '::' . $name . '()'], $visited, $violations);
            }
        }
        // new 类(…) 进入构造函数；(new 类(…))->方法(…) 进入该方法。
        for ($index = 0; $index < $count; $index++) {
            if (!$tokens[$index]->is(T_NEW) || !isset($tokens[$index + 1]) || !$this->isName($tokens[$index + 1])) continue;
            $class = $this->classOf($tokens[$index + 1]->text, $owner, $context);
            if ($class === null) continue;
            if ($this->method($class, '__construct') !== null) {
                $this->walk($class, '__construct', [...$frames, $class . '::__construct()'], $visited, $violations);
            }
            $after = $index + 2;
            if (($tokens[$after] ?? null)?->text === '(') $after = $this->closing($tokens, $after) + 1;
            if (($tokens[$after] ?? null)?->text === ')' && ($tokens[$after + 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])
                && ($tokens[$after + 2] ?? null)?->is(T_STRING) && ($tokens[$after + 3] ?? null)?->text === '(') {
                $method = $tokens[$after + 2]->text;
                if ($this->method($class, strtolower($method)) !== null) {
                    $this->walk($class, strtolower($method), [...$frames, $class . '::' . $method . '()'], $visited, $violations);
                }
            }
        }
    }

    /** 位置 $index 是 “-> 名称 (” 或 “:: 名称 (” 中的名称时返回 [运算符, 名称, '(' 的位置]。 */
    private function callAt(array $tokens, int $index): ?array
    {
        $token = $tokens[$index];
        $previous = $tokens[$index - 1] ?? null;
        if (!$token->is(T_STRING) || ($tokens[$index + 1] ?? null)?->text !== '(' || $previous === null) return null;
        if ($previous->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])) return ['->', $token->text, $index + 1];
        if ($previous->is(T_DOUBLE_COLON)) return ['::', $token->text, $index + 1];
        return null;
    }

    /** 调用目标类：self/static/parent::、类名::、$this->（同类）。 */
    private function callTarget(array $tokens, int $index, string $operator, string $owner, array $context): ?string
    {
        $receiver = $tokens[$index - 2] ?? null;
        if ($receiver === null) return null;
        if ($operator === '->') return $receiver->is(T_VARIABLE) && $receiver->text === '$this' ? $owner : null;
        if (!$this->isName($receiver)) return null;
        return $this->classOf($receiver->text, $owner, $context);
    }

    private function classOf(string $name, string $owner, array $context): ?string
    {
        $lower = strtolower($name);
        if ($lower === 'self' || $lower === 'static') return $owner;
        if ($lower === 'parent') return $context['parent'];
        $class = $this->resolve($name, $context);
        return $this->load($class) ? $class : null;
    }

    /** 接收者是 Schema Builder：schema() / getSchemaBuilder() 调用的结果，或已知的 Builder 变量。 */
    private function builderReceiver(array $tokens, int $index, array $builders): bool
    {
        $receiver = $tokens[$index - 1] ?? null;
        if ($receiver === null) return false;
        if ($receiver->is(T_VARIABLE)) return isset($builders[$receiver->text]);
        if ($receiver->text !== ')') return false;
        $open = $this->opening($tokens, $index - 1);
        $source = $tokens[$open - 1] ?? null;
        return $source !== null && $source->is(T_STRING) && in_array(strtolower($source->text), self::BUILDER_SOURCES, true);
    }

    /** 赋值右侧以 schema() / getSchemaBuilder() 调用结束（紧接分号）。 */
    private function assignsBuilder(array $tokens, int $start): bool
    {
        for ($index = $start; isset($tokens[$index]) && $tokens[$index]->text !== ';'; $index++) {
            if ($tokens[$index]->is(T_STRING) && in_array(strtolower($tokens[$index]->text), self::BUILDER_SOURCES, true)
                && ($tokens[$index + 1] ?? null)?->text === '(') {
                $close = $this->closing($tokens, $index + 1);
                if (($tokens[$close + 1] ?? null)?->text === ';') return true;
            }
        }
        return false;
    }

    /** 第一个实参是单独的字符串字面量（不含插值）时返回其值。 */
    private function literalArgument(array $tokens, int $open): ?string
    {
        $argument = $tokens[$open + 1] ?? null;
        $after = $tokens[$open + 2] ?? null;
        if ($argument === null || !$argument->is(T_CONSTANT_ENCAPSED_STRING) || !in_array($after?->text, [')', ','], true)) return null;
        $quote = $argument->text[0];
        $body = substr($argument->text, 1, -1);
        return $quote === "'" ? str_replace(["\\\\", "\\'"], ["\\", "'"], $body) : stripcslashes($body);
    }

    /** 与 MigrationService 相同的 DDL 规则；词法器拒绝的 SQL 退回到首个关键字判断。 */
    private static function ddlKeyword(string $sql): ?string
    {
        try {
            if (!MigrationService::isDdlStatement($sql)) return null;
        } catch (\Throwable) {
            if (preg_match('/\A[\s(]*(create|alter|drop|truncate|rename|prepare|execute)\b/i', $sql) !== 1) return null;
        }
        preg_match('/[A-Za-z]+/', $sql, $match);
        return strtoupper($match[0] ?? 'DDL');
    }

    /** @return array{0: string, 1: array}|null 方法所在类与定义（沿插件内的父类查找）。 */
    private function method(string $class, string $method): ?array
    {
        for ($depth = 0; $class !== null && $depth < self::MAX_DEPTH && $this->load($class); $depth++) {
            if (isset($this->classes[$class]['methods'][$method])) return [$class, $this->classes[$class]['methods'][$method]];
            $class = $this->classes[$class]['parent'];
        }
        return null;
    }

    /** 只加载插件目录内的类：plugins\<code>\… → 插件根，app\… → 插件根/app/…。 */
    private function load(?string $class): bool
    {
        if ($class === null) return false;
        if (array_key_exists($class, $this->classes)) return $this->classes[$class] !== null;
        $this->classes[$class] = null;
        $prefix = 'plugins\\' . $this->code . '\\';
        $relative = match (true) {
            str_starts_with($class, $prefix) => substr($class, strlen($prefix)),
            str_starts_with($class, 'app\\') => 'app\\' . substr($class, 4),
            default => null,
        };
        if ($relative === null) return false;
        $file = realpath($this->directory . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php');
        if ($file === false || !str_starts_with($file, $this->directory . DIRECTORY_SEPARATOR)) return false;
        $this->parseFile($file);
        return $this->classes[$class] !== null;
    }

    /** @return list<string> 文件中声明的类 */
    private function parseFile(string $file): array
    {
        if (isset($this->parsedFiles[$file])) return [];
        $this->parsedFiles[$file] = true;
        $source = file_get_contents($file);
        if (!is_string($source)) return [];
        $tokens = array_values(array_filter(PhpToken::tokenize($source), static fn (PhpToken $token): bool => !$token->isIgnorable()));
        $namespace = '';
        $uses = [];
        $declared = [];
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token->is(T_NAMESPACE) && $this->isName($tokens[$index + 1] ?? null)) {
                $namespace = ltrim($tokens[$index + 1]->text, '\\');
                $uses = [];
                continue;
            }
            if ($token->is(T_USE)) {
                $index = $this->parseUse($tokens, $index, $uses);
                continue;
            }
            if ($token->is([T_CLASS, T_TRAIT, T_ENUM]) && ($tokens[$index + 1] ?? null)?->is(T_STRING)
                && !($tokens[$index - 1] ?? null)?->is([T_DOUBLE_COLON, T_NEW])) {
                $class = ($namespace === '' ? '' : $namespace . '\\') . $tokens[$index + 1]->text;
                $parent = null;
                $cursor = $index + 2;
                if (($tokens[$cursor] ?? null)?->is(T_EXTENDS) && $this->isName($tokens[$cursor + 1] ?? null)) {
                    $parent = $this->resolve($tokens[$cursor + 1]->text, ['namespace' => $namespace, 'uses' => $uses]);
                }
                while ($cursor < $count && $tokens[$cursor]->text !== '{') $cursor++;
                $end = $this->closing($tokens, $cursor);
                $this->classes[$class] = ['namespace' => $namespace, 'uses' => $uses, 'parent' => $parent, 'methods' => $this->methods($tokens, $cursor, $end)];
                $declared[] = $class;
                $index = $end;
            }
        }
        return $declared;
    }

    /** 顶层 use（含分组 use A\{B, C as D}）；use function / use const 忽略。 */
    private function parseUse(array $tokens, int $index, array &$uses): int
    {
        $index++;
        if (($tokens[$index] ?? null)?->is([T_FUNCTION, T_CONST])) {
            while (isset($tokens[$index]) && $tokens[$index]->text !== ';') $index++;
            return $index;
        }
        $prefix = '';
        while (isset($tokens[$index]) && $tokens[$index]->text !== ';') {
            $token = $tokens[$index];
            if ($this->isName($token)) {
                $name = ltrim($token->text, '\\');
                if (($tokens[$index + 1] ?? null)?->is(T_NS_SEPARATOR) && ($tokens[$index + 2] ?? null)?->text === '{') {
                    $prefix = $name . '\\';
                    $index += 3;
                    continue;
                }
                $alias = substr($name, (int) strrpos('\\' . $name, '\\'));
                if (($tokens[$index + 1] ?? null)?->is(T_AS) && ($tokens[$index + 2] ?? null)?->is(T_STRING)) {
                    $alias = $tokens[$index + 2]->text;
                    $index += 2;
                }
                $uses[strtolower($alias)] = $prefix . $name;
            } elseif ($token->text === '}') {
                $prefix = '';
            }
            $index++;
        }
        return $index;
    }

    /** @return array<string, array{params: array<string, string>, body: list<PhpToken>}> */
    private function methods(array $tokens, int $open, int $close): array
    {
        $methods = [];
        $depth = 0;
        for ($index = $open; $index <= $close; $index++) {
            $token = $tokens[$index];
            if ($this->opens($token)) $depth++;
            if ($token->text === '}') $depth--;
            if ($depth !== 1 || !$token->is(T_FUNCTION)) continue;
            $nameIndex = $index + 1;
            if (($tokens[$nameIndex] ?? null)?->text === '&') $nameIndex++;
            if (!($tokens[$nameIndex] ?? null)?->is(T_STRING) || ($tokens[$nameIndex + 1] ?? null)?->text !== '(') continue;
            $paramsEnd = $this->closing($tokens, $nameIndex + 1);
            $params = [];
            $type = null;
            for ($cursor = $nameIndex + 2; $cursor < $paramsEnd; $cursor++) {
                if ($this->isName($tokens[$cursor])) $type = $tokens[$cursor]->text;
                if ($tokens[$cursor]->is(T_VARIABLE)) {
                    if ($type !== null) $params[$tokens[$cursor]->text] = $type;
                    $type = null;
                }
                if ($tokens[$cursor]->text === ',') $type = null;
            }
            $bodyStart = $paramsEnd + 1;
            while ($bodyStart <= $close && $tokens[$bodyStart]->text !== '{' && $tokens[$bodyStart]->text !== ';') $bodyStart++;
            if ($tokens[$bodyStart]->text === ';') {
                $index = $bodyStart;
                continue;
            }
            $bodyEnd = $this->closing($tokens, $bodyStart);
            $methods[strtolower($tokens[$nameIndex]->text)] = ['params' => $params, 'body' => array_slice($tokens, $bodyStart + 1, $bodyEnd - $bodyStart - 1)];
            $index = $bodyEnd;
        }
        return $methods;
    }

    private function resolve(string $name, array $context): string
    {
        if (str_starts_with($name, '\\')) return ltrim($name, '\\');
        if (strtolower($name) === 'namespace') return $context['namespace'];
        $segments = explode('\\', $name, 2);
        $alias = $context['uses'][strtolower($segments[0])] ?? null;
        if ($alias !== null) return $alias . (isset($segments[1]) ? '\\' . $segments[1] : '');
        return ($context['namespace'] === '' ? '' : $context['namespace'] . '\\') . $name;
    }

    private function isName(?PhpToken $token): bool
    {
        return $token !== null && $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STATIC]);
    }

    private function opens(PhpToken $token): bool
    {
        return $token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]);
    }

    /** 与 $open 处的 ( 或 { 配对的位置；未闭合时返回最后一个位置。 */
    private function closing(array $tokens, int $open): int
    {
        $depth = 0;
        $count = count($tokens);
        for ($index = $open; $index < $count; $index++) {
            $text = $tokens[$index]->text;
            if ($text === '(' || $text === '[' || $this->opens($tokens[$index])) $depth++;
            if ($text === ')' || $text === ']' || $text === '}') $depth--;
            if ($depth === 0) return $index;
        }
        return $count - 1;
    }

    private function opening(array $tokens, int $close): int
    {
        $depth = 0;
        for ($index = $close; $index >= 0; $index--) {
            $text = $tokens[$index]->text;
            if ($text === ')' || $text === ']' || $text === '}') $depth++;
            if ($text === '(' || $text === '[' || $this->opens($tokens[$index])) $depth--;
            if ($depth === 0) return $index;
        }
        return 0;
    }
}
