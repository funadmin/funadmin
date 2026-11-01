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
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use Mcp\Server\Transport\TransportInterface;
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
            ->addTool([$this, 'handleThinkCommand'], 'think-command', description: '执行ThinkPHP内置命令')
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
            Log::info('MCP服务器启动成功');
            return $this->buildServer()->run($transport);
        } catch (\Throwable $exception) {
            Log::error('MCP服务器启动失败: ' . $exception->getMessage());
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