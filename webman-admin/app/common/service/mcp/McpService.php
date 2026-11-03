<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 * Author: AI Assistant
 * Date: 2024
 */

namespace app\common\service\mcp;

use app\common\service\AbstractService;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use Mcp\Server\Transport\TransportInterface;
use InvalidArgumentException;
use RuntimeException;
use support\Db;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * MCP(Model Context Protocol)服务类
 * 提供与AI模型交互的上下文协议服务
 */
class McpService extends AbstractService
{
    protected const NAME = 'mcp';
    protected const VERSION = '1.0.0';

    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    private function projectPath(string $path = ''): string
    {
        return rtrim($this->projectRoot ?? base_path(), DIRECTORY_SEPARATOR) . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR));
    }

    /**
     * MCP服务器实例
     * @var Server|null
     */
    protected ?Server $server = null;

    /**
     * 日志记录器
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;


    /**
     * 初始化MCP服务
     */
    protected function initialize()
    {
        parent::initialize();
        $this->logger = new NullLogger();
        return $this;
    }

    /**
     * 构建MCP服务器
     */
    protected function buildServer(): Server
    {
        if ($this->server !== null) {
            return $this->server;
        }

        $this->server = Server::builder()
            ->setServerInfo(self::NAME, self::VERSION)
            ->setLogger($this->logger)
            ->addTool([$this, 'handleDbQuery'], 'db-query', description: '执行数据库查询操作（仅支持SELECT语句）')
            ->addTool([$this, 'handleSysConfig'], 'sys-config', description: '获取系统配置信息')
            ->addTool([$this, 'handleWriteLog'], 'write-log', description: '写入系统日志')
            ->addTool([$this, 'handleFileOperation'], 'file-operation', description: '文件读写操作')
            ->addTool([$this, 'handleUserManagement'], 'user-management', description: '用户管理相关操作')
            ->addTool([$this, 'handleSystemInfo'], 'system-info', description: '获取系统运行信息')
            ->addTool([$this, 'handleCrud'], 'crud', description: '根据项目内 JSON 配置生成后台 API 与 Vue CRUD 页面只读预览')
            ->addTool([$this, 'handleWebmanCommand'], 'webman-command', description: '执行只读白名单内的 Webman 命令')
            ->addResource([$this, 'handleConfigResource'], 'config://system', 'config-system', description: '系统配置信息资源', mimeType: 'application/json')
            ->addResource([$this, 'handleSchemaResource'], 'schema://database', 'schema-database', description: '数据库表结构信息资源', mimeType: 'application/json')
            ->build();

        return $this->server;
    }



    /**
     * 处理数据库查询
     * @param string $query SQL查询语句
     * @param array $params 查询参数
     * @return array
     */
    public function handleDbQuery(string $query, array $params = []): array
    {
        try {
            if (empty($query)) {
                throw new RuntimeException('SQL查询语句不能为空');
            }

            if (strlen($query) > 16384 || !preg_match('/\A\s*SELECT\s+/i', $query)
                || preg_match('/;|--|#|\/\*|\b(?:INTO|OUTFILE|DUMPFILE|LOAD_FILE|SLEEP|BENCHMARK|GET_LOCK|RELEASE_LOCK|FOR\s+UPDATE)\b/i', $query)) {
                throw new RuntimeException('只允许单条无副作用 SELECT 查询');
            }
            $connection = Db::connection();
            if ($connection->transactionLevel() !== 0) throw new RuntimeException('只读查询不能复用外部事务');
            $driver = $connection->getDriverName();
            if (!in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) throw new RuntimeException('当前连接未实现只读查询保护');
            if ($driver === 'pgsql') $this->assertPostgresqlReadOnly($query);
            $pdo = $connection->getPdo();
            $queryOnly = $driver === 'sqlite' ? (int) $pdo->query('PRAGMA query_only')->fetchColumn() : 0;
            try {
                if ($driver === 'pgsql') {
                    $pdo->exec('BEGIN TRANSACTION READ ONLY');
                    $pdo->exec("SET LOCAL statement_timeout = '3s'");
                    $pdo->exec("SET LOCAL lock_timeout = '1s'");
                    $schema = (string) ($connection->getConfig('search_path') ?: 'public');
                    if (!preg_match('/\A[a-z_][a-z0-9_]{0,62}\z/D', $schema) || str_starts_with($schema, 'pg_') || $schema === 'information_schema') throw new RuntimeException('数据库 schema 不合法');
                    $pdo->exec('SET LOCAL search_path = pg_catalog, "' . $schema . '"');
                } elseif ($driver === 'mysql') $pdo->exec('START TRANSACTION READ ONLY');
                else { $pdo->exec('PRAGMA query_only = ON'); $pdo->beginTransaction(); }
                $statement = $pdo->prepare($query);
                $statement->execute($params);
                $result = [];
                while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
                    if (count($result) >= 1000) throw new RuntimeException('查询结果超过 1000 行限制');
                    $result[] = $row;
                }
            } finally {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($driver === 'sqlite') $pdo->exec('PRAGMA query_only = ' . $queryOnly);
            }

            return [
                'success' => true,
                'data' => $result,
                'count' => count($result),
                'message' => '查询执行成功'
            ];

        } catch (\Throwable $e) {
            $this->logger->warning('MCP 数据库查询失败', ['type' => $e::class]);
            return [
                'success' => false,
                'error' => '只读查询被拒绝或执行失败',
            ];
        }
    }

    private function assertPostgresqlReadOnly(string $query): void
    {
        // 只读事务不能阻止会话锁、配置函数或 SECURITY DEFINER；函数调用采取白名单。
        $source = preg_replace("/'(?:[^']|'')*'/s", "''", $query);
        if (str_contains($source, '$') || preg_match('/\bFOR\s+(?:UPDATE|SHARE|NO\s+KEY|KEY\s+SHARE)\b|"[^"\n]+"\s*\(/i', $source)) throw new RuntimeException('只读查询包含不允许的语法');
        preg_match_all('/\b([a-z_][a-z0-9_]*(?:\s*\.\s*[a-z_][a-z0-9_]*)*)\s*\(/i', $source, $calls);
        $allowed = ['count', 'sum', 'avg', 'min', 'max', 'coalesce', 'nullif', 'lower', 'upper', 'length', 'char_length', 'trim', 'substring', 'round', 'abs', 'ceil', 'floor', 'date_trunc', 'extract', 'cast', 'concat', 'concat_ws', 'jsonb_array_length', 'jsonb_typeof', 'json_typeof', 'generate_series', 'in', 'exists', 'select'];
        foreach ($calls[1] as $call) {
            $call = strtolower(preg_replace('/\s+/', '', $call));
            if (str_starts_with($call, 'pg_catalog.')) $call = substr($call, 11);
            if (!in_array($call, $allowed, true)) throw new RuntimeException('只读查询函数不在白名单');
        }
    }

    /**
     * 处理配置获取
     * @param string $key 配置键名（可选）
     * @return array
     */
    public function handleSysConfig(string $key = ''): array
    {
        $connection = (array) config('database.connections.' . config('database.default'), []);
        $public = [
            'app' => ['debug' => (bool) config('app.debug'), 'default_timezone' => config('app.default_timezone')],
            'database' => ['type' => $connection['driver'] ?? '', 'charset' => $connection['charset'] ?? null, 'collation' => $connection['collation'] ?? null],
            'cache' => ['default' => config('cache.default')],
        ];
        if ($key === '') return $public;
        $allowed = ['app.debug', 'app.default_timezone', 'database.type', 'database.charset', 'database.collation', 'cache.default'];
        if (!in_array($key, $allowed, true)) throw new RuntimeException('配置不在公开白名单');
        [$section, $field] = explode('.', $key, 2);
        return ['key' => $key, 'value' => $public[$section][$field]];
    }

    /**
     * 处理日志写入
     * @param string $message 日志消息
     * @param string $level 日志级别
     * @param array $context 上下文数据
     * @return string
     */
    public function handleWriteLog(string $message, string $level = 'info', array $context = []): string
    {
        try {
            if (empty($message)) {
                throw new RuntimeException('日志消息不能为空');
            }

            // 支持的日志级别
            $allowedLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
            
            if (!in_array($level, $allowedLevels)) {
                $level = 'info';
            }

            $context = (new \app\admin\ai\service\AiAuditService(''))->redact($context);
            $message = (string) (new \app\admin\ai\service\AiAuditService(''))->redact($message);
            $this->logger->log($level, mb_substr($message, 0, 4096), $context);

            return "日志记录成功 [级别: {$level}]";

        } catch (\Throwable $e) {
            $this->logger->warning('MCP日志写入错误', ['type' => $e::class]);
            throw $e;
        }
    }

    /**
     * 处理文件操作
     * @param string $operation 操作类型
     * @param string $filepath 文件路径
     * @return array
     */
    public function handleFileOperation(string $operation, string $filepath): array
    {
        $root = realpath($this->projectPath('public/uploads'));
        $candidate = str_starts_with($filepath, DIRECTORY_SEPARATOR) ? $filepath : $this->projectPath($filepath);
        $resolved = realpath($candidate);
        if ($root === false || $resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)
            || preg_match('#(?:^|/)(?:\.env|.*\.(?:pem|key))$#i', $resolved)) {
            throw new RuntimeException('文件路径不在公开范围');
        }
        if ($operation === 'exists') return ['exists' => true];
        if (!is_file($resolved) || !is_readable($resolved)) throw new RuntimeException('文件不可读');
        $size = filesize($resolved);
        if ($operation === 'info') return ['size' => $size, 'modified' => date('Y-m-d H:i:s', filemtime($resolved)), 'is_file' => true];
        if ($operation !== 'read') throw new RuntimeException('不支持的操作类型');
        if ($size > 1048576) throw new RuntimeException('文件超过 1MiB 读取限制');
        return ['content' => file_get_contents($resolved), 'size' => $size, 'modified' => date('Y-m-d H:i:s', filemtime($resolved))];
    }

    /**
     * 处理用户管理
     * @param string $action 操作类型
     * @param int $userId 用户ID（可选）
     * @param int $limit 返回数量限制（可选）
     * @return array
     */
    public function handleUserManagement(string $action, int $userId = 0, int $limit = 10): array
    {
        try {
            switch ($action) {
                case 'list':
                    $users = Db::table('admin')->whereNull('deleted_at')
                        ->select(['id', 'username', 'email', 'mobile', 'created_at', 'status'])
                        ->limit(max(1, min(100, $limit)))
                        ->get();
                    
                    return [
                        'users' => array_map(static fn (object $row): array => (array) $row, $users->all()),
                        'count' => count($users)
                    ];

                case 'info':
                    if (!$userId) {
                        throw new RuntimeException('用户ID不能为空');
                    }

                    $user = Db::table('admin')->whereNull('deleted_at')
                        ->select(['id', 'username', 'real_name', 'email', 'mobile', 'created_at', 'status'])
                        ->where('id', $userId)
                        ->first();

                    if (!$user) {
                        throw new RuntimeException('用户不存在');
                    }

                    return (array) $user;

                case 'count':
                    $total = Db::table('admin')->whereNull('deleted_at')->count();
                    $active = Db::table('admin')->whereNull('deleted_at')->where('status', 1)->count();
                    
                    return [
                        'total' => $total,
                        'active' => $active,
                        'inactive' => $total - $active
                    ];

                default:
                    throw new RuntimeException('不支持的操作类型: ' . $action);
            }

        } catch (\Throwable $e) {
            $this->logger->warning('MCP用户管理错误', ['type' => $e::class]);
            throw $e;
        }
    }

    /**
     * 处理系统信息
     * @param string $type 信息类型
     * @return array
     */
    public function handleSystemInfo(string $type = 'general'): array
    {
        try {
            switch ($type) {
                case 'general':
                    return [
                        'php_version' => PHP_VERSION,
                        'framework' => 'Webman',
                        'framework_version' => \Composer\InstalledVersions::getPrettyVersion('workerman/webman-framework'),
                        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                        'memory_limit' => ini_get('memory_limit'),
                        'max_execution_time' => ini_get('max_execution_time'),
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'post_max_size' => ini_get('post_max_size'),
                    ];

                case 'database':
                    $version = 'Unknown';
                    try {
                        $versionResult = Db::select('SELECT VERSION() as version');
                        $version = $versionResult[0]->version ?? 'Unknown';
                    } catch (\Throwable $e) {
                        // 数据库连接失败时使用默认值
                    }
                    
                    return [
                        'type' => config('database.default'),
                        'version' => $version,
                        'charset' => config('database.connections.' . config('database.default') . '.charset'),
                        'collation' => config('database.connections.' . config('database.default') . '.collation'),
                    ];

                case 'performance':
                    return [
                        'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                        'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
                        'included_files' => count(get_included_files()),
                    ];

                case 'cache':
                    return [
                        'default_driver' => config('cache.default'),
                        'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
                        'redis_available' => extension_loaded('redis'),
                        'memcached_available' => extension_loaded('memcached'),
                    ];

                default:
                    throw new RuntimeException('不支持的系统信息类型: ' . $type);
            }

        } catch (\Throwable $e) {
            $this->logger->warning('MCP系统信息错误', ['type' => $e::class]);
            throw $e;
        }
    }

    /**
     * 格式化字节数
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }
        
        return round($bytes, 2) . ' ' . $units[$index];
    }

    /**
     * 处理配置资源
     * @return string
     */
    public function handleConfigResource(): string
    {
        return json_encode($this->handleSysConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * 处理数据库模式资源
     * @return string
     */
    public function handleSchemaResource(): string
    {
        try {
            $schema = [];
            $builder = Db::schema();
            foreach ($builder->getTables() as $table) {
                $name = (string) $table['name'];
                $prefix = Db::connection()->getTablePrefix();
                if ($prefix !== '' && !str_starts_with($name, $prefix)) continue;
                $logical = $prefix === '' ? $name : substr($name, strlen($prefix));
                $schema[$name] = $builder->getColumns($logical);
            }
            return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        } catch (\Throwable $e) {
            $this->logger->warning('MCP数据库模式获取错误', ['type' => $e::class]);
            return json_encode(['error' => '数据库模式不可用'], JSON_UNESCAPED_UNICODE);
        }
    }


    /**
     * 设置日志记录器
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 启动MCP服务器（STDIO传输）
     */
    public function startWithStdio(): int
    {
        return $this->startWithTransport(new StdioTransport(logger: $this->logger));
    }

    /**
     * 使用指定传输启动MCP服务器
     */
    public function startWithTransport(TransportInterface $transport): mixed
    {
        try {
            $this->logger->info('MCP服务器启动');
            return $this->buildServer()->run($transport);
        } catch (\Throwable $exception) {
            $this->logger->error('MCP服务器启动失败', ['type' => $exception::class]);
            throw $exception;
        }
    }

    /**
     * 获取服务器实例
     * @return Server|null
     */
    public function getServer(): Server
    {
        return $this->buildServer();
    }

    /**
     * 获取服务信息
     * @return array
     */
    public function getServiceInfo(): array
    {
        return [
            'name' => self::NAME,
            'version' => self::VERSION,
            'tools' => 8,
            'resources' => 2,
            'prompt' => 0,
            'transport' => 'stdio',
            'status' => 'ready',
        ];
    }

    /**
     * 获取支持的字段类型
     * @return array
     */
    public function getSupportedFieldTypes(): array
    {
        return [
            '整数类型' => [
                'int(11)' => '整数类型，11位长度',
                'bigint(20)' => '大整数类型，20位长度',
                'tinyint(1)' => '小整数类型，1位长度',
                'smallint(6)' => '小整数类型，6位长度',
                'mediumint(9)' => '中等整数类型，9位长度'
            ],
            '字符串类型' => [
                'varchar(255)' => '可变长度字符串，最大255字符',
                'char(50)' => '固定长度字符串，50字符',
                'text' => '长文本类型',
                'longtext' => '超长文本类型',
                'mediumtext' => '中等长度文本类型',
                'tinytext' => '短文本类型'
            ],
            '浮点数类型' => [
                'decimal(10,2)' => '定点数类型，10位总长度，2位小数',
                'float' => '单精度浮点数',
                'double' => '双精度浮点数'
            ],
            '日期时间类型' => [
                'datetime' => '日期时间类型',
                'timestamp' => '时间戳类型',
                'date' => '日期类型',
                'time' => '时间类型',
                'year' => '年份类型'
            ],
            '其他类型' => [
                'json' => 'JSON数据类型',
                'blob' => '二进制大对象',
                'longblob' => '长二进制大对象',
                'mediumblob' => '中等二进制大对象',
                'tinyblob' => '小二进制大对象'
            ]
        ];
    }

    /**
     * 处理只读 CRUD 生成预览；MCP 永不暴露确认 token，也不执行写入。
     * @param string $configPath 项目内 Definition 路径
     * @return array
     */
    public function handleCrud(string $configPath): array
    {
        try {
            $root = rtrim($this->projectPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $path = trim($configPath);
            if ($path === '' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') {
                throw new InvalidArgumentException('必须指定项目目录内的 JSON 配置文件');
            }
            $candidate = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
                ? $path
                : $root . ltrim($path, '/\\');
            $resolved = realpath($candidate);
            $resolvedRoot = realpath($root);
            if ($resolved === false || !is_file($resolved)) {
                throw new InvalidArgumentException('CRUD 配置文件不存在');
            }
            if ($resolvedRoot === false || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $resolvedRoot . DIRECTORY_SEPARATOR)) {
                throw new InvalidArgumentException('CRUD 配置文件必须位于项目目录内');
            }
            $json = file_get_contents($resolved);
            if ($json === false) {
                throw new \RuntimeException('无法读取 CRUD Definition');
            }
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !isset($data['schemaVersion'])) {
                throw new InvalidArgumentException('必须使用版本化 CRUD Definition');
            }
            $plan = (new CrudGenerator($root))->plan(CrudDefinition::fromArray($data));
            unset($plan['confirmToken']);
            return [
                'success' => true,
                'message' => 'CRUD 生成预览成功',
                'data' => [
                    'config' => str_replace(DIRECTORY_SEPARATOR, '/', substr($resolved, strlen($root))),
                    'dryRun' => true,
                    'plan' => $plan,
                ],
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('CRUD 生成错误', ['type' => $e::class]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 执行 Webman 内置命令
     * @param string $command 命令名称
     * @param array $params 命令参数 (可选)
     * @param array $options 命令选项 (可选)
     * @return array
     */
    public function handleWebmanCommand(string $command, array $params = [], array $options = []): array
    {
        $allowed = ['version', 'list', 'help', 'route:list'];
        try {
            if (!in_array($command, $allowed, true) || $options !== [] || !array_is_list($params)
                || ($command !== 'help' && $params !== []) || count($params) > 1) {
                throw new InvalidArgumentException('命令或参数不在只读白名单');
            }
            foreach ($params as $param) {
                if (!is_string($param) || !preg_match('/\A[a-z][a-z0-9:-]*\z/D', $param)) throw new InvalidArgumentException('命令参数无效');
            }
            $argv = [PHP_BINARY, $this->projectPath('webman'), '--no-ansi', '--no-interaction', $command, ...$params];
            $process = proc_open($argv, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->projectPath());
            if (!is_resource($process)) throw new RuntimeException('无法启动 Webman 命令');
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $content = '';
            $deadline = microtime(true) + 10;
            try {
                do {
                    $content .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                    $state = proc_get_status($process);
                    if (strlen($content) > 1048576 || microtime(true) > $deadline) {
                        proc_terminate($process, 9);
                        throw new RuntimeException('命令输出或执行时间超过限制');
                    }
                    if (!$state['running']) break;
                    usleep(10000);
                } while (true);
                $content .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            } finally {
                fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
            }
            $code = (int) $state['exitcode'];
            return ['success' => $code === 0, 'command' => $command, 'return_code' => $code, 'output' => $content, 'output_lines' => explode("\n", rtrim($content))];
        } catch (\Throwable $error) {
            return ['success' => false, 'command' => $command, 'error' => $error->getMessage()];
        }
    }
}
