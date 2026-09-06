<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: AI Assistant
 * Date: 2024
 */

namespace app\common\service;

use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;
use PhpMcp\Server\Defaults\BasicContainer;
use InvalidArgumentException;
use think\facade\Db;
use think\facade\Log;
use think\facade\App;
use think\Exception;
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
     * 超时配置（毫秒）
     * @var int
     */
    protected int $timeout = 600000;

    /**
     * 连接超时配置（毫秒）
     * @var int
     */
    protected int $connectTimeout = 30000;

    /**
     * 读取超时配置（毫秒）
     * @var int
     */
    protected int $readTimeout = 30000;

    /**
     * 重试次数
     * @var int
     */
    protected int $retryAttempts = 3;

    /**
     * 重试延迟（毫秒）
     * @var int
     */
    protected int $retryDelay = 1000;

    /**
     * 调试模式
     * @var bool
     */
    protected bool $debug = false;

    /**
     * 缓冲区大小
     * @var int
     */
    protected int $bufferSize = 8192;

    /**
     * 心跳机制启用
     * @var bool
     */
    protected bool $heartbeatEnabled = false;

    /**
     * 心跳间隔（秒）
     * @var int
     */
    protected int $heartbeatInterval = 30;

    /**
     * 初始化MCP服务
     */
    protected function initialize()
    {
        parent::initialize();
        $this->logger = new NullLogger(); // 默认使用空日志记录器
        
        // 读取MCP配置文件
        $this->loadMcpConfig();
        
        return $this;
    }

    /**
     * 加载MCP配置
     */
    protected function loadMcpConfig()
    {
        try {
            // 对于长时间运行的服务器，设置为无限制
            ini_set('max_execution_time', 0);
            set_time_limit(0);
            
            // 忽略用户中断，保持服务器运行
            ignore_user_abort(true);
        
            $mcpConfig = config('mcp', []);
            
            // 设置超时配置
            if (isset($mcpConfig['timeout']) && $mcpConfig['timeout'] > 0) {
                $this->timeout = (int) $mcpConfig['timeout'];
            }
            
            if (isset($mcpConfig['connect_timeout']) && $mcpConfig['connect_timeout'] > 0) {
                $this->connectTimeout = (int) $mcpConfig['connect_timeout'];
            }
            
            if (isset($mcpConfig['read_timeout']) && $mcpConfig['read_timeout'] > 0) {
                $this->readTimeout = (int) $mcpConfig['read_timeout'];
            }
            
            // 设置重试配置
            if (isset($mcpConfig['retry_attempts']) && $mcpConfig['retry_attempts'] > 0) {
                $this->retryAttempts = (int) $mcpConfig['retry_attempts'];
            }
            
            if (isset($mcpConfig['retry_delay']) && $mcpConfig['retry_delay'] > 0) {
                $this->retryDelay = (int) $mcpConfig['retry_delay'];
            }
            
            // 设置调试模式
            if (isset($mcpConfig['debug'])) {
                $this->debug = (bool) $mcpConfig['debug'];
            }
            
            // 设置内存限制
            if (isset($mcpConfig['memory_limit'])) {
                ini_set('memory_limit', $mcpConfig['memory_limit']);
            }
            
            // 设置缓冲区大小
            if (isset($mcpConfig['buffer_size'])) {
                $this->bufferSize = (int) $mcpConfig['buffer_size'];
            }
            
            // 设置心跳配置
            if (isset($mcpConfig['heartbeat_enabled'])) {
                $this->heartbeatEnabled = (bool) $mcpConfig['heartbeat_enabled'];
            }
            
            if (isset($mcpConfig['heartbeat_interval'])) {
                $this->heartbeatInterval = (int) $mcpConfig['heartbeat_interval'];
            }
            
            Log::info('MCP配置加载成功', [
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
                'read_timeout' => $this->readTimeout,
                'retry_attempts' => $this->retryAttempts,
                'retry_delay' => $this->retryDelay,
                'heartbeat_enabled' => $this->heartbeatEnabled,
                'heartbeat_interval' => $this->heartbeatInterval
            ]);
            
        } catch (Exception $e) {
            Log::warning('MCP配置加载失败，使用默认配置: ' . $e->getMessage());
        }
    }

    /**
     * 启动心跳机制
     */
    protected function startHeartbeat()
    {
        if (!$this->heartbeatEnabled) {
            return;
        }
        
        // 在后台启动心跳线程
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            if ($pid == 0) {
                // 子进程执行心跳
                $this->heartbeatLoop();
                exit(0);
            }
        } else {
            // Windows系统使用定时器
            $this->scheduleHeartbeat();
        }
    }

    /**
     * 心跳循环
     */
    protected function heartbeatLoop()
    {
        while (true) {
            try {
                // 发送心跳信号
                $this->sendHeartbeat();
                
                // 等待下次心跳
                sleep($this->heartbeatInterval);
                
            } catch (Exception $e) {
                Log::error('心跳发送失败: ' . $e->getMessage());
                sleep(5); // 失败后等待5秒再重试
            }
        }
    }

    /**
     * 发送心跳信号
     */
    protected function sendHeartbeat()
    {
        // 记录心跳日志
        if ($this->debug) {
            Log::debug('发送心跳信号', [
                'timestamp' => time(),
                'memory_usage' => memory_get_usage(true)
            ]);
        }
        
        // 这里可以添加实际的心跳逻辑
        // 比如向客户端发送ping消息
    }

    /**
     * 调度心跳（Windows系统）
     */
    protected function scheduleHeartbeat()
    {
        // Windows系统下的心跳调度
        if (function_exists('register_tick_function')) {
            register_tick_function([$this, 'sendHeartbeat']);
            declare(ticks=1);
        }
    }

    /**
     * 带重试机制的操作执行
     */
    protected function executeWithRetry(callable $operation, string $operationName = 'operation')
    {
        $attempts = 0;
        $lastException = null;
        
        while ($attempts < $this->retryAttempts) {
            try {
                $attempts++;
                Log::info("执行{$operationName}，第{$attempts}次尝试");
                
                $result = $operation();
                
                if ($attempts > 1) {
                    Log::info("{$operationName}在第{$attempts}次尝试后成功");
                }
                
                return $result;
                
            } catch (Exception $e) {
                $lastException = $e;
                Log::warning("{$operationName}第{$attempts}次尝试失败: " . $e->getMessage());
                
                if ($attempts < $this->retryAttempts) {
                    $delay = $this->retryDelay * pow(1.5, $attempts - 1); // 指数退避
                    Log::info("等待{$delay}ms后重试");
                    usleep($delay * 1000);
                }
            }
        }
        
        Log::error("{$operationName}在{$this->retryAttempts}次尝试后仍然失败");
        throw $lastException;
    }

    /**
     * 构建MCP服务器
     */
    protected function buildServer()
    {
        if ($this->server !== null) {
            return $this->server;
        }

        // 创建容器并注册服务实例
        $container = new BasicContainer();
        $container->set(LoggerInterface::class, $this->logger);
        $container->set(self::class, $this);

        $this->server = Server::make()
            ->withServerInfo(self::NAME, self::VERSION)
            ->withLogger($this->logger)
            ->withContainer($container)
            ->withTool([self::class, 'handleDbQuery'], 'db-query', '执行数据库查询操作（仅支持SELECT语句）')
            ->withTool([self::class, 'handleSysConfig'], 'sys-config', '获取系统配置信息')
            ->withTool([self::class, 'handleWriteLog'], 'write-log', '写入系统日志')
            ->withTool([self::class, 'handleFileOperation'], 'file-operation', '文件读写操作')
            ->withTool([self::class, 'handleUserManagement'], 'user-management', '用户管理相关操作')
            ->withTool([self::class, 'handleSystemInfo'], 'system-info', '获取系统运行信息')
            ->withTool([self::class, 'handleCrud'], 'crud', '根据项目内 JSON 配置生成后台 API 与 Vue CRUD 页面只读预览')
            ->withTool([self::class, 'handleThinkCommand'], 'think-command', '执行ThinkPHP内置命令')
            ->withResource([self::class, 'handleConfigResource'], 'config://system', 'config-system', '系统配置信息资源', 'application/json')
            ->withResource([self::class, 'handleSchemaResource'], 'schema://database', 'schema-database', '数据库表结构信息资源', 'application/json')
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
                throw new Exception('SQL查询语句不能为空');
            }

            // 安全检查：只允许SELECT查询
            if (!preg_match('/^\s*select\s+/i', trim($query))) {
                throw new Exception('出于安全考虑，只允许执行SELECT查询');
            }

            $result = Db::query($query, $params);

            return [
                'success' => true,
                'data' => $result,
                'count' => count($result),
                'message' => '查询执行成功'
            ];

        } catch (Exception $e) {
            Log::error('MCP数据库查询错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理配置获取
     * @param string $key 配置键名（可选）
     * @return array
     */
    public function handleSysConfig(string $key = ''): array
    {
        try {
            if (empty($key)) {
                // 返回常用配置的概览
                return [
                    'app' => [
                        'debug' => config('app.debug'),
                        'default_timezone' => config('app.default_timezone'),
                        'default_lang' => config('app.default_lang'),
                    ],
                    'database' => [
                        'type' => config('database.default.type'),
                        'hostname' => config('database.default.hostname'),
                        'database' => config('database.default.database'),
                    ],
                    'cache' => [
                        'default' => config('cache.default'),
                        'stores' => array_keys(config('cache.stores', [])),
                    ]
                ];
            } else {
                return ['value' => config($key), 'key' => $key];
            }

        } catch (Exception $e) {
            Log::error('MCP配置获取错误: ' . $e->getMessage());
            throw $e;
        }
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
                throw new Exception('日志消息不能为空');
            }

            // 支持的日志级别
            $allowedLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
            
            if (!in_array($level, $allowedLevels)) {
                $level = 'info';
            }

            Log::record($message, $level, $context);

            return "日志记录成功 [级别: {$level}]";

        } catch (Exception $e) {
            Log::error('MCP日志写入错误: ' . $e->getMessage());
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
        try {
            if (empty($operation) || empty($filepath)) {
                throw new Exception('操作类型和文件路径不能为空');
            }

            // 安全检查：限制文件路径范围
            $allowedPaths = [
                root_path() . 'runtime/',
                root_path() . 'public/uploads/',
                root_path() . 'config/',
            ];

            $isAllowed = false;
            $realFilePath = realpath($filepath);
            if ($realFilePath) {
                foreach ($allowedPaths as $allowedPath) {
                    $realAllowedPath = realpath($allowedPath);
                    if ($realAllowedPath && strpos($realFilePath, $realAllowedPath) === 0) {
                        $isAllowed = true;
                        break;
                    }
                }
            }

            if (!$isAllowed) {
                throw new Exception('文件路径不在允许的范围内');
            }

            switch ($operation) {
                case 'read':
                    if (!file_exists($filepath)) {
                        throw new Exception('文件不存在');
                    }
                    return [
                        'content' => file_get_contents($filepath),
                        'size' => filesize($filepath),
                        'modified' => date('Y-m-d H:i:s', filemtime($filepath))
                    ];

                case 'exists':
                    return ['exists' => file_exists($filepath)];

                case 'info':
                    if (!file_exists($filepath)) {
                        throw new Exception('文件不存在');
                    }
                    return [
                        'size' => filesize($filepath),
                        'modified' => date('Y-m-d H:i:s', filemtime($filepath)),
                        'is_file' => is_file($filepath),
                        'is_dir' => is_dir($filepath),
                        'is_readable' => is_readable($filepath),
                        'is_writable' => is_writable($filepath)
                    ];

                default:
                    throw new Exception('不支持的操作类型: ' . $operation);
            }

        } catch (Exception $e) {
            Log::error('MCP文件操作错误: ' . $e->getMessage());
            throw $e;
        }
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
                    $users = Db::name('admin')
                        ->field('id,username,email,mobile,created_at,status')
                        ->limit($limit)
                        ->select();
                    
                    return [
                        'users' => $users->toArray(),
                        'count' => count($users)
                    ];

                case 'info':
                    if (!$userId) {
                        throw new Exception('用户ID不能为空');
                    }

                    $user = Db::name('admin')
                        ->field('id,username,nickname,email,mobile,created_at,status')
                        ->where('id', $userId)
                        ->find();

                    if (!$user) {
                        throw new Exception('用户不存在');
                    }

                    return $user;

                case 'count':
                    $total = Db::name('admin')->count();
                    $active = Db::name('admin')->where('status', 1)->count();
                    
                    return [
                        'total' => $total,
                        'active' => $active,
                        'inactive' => $total - $active
                    ];

                default:
                    throw new Exception('不支持的操作类型: ' . $action);
            }

        } catch (Exception $e) {
            Log::error('MCP用户管理错误: ' . $e->getMessage());
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
                        'framework' => 'ThinkPHP',
                        'framework_version' => App::version(),
                        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                        'memory_limit' => ini_get('memory_limit'),
                        'max_execution_time' => ini_get('max_execution_time'),
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'post_max_size' => ini_get('post_max_size'),
                    ];

                case 'database':
                    $version = 'Unknown';
                    try {
                        $versionResult = Db::query('SELECT VERSION() as version');
                        $version = $versionResult[0]['version'] ?? 'Unknown';
                    } catch (Exception $e) {
                        // 数据库连接失败时使用默认值
                    }
                    
                    return [
                        'type' => config('database.default.type'),
                        'version' => $version,
                        'charset' => config('database.default.charset'),
                        'collation' => config('database.default.collate'),
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
                    throw new Exception('不支持的系统信息类型: ' . $type);
            }

        } catch (Exception $e) {
            Log::error('MCP系统信息错误: ' . $e->getMessage());
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
        $configs = [
            'app' => config('app'),
            'database' => [
                'type' => config('database.default.type'),
                'charset' => config('database.default.charset'),
                'debug' => config('database.debug'),
            ],
            'cache' => config('cache'),
            'session' => config('session'),
            'log' => config('log'),
        ];

        return json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 处理数据库模式资源
     * @return string
     */
    public function handleSchemaResource(): string
    {
        try {
            // 获取所有表名
            $tables = Db::query('SHOW TABLES');
            $schema = [];

            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                
                // 获取表结构
                $columns = Db::query("SHOW COLUMNS FROM `{$tableName}`");
                $schema[$tableName] = $columns;
            }

            return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            Log::error('MCP数据库模式获取错误: ' . $e->getMessage());
            return json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
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
     * 获取当前配置信息
     * @return array
     */
    public function getConfig()
    {
        return [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'read_timeout' => $this->readTimeout,
            'retry_attempts' => $this->retryAttempts,
            'retry_delay' => $this->retryDelay,
            'debug' => $this->debug
        ];
    }

    /**
     * 启动MCP服务器（STDIO传输）
     */
    public function startWithStdio()
    {
        try {
            // 启动心跳机制
            $this->startHeartbeat();
            
            $server = $this->buildServer();
            $transport = new StdioServerTransport();
            
            Log::info('MCP STDIO服务器启动成功');
            $server->listen($transport);

        } catch (Exception $e) {
            Log::error('MCP STDIO服务器启动失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 使用指定传输启动MCP服务器
     */
    public function startWithTransport($transport)
    {
        try {
            // 启动心跳机制
            $this->startHeartbeat();
            
            $server = $this->buildServer();
            $server->listen($transport);
            
            Log::info('MCP服务器启动成功');

        } catch (Exception $e) {
            Log::error('MCP服务器启动失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 使用SSE传输启动MCP服务器
     */
    public function startWithSse(string $host = '127.0.0.1', int $port = 8080, string $mcpPath = 'mcp')
    {
        try {
            // 启动心跳机制
            $this->startHeartbeat();
            
            $server = $this->buildServer();
            $transport = new \PhpMcp\Server\Transports\StreamableHttpServerTransport($host, $port, $mcpPath);
            
            Log::info("MCP SSE服务器启动成功，监听地址: http://{$host}:{$port}/{$mcpPath}");
            $server->listen($transport);

        } catch (Exception $e) {
            Log::error('MCP SSE服务器启动失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 使用HTTP传输启动MCP服务器
     */
    public function startWithHttp(string $host = '127.0.0.1', int $port = 8080, string $mcpPath = 'mcp')
    {
        try {
            // 启动心跳机制
            $this->startHeartbeat();
            
            $server = $this->buildServer();
            $transport = new \PhpMcp\Server\Transports\HttpServerTransport($host, $port, $mcpPath);
            
            Log::info("MCP HTTP服务器启动成功，监听地址: http://{$host}:{$port}/{$mcpPath}");
            $server->listen($transport);

        } catch (Exception $e) {
            Log::error('MCP HTTP服务器启动失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取服务器实例
     * @return Server|null
     */
    public function getServer()
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
            'status' => 'ready',
            'config' => $this->getConfig()
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
            $root = rtrim(root_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
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
            Log::error('CRUD 生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 执行ThinkPHP内置命令
     * @param string $command 命令名称
     * @param array $params 命令参数 (可选)
     * @param array $options 命令选项 (可选)
     * @return array
     */
    public function handleThinkCommand(string $command, array $params = [], array $options = []): array
    {
        try {
            // 验证命令是否为安全的内置命令
            $allowedCommands = [
                // 基础命令
                'list', 'help', 'version', 'clear', 'build',
                // optimize 命令组
                'optimize:config', 'optimize:route', 'optimize:schema',
                // route 命令组
                'route:list',
                // service 命令组
                'service:discover',
                // vendor 命令组
                'vendor:publish',
                // queue 命令组（只允许查看相关的）
                'queue:failed', 'queue:failed-table', 'queue:table',
                // auth 命令组
                'auth:config',
                // FunAdmin 特有命令
                'crud:inspect', 'crud:validate', 'crud:preview', 'mcp'
            ];

            if (!in_array($command, $allowedCommands)) {
                return [
                    'success' => false,
                    'message' => '不支持的命令或命令不安全',
                    'allowed_commands' => $allowedCommands
                ];
            }

            // 构建完整的命令
            $fullCommand = 'php think ' . $command;
            
            // 添加参数
            if (!empty($params)) {
                foreach ($params as $param) {
                    $fullCommand .= ' ' . escapeshellarg($param);
                }
            }
            
            // 添加选项
            if (!empty($options)) {
                foreach ($options as $option => $value) {
                    if (is_numeric($option)) {
                        // 简单选项，如 --verbose
                        $fullCommand .= ' --' . $value;
                    } else {
                        // 带值选项，如 --name=value
                        $fullCommand .= ' --' . $option . '=' . escapeshellarg($value);
                    }
                }
            }

            // 切换到项目根目录执行命令
            $rootPath = App::getRootPath();
            $originalDir = getcwd();
            
            if ($originalDir !== $rootPath) {
                chdir($rootPath);
            }

            // 执行命令并捕获输出
            $output = [];
            $returnCode = 0;
            exec($fullCommand . ' 2>&1', $output, $returnCode);
            
            // 恢复原始目录
            if ($originalDir !== $rootPath) {
                chdir($originalDir);
            }

            // 记录命令执行日志
            $this->handleWriteLog("执行ThinkPHP命令: {$fullCommand}", 'info', [
                'return_code' => $returnCode,
                'output_lines' => count($output)
            ]);

            $content = implode("\n", $output);
            $success = $returnCode === 0 || strpos($content, '成功') !== false;

            return [
                'success' => $success,
                'message' => $success ? '命令执行成功' : '命令执行失败',
                'command' => $fullCommand,
                'return_code' => $returnCode,
                'output' => $content,
                'output_lines' => $output
            ];

        } catch (\Exception $e) {
            $this->handleWriteLog('执行ThinkPHP命令时出错: ' . $e->getMessage(), 'error', [
                'command' => $command,
                'params' => $params,
                'options' => $options,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => '命令执行异常: ' . $e->getMessage(),
                'command' => $command,
                'error' => $e->getMessage()
            ];
        }
    }
}