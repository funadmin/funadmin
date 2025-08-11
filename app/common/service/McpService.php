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

use PhpMcp\Server\Server;
use PhpMcp\Server\ServerBuilder;
use PhpMcp\Server\Transports\StdioServerTransport;
use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Schema\Tool;
use PhpMcp\Schema\Resource;
use PhpMcp\Schema\ToolAnnotations;
use PhpMcp\Schema\Annotations;
use think\facade\Config;
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
    protected $name = 'funadmin-mcp';

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
        
            $mcpConfig = Config::get('mcp', []);
            
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
            ->withTool([self::class, 'handleCreateView'], 'view', '生成FunAdmin视图文件')
            ->withTool([self::class, 'handleCreateJs'], 'js', '生成FunAdmin JS文件')
            ->withTool([self::class, 'handleCreateApi'], 'api', '生成FunAdmin API接口文件')
            ->withTool([self::class, 'handleCurd'], 'curd', '生成FunAdmin CURD模块')
            ->withTool([self::class, 'handleAddon'], 'addon', '生成FunAdmin 插件模块')
            ->withTool([self::class, 'handleMenu'], 'menu', '生成FunAdmin 菜单模块')
            ->withTool([self::class, 'handleCreateTable'], 'table', '创建数据库表格，   支持字段信息、类型、注释等')
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
                        'debug' => Config::get('app.debug'),
                        'default_timezone' => Config::get('app.default_timezone'),
                        'default_lang' => Config::get('app.default_lang'),
                    ],
                    'database' => [
                        'type' => Config::get('database.default.type'),
                        'hostname' => Config::get('database.default.hostname'),
                        'database' => Config::get('database.default.database'),
                    ],
                    'cache' => [
                        'default' => Config::get('cache.default'),
                        'stores' => array_keys(Config::get('cache.stores', [])),
                    ]
                ];
            } else {
                return ['value' => Config::get($key), 'key' => $key];
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
                        ->field('id,username,email,mobile,create_time,status')
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
                        ->field('id,username,nickname,email,mobile,create_time,status')
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
                        'type' => Config::get('database.default.type'),
                        'version' => $version,
                        'charset' => Config::get('database.default.charset'),
                        'collation' => Config::get('database.default.collate'),
                    ];

                case 'performance':
                    return [
                        'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                        'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
                        'included_files' => count(get_included_files()),
                    ];

                case 'cache':
                    return [
                        'default_driver' => Config::get('cache.default'),
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
            'app' => Config::get('app'),
            'database' => [
                'type' => Config::get('database.default.type'),
                'charset' => Config::get('database.default.charset'),
                'debug' => Config::get('database.debug'),
            ],
            'cache' => Config::get('cache'),
            'session' => Config::get('session'),
            'log' => Config::get('log'),
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
            'tools' => 15, // 10个工具（新增1个）
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
        try {
            // 生成控制器类名
            $controllerClass = ucfirst($controller);
            $controllerPath = "app/{$module}/controller/{$controllerClass}.php";
            
            // 检查文件是否已存在
            if (file_exists($controllerPath)) {
                return [
                    'success' => false,
                    'error' => "控制器文件 {$controllerPath} 已存在"
                ];
            }

            // 生成控制器内容
            $controllerContent = $this->CreateControllerContent($module, $controllerClass, $fields, $description);
            
            // 确保目录存在
            $dir = dirname($controllerPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 写入文件
            if (file_put_contents($controllerPath, $controllerContent)) {
                Log::info("FunAdmin控制器生成成功: {$controllerPath}");
                return [
                    'success' => true,
                    'message' => '控制器生成成功',
                    'file_path' => $controllerPath,
                    'content' => $controllerContent
                ];
            } else {
                throw new Exception('文件写入失败');
            }

        } catch (Exception $e) {
            Log::error('FunAdmin控制器生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
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
            $modelClass = ucfirst($modelName);
            $modelPath = "app/common/model/{$modelClass}.php";
            
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
            $modelContent = $this->CreateModelContent($modelClass, $tableName, $fields, $description);
            
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
     * 生成控制器内容
     * @param string $module 模块名称
     * @param string $controllerClass 控制器类名
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function CreateControllerContent(string $module, string $controllerClass, array $fields = [], string $description = ''): string
    {
        $namespace = "app\\{$module}\\controller";
        $baseController = $module === 'api' ? 'Api' : 'Backend';
        $useStatement = $module === 'api' ? 'use app\\common\\controller\\Api;' : 'use app\\common\\controller\\Backend;';
        
        $description = $description ?: $controllerClass;
        
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
namespace {$namespace};

{$useStatement}
use think\\App;
use think\\Request;
use app\\common\\annotation\\ControllerAnnotation;
use app\\common\\annotation\\NodeAnnotation;
use think\\exception\\ValidateException;
/**
 * @ControllerAnnotation('{$description}')
 * Class {$controllerClass}
 * @package {$namespace}
 */
class {$controllerClass} extends {$baseController}
{
    protected array \$noNeedLogin = [];
    protected array \$noNeedRight = [];
    
    public function __construct(App \$app)
    {
        parent::__construct(\$app);
    }

    /**
     * @NodeAnnotation(title='列表')
     * @param Request \$request
     * @return \\think\\response\\View
     */
    public function index()
    {
        if (request()->isAjax()) {
            if (request()->param('selectFields')) {
                \$this->selectList();
            }
            list(\$this->page, \$this->pageSize,\$sort,\$where,\$tableName) = \$this->buildParames();
            \$list = \$this->modelClass->where(\$where)->order(\$sort)->paginate([
                'list_rows'=> \$this->pageSize,
                'page' => \$this->page,
            ]);
            if(!empty(\$this->hiddenFields) ){
                foreach (\$this->hiddenFields as \$key=>\$field){
                    \$this->hiddenFields[\$key] = \$tableName.\$field;
                }
                \$list = \$list->hidden(\$this->hiddenFields);
            }
            if(!empty(\$this->visibleFields) ){
                foreach (\$this->visibleFields as \$key=>\$field){
                    \$this->visibleFields[\$key] = \$tableName.\$field;
                }
                \$list = \$list->visible(\$this->visibleFields);
            }
            \$result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => \$list->items(), 'count' =>\$list->total()];
            return json(\$result);
        }
        return view();
    }

    /**
     * @NodeAnnotation(title='添加')
     * @param Request \$request
     * @return \\think\\response\\View
     */
    public function add()
    {
       if (request()->isPost()) {
            \$post = request()->post();
            foreach (\$post as \$k=>\$v){
                if(is_array(\$v)){
                    \$post[\$k] = implode(',',\$v);
                }
            }
            \$rule = [];
            try {
                \$this->validate(\$post, \$rule);
            }catch (ValidateException \$e){
                \$this->error(lang(\$e->getMessage()));
            }
            try {
                \$save = \$this->modelClass->save(\$post);
            } catch (\Exception \$e) {
                \$this->error(lang(\$e->getMessage()));
            }
            \$save ? \$this->success(lang('operation success')) : \$this->error(lang('operation failed'));
        }
        \$view = [
            'formData' => '',
            'title' => lang('Add'),
        ];
        return view('add',\$view);
    }

    /**
     * @NodeAnnotation(title='编辑')
     * @return \\think\\response\\View
     */
    public function edit()
    {
        \$id = request()->param(\$this->modelClass->getPk());
        \$list = \$this->findModel(\$id);
        if(empty(\$list)) \$this->error(lang('Data is not exist'));
        if (request()->isPost()) {
            \$post = request()->post();
            \$rule = [];
            try {
                \$this->validate(\$post, \$rule);
            }catch (ValidateException \$e){
                \$this->error(lang(\$e->getMessage()));
            }
            foreach (\$post as \$k=>\$v){
                if(is_array(\$v)){
                    \$post[\$k] = implode(',',\$v);
                }
                if (\$v == '0000-00-00 00:00:00') {//避免插入数据库时出现错误，日期不能为空
		            \$post[\$k] = null;
	            }
	            if (\$v == '') {//避免插入数据库时出现错误，日期不能为空
		            \$post[\$k] = null;
	            }
            }
            try {
                \$save = \$list->save(\$post);
            } catch (\Exception \$e) {
                \$this->error(lang(\$e->getMessage()));
            }
            \$save ? \$this->success(lang('operation success')) : \$this->error(lang('operation failed'));
        }
        \$view = ['formData'=>\$list,'title' => lang('Edit'),];
        return view('add',\$view);
    }
}";

        return $content;
    }

    /**
     * 生成模型内容
     * @param string $modelClass 模型类名
     * @param string $tableName 表名
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function CreateModelContent(string $modelClass, string $tableName, array $fields = [], string $description = ''): string
    {
        $description = $description ?: $modelClass;
        
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
 * Class {$modelClass}
 * @package app\\common\\model
 */
class {$modelClass} extends BaseModel
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
    protected \$deleteTime = 'delete_time';

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
        $hasCreateTime = false;
        $hasUpdateTime = false;
        $hasDeleteTime = false;

        foreach ($fields as $field) {
            if ($field['name'] === 'id') $hasId = true;
            if ($field['name'] === 'create_time') $hasCreateTime = true;
            if ($field['name'] === 'update_time') $hasUpdateTime = true;
            if ($field['name'] === 'delete_time') $hasDeleteTime = true;
        }

        // 如果没有ID字段，添加默认ID字段
        if (!$hasId) {
            array_unshift($fieldDefinitions, "    `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID'");
        }

        // 添加默认时间字段
        if (!$hasCreateTime) {
            $fieldDefinitions[] = "    `create_time` int(11) DEFAULT NULL COMMENT '创建时间'";
        }
        if (!$hasUpdateTime) {
            $fieldDefinitions[] = "    `update_time` int(11) DEFAULT NULL COMMENT '更新时间'";
        }
        if (!$hasDeleteTime) {
            $fieldDefinitions[] = "    `delete_time` int(11) DEFAULT NULL COMMENT '删除时间'";
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
     * 处理CRUD生成，基于fun/curd/Curd.php功能
     * @param string $tableName 表名
     * @param string $module 模块名（backend/frontend/api）
     * @param array $fields 字段信息
     * @param string $description 描述
     * @param array $options 其他选项
     * @return array
     */
    public function handleCurd(string $tableName, string $module = 'backend', array $fields = [], string $description = '', array $options = []): array
    {
        try {
            // 构建命令行参数
            $parameters = [
                '--table=' . $tableName,
                '--app=' . $module,
                '--controller=' . $this->convertTableNameToControllerName($tableName),
                '--model=' . $this->convertTableNameToModelName($tableName),
                '--validate=' . $this->convertTableNameToModelName($tableName),
            ];

            // 添加可选参数
            if (!empty($options['force'])) {
                $parameters[] = '--force=1';
            }
            if (!empty($options['menu'])) {
                $parameters[] = '--menu=1';
            }
            if (!empty($options['menuname'])) {
                $parameters[] = '--menuname=' . $options['menuname'];
            }
            if (!empty($options['common'])) {
                $parameters[] = '--common=1';
            }

            // 调用curd命令
            $output = \think\facade\Console::call('curd', $parameters);
            $content = $output->fetch();

            // 检查执行结果
            if (strpos($content, 'success') !== false || strpos($content, 'make success') !== false) {
                return [
                    'success' => true,
                    'message' => 'CRUD模块生成成功',
                    'data' => [
                        'table' => $tableName,
                        'module' => $module,
                        'controller' => $this->convertTableNameToControllerName($tableName),
                        'model' => $this->convertTableNameToModelName($tableName),
                        'output' => $content
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'CRUD模块生成失败',
                    'output' => $content
                ];
            }

        } catch (\Exception $e) {
            Log::error('CRUD生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理插件管理，基于fun/curd/Addon.php功能
     * @param string $action 操作类型（create/install/uninstall/enable/disable）
     * @param string $addonName 插件名称
     * @param array $options 其他选项
     * @return array
     */
    public function handleAddon(string $action, string $addonName = '', array $options = []): array
    {
        try {
            // 构建命令行参数
            $parameters = [];

            switch ($action) {
                case 'create':
                    if (empty($addonName)) {
                        throw new \Exception('插件名称不能为空');
                    }
                    $parameters = [
                        '--app=' . $addonName,
                        '--title=' . ($options['title'] ?? $addonName),
                        '--description=' . ($options['description'] ?? $addonName),
                        '--author=' . ($options['author'] ?? 'FunAdmin'),
                        '--ver=' . ($options['version'] ?? '1.0.0'),
                        '--requires=' . ($options['requires'] ?? '1.0.0'),

                    ];
                    if (!empty($options['force'])) {
                        $parameters[] = '--force=1';
                    }
                    break;

                case 'install':
                    if (empty($addonName)) {
                        throw new \Exception('插件名称不能为空');
                    }
                    $parameters = [
                        '--install=1',
                        '--app=' . $addonName
                    ];
                    break;

                case 'uninstall':
                    if (empty($addonName)) {
                        throw new \Exception('插件名称不能为空');
                    }
                    $parameters = [
                        '--delete=1',
                        '--force=1',
                        '--app=' . $addonName,
                        

                    ];
                    break;

                case 'enable':
                    if (empty($addonName)) {
                        throw new \Exception('插件名称不能为空');
                    }
                    // 这里需要根据具体的addon命令参数来实现
                    $parameters = [
                        '--app=' . $addonName,
                        '--enable=1',
                    ];
                    break;

                case 'disable':
                    if (empty($addonName)) {
                        throw new \Exception('插件名称不能为空');
                    }
                    // 这里需要根据具体的addon命令参数来实现
                    $parameters = [
                        '--app=' . $addonName,
                        '--disable=1',
                    ];
                    break;
                default:
                    throw new \Exception('不支持的操作类型: ' . $action);
            }

            // 调用addon命令
            $output = \think\facade\Console::call('addon', $parameters);
            $content = $output->fetch();

            // 检查执行结果
            if (strpos($content, 'success') !== false || strpos($content, 'make success') !== false) {
                return [
                    'success' => true,
                    'message' => "插件{$action}操作成功",
                    'data' => [
                        'action' => $action,
                        'addon_name' => $addonName,
                        'output' => $content
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "插件{$action}操作失败",
                    'output' => $content
                ];
            }

        } catch (\Exception $e) {
            Log::error('插件管理错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理菜单管理，基于fun/curd/Menu.php功能
     * @param string $action 操作类型（create/delete）
     * @param array $menuData 菜单数据
     * @param array $options 其他选项
     * @return array
     */
    public function handleMenu(string $action, array $menuData = [], array $options = []): array
    {
        try {
            // 构建命令行参数
            $parameters = [];

            switch ($action) {
                case 'create':
                    if (empty($menuData['controller'])) {
                        throw new \Exception('控制器名称不能为空');
                    }
                    $parameters = [
                        '--controller=' . $menuData['controller'],
                        '--app=' . ($menuData['app'] ?? 'backend'),
                    ];
                    if (!empty($menuData['menuname'])) {
                        $parameters[] = '--menuname=' . $menuData['menuname'];
                    }
                    if (!empty($options['force'])) {
                        $parameters[] = '--force=1';
                    }
                    break;
                case 'delete':
                    if (empty($menuData['controller'])) {
                        throw new \Exception('控制器名称不能为空');
                    }
                    $parameters = [
                        '--controller=' . $menuData['controller'],
                        '--app=' . ($menuData['app'] ?? 'backend'),
                        '--delete=1',
                        '--force=1'
                    ];
                    break;
                default:
                    throw new \Exception('不支持的操作类型: ' . $action);
            }

            // 调用menu命令
            $output = \think\facade\Console::call('menu', $parameters);
            $content = $output->fetch();

            // 检查执行结果
            if (strpos($content, 'success') !== false || strpos($content, 'make success') !== false) {
                return [
                    'success' => true,
                    'message' => "菜单{$action}操作成功",
                    'data' => [
                        'action' => $action,
                        'menu_data' => $menuData,
                        'output' => $content
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "菜单{$action}操作失败",
                    'output' => $content
                ];
            }

        } catch (\Exception $e) {
            Log::error('菜单管理错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 通过自然语言描述生成数据库表、控制器、模型等
     * @param string $prompt 自然语言描述
     * @param string $type 生成类型 (table/controller/model/js/api/view/all)
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

            if (in_array($type, ['js', 'all'])) {
                if (!empty($parsedData['js'])) {
                    $jsResult = $this->handleCreateJs(
                        $parsedData['js']['module'] ?? 'backend',
                        $parsedData['js']['name'],
                        $parsedData['js']['fields'] ?? [],
                        $parsedData['js']['description'] ?? ''
                    );
                    $results['js'] = $jsResult;
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

            if (in_array($type, ['view', 'all'])) {
                if (!empty($parsedData['view'])) {
                    $viewResult = $this->handleCreateView(
                        $parsedData['view']['module'] ?? 'backend',
                        $parsedData['view']['name'],
                        $parsedData['view']['fields'] ?? [],
                        $parsedData['view']['description'] ?? ''
                    );
                    $results['view'] = $viewResult;
                }
            }

            if (in_array($type, ['addon', 'all'])) {
                if (!empty($parsedData['addon'])) {
                    $addonResult = $this->handleAddon(
                        'create',
                        $parsedData['addon']['name'],
                        $parsedData['addon']['options'] ?? []
                    );
                    $results['addon'] = $addonResult;
                }
            }

            if (in_array($type, ['curd', 'all'])) {
                if (!empty($parsedData['curd'])) {
                    $curdResult = $this->handleCurd(
                        $parsedData['curd']['name'],
                        $parsedData['curd']['module'] ?? 'backend',
                        $parsedData['curd']['fields'] ?? [],
                        $parsedData['curd']['description'] ?? '',
                        $parsedData['curd']['options'] ?? []
                    );
                    $results['curd'] = $curdResult;
                }   
            }

            if (in_array($type, ['menu', 'all'])) {
                if (!empty($parsedData['menu'])) {
                    $menuResult = $this->handleMenu(
                        'create',
                        $parsedData['menu']['data'] ?? [],
                        $parsedData['menu']['options'] ?? []
                    );
                    $results['menu'] = $menuResult;
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
            'js' => null,
            'api' => null,
            'view' => null,
            'addon' => null,
            'curd' => null,
            'menu' => null
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

            // 构建JS数据
            $parsedData['js'] = [
                'module' => 'backend',
                'name' => $controllerName,
                'fields' => $fields,
                'description' => $tableComment ?: $controllerName
            ];

            // 构建API数据
            $parsedData['api'] = [
                'module' => 'api',
                'name' => $controllerName,
                'fields' => $fields,
                'description' => $tableComment ?: $controllerName
            ];

            // 构建视图数据
            $parsedData['view'] = [
                'module' => 'backend',
                'name' => $controllerName,
                'fields' => $fields,
                'description' => $tableComment ?: $controllerName
            ];

            // 构建CRUD数据
            $parsedData['curd'] = [
                'name' => $tableName,
                'module' => 'backend',
                'fields' => $fields,
                'description' => $tableComment ?: $tableName,
                'options' => []
            ];

            // 构建菜单数据
            $parsedData['menu'] = [
                'data' => [
                    'controller' => $controllerName,
                    'app' => 'backend',
                    'menuname' => $tableComment ?: $tableName
                ],
                'options' => []
            ];
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
        // 解析JS相关操作
        if (strpos($lowerPrompt, 'js') !== false || strpos($lowerPrompt, 'javascript') !== false || strpos($lowerPrompt, '前端') !== false) {
            if (!empty($parsedData['js'])) {
                $parsedData['js']['description'] = '前端JS文件';
            }
        }

        // 解析API相关操作
        if (strpos($lowerPrompt, 'api') !== false || strpos($lowerPrompt, '接口') !== false || strpos($lowerPrompt, '接口文件') !== false) {
            if (!empty($parsedData['api'])) {
                $parsedData['api']['description'] = 'API接口文件';
            }
        }

        // 解析视图相关操作
        if (strpos($lowerPrompt, 'view') !== false || strpos($lowerPrompt, '视图') !== false || strpos($lowerPrompt, '页面') !== false) {
            if (!empty($parsedData['view'])) {
                $parsedData['view']['description'] = '视图文件';
            }
        }

        // 解析插件相关操作
        if (strpos($lowerPrompt, 'addon') !== false || strpos($lowerPrompt, '插件') !== false) {
            if (preg_match('/(?:创建|生成|建立).*?(?:插件|addon).*?[名为|叫|是]\s*([a-zA-Z_][a-zA-Z0-9_]*)/', $lowerPrompt, $matches)) {
                $addonName = $matches[1];
                $parsedData['addon'] = [
                    'name' => $addonName,
                    'options' => [
                        'title' => $addonName,
                        'description' => $addonName . '插件',
                        'author' => 'FunAdmin',
                        'version' => '1.0.0',
                        'requires' => '1.0.0'
                    ]
                ];
            }
        }

        // 解析菜单相关操作
        if (strpos($lowerPrompt, 'menu') !== false || strpos($lowerPrompt, '菜单') !== false) {
            if (!empty($parsedData['menu'])) {
                $parsedData['menu']['description'] = '菜单权限';
            }
        }

        // 解析CRUD相关操作
        if (strpos($lowerPrompt, 'curd') !== false || strpos($lowerPrompt, 'crud') !== false || strpos($lowerPrompt, '增删改查') !== false) {
            if (!empty($parsedData['curd'])) {
                $parsedData['curd']['description'] = 'CRUD模块';
            }
        }

        // 解析模块类型
        if (strpos($lowerPrompt, 'backend') !== false || strpos($lowerPrompt, '后台') !== false) {
            if (!empty($parsedData['controller'])) {
                $parsedData['controller']['module'] = 'backend';
            }
            if (!empty($parsedData['js'])) {
                $parsedData['js']['module'] = 'backend';
            }
            if (!empty($parsedData['view'])) {
                $parsedData['view']['module'] = 'backend';
            }
        }

        if (strpos($lowerPrompt, 'frontend') !== false || strpos($lowerPrompt, '前台') !== false) {
            if (!empty($parsedData['controller'])) {
                $parsedData['controller']['module'] = 'frontend';
            }
            if (!empty($parsedData['js'])) {
                $parsedData['js']['module'] = 'frontend';
            }
            if (!empty($parsedData['view'])) {
                $parsedData['view']['module'] = 'frontend';
            }
        }

        if (strpos($lowerPrompt, 'api') !== false) {
            if (!empty($parsedData['controller'])) {
                $parsedData['controller']['module'] = 'api';
            }
            if (!empty($parsedData['js'])) {
                $parsedData['js']['module'] = 'api';
            }
            if (!empty($parsedData['view'])) {
                $parsedData['view']['module'] = 'api';
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
            'sort' => ['排序', 'sort', '顺序'],
            'remark' => ['备注', 'remark', '说明'],
            
            // 时间相关字段
            'create_time' => ['创建时间', 'create_time'],
            'update_time' => ['更新时间', 'update_time'],
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
            'sort' => [
                'name' => 'sort',
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
     * 转换表名为控制器名
     * @param string $tableName
     * @return string
     */
    private function convertTableNameToControllerName(string $tableName): string
    {
        // 移除表前缀
        $prefix = config('database.connections.mysql.prefix');
        if (strpos($tableName, $prefix) === 0) {
            $tableName = substr($tableName, strlen($prefix));
        }
        
        // 转换为驼峰命名
        return ucfirst(\think\helper\Str::camel($tableName));
    }

    /**
     * 转换表名为模型名
     * @param string $tableName
     * @return string
     */
    private function convertTableNameToModelName(string $tableName): string
    {
        return $this->convertTableNameToControllerName($tableName);
    }

    /**
     * 生成FunAdmin JS文件
     * @param string $module 模块名称 (backend/api/frontend等)
     * @param string $controller 控制器名称
     * @param array $fields 字段信息 (可选)
     * @param string $description 描述 (可选)
     * @return array
     */
    public function handleCreateJs(string $module, string $controller, array $fields = [], string $description = ''): array
    {
        try {
            // 生成JS文件名
            $jsFileName = strtolower($controller);
            $jsPath = "public/static/{$module}/js/{$jsFileName}.js";
            
            // 检查文件是否已存在
            if (file_exists($jsPath)) {
                return [
                    'success' => false,
                    'error' => "JS文件 {$jsPath} 已存在"
                ];
            }

            // 生成JS内容
            $jsContent = $this->generateJsContent($module, $controller, $fields, $description);
            
            // 确保目录存在
            $dir = dirname($jsPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 写入文件
            if (file_put_contents($jsPath, $jsContent)) {
                Log::info("FunAdmin JS文件生成成功: {$jsPath}");
                return [
                    'success' => true,
                    'message' => 'JS文件生成成功',
                    'file_path' => $jsPath,
                    'content' => $jsContent
                ];
            } else {
                throw new Exception('文件写入失败');
            }

        } catch (Exception $e) {
            Log::error('FunAdmin JS文件生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成FunAdmin API接口文件
     * @param string $controller 控制器名称
     * @param string $module 模块名称 (api等)
     * @param array $fields 字段信息 (可选)
     * @param string $description 描述 (可选)
     * @return array
     */
    public function handleCreateApi(string $controller, string $module = 'api', array $fields = [], string $description = ''): array
    {
        try {
            // 生成API控制器类名
            $controllerClass = ucfirst($controller);
            $apiPath = "app/{$module}/controller/{$controllerClass}.php";
            
            // 检查文件是否已存在
            if (file_exists($apiPath)) {
                return [
                    'success' => false,
                    'error' => "API文件 {$apiPath} 已存在"
                ];
            }

            // 生成API内容
            $apiContent = $this->generateApiContent($module, $controllerClass, $fields, $description);
            
            // 确保目录存在
            $dir = dirname($apiPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 写入文件
            if (file_put_contents($apiPath, $apiContent)) {
                Log::info("FunAdmin API文件生成成功: {$apiPath}");
                return [
                    'success' => true,
                    'message' => 'API文件生成成功',
                    'file_path' => $apiPath,
                    'content' => $apiContent
                ];
            } else {
                throw new Exception('文件写入失败');
            }

        } catch (Exception $e) {
            Log::error('FunAdmin API文件生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成JS文件内容
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function generateJsContent(string $module, string $controller, array $fields = [], string $description = ''): string
    {
        $controllerLower = strtolower($controller);
        $description = $description ?: $controller;
        
        // 生成表格列配置
        $jsCols = $this->generateJsCols($fields);
        
        $content = "define(['table', 'form'], function (Table, Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: '{$controllerLower}/index',
                    add_url: '{$controllerLower}/add',
                    edit_url: '{$controllerLower}/edit',
                    del_url: '{$controllerLower}/del',
                    multi_url: '{$controllerLower}/multi',
                    table_url: '{$controllerLower}/table',
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh', 'add', 'destroy', 'import', 'export'],
                cols: [[
                    {type: 'checkbox'},
                    {field: 'id', title: 'ID', width: 80, sort: true},
{$jsCols}
                    {field: 'create_time', title: '创建时间', width: 180, sort: true},
                    {field: 'update_time', title: '更新时间', width: 180, sort: true},
                    {title: '操作', width: 250, align: 'center', operat: ['index_url','copy', 'destroy']}
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true,
                done: function (res, curr, count) {
                    // 表格渲染完成后的回调
                }
            });
            Table.api.bindEvent(Table.init.tableId);
        },
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()
        },
        copy:function () {
            Controller.api.bindevent();
        },
        recycle: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {type: 'checkbox'},
                    {field: 'id', title: 'ID', width: 80, sort: true},
{$jsCols}
                    {field: 'create_time', title: '创建时间', width: 180, sort: true},
                    {field: 'update_time', title: '更新时间', width: 180, sort: true},
                    {title: '操作', width: 250, align: 'center', operat: ['restore', 'delete']}
                ]],
                limits: [10, 15, 20, 25, 50, 100,500,1000,5000],
                limit: 15,
                page: true
            });
            Table.api.bindEvent(Table.init.tableId);
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }
    };
    return Controller;
});";
        
        return $content;
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
        $namespace = "app\\{$module}\\controller";
        $description = $description ?: $controllerClass;
        
        // 生成字段验证规则
        $validationRules = $this->generateApiValidationRules($fields);
        
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
namespace {$namespace};

use app\\common\\controller\\Api;
use think\\App;
use think\\Request;
use app\\common\\annotation\\ControllerAnnotation;
use app\\common\\annotation\\NodeAnnotation;

/**
 * @ControllerAnnotation('{$description}')
 * Class {$controllerClass}
 * @package {$namespace}
 */
class {$controllerClass} extends Api
{
    protected \$modelClass = null;
    protected \$noNeedLogin = ['index', 'read'];
    protected \$noNeedRight = ['index', 'read'];

    public function __construct(App \$app)
    {
        parent::__construct(\$app);
        \$this->modelClass = new \\app\\common\\model\\{$controllerClass}();
    }

    /**
     * @NodeAnnotation(title='列表')
     */
    public function index()
    {
        \$where = \$this->request->get();
        \$list = \$this->modelClass
            ->where(\$where)
            ->order('id desc')
            ->paginate([
                'list_rows' => \$this->request->get('limit', 15),
                'page' => \$this->request->get('page', 1),
            ]);
        
        \$this->success('获取成功', \$list);
    }

    /**
     * @NodeAnnotation(title='详情')
     */
    public function read(\$id)
    {
        \$row = \$this->modelClass->find(\$id);
        if (!\$row) {
            \$this->error('记录不存在');
        }
        \$this->success('获取成功', \$row);
    }

    /**
     * @NodeAnnotation(title='新增')
     */
    public function save()
    {
        \$params = \$this->request->post();
        
        // 验证数据
        try {
            validate([
                'title' => 'require|max:255',
                'content' => 'require',
            ])->check(\$params);
        } catch (ValidateException \$e) {
            \$this->error(\$e->getError());
        }
        
        \$result = \$this->modelClass->save(\$params);
        if (\$result) {
            \$this->success('添加成功');
        } else {
            \$this->error('添加失败');
        }
    }

    /**
     * @NodeAnnotation(title='编辑')
     */
    public function update(\$id)
    {
        \$row = \$this->modelClass->find(\$id);
        if (!\$row) {
            \$this->error('记录不存在');
        }
        
        \$params = \$this->request->put();
        
        // 验证数据
        try {
            validate([
                'title' => 'require|max:255',
                'content' => 'require',
            ])->check(\$params);
        } catch (ValidateException \$e) {
            \$this->error(\$e->getError());
        }
        
        \$result = \$row->save(\$params);
        if (\$result) {
            \$this->success('更新成功');
        } else {
            \$this->error('更新失败');
        }
    }

    /**
     * @NodeAnnotation(title='删除')
     */
    public function delete(\$id)
    {
        \$row = \$this->modelClass->find(\$id);
        if (!\$row) {
            \$this->error('记录不存在');
        }
        
        \$result = \$row->delete();
        if (\$result) {
            \$this->success('删除成功');
        } else {
            \$this->error('删除失败');
        }
    }
}";
        
        return $content;
    }

    /**
     * 生成JS表格列配置
     * @param array $fields 字段信息
     * @return string
     */
    private function generateJsCols(array $fields): string
    {
        if (empty($fields)) {
            return "                    {field: 'title', title: '标题', width: 200},";
        }
        
        $cols = [];
        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldComment = $field['comment'] ?? $fieldName;
            $fieldType = $field['type'] ?? 'varchar';
            
            // 跳过系统字段
            if (in_array($fieldName, ['id', 'create_time', 'update_time', 'delete_time'])) {
                continue;
            }
            
            // 根据字段类型设置不同的显示方式
            $width = 200;
            $templet = '';
            
            if (strpos($fieldType, 'text') !== false) {
                $width = 300;
            } elseif (strpos($fieldType, 'int') !== false) {
                $width = 100;
            } elseif (strpos($fieldType, 'datetime') !== false || strpos($fieldType, 'timestamp') !== false) {
                $width = 180;
                $templet = ", templet: function(d) { return d.{$fieldName} ? layui.util.toDateString(d.{$fieldName} * 1000) : ''; }";
            } elseif (strpos($fieldType, 'tinyint') !== false) {
                $width = 100;
                $templet = ", templet: function(d) { return d.{$fieldName} == 1 ? '<span class=\"layui-badge layui-bg-green\">启用</span>' : '<span class=\"layui-badge layui-bg-gray\">禁用</span>'; }";
            }
            
            $cols[] = "                    {field: '{$fieldName}', title: '{$fieldComment}', width: {$width}{$templet},";
        }
        
        return implode("\n", $cols);
    }

    /**
     * 生成JS请求配置
     * @param string $controller 控制器名称
     * @return string
     */
    private function generateJsRequests(string $controller): string
    {
        return "                    index_url: '{$controller}/index',
                    add_url: '{$controller}/add',
                    edit_url: '{$controller}/edit',
                    del_url: '{$controller}/del',
                    multi_url: '{$controller}/multi',
                    table_url: '{$controller}/table',";
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
            
            if (empty($fieldName) || in_array($fieldName, ['id', 'create_time', 'update_time', 'delete_time'])) {
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
     * 生成FunAdmin视图文件
     * @param string $module 模块名称 (backend/api/frontend等)
     * @param string $controller 控制器名称
     * @param array $fields 字段信息 (可选)
     * @param string $description 描述 (可选)
     * @return array
     */
    public function handleCreateView(string $module, string $controller, array $fields = [], string $description = ''): array
    {
        try {
            // 生成视图文件名
            $viewFileName = strtolower($controller);
            $viewPath = "app/{$module}/view/{$viewFileName}";
            
            // 检查目录是否已存在
            if (is_dir($viewPath)) {
                return [
                    'success' => false,
                    'error' => "视图目录 {$viewPath} 已存在"
                ];
            }

            // 生成视图内容
            $viewFiles = $this->generateViewFiles($module, $controller, $fields, $description);
            
            // 确保目录存在
            if (!is_dir($viewPath)) {
                mkdir($viewPath, 0755, true);
            }

            $generatedFiles = [];
            foreach ($viewFiles as $viewFile) {
                $filePath = $viewPath . '/' . $viewFile['name'];
                if (file_put_contents($filePath, $viewFile['content'])) {
                    $generatedFiles[] = $filePath;
                    Log::info("FunAdmin 视图文件生成成功: {$filePath}");
                }
            }

            if (!empty($generatedFiles)) {
                return [
                    'success' => true,
                    'message' => '视图文件生成成功',
                    'file_paths' => $generatedFiles,
                    'files_count' => count($generatedFiles)
                ];
            } else {
                throw new Exception('视图文件写入失败');
            }

        } catch (Exception $e) {
            Log::error('FunAdmin 视图文件生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成视图文件
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return array
     */
    private function generateViewFiles(string $module, string $controller, array $fields = [], string $description = ''): array
    {
        $controllerLower = strtolower($controller);
        $description = $description ?: $controller;
        
        $viewFiles = [];
        
        // 生成 index.html
        $indexContent = $this->generateIndexView($module, $controllerLower, $fields, $description);
        $viewFiles[] = [
            'name' => 'index.html',
            'content' => $indexContent
        ];
        
        // 生成 add.html
        $addContent = $this->generateAddView($module, $controllerLower, $fields, $description);
        $viewFiles[] = [
            'name' => 'add.html',
            'content' => $addContent
        ];
        
        // 生成 edit.html
       /*  $viewFiles[] = [
            'name' => 'edit.html',
            'content' => $addContent
        ]; */
        
        return $viewFiles;
    }

    /**
     * 生成index视图
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function generateIndexView(string $module, string $controller, array $fields = [], string $description = ''): string
    {
        $content = <<<EOF
        <table class="layui-table" id="list" lay-filter="list" data-primaryKey="id"
        data-node-add="{:auth(__u('add'))}"
        data-node-edit="{:auth(__u('edit'))}"
        data-node-delete="{:auth(__u('delete'))}"
        data-node-destroy="{:auth(__u('destroy'))}"
        data-node-modify="{:auth(__u('modify'))}"
        data-node-recycle="{:auth(__u('recycle'))}"
        data-node-restore="{:auth(__u('restore'))}"
        data-node-import="{:auth(__u('import'))}"
        data-node-export="{:auth(__u('export'))}"
        data-node-copy="{:auth(__u('copy'))}"
</table>
EOF;
        
        return $content;
    }

    /**
     * 生成add视图
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function generateAddView(string $module, string $controller, array $fields = [], string $description = ''): string
    {
        $content = "<form class=\"layui-form\" lay-filter=\"form\">\n";
        
        foreach ($fields as $field) {
            if (in_array($field['name'], ['id', 'create_time', 'update_time', 'delete_time'])) {
                continue;
            }
            $fieldName = $field['name'];
            $fieldComment = $field['comment'] ?? $fieldName;
            $fieldType = $field['type'] ?? 'varchar';
            
            // 根据字段类型生成不同的表单控件
            if (strpos($fieldType, 'text') !== false || strpos($fieldType, 'longtext') !== false) {
                // 文本域
                $content .= "    {:Form::textarea('{$fieldName}', ['label'=>'{$fieldComment}', 'verify'=>'required', 'tips'=>'请输入{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            } elseif (strpos($fieldType, 'tinyint') !== false && in_array($fieldName, ['status', 'is_show', 'is_enable'])) {
                // 开关控件 - 状态类字段
                $content .= "    {:Form::switchs('{$fieldName}', ['1'=>'启用', '0'=>'禁用'], ['label'=>'{$fieldComment}', 'tips'=>'请选择{$fieldComment}'], \$row.{$fieldName}??'1')}\n";
            } elseif ($this->shouldUseSelect($fieldName, $fieldComment, $fieldType)) {
                // 下拉选择框 - 根据字段名称、注释、类型判断
                $selectOptions = $this->generateSelectOptions($fieldName, $fieldComment);
                $isMultiple = $this->isMultipleSelect($fieldComment);
            
                if ($isMultiple) {
                    // 多选下拉框
                    $content .= "    {:Form::selects('{$fieldName}', {$selectOptions}, ['label'=>'{$fieldComment}', 'verify'=>'required', 'tips'=>'请选择{$fieldComment}（可多选）'], \$row.{$fieldName}??'')}\n";
            } else {
                    // 单选下拉框
                    $content .= "    {:Form::select('{$fieldName}', {$selectOptions}, ['label'=>'{$fieldComment}', 'verify'=>'required', 'tips'=>'请选择{$fieldComment}'], \$row.{$fieldName}??'')}\n";
                }
            } elseif (strpos($fieldType, 'decimal') !== false || strpos($fieldType, 'float') !== false) {
                // 数字输入框
                $content .= "    {:Form::number('{$fieldName}', ['label'=>'{$fieldComment}', 'verify'=>'required|number', 'tips'=>'请输入{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            } elseif ($fieldName === 'password') {
                // 密码框
                $content .= "    {:Form::password('{$fieldName}', ['label'=>'{$fieldComment}', 'verify'=>'required', 'tips'=>'请输入{$fieldComment}'], '')}\n";
            } elseif ($fieldName === 'email') {
                // 邮箱输入框
                $content .= "    {:Form::email('{$fieldName}', ['label'=>'{$fieldComment}', 'verify'=>'required|email', 'tips'=>'请输入{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            } elseif ($fieldName === 'tel' || $fieldName === 'phone' || $fieldName === 'mobile') {
                // 电话号码输入框
                $content .= "    {:Form::tel('{$fieldName}', ['label'=>'{$fieldComment}', 'verify'=>'required|phone', 'tips'=>'请输入{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            } elseif (strpos($fieldName, 'time') !== false || strpos($fieldName, 'date') !== false) {
                // 日期时间选择器
                $content .= "    {:Form::date('{$fieldName}', ['label'=>'{$fieldComment}', 'verify'=>'required', 'type'=>'datetime', 'tips'=>'请选择{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            } elseif (strpos($fieldName, 'image') !== false || strpos($fieldName, 'avatar') !== false || strpos($fieldName, 'photo') !== false) {
                // 图片上传
                $content .= "    {:Form::upload('{$fieldName}', ['label'=>'{$fieldComment}', 'verify'=>'required', 'type'=>'image', 'tips'=>'请上传{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            } elseif (strpos($fieldName, 'file') !== false) {
                // 文件上传
                $content .= "    {:Form::upload('{$fieldName}', ['label'=>'{$fieldComment}', 'verify'=>'required', 'type'=>'file', 'tips'=>'请上传{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            } elseif ($fieldName === 'color') {
                // 颜色选择器
                $content .= "    {:Form::color('{$fieldName}', ['label'=>'{$fieldComment}', 'tips'=>'请选择{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            } elseif ($fieldName === 'icon') {
                // 图标选择器
                $content .= "    {:Form::icon('{$fieldName}', ['label'=>'{$fieldComment}', 'tips'=>'请选择{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            } elseif ($fieldName === 'url' || $fieldName === 'website') {
                // URL输入框
                $content .= "    {:Form::url('{$fieldName}', ['label'=>'{$fieldComment}', 'verify'=>'required|url', 'tips'=>'请输入{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            } else {
                // 默认文本输入框
                $content .= "    {:Form::input('{$fieldName}', 'text', ['label'=>'{$fieldComment}', 'verify'=>'required', 'tips'=>'请输入{$fieldComment}'], \$row.{$fieldName}??'')}\n";
            }
        }

        // 添加提交按钮
        $content .= "    {:Form::submit()}\n";
        $content .= "</form>\n";
        
        return $content;
    }

    /**
     * 判断是否应该使用下拉选择框
     * @param string $fieldName 字段名称
     * @param string $fieldComment 字段注释
     * @param string $fieldType 字段类型
     * @return bool
     */
    private function shouldUseSelect(string $fieldName, string $fieldComment, string $fieldType): bool
    {
        // 1. 根据字段注释判断
        $commentKeywords = [
            '选择', '类型', '分类', '等级', '级别', '状态', '方式', '模式', '种类',
            '下拉', '列表', '枚举', '选项', '菜单', '角色', '权限', '部门',
            '省份', '城市', '地区', '国家', '行业', '职业', '学历', '婚姻',
            '1:', '2:', '3:', '|', '，', ',', '/', '\\', // 包含选项分隔符的注释
        ];
        
        foreach ($commentKeywords as $keyword) {
            if (strpos($fieldComment, $keyword) !== false) {
                return true;
            }
        }
        
        // 2. 根据字段名称判断
        $nameKeywords = [
            '_id', 'type', 'category', 'level', 'grade', 'status', 'state',
            'kind', 'class', 'group', 'dept', 'role', 'auth', 'mode',
            'method', 'way', 'style', 'format', 'gender', 'sex',
            'province', 'city', 'area', 'region', 'country', 'nation'
        ];
        
        foreach ($nameKeywords as $keyword) {
            if (strpos($fieldName, $keyword) !== false) {
                return true;
            }
        }
        
        // 3. 根据字段类型判断
        if (strpos($fieldType, 'enum') !== false) {
            return true;
        }
        
        // 4. 小范围的整数字段可能是选择字段
        if (strpos($fieldType, 'tinyint') !== false || strpos($fieldType, 'smallint') !== false) {
            // 排除明确的布尔字段（is_开头的字段）
            if (strpos($fieldName, 'is_') !== 0 && !in_array($fieldName, ['status', 'sort', 'weigh', 'createtime', 'updatetime'])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 判断是否为多选字段
     * @param string $fieldComment 字段注释
     * @return bool
     */
    private function isMultipleSelect(string $fieldComment): bool
    {
        $multipleKeywords = [
            '多选', '复选', '多个', '批量', '多种', '可选多个', '可多选',
            '标签', '爱好', '技能', '特长', '兴趣', '权限', '角色组'
        ];
        
        foreach ($multipleKeywords as $keyword) {
            if (strpos($fieldComment, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 生成下拉选择框的选项
     * @param string $fieldName 字段名称
     * @param string $fieldComment 字段注释
     * @return string 选项数组的字符串表示
     */
    private function generateSelectOptions(string $fieldName, string $fieldComment): string
    {
        // 1. 首先尝试从字段注释中解析选项
        $parsedOptions = $this->parseOptionsFromComment($fieldComment);
        if (!empty($parsedOptions)) {
            return $parsedOptions;
        }
        
        // 2. 根据字段名称智能生成选项
        if (strpos($fieldName, 'status') !== false) {
            // 状态字段
            return "['0'=>'禁用', '1'=>'启用']";
        } elseif (strpos($fieldName, 'is_') === 0) {
            // 布尔类型字段 (is_show, is_enable等)
            return "['0'=>'否', '1'=>'是']";
        } elseif (strpos($fieldName, 'gender') !== false || strpos($fieldName, 'sex') !== false) {
            // 性别字段
            return "['0'=>'保密', '1'=>'男', '2'=>'女']";
        } elseif (strpos($fieldName, 'type') !== false) {
            // 类型字段 - 根据具体用途生成
            if (strpos($fieldName, 'user') !== false) {
                return "['1'=>'普通用户', '2'=>'VIP用户', '3'=>'超级用户']";
            } elseif (strpos($fieldName, 'content') !== false || strpos($fieldName, 'article') !== false) {
                return "['1'=>'文章', '2'=>'图片', '3'=>'视频']";
            } elseif (strpos($fieldName, 'pay') !== false) {
                return "['1'=>'微信支付', '2'=>'支付宝', '3'=>'银行卡']";
            } else {
                return "['1'=>'类型一', '2'=>'类型二', '3'=>'类型三']";
            }
        } elseif (strpos($fieldName, 'level') !== false) {
            // 等级字段
            return "['1'=>'初级', '2'=>'中级', '3'=>'高级', '4'=>'专家级']";
        } elseif (strpos($fieldName, 'grade') !== false) {
            // 等级字段
            return "['A'=>'A级', 'B'=>'B级', 'C'=>'C级', 'D'=>'D级']";
        } elseif (strpos($fieldName, 'priority') !== false) {
            // 优先级字段
            return "['1'=>'低', '2'=>'中', '3'=>'高', '4'=>'紧急']";
        } elseif (strpos($fieldName, 'category') !== false) {
            // 分类字段 - 提供更通用的选项
            return "[''=>'请选择分类', '1'=>'默认分类', '2'=>'热门分类', '3'=>'推荐分类']";
        } elseif (strpos($fieldName, 'admin_id') !== false) {
            // 管理员字段 - FunAdmin系统表
            return "\\think\\facade\\Db::name('admin')->column('username', 'id') ?: [''=>'请选择管理员']";
        } elseif (strpos($fieldName, 'member_id') !== false) {
            // 会员字段 - FunAdmin系统表
            return "\\think\\facade\\Db::name('member')->column('username', 'id') ?: [''=>'请选择会员']";
        } elseif (strpos($fieldName, 'user_id') !== false) {
            // 用户字段 - 通用处理
            return "\\think\\facade\\Db::name('user')->column('username', 'id') ?: [''=>'请选择用户']";
        } elseif (strpos($fieldName, 'role_id') !== false) {
            // 角色字段 - FunAdmin权限表
            return "\\think\\facade\\Db::name('auth_role')->column('name', 'id') ?: [''=>'请选择角色']";
        } elseif (strpos($fieldName, 'dept_id') !== false || strpos($fieldName, 'department_id') !== false) {
            // 部门字段
            return "\\think\\facade\\Db::name('dept')->column('name', 'id') ?: [''=>'请选择部门']";
        } elseif (strpos($fieldName, 'parent_id') !== false || strpos($fieldName, 'pid') !== false) {
            // 父级字段 - 提供静态选项避免表不存在的问题
            return "['0'=>'顶级分类', ''=>'请选择上级分类']";
        } elseif (strpos($fieldName, 'province') !== false || strpos($fieldName, 'city') !== false || strpos($fieldName, 'area') !== false) {
            // 地区字段 - 提供静态选项
            if (strpos($fieldName, 'province') !== false) {
                return "['11'=>'北京市', '12'=>'天津市', '13'=>'河北省', '21'=>'辽宁省', '31'=>'上海市', '32'=>'江苏省', '44'=>'广东省']";
            } elseif (strpos($fieldName, 'city') !== false) {
                return "['1101'=>'东城区', '1102'=>'西城区', '1103'=>'朝阳区', '1104'=>'丰台区', '1105'=>'石景山区']";
            } else {
                return "['110101'=>'东华门街道', '110102'=>'景山街道', '110103'=>'交道口街道']";
            }
        } elseif (strpos($fieldName, 'sort') !== false || strpos($fieldName, 'order') !== false) {
            // 排序字段
            return "['1'=>'1', '10'=>'10', '50'=>'50', '100'=>'100', '999'=>'999']";
        } elseif (strpos($fieldName, 'state') !== false) {
            // 状态字段
            return "['0'=>'待处理', '1'=>'处理中', '2'=>'已完成', '3'=>'已取消']";
        } else {
            // 默认选项
            return "[''=>'请选择{$fieldComment}', '1'=>'选项一', '2'=>'选项二', '3'=>'选项三']";
        }
    }

    /**
     * 从字段注释中解析选项
     * @param string $comment 字段注释
     * @return string 解析出的选项数组字符串，如果解析失败返回空字符串
     */
    private function parseOptionsFromComment(string $comment): string
    {
        if (empty($comment)) {
            return '';
        }
        
        // 解析模式1: "sex=1:男,2:女,3:未知" 或 "sex=1:男|2:女|3:未知" 或 "status=[1:可用,0:不可用]" 或 "status=(1:可用,0:不可用)"
        // 先提取等号后面的部分，如果没有等号则使用整个注释
        $commentPart = $comment;
        if (preg_match('/^[^=]*=(.+)$/', $comment, $eqMatch)) {
            $commentPart = $eqMatch[1];
        }
        
        // 匹配 数字:值 的格式
        if (preg_match_all('/(\d+)\s*[:：]\s*([^,|)\]（）\s]+)/', $commentPart, $matches, PREG_SET_ORDER)) {
            $options = [];
            foreach ($matches as $match) {
                $key = trim($match[1]);
                $value = trim($match[2]);
                // 清理值中的特殊字符
                $value = preg_replace('/[()（）\[\]【】]/', '', $value);
                $options[] = "'{$key}'=>'{$value}'";
            }
            if (!empty($options)) {
                return '[' . implode(', ', $options) . ']';
            }
        }
        
        // 解析模式2: "性别=男,女,未知" 或 "类型=[选项1,选项2]" 或 "选项:(男|女|未知)"
        if (preg_match('/[,|，]/', $comment)) {
            // 先提取选项部分（支持等号、冒号、括号等分隔符）
            $optionsPart = $comment;
            
            // 处理等号分隔的格式: "性别=男,女"
            if (preg_match('/^[^=]*=\s*[(\[（【]?([^)\]）】]+)[)\]）】]?$/', $comment, $eqMatch)) {
                $optionsPart = $eqMatch[1];
            }
            // 处理冒号分隔的格式: "选项:[男,女]" 或 "选项:(男,女)"
            elseif (preg_match('/[:：]\s*[(\[（【]?([^)\]）】]+)[)\]）】]?$/', $comment, $colonMatch)) {
                $optionsPart = $colonMatch[1];
            }
            
            // 分割选项
            $items = preg_split('/[,|，、]/', $optionsPart);
            $options = [];
            foreach ($items as $index => $item) {
                $item = trim($item);
                // 清理特殊字符
                $item = preg_replace('/[()（）\[\]【】]/', '', $item);
                
                if (!empty($item) && !preg_match('/^\d+$/', $item)) { // 排除纯数字和空值
                    $key = $index + 1;
                    $options[] = "'{$key}'=>'{$item}'";
                }
            }
            if (count($options) > 1) {
                return '[' . implode(', ', $options) . ']';
            }
        }
        
        // 解析模式3: "选择类型：1-普通 2-VIP 3-超级" 或 "状态=1-启用 0-禁用"
        if (preg_match_all('/(\d+)\s*[-—=]\s*([^\s,|]+)/', $comment, $matches, PREG_SET_ORDER)) {
            $options = [];
            foreach ($matches as $match) {
                $key = trim($match[1]);
                $value = trim($match[2]);
                // 清理值中的特殊字符
                $value = preg_replace('/[()（）\[\]【】]/', '', $value);
                $options[] = "'{$key}'=>'{$value}'";
            }
            if (!empty($options)) {
                return '[' . implode(', ', $options) . ']';
            }
        }
        
        // 解析模式4: 包含"或"的表达式 "男或女" "是或否" "启用或禁用"
        if (preg_match('/(.+?)或(.+)/', $comment, $matches)) {
            $option1 = trim($matches[1]);
            $option2 = trim($matches[2]);
            // 清理特殊字符
            $option1 = preg_replace('/[()（）\[\]【】=:]/', '', $option1);
            $option2 = preg_replace('/[()（）\[\]【】]/', '', $option2);
            
            // 如果是状态类的，使用0/1作为键值
            if (preg_match('/(启用|开启|打开|显示|是)/', $option1) || preg_match('/(禁用|关闭|隐藏|否)/', $option2)) {
                return "['1'=>'{$option1}', '0'=>'{$option2}']";
            } else {
                return "['1'=>'{$option1}', '2'=>'{$option2}']";
            }
        }
        
        // 解析模式5: 枚举值格式 "enum('male','female','unknown')" 或 "ENUM(1,2,3)"
        if (preg_match('/enum\s*\(\s*([^)]+)\s*\)/i', $comment, $matches)) {
            $enumValues = $matches[1];
            // 移除引号并分割
            $items = preg_split('/[,，]/', $enumValues);
            $options = [];
            foreach ($items as $index => $item) {
                $item = trim($item, " '\"");
                if (!empty($item)) {
                    $key = is_numeric($item) ? $item : ($index + 1);
                    $options[] = "'{$key}'=>'{$item}'";
                }
            }
            if (!empty($options)) {
                return '[' . implode(', ', $options) . ']';
            }
        }
        
        return '';
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

        // 处理插件操作
        if (strpos($lowerPrompt, '插件') !== false || strpos($lowerPrompt, 'addon') !== false) {
            $results['addon'] = [
                'success' => true,
                'message' => '检测到插件操作',
                'suggestion' => '请使用 addon 工具进行插件管理'
            ];
        }

        // 处理菜单操作
        if (strpos($lowerPrompt, '菜单') !== false || strpos($lowerPrompt, 'menu') !== false) {
            $results['menu'] = [
                'success' => true,
                'message' => '检测到菜单操作',
                'suggestion' => '请使用 menu 工具进行菜单管理'
            ];
        }

        // 处理CRUD操作
        if (strpos($lowerPrompt, 'crud') !== false || strpos($lowerPrompt, '增删改查') !== false) {
            $results['curd'] = [
                'success' => true,
                'message' => '检测到CRUD操作',
                'suggestion' => '请使用 curd 工具生成CRUD模块'
            ];
        }

        // 处理JS文件生成操作
        if (strpos($lowerPrompt, 'js') !== false || strpos($lowerPrompt, 'javascript') !== false || strpos($lowerPrompt, '前端') !== false) {
            $results['js'] = [
                'success' => true,
                'message' => '检测到JS文件生成操作',
                'suggestion' => '请使用 js 工具生成前端JS文件'
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

        // 处理视图文件生成操作
        if (strpos($lowerPrompt, '视图') !== false || strpos($lowerPrompt, 'view') !== false || strpos($lowerPrompt, '页面') !== false) {
            $results['view'] = [
                'success' => true,
                'message' => '检测到视图文件生成操作',
                'suggestion' => '请使用 view 工具生成视图文件'
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
                    '- 生成JS文件：指定模块和控制器名称' . "\n" .
                    '- 生成API接口：指定模块和控制器名称' . "\n" .
                    '- 生成视图文件：指定模块和控制器名称' . "\n" .
                    '- 创建插件：指定插件名称' . "\n" .
                    '- 创建菜单：指定菜单信息' . "\n" .
                    '- 生成CRUD模块：指定表名和字段信息' . "\n" .
                    '- 数据库查询：使用SELECT语句' . "\n" .
                    '- 系统配置：获取系统配置信息' . "\n" .
                    '- 文件操作：进行文件读写操作' . "\n" .
                    '- 用户管理：进行用户相关操作' . "\n" .
                    '- 系统信息：获取系统运行信息'
            ];
        }

        return $results;
    }
}