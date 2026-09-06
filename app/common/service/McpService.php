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
    /**
     * MCP服务器实例
     * @var Server|null
     */
    protected $server = null;

    /**
     * 日志记录器
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 超时配置（毫秒）
     * @var int
     */
    protected $timeout = 600000;

    /**
     * 连接超时配置（毫秒）
     * @var int
     */
    protected $connectTimeout = 30000;

    /**
     * 读取超时配置（毫秒）
     * @var int
     */
    protected $readTimeout = 30000;

    /**
     * 重试次数
     * @var int
     */
    protected $retryAttempts = 3;

    /**
     * 重试延迟（毫秒）
     * @var int
     */
    protected $retryDelay = 1000;

    /**
     * 调试模式
     * @var bool
     */
    protected $debug = false;

    /**
     * 服务名称
     * @var string
     */
    protected $name = 'mcp';

    /**
     * 服务版本
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * 内存限制
     * @var string
     */
    protected $memoryLimit;

    /**
     * 缓冲区大小
     * @var int
     */
    protected $bufferSize;

    /**
     * 心跳机制启用
     * @var bool
     */
    protected $heartbeatEnabled;

    /**
     * 心跳间隔（秒）
     * @var int
     */
    protected $heartbeatInterval;

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
                $this->timeout = $mcpConfig['timeout'];
            }
            
            if (isset($mcpConfig['connect_timeout']) && $mcpConfig['connect_timeout'] > 0) {
                $this->connectTimeout = $mcpConfig['connect_timeout'];
            }
            
            if (isset($mcpConfig['read_timeout']) && $mcpConfig['read_timeout'] > 0) {
                $this->readTimeout = $mcpConfig['read_timeout'];
            }
            
            // 设置重试配置
            if (isset($mcpConfig['retry_attempts']) && $mcpConfig['retry_attempts'] > 0) {
                $this->retryAttempts = $mcpConfig['retry_attempts'];
            }
            
            if (isset($mcpConfig['retry_delay']) && $mcpConfig['retry_delay'] > 0) {
                $this->retryDelay = $mcpConfig['retry_delay'];
            }
            
            // 设置调试模式
            if (isset($mcpConfig['debug'])) {
                $this->debug = $mcpConfig['debug'];
            }
            
            // 设置内存限制
            if (isset($mcpConfig['memory_limit'])) {
                ini_set('memory_limit', $mcpConfig['memory_limit']);
            }
            
            // 设置缓冲区大小
            if (isset($mcpConfig['buffer_size'])) {
                $this->bufferSize = $mcpConfig['buffer_size'];
            }
            
            // 设置心跳配置
            if (isset($mcpConfig['heartbeat_enabled'])) {
                $this->heartbeatEnabled = $mcpConfig['heartbeat_enabled'];
            }
            
            if (isset($mcpConfig['heartbeat_interval'])) {
                $this->heartbeatInterval = $mcpConfig['heartbeat_interval'];
            }
            
            Log::info('MCP配置加载成功', [
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
                'read_timeout' => $this->readTimeout,
                'retry_attempts' => $this->retryAttempts,
                'retry_delay' => $this->retryDelay,
                'heartbeat_enabled' => $this->heartbeatEnabled ?? false,
                'heartbeat_interval' => $this->heartbeatInterval ?? 30
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
            ->withServerInfo($this->name, $this->version)
            ->withLogger($this->logger)
            ->withContainer($container)
            ->withTool([self::class, 'handleDbQuery'], 'db-query', '执行数据库查询操作（仅支持SELECT语句）')
            ->withTool([self::class, 'handleSysConfig'], 'sys-config', '获取系统配置信息')
            ->withTool([self::class, 'handleWriteLog'], 'write-log', '写入系统日志')
            ->withTool([self::class, 'handleFileOperation'], 'file-operation', '文件读写操作')
            ->withTool([self::class, 'handleUserManagement'], 'user-management', '用户管理相关操作')
            ->withTool([self::class, 'handleSystemInfo'], 'system-info', '获取系统运行信息')
            ->withTool([self::class, 'handleCreateController'], 'controller', '生成FunAdmin控制器文件')
            ->withTool([self::class, 'handleCreateModel'], 'model', '生成FunAdmin模型文件')
            ->withTool([self::class, 'handleCreateApi'], 'api', '生成FunAdmin API接口文件')
            ->withTool([self::class, 'handleCrud'], 'crud', '根据项目内 JSON 配置生成后台 API 与 Vue CRUD 页面')
            ->withTool([self::class, 'handleCreateTable'], 'table', '创建数据库表格，支持字段信息、类型、注释等')
            ->withTool([self::class, 'handleThinkCommand'], 'think-command', '执行ThinkPHP内置命令')
            ->withPrompt([self::class, 'handleWithPrompt'], 'with-prompt', '通过自然语言描述生成数据库表、控制器、模型等')
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
            'name' => $this->name,
            'version' => $this->version,
            'tools' => 16, // 16个工具（新增ThinkPHP命令工具）
            'resources' => 3, // 3个资源
            'prompt' => 1, // 1个提示词
            'status' => 'ready',
            'config' => $this->getConfig()
        ];
    }

    /**
     * 生成FunAdmin控制器文件
     * @param string $module 模块名称 (backend/api/frontend等)
     * @param string $controller 控制器名称
     * @param array $fields 字段信息 (可选)
     * @param string $description 控制器描述 (可选)
     * @return array
     */
    public function handleCreateController(string $module, string $controller, array $fields = [], string $description = ''): array
    {
        return [
            'success' => false,
            'error' => strtolower($module) === 'api'
                ? 'API 控制器必须使用专用 API 生成器'
                : '通用控制器生成器已停用，请使用 CRUD Workbench 生成带官方 Attribute 路由的后台控制器',
        ];
    }

    /**
     * 生成FunAdmin模型文件
     * @param string $modelName 模型名称
     * @param array $fields 字段信息 (可选)
     * @param string $tableName 表名 (可选，默认使用模型名)
     * @param string $description 模型描述 (可选)
     * @return array
     */
    public function handleCreateModel(string $modelName, array $fields = [], string $tableName = '', string $description = ''): array
    {
        try {
            // 生成模型类名
            $model = ucfirst($modelName);
            $modelPath = "app/common/model/{$model}.php";
            
            // 检查文件是否已存在
            if (file_exists($modelPath)) {
                return [
                    'success' => false,
                    'error' => "模型文件 {$modelPath} 已存在"
                ];
            }

            // 如果没有指定表名，使用模型名
            if (empty($tableName)) {
                $tableName = strtolower($modelName);
            }

            // 生成模型内容
            $modelContent = $this->CreateModelContent($model, $tableName, $fields, $description);
            
            // 确保目录存在
            $dir = dirname($modelPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 写入文件
            if (file_put_contents($modelPath, $modelContent)) {
                Log::info("FunAdmin模型生成成功: {$modelPath}");
                return [
                    'success' => true,
                    'message' => '模型生成成功',
                    'file_path' => $modelPath,
                    'content' => $modelContent
                ];
            } else {
                throw new Exception('文件写入失败');
            }

        } catch (Exception $e) {
            Log::error('FunAdmin模型生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成模型内容
     * @param string $model 模型类名
     * @param string $tableName 表名
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function CreateModelContent(string $model, string $tableName, array $fields = [], string $description = ''): string
    {
        $description = $description ?: $model;
        
        // 生成字段定义
        $fieldDefinitions = '';
        if (!empty($fields)) {
            $fieldDefinitions = "    // 字段定义\n";
            foreach ($fields as $field) {
                $fieldName = $field['name'] ?? '';
                $fieldType = $field['type'] ?? 'string';
                $fieldComment = $field['comment'] ?? '';
                if ($fieldName) {
                    $fieldDefinitions .= "    protected \$" . $fieldName . " = ''; // {$fieldComment}\n";
                }
            }
        }

        $content = "<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: AI Assistant
 * Date: " . date('Y/m/d') . "
 */
namespace app\\common\\model;

use think\\model\\concern\\SoftDelete;

/**
 * {$description}模型
 * Class {$model}
 * @package app\\common\\model
 */
class {$model} extends BaseModel
{
    use SoftDelete;

    /**
     * 数据表名
     * @var string
     */
    protected \$table = '{$tableName}';

    /**
     * 软删除字段
     * @var string
     */
    protected \$deleteTime = 'deleted_at';

{$fieldDefinitions}

}";

        return $content;
    }

    /**
     * 创建数据库表格
     * @param string $tableName 表名
     * @param array $fields 字段信息数组
     * @param string $tableComment 表注释
     * @param string $engine 存储引擎 (默认 InnoDB)
     * @param string $charset 字符集 (默认 utf8mb4)
     * @return array
     */
    public function handleCreateTable(string $tableName, array $fields, string $tableComment = '', string $engine = 'InnoDB', string $charset = 'utf8mb4'): array
    {
        try {
            // 验证表名
            if (empty($tableName)) {
                throw new Exception('表名不能为空');
            }

            // 验证字段信息
            if (empty($fields) || !is_array($fields)) {
                throw new Exception('字段信息不能为空且必须是数组');
            }

            // 检查表是否已存在
            $existingTables = Db::query("SHOW TABLES LIKE '{$tableName}'");
            if (!empty($existingTables)) {
                return [
                    'success' => false,
                    'error' => "表 {$tableName} 已存在"
                ];
            }

            // 生成建表SQL
            $createSql = $this->CreateCreateTableSql($tableName, $fields, $tableComment, $engine, $charset);

            // 执行建表SQL
            $result = Db::execute($createSql);

            if ($result !== false) {
                Log::info("FunAdmin数据库表创建成功: {$tableName}");
                return [
                    'success' => true,
                    'message' => '表创建成功',
                    'table_name' => $tableName,
                    'sql' => $createSql,
                    'fields_count' => count($fields)
                ];
            } else {
                throw new Exception('建表SQL执行失败');
            }

        } catch (Exception $e) {
            Log::error('FunAdmin创建表格错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成建表SQL
     * @param string $tableName 表名
     * @param array $fields 字段信息
     * @param string $tableComment 表注释
     * @param string $engine 存储引擎
     * @param string $charset 字符集
     * @return string
     */
    private function CreateCreateTableSql(string $tableName, array $fields, string $tableComment = '', string $engine = 'InnoDB', string $charset = 'utf8mb4'): string
    {
        $sql = "CREATE TABLE `{$tableName}` (\n";
        
        $fieldDefinitions = [];
        $primaryKey = null;

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldType = $field['type'] ?? 'varchar(255)';
            $fieldLength = $field['length'] ?? '';
            $fieldDefault = $field['default'] ?? '';
            $fieldComment = $field['comment'] ?? '';
            $fieldNull = isset($field['null']) && $field['null'] ? 'NULL' : 'NOT NULL';
            $fieldAutoIncrement = isset($field['auto_increment']) && $field['auto_increment'] ? 'AUTO_INCREMENT' : '';
            $fieldPrimary = isset($field['primary']) && $field['primary'] ? 'PRIMARY KEY' : '';

            // 构建字段定义
            $fieldDef = "    `{$fieldName}` {$fieldType}";
            
            // 添加长度
            if (!empty($fieldLength) && !in_array(strtolower($fieldType), ['text', 'longtext', 'mediumtext', 'tinytext', 'blob', 'longblob', 'mediumblob', 'tinyblob'])) {
                $fieldDef .= "({$fieldLength})";
            }
            
            // 添加默认值
            if ($fieldDefault !== '') {
                if (is_string($fieldDefault)) {
                    $fieldDef .= " DEFAULT '{$fieldDefault}'";
                } else {
                    $fieldDef .= " DEFAULT {$fieldDefault}";
                }
            }
            
            // 添加NULL/NOT NULL
            $fieldDef .= " {$fieldNull}";
            
            // 添加自增
            if (!empty($fieldAutoIncrement)) {
                $fieldDef .= " {$fieldAutoIncrement}";
            }
            
            // 添加注释
            if (!empty($fieldComment)) {
                $fieldDef .= " COMMENT '{$fieldComment}'";
            }
            
            // 添加主键
            if (!empty($fieldPrimary)) {
                $fieldDef .= " {$fieldPrimary}";
                $primaryKey = $fieldName;
            }

            $fieldDefinitions[] = $fieldDef;
        }

        // 添加默认字段（如果不存在）
        $hasId = false;
        $hasCreatedAt = false;
        $hasUpdatedAt = false;
        $hasDeletedAt = false;

        foreach ($fields as $field) {
            if ($field['name'] === 'id') $hasId = true;
            if ($field['name'] === 'created_at') $hasCreatedAt = true;
            if ($field['name'] === 'updated_at') $hasUpdatedAt = true;
            if ($field['name'] === 'deleted_at') $hasDeletedAt = true;
        }

        // 如果没有ID字段，添加默认ID字段
        if (!$hasId) {
            array_unshift($fieldDefinitions, "    `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID'");
        }

        // 添加 Laravel 风格默认时间字段
        if (!$hasCreatedAt) {
            $fieldDefinitions[] = "    `created_at` datetime DEFAULT NULL COMMENT '创建时间'";
        }
        if (!$hasUpdatedAt) {
            $fieldDefinitions[] = "    `updated_at` datetime DEFAULT NULL COMMENT '更新时间'";
        }
        if (!$hasDeletedAt) {
            $fieldDefinitions[] = "    `deleted_at` datetime DEFAULT NULL COMMENT '删除时间'";
        }

        $sql .= implode(",\n", $fieldDefinitions);
        $sql .= "\n)";

        // 添加表注释
        if (!empty($tableComment)) {
            $sql .= " COMMENT='{$tableComment}'";
        }

        // 添加存储引擎和字符集
        $sql .= " ENGINE={$engine} DEFAULT CHARSET={$charset}";

        return $sql;
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
     * 通过自然语言描述生成数据库表、控制器、模型等
     * @param string $prompt 自然语言描述
     * @param string $type 生成类型 (table/controller/model/api/crud/all)
     * @return array
     */
    public function handleWithPrompt(string $prompt, string $type = 'all'): array
    {
        try {
            if (empty($prompt)) {
                throw new Exception('描述不能为空');
            }

            // 解析提示词
            $parsedData = $this->parsePrompt($prompt);
            
            $results = [];
            
            // 根据类型生成相应的内容
            if (in_array($type, ['table', 'all'])) {
                if (!empty($parsedData['table'])) {
                    $tableResult = $this->handleCreateTable(
                        $parsedData['table']['name'],
                        $parsedData['table']['fields'],
                        $parsedData['table']['comment'] ?? '',
                        'InnoDB',
                        'utf8mb4'
                    );
                    $results['table'] = $tableResult;
                }
            }
            
            if (in_array($type, ['controller', 'all'])) {
                if (!empty($parsedData['controller'])) {
                    $controllerResult = $this->handleCreateController(
                        $parsedData['controller']['module'] ?? 'backend',
                        $parsedData['controller']['name'],
                        $parsedData['controller']['fields'] ?? [],
                        $parsedData['controller']['description'] ?? ''
                    );
                    $results['controller'] = $controllerResult;
                }
            }
            
            if (in_array($type, ['model', 'all'])) {
                if (!empty($parsedData['model'])) {
                    $modelResult = $this->handleCreateModel(
                        $parsedData['model']['name'],
                        $parsedData['model']['fields'] ?? [],
                        $parsedData['model']['table'] ?? '',
                        $parsedData['model']['description'] ?? ''
                    );
                    $results['model'] = $modelResult;
                }
            }

            if (in_array($type, ['api', 'all'])) {
                if (!empty($parsedData['api'])) {
                    $apiResult = $this->handleCreateApi(
                        $parsedData['api']['module'] ?? 'api',
                        $parsedData['api']['name'],
                        $parsedData['api']['fields'] ?? [],
                        $parsedData['api']['description'] ?? ''
                    );
                    $results['api'] = $apiResult;
                }
            }

            if (in_array($type, ['crud', 'all'])) {
                if (!empty($parsedData['crud'])) {
                    $crudResult = $this->handleCrud(
                        (string) ($parsedData['crud']['config'] ?? '')
                    );
                    $results['crud'] = $crudResult;
                }   
            }

            //如果这里面都没有 那么执行其他操作
            if (empty($results)) {
                $results = $this->handleOtherOperation($prompt);
            }

            return [
                'success' => true,
                'message' => '通过提示词生成成功',
                'parsed_data' => $parsedData,
                'results' => $results
            ];

        } catch (Exception $e) {
            Log::error('FunAdmin withPrompt错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 解析自然语言提示词
     * @param string $prompt
     * @return array
     */
    private function parsePrompt(string $prompt): array
    {
        $parsedData = [
            'table' => null,
            'controller' => null,
            'model' => null,
            'api' => null,
            'crud' => null,
        ];

        // 转换为小写便于匹配
        $lowerPrompt = strtolower($prompt);
        
        // 提取表名
        $tableName = null;
        if (preg_match('/(?:创建|生成|建立).*?(?:表|table).*?[名为|叫|是]\s*([a-zA-Z_][a-zA-Z0-9_]*)/', $lowerPrompt, $matches)) {
            $tableName = $matches[1];
        } elseif (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*)\s*(?:表|table)/', $lowerPrompt, $matches)) {
            $tableName = $matches[1];
        } elseif (preg_match('/(?:创建|生成|建立).*?([a-zA-Z_][a-zA-Z0-9_]*)/', $lowerPrompt, $matches)) {
            $tableName = $matches[1];
        }

        // 提取字段信息
        $fields = $this->extractFieldsFromPrompt($prompt);
        
        // 提取表注释
        $tableComment = $this->extractTableComment($prompt);
        
        // 构建表数据
        if ($tableName) {
            $parsedData['table'] = [
                'name' => $tableName,
                'fields' => $fields,
                'comment' => $tableComment
            ];

            // 构建控制器数据
            $controllerName = ucfirst($tableName) . 'Controller';
            $parsedData['controller'] = [
                'module' => 'backend',
                'name' => $controllerName,
                'fields' => $fields,
                'description' => $tableComment ?: $controllerName
            ];

            // 构建模型数据
            $modelName = ucfirst($tableName);
            $parsedData['model'] = [
                'name' => $modelName,
                'fields' => $fields,
                'table' => $tableName,
                'description' => $tableComment ?: $modelName
            ];

            // 构建API数据
            $parsedData['api'] = [
                'module' => 'api',
                'name' => $controllerName,
                'fields' => $fields,
                'description' => $tableComment ?: $controllerName
            ];

            // 构建CRUD数据
            $parsedData['crud'] = null;
        }

        // 解析特殊操作
        $this->parseSpecialOperations($lowerPrompt, $parsedData);

        return $parsedData;
    }

    /**
     * 解析特殊操作
     * @param string $lowerPrompt
     * @param array &$parsedData
     */
    private function parseSpecialOperations(string $lowerPrompt, array &$parsedData): void
    {
        // 解析API相关操作
        if (strpos($lowerPrompt, 'api') !== false || strpos($lowerPrompt, '接口') !== false || strpos($lowerPrompt, '接口文件') !== false) {
            if (!empty($parsedData['api'])) {
                $parsedData['api']['description'] = 'API接口文件';
            }
        }

        // 解析CRUD相关操作
        if (strpos($lowerPrompt, 'crud') !== false || strpos($lowerPrompt, '增删改查') !== false) {
            if (!empty($parsedData['crud'])) {
                $parsedData['crud']['description'] = 'CRUD模块';
            }
        }

        // 解析模块类型
        if (strpos($lowerPrompt, 'backend') !== false || strpos($lowerPrompt, '后台') !== false) {
            if (!empty($parsedData['controller'])) {
                $parsedData['controller']['module'] = 'backend';
            }
        }

        if (strpos($lowerPrompt, 'frontend') !== false || strpos($lowerPrompt, '前台') !== false) {
            if (!empty($parsedData['controller'])) {
                $parsedData['controller']['module'] = 'frontend';
            }
        }

        if (strpos($lowerPrompt, 'api') !== false) {
            if (!empty($parsedData['controller'])) {
                $parsedData['controller']['module'] = 'api';
            }
        }
    }

    /**
     * 从提示词中提取字段信息
     * @param string $prompt
     * @return array
     */
    private function extractFieldsFromPrompt(string $prompt): array
    {
        $fields = [];
        
        // 常见的字段模式匹配
        $fieldPatterns = [
            // 用户相关字段
            'username' => ['用户名', 'username', '用户名称'],
            'email' => ['邮箱', 'email', '邮件'],
            'phone' => ['手机', 'phone', '电话', '手机号'],
            'password' => ['密码', 'password'],
            'nickname' => ['昵称', 'nickname', '昵名'],
            'avatar' => ['头像', 'avatar', '照片'],
            'gender' => ['性别', 'gender'],
            'birthday' => ['生日', 'birthday', '出生日期'],
            'address' => ['地址', 'address', '住址'],
            'status' => ['状态', 'status'],
            
            // 通用字段
            'title' => ['标题', 'title', '名称'],
            'content' => ['内容', 'content', '描述'],
            'description' => ['描述', 'description', '说明'],
            'price' => ['价格', 'price', '金额'],
            'amount' => ['数量', 'amount', '数量'],
            'category_id' => ['分类', 'category', '分类ID'],
            'sort_order' => ['排序', 'sort_order', '顺序'],
            'remark' => ['备注', 'remark', '说明'],
            
            // 时间相关字段
            'created_at' => ['创建时间', 'created_at'],
            'updated_at' => ['更新时间', 'updated_at'],
            'publish_time' => ['发布时间', 'publish_time'],
            'expire_time' => ['过期时间', 'expire_time']
        ];

        foreach ($fieldPatterns as $fieldName => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($prompt, $pattern) !== false) {
                    $fields[] = $this->CreateFieldByType($fieldName);
                    break;
                }
            }
        }

        // 如果没有找到字段，添加默认字段
        if (empty($fields)) {
            $fields = [
                [
                    'name' => 'title',
                    'type' => 'varchar(255)',
                    'comment' => '标题',
                    'null' => false,
                    'default' => ''
                ],
                [
                    'name' => 'content',
                    'type' => 'text',
                    'comment' => '内容',
                    'null' => true
                ],
                [
                    'name' => 'status',
                    'type' => 'tinyint(1)',
                    'comment' => '状态：0=禁用，1=启用',
                    'null' => false,
                    'default' => 1
                ]
            ];
        }

        return $fields;
    }

    /**
     * 根据字段名生成字段配置
     * @param string $fieldName
     * @return array
     */
    private function CreateFieldByType(string $fieldName): array
    {
        $fieldConfigs = [
            'username' => [
                'name' => 'username',
                'type' => 'varchar(50)',
                'comment' => '用户名',
                'null' => false,
                'default' => ''
            ],
            'email' => [
                'name' => 'email',
                'type' => 'varchar(100)',
                'comment' => '邮箱',
                'null' => false,
                'default' => ''
            ],
            'phone' => [
                'name' => 'phone',
                'type' => 'varchar(20)',
                'comment' => '手机号',
                'null' => true
            ],
            'password' => [
                'name' => 'password',
                'type' => 'varchar(255)',
                'comment' => '密码',
                'null' => false,
                'default' => ''
            ],
            'nickname' => [
                'name' => 'nickname',
                'type' => 'varchar(50)',
                'comment' => '昵称',
                'null' => true
            ],
            'avatar' => [
                'name' => 'avatar',
                'type' => 'varchar(255)',
                'comment' => '头像',
                'null' => true
            ],
            'gender' => [
                'name' => 'gender',
                'type' => 'tinyint(1)',
                'comment' => '性别：0=未知，1=男，2=女',
                'null' => false,
                'default' => 0
            ],
            'birthday' => [
                'name' => 'birthday',
                'type' => 'date',
                'comment' => '生日',
                'null' => true
            ],
            'address' => [
                'name' => 'address',
                'type' => 'text',
                'comment' => '地址',
                'null' => true
            ],
            'status' => [
                'name' => 'status',
                'type' => 'tinyint(1)',
                'comment' => '状态：0=禁用，1=启用',
                'null' => false,
                'default' => 1
            ],
            'title' => [
                'name' => 'title',
                'type' => 'varchar(255)',
                'comment' => '标题',
                'null' => false,
                'default' => ''
            ],
            'content' => [
                'name' => 'content',
                'type' => 'text',
                'comment' => '内容',
                'null' => true
            ],
            'description' => [
                'name' => 'description',
                'type' => 'text',
                'comment' => '描述',
                'null' => true
            ],
            'price' => [
                'name' => 'price',
                'type' => 'decimal(10,2)',
                'comment' => '价格',
                'null' => false,
                'default' => 0.00
            ],
            'amount' => [
                'name' => 'amount',
                'type' => 'int(11)',
                'comment' => '数量',
                'null' => false,
                'default' => 0
            ],
            'category_id' => [
                'name' => 'category_id',
                'type' => 'int(11)',
                'comment' => '分类ID',
                'null' => false,
                'default' => 0
            ],
            'sort_order' => [
                'name' => 'sort_order',
                'type' => 'int(11)',
                'comment' => '排序',
                'null' => false,
                'default' => 0
            ],
            'remark' => [
                'name' => 'remark',
                'type' => 'varchar(255)',
                'comment' => '备注',
                'null' => true
            ],
            'publish_time' => [
                'name' => 'publish_time',
                'type' => 'datetime',
                'comment' => '发布时间',
                'null' => true
            ],
            'expire_time' => [
                'name' => 'expire_time',
                'type' => 'datetime',
                'comment' => '过期时间',
                'null' => true
            ]
        ];

        return $fieldConfigs[$fieldName] ?? [
            'name' => $fieldName,
            'type' => 'varchar(255)',
            'comment' => $fieldName,
            'null' => true
        ];
    }

    /**
     * 从提示词中提取表注释
     * @param string $prompt
     * @return string
     */
    private function extractTableComment(string $prompt): string
    {
        // 提取表注释的模式
        $patterns = [
            '/(?:用于|用来|存储|管理).*?(?:信息|数据|记录)/',
            '/(?:.*?)(?:表|table)/',
            '/(?:.*?)(?:管理|系统|模块)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches)) {
                return trim($matches[0]) . '表';
            }
        }

        return '';
    }

    /**
     * 生成FunAdmin API接口文件
     * @param string $module 模块名称（仅允许 api）
     * @param string $controller 控制器名称
     * @param array $fields 字段信息 (可选)
     * @param string $description 描述 (可选)
     * @return array
     */
    public function handleCreateApi(string $module, string $controller, array $fields = [], string $description = ''): array
    {
        try {
            $this->validateApiDefinition($module, $controller, $fields);
            $apiPath = "app/{$module}/controller/v2/{$controller}.php";

            // 生成API内容
            $apiContent = $this->generateApiContent($module, $controller, $fields, $description);

            // 确保目录存在
            $dir = dirname($apiPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (is_file($apiPath)) {
                throw new Exception("API 文件 {$apiPath} 已存在");
            }
            $this->atomicWrite($apiPath, $apiContent);

            Log::info("FunAdmin API文件生成成功: {$apiPath}");
            return [
                'success' => true,
                'message' => 'API文件生成成功',
                'file_path' => $apiPath,
                'content' => $apiContent
            ];

        } catch (\Throwable $e) {
            try {
                Log::error('FunAdmin API文件生成错误: ' . $e->getMessage());
            } catch (\Throwable) {
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成API文件内容
     * @param string $module 模块名称
     * @param string $controllerClass 控制器类名
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function generateApiContent(string $module, string $controllerClass, array $fields = [], string $description = ''): string
    {
        $this->validateApiDefinition($module, $controllerClass, $fields);
        $version = 'v2';
        $namespace = "app\\{$module}\\controller\\{$version}";
        $description = $this->sanitizeApiDescription($description !== '' ? $description : $controllerClass);
        $model = "\\app\\common\\model\\{$controllerClass}";
        $routeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $controllerClass));
        $validationRules = $this->generateApiValidationRules($fields);
        $allowedFields = array_values(array_filter(array_map(
            static fn (array $field): string => (string) ($field['name'] ?? ''),
            $fields
        ), static fn (string $field): bool => $field !== '' && !in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'], true)));
        $allowedFieldsCode = var_export($allowedFields, true);

        $content = "<?php

declare(strict_types=1);

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: AI Assistant
 * Date: " . date('Y/m/d') . "
 */
namespace {$namespace};

use app\\common\\controller\\Api;
use app\\common\\middleware\\MApi;
use think\\annotation\\route\\Delete;
use think\\annotation\\route\\Get;
use think\\annotation\\route\\Group;
use think\\annotation\\route\\Middleware;
use think\\annotation\\route\\Pattern;
use think\\annotation\\route\\Post;
use think\\annotation\\route\\Put;
use think\\exception\\ValidateException;
use think\\Request;
use think\\Response;

/**
 * {$description}
 */
#[Group('v2/{$routeName}')]
#[Middleware(MApi::class)]
class {$controllerClass} extends Api
{
    private const ALLOWED_FIELDS = {$allowedFieldsCode};

    public function __construct(private readonly {$model} \$model)
    {
    }

    /**
     * @NodeAnnotation(title='列表')
     */
    #[Get('')]
    public function index(Request \$request): Response
    {
        \$page = max(1, (int) \$request->get('page', 1));
        \$pageSize = min(100, max(1, (int) \$request->get('pageSize', 15)));
        \$result = \$this->model->field(array_merge(['id'], self::ALLOWED_FIELDS))
            ->order('id', 'desc')
            ->paginate(['list_rows' => \$pageSize, 'page' => \$page]);

        return \$this->ok(data: [
            'list' => \$result->items(),
            'total' => \$result->total(),
            'page' => \$page,
            'pageSize' => \$pageSize,
        ]);
    }

    /**
     * @NodeAnnotation(title='详情')
     */
    #[Get(':id')]
    #[Pattern('id', '\\\\d+')]
    public function show(int \$id): Response
    {
        \$row = \$this->model->find(\$id);
        if (!\$row) {
            return \$this->fail(msg: '记录不存在', code: 404);
        }
        return \$this->ok(msg: '获取成功', data: \$row);
    }

    /**
     * @NodeAnnotation(title='新增')
     */
    #[Post('')]
    public function create(Request \$request): Response
    {
        \$params = \$request->only(self::ALLOWED_FIELDS, 'post');
        
        // 验证数据
        try {
            validate([
                {$validationRules}
            ])->check(\$params);
        } catch (ValidateException \$e) {
            return \$this->fail(msg: \$e->getError(), code: 422);
        }
        
        \$result = \$this->model->save(\$params);
        if (!\$result) {
            return \$this->fail(msg: '添加失败');
        }
        return \$this->ok(msg: '添加成功');
    }

    /**
     * @NodeAnnotation(title='编辑')
     */
    #[Put(':id')]
    #[Pattern('id', '\\\\d+')]
    public function update(Request \$request, int \$id): Response
    {
        \$row = \$this->model->find(\$id);
        if (!\$row) {
            return \$this->fail(msg: '记录不存在', code: 404);
        }
        
        \$params = \$request->only(self::ALLOWED_FIELDS, 'put');
        
        // 验证数据
        try {
            validate([
                {$validationRules}
            ])->check(\$params);
        } catch (ValidateException \$e) {
            return \$this->fail(msg: \$e->getError(), code: 422);
        }
        
        \$result = \$row->save(\$params);
        if (!\$result) {
            return \$this->fail(msg: '更新失败');
        }
        return \$this->ok(msg: '更新成功');
    }

    /**
     * @NodeAnnotation(title='删除')
     */
    #[Delete(':id')]
    #[Pattern('id', '\\\\d+')]
    public function delete(int \$id): Response
    {
        \$row = \$this->model->find(\$id);
        if (!\$row) {
            return \$this->fail(msg: '记录不存在', code: 404);
        }
        
        \$result = \$row->delete();
        if (!\$result) {
            return \$this->fail(msg: '删除失败');
        }
        return \$this->ok(msg: '删除成功');
    }
}";
        
        return $content;
    }

    private function atomicWrite(string $file, string $content): void
    {
        $directory = dirname($file);
        $temporary = tempnam($directory, '.api-');
        if ($temporary === false) {
            throw new Exception('无法创建 API 临时文件');
        }
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $file)) {
                throw new Exception('API 文件原子写入失败');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function validateApiDefinition(string $module, string $controller, array $fields): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $module) || $module !== 'api') {
            throw new InvalidArgumentException('API 模块仅允许 api');
        }
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $controller)) {
            throw new InvalidArgumentException('API 控制器必须为 Studly PHP 类名');
        }
        $typePattern = '/^(?:varchar(?:\([1-9][0-9]{0,3}\))?|char(?:\([1-9][0-9]{0,3}\))?|(?:tiny|medium|long)?text|(?:tiny|small|medium|big)?int(?: unsigned)?|integer(?: unsigned)?|decimal\([1-9][0-9]?,[0-9]{1,2}\)|float(?:\([1-9][0-9]?,[0-9]{1,2}\))?|double(?:\([1-9][0-9]?,[0-9]{1,2}\))?|bool|boolean|date|datetime|timestamp|time|year|json)$/i';
        foreach ($fields as $field) {
            if (!is_array($field)) {
                throw new InvalidArgumentException('API 字段必须为对象数组');
            }
            $name = $field['name'] ?? null;
            $type = $field['type'] ?? 'varchar';
            if (!is_string($name) || !preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException('API 字段名格式非法');
            }
            if (!is_string($type) || !preg_match($typePattern, $type)) {
                throw new InvalidArgumentException('API 字段类型格式非法');
            }
        }
    }

    private function sanitizeApiDescription(string $description): string
    {
        $description = str_replace('*/', '', $description);
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $description));
    }

    /**
     * 生成API验证规则
     * @param array $fields 字段信息
     * @return string
     */
    private function generateApiValidationRules(array $fields): string
    {
        if (empty($fields)) {
            return "'title' => 'require|max:255',
                'content' => 'require',";
        }
        
        $rules = [];
        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldType = $field['type'] ?? 'varchar';
            
            if (empty($fieldName) || in_array($fieldName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            
            $rule = "'{$fieldName}' => '";
            
            // 根据字段类型设置验证规则
            if (strpos($fieldType, 'varchar') !== false) {
                $maxLength = 255;
                if (preg_match('/varchar\((\d+)\)/', $fieldType, $matches)) {
                    $maxLength = $matches[1];
                }
                $rule .= "max:{$maxLength}";
            } elseif (strpos($fieldType, 'text') !== false) {
                $rule .= "require";
            } elseif (strpos($fieldType, 'int') !== false) {
                $rule .= "number";
            } elseif (strpos($fieldType, 'datetime') !== false || strpos($fieldType, 'timestamp') !== false) {
                $rule .= "date";
            } else {
                $rule .= "require";
            }
            
            $rule .= "'";
            $rules[] = $rule;
        }
        
        return implode(",\n                ", $rules);
    }

    /**
     * 处理其他操作
     * @param string $prompt 自然语言描述
     * @return array
     */
    private function handleOtherOperation(string $prompt): array
    {
        $lowerPrompt = strtolower($prompt);
        $results = [];

        // 处理数据库查询操作
        if (strpos($lowerPrompt, '查询') !== false || strpos($lowerPrompt, 'select') !== false) {
            $results['db_query'] = [
                'success' => true,
                'message' => '检测到数据库查询操作',
                'suggestion' => '请使用 db-query 工具执行数据库查询'
            ];
        }

        // 处理系统配置操作
        if (strpos($lowerPrompt, '配置') !== false || strpos($lowerPrompt, 'config') !== false) {
            $results['sys_config'] = [
                'success' => true,
                'message' => '检测到系统配置操作',
                'suggestion' => '请使用 sys-config 工具获取系统配置'
            ];
        }

        // 处理日志操作
        if (strpos($lowerPrompt, '日志') !== false || strpos($lowerPrompt, 'log') !== false) {
            $results['write_log'] = [
                'success' => true,
                'message' => '检测到日志操作',
                'suggestion' => '请使用 write-log 工具写入系统日志'
            ];
        }

        // 处理文件操作
        if (strpos($lowerPrompt, '文件') !== false || strpos($lowerPrompt, 'file') !== false) {
            $results['file_operation'] = [
                'success' => true,
                'message' => '检测到文件操作',
                'suggestion' => '请使用 file-operation 工具进行文件读写操作'
            ];
        }

        // 处理用户管理操作
        if (strpos($lowerPrompt, '用户') !== false || strpos($lowerPrompt, 'user') !== false) {
            $results['user_management'] = [
                'success' => true,
                'message' => '检测到用户管理操作',
                'suggestion' => '请使用 user-management 工具进行用户管理'
            ];
        }

        // 处理系统信息操作
        if (strpos($lowerPrompt, '系统信息') !== false || strpos($lowerPrompt, 'system') !== false) {
            $results['system_info'] = [
                'success' => true,
                'message' => '检测到系统信息操作',
                'suggestion' => '请使用 system-info 工具获取系统运行信息'
            ];
        }

        // 处理控制器生成操作
        if (strpos($lowerPrompt, '控制器') !== false || strpos($lowerPrompt, 'controller') !== false) {
            $results['controller'] = [
                'success' => true,
                'message' => '检测到控制器生成操作',
                'suggestion' => '请使用 controller 工具生成控制器文件'
            ];
        }

        // 处理模型生成操作
        if (strpos($lowerPrompt, '模型') !== false || strpos($lowerPrompt, 'model') !== false) {
            $results['model'] = [
                'success' => true,
                'message' => '检测到模型生成操作',
                'suggestion' => '请使用 model 工具生成模型文件'
            ];
        }

        // 处理数据库表创建操作
        if (strpos($lowerPrompt, '数据库表') !== false || strpos($lowerPrompt, 'table') !== false) {
            $results['table'] = [
                'success' => true,
                'message' => '检测到数据库表创建操作',
                'suggestion' => '请使用 table 工具创建数据库表'
            ];
        }

        // 处理CRUD操作
        if (strpos($lowerPrompt, 'crud') !== false || strpos($lowerPrompt, '增删改查') !== false) {
            $results['crud'] = [
                'success' => true,
                'message' => '检测到CRUD操作',
                'suggestion' => '请使用 crud 工具生成 CRUD 模块'
            ];
        }

        // 处理API接口生成操作
        if (strpos($lowerPrompt, 'api') !== false || strpos($lowerPrompt, '接口') !== false) {
            $results['api'] = [
                'success' => true,
                'message' => '检测到API接口生成操作',
                'suggestion' => '请使用 api 工具生成API接口文件'
            ];
        }

        // 处理ThinkPHP命令执行操作
        if (strpos($lowerPrompt, 'think') !== false || strpos($lowerPrompt, '命令') !== false || 
            strpos($lowerPrompt, 'command') !== false || strpos($lowerPrompt, '执行') !== false ||
            strpos($lowerPrompt, 'make:') !== false || strpos($lowerPrompt, 'optimize') !== false ||
            strpos($lowerPrompt, 'clear') !== false || strpos($lowerPrompt, 'build') !== false) {
            $results['think-command'] = [
                'success' => true,
                'message' => '检测到ThinkPHP命令执行操作',
                'suggestion' => '请使用 think-command 工具执行ThinkPHP内置命令，支持的命令包括：make、optimize、clear、build、route:list等'
            ];
        }

        // 如果没有匹配到任何操作，返回通用建议
        if (empty($results)) {
            $results['general'] = [
                'success' => false,
                'message' => '未能识别具体的操作类型',
                'suggestion' => '请尝试以下操作：' . "\n" .
                    '- 创建数据库表：包含表名和字段信息' . "\n" .
                    '- 生成控制器：指定模块和控制器名称' . "\n" .
                    '- 生成模型：指定模型名称和字段信息' . "\n" .
                    '- 生成API接口：指定模块和控制器名称' . "\n" .
                    '- 生成CRUD模块：指定版本化 CRUD Definition JSON' . "\n" .
                    '- 数据库查询：使用SELECT语句' . "\n" .
                    '- 系统配置：获取系统配置信息' . "\n" .
                    '- 文件操作：进行文件读写操作' . "\n" .
                    '- 用户管理：进行用户相关操作' . "\n" .
                    '- 系统信息：获取系统运行信息' . "\n" .
                    '- ThinkPHP命令：执行框架内置命令（make、optimize、clear等）'
            ];
        }

        return $results;
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
                // make 命令组
                'make:command', 'make:controller', 'make:event', 'make:listener', 
                'make:middleware', 'make:model', 'make:service', 'make:subscribe', 'make:validate',
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
                'crud:inspect', 'crud:validate', 'crud:preview', 'crud:generate', 'mcp'
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

            return [
                'success' => $returnCode === 0,
                'message' => $returnCode === 0 ? '命令执行成功' : '命令执行失败',
                'command' => $fullCommand,
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
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