<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2020/9/21
 */

namespace fun\curd;

use think\facade\Cache;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use app\backend\model\Admin;
use app\common\service\MigrationService;
use think\facade\Db;

class Install extends Command
{
    //开发模式
    protected $app_debug = false;

    protected $config;
    //错误信息
    protected $msg = '';
    //安装文件
    protected $lockFile;
    //数据库
    protected $databaseConfigFile;
    protected $envFile;
    //模版
    protected $envTpl;
    /**
     * 安装期间按文件名顺序执行 database/migrations。
     * 仅面向空数据库执行只向前 migration，不加载历史升级 SQL。
     */
    protected $sqlFileDir = '';
    //mysql版本
    protected $mysqlVersion = '5.7';
    //database模板
    protected $databaseTpl = '';
    protected $adminTpl = '';




    protected function configure()
    {
        $db["database"] = config('database');
        $default = $db["database"]['default'];
        $config = $db["database"]['connections'][$default];
        $this->setName('install')
            ->addOption('hostname', 'm', Option::VALUE_OPTIONAL, 'hostname', $config['hostname'])
            ->addOption('hostport', 'r', Option::VALUE_OPTIONAL, 'hostport', $config['hostport'])
            ->addOption('database', 'd', Option::VALUE_OPTIONAL, 'database', $config['database'])
            ->addOption('charset', 'c', Option::VALUE_OPTIONAL, 'database', $config['charset'])
            ->addOption('prefix', 'x', Option::VALUE_OPTIONAL, 'prefix', $config['prefix'])
            ->addOption('username', 'u', Option::VALUE_OPTIONAL, 'mysql username', $config['username'])
            ->addOption('password', 'p', Option::VALUE_OPTIONAL, 'mysql password', $config['password'])
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, 'force override', false)
            ->addOption('app_debug', 'debug', Option::VALUE_OPTIONAL, 'force override', 1)
            ->addOption('standalone', 'standalone', Option::VALUE_OPTIONAL, 'force override', 1)
            ->setDescription('FunAdmin install command');
    }

    /**
     *
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    protected function execute(Input $input, Output $output)
    {

        $force = $input->getOption('force');
        $app_debug = $input->getOption('app_debug');
        $standalone = $input->getOption('standalone');

        $this->databaseConfigFile = config_path() . "database.php";
        $this->envFile = root_path() . ".env";
        $this->lockFile = public_path() . "install.lock";
        $this->databaseTpl = app()->getBasePath()  . "install/view/tpl/database.tpl";
        $this->adminTpl = app()->getBasePath()  . "install/view/tpl/admin.tpl";
        $this->envTpl = app()->getBasePath()  . "install/view/tpl/env.example";
        $this->sqlFileDir = root_path() . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $this->config = [
            'siteName' => "FunAdmin",
            'siteVersion' => config('app.version'),
            'tablePrefix' => "fun_",
            'runtimePath' => runtime_path(),
            'lockFile' => $this->lockFile,
        ];
        if (is_file($this->lockFile) && !$force) {
            $this->output->highlight("已经安装了,如需重新安装请输入 -f 1或 --force 1");
            exit();
        }
        $this->detectionEnv();
        $this->install($input);
    }
    /**
     * 环境检测
     *
     * @time 2019年11月29日
     * @return void
     */
    protected function detectionEnv(): void
    {
        $this->output->info('environment begin to check...');
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $this->output->error('php version should >= 8.0');
            exit();
        }
        $this->output->info('php version ' . PHP_VERSION);
        if (!function_exists('session_start')) {
            $this->output->error('session extension not install');
            exit();
        }
        $this->output->info('session extension is installed');
        if (!function_exists('curl_exec')) {
            $this->output->error('curl extension not install');
            exit();
        }
        $this->output->info('curl extension is installed');
//
//        if (!extension_loaded('fileinfo')) {
//            $this->output->error('fileinfo extension not install');
//            exit();
//        }
//        $this->output->info('fileinfo extension is installed');

        if (!extension_loaded('openssl')) {
            $this->output->error('openssl extension not install');
            exit();
        }
        $this->output->info('openssl extension is installed');

        if (!extension_loaded('pdo')) {
            $this->output->error('pdo extension not install');
            exit();
        }
        $this->output->info('pdo extension is installed');

        if (!is_writable(root_path().'runtime')) {
            $this->output->error('runtime path is  not writeable');
            exit();
        }

        $this->output->info('runtime  is witeable');

        $sqlFiles = glob($this->sqlFileDir . '/*.sql') ?: [];
        if (!$sqlFiles) {
            $this->output->error('migration 文件不存在');
            exit();
        }
        foreach ($sqlFiles as $value) {
            if (!is_readable($value)) {
                $this->output->error("无法读取{$value}文件，请检查读权限");
                exit();
            }
        }
        $this->output->info('sql file is witeable');

        $this->output->info('🎉 environment checking finished');


    }
    /**
     * 开始安装
     * @return void
     */
    protected function install($input): void{
        $env = root_path() . '.env';
        $standalone = $input->getOption('standalone');
        $app_debug = $input->getOption('app_debug');
        $db["host"] = $input->getOption('hostname');
        $db["port"] = $input->getOption('hostport');
        $db["database"] = $input->getOption('database');
        $db["charset"] = $input->getOption('charset');
        $db["username"] =$input->getOption('username');
        $db["password"] = $input->getOption('password');
        $db["prefix"] = $input->getOption('prefix');
        if(file_exists($env)){
            $env = \parse_ini_file($env, true);
            $db["host"] =  $env['DB_HOST'] ;
            $db["port"] = $env['DB_PORT']  ;
            $db["database"] = $env['DB_NAME'] ;
            $db["charset"] = $env['DB_CHARSET']  ;
            $db["prefix"] = $env['DB_PREFIX']  ;
            $db["username"] = $env['DB_USER']  ;
            $db["password"] = $env['DB_PASS']  ;
        }
        $prefix = env('DB_PREFIX');
        $app_debug = strtolower($this->output->ask($this->input, '👉 Set debug model default yes',$app_debug))?:$app_debug;
        $standalone = strtolower($this->output->ask($this->input, '👉 Set standalone backend default true',$standalone))?:$app_debug;
        $db["host"] = strtolower($this->output->ask($this->input, '👉 Set mysql hostname default(127.0.0.1)',$db["host"]))?:$db["host"];
        $db["port"] = strtolower($this->output->ask($this->input, '👉 Set mysql hostport default (3306)',$db["port"]))?:$db["port"] ;
        $db['database'] = strtolower($this->output->ask($this->input, '👉 Set mysql database default (funadmin)',$db["database"]))?:$db["prefix"];
        $db['prefix'] = strtolower($this->output->ask($this->input, "👉 Set mysql table prefix default( $prefix )",$db["prefix"]))?:$db["prefix"];
        $db["charset"] = strtolower($this->output->ask($this->input, '👉 Set mysql table charset default (utf8mb4)',$db["charset"]))?:$db["charset"];
        $db['username'] = strtolower($this->output->ask($this->input, '👉 Set mysql username default (root)',$db["username"]))?:$db["username"];
        $db['password'] = strtolower($this->output->ask($this->input, '👉 Set mysql password required',$db["password"]))?: $db["password"];
        $admin["username"] = strtolower($this->output->ask($this->input, '👉 Set admin username required default (admin)','admin'))?:'admin';
        $admin["password"] = strtolower($this->output->ask($this->input, '👉 Set admin password required default (admin123456)','admin123456'))?:'admin123456';
        $admin['rePassword'] = strtolower($this->output->ask($this->input, '👉 Set admin repeat password default (admin123456)','admin123456'))?:'admin123456';
        $admin['email'] = strtolower($this->output->ask($this->input, '👉 Set admin email','admin@admin.com'))?:'admin@admin.com';

        if(!$admin["username"] || !$admin['rePassword'] ){
            $this->output->error('请输入管理员帐号和密码');
            while (!$admin["username"]) {
                $admin["username"] = $this->output->ask($this->input, '👉 请输入管理员账号: ');
                if ($admin["username"]) {
                    break;
                }
            }
            while (!$admin['rePassword']) {
                $rePassword = $this->output->ask($this->input, '👉 请输入管理员密码重复: ');
                if ($rePassword) {
                    break;
                }
            }
            exit();
        }
        if (!preg_match("/^\w+$/", $admin["username"]) || strlen($admin["username"]) < 3 || strlen($admin["username"]) > 24) {
            $this->output->error('管理员用户名只能输入字母、数字、下划线！用户名请输入3~24位字符！');
            while (!$admin["username"]) {
                $admin["username"] = $this->output->ask($this->input, '👉 请输入管理员账号');
                if ($admin["username"]) {
                    break;
                }
            }
        }
        $adminPassword = $admin['password'];
        if(!preg_match('/^[0-9a-z_$]{6,16}$/i', $admin['password']) || strlen($admin['password']) < 5 || strlen($admin['password']) > 16){
            $this->output->error('管理员密码必须6-16位,且必须包含字母和数字,不能有中文和空格');
            while (!$adminPassword) {
                $adminPassword = $this->output->ask($this->input, '👉 请输入管理员密码');
                if ($adminPassword) {
                    break;
                }
            }
        }
        //判断两次输入是否一致
        if ($admin['password'] != $admin['rePassword']) {
            $this->output->error('管理员登录密码两次输入不一致！');
            while ($admin['password'] != $admin['rePassword']) {
                $adminPassword = $this->output->ask($this->input, '👉 请输入管理员密码');
                $rePassword = $this->output->ask($this->input, '👉 请输入管理员重复密码');
                if ($admin['password'] == $admin['rePassword']) {
                    break;
                }
            }
        }
        try {
            $this->output->highlight('连接数据库...');
            // 连接数据库
            $link = @new \mysqli("{$db['host']}:{$db['port']}", $db['username'], $db['password']);
            if (mysqli_connect_errno()) {
                $this->output->error("数据库链接失败:".mysqli_connect_errno());
                exit();
            }
            $link->query("SET NAMES 'utf8mb4'");
            if ($link->server_info < $this->mysqlVersion) {
                exit("MySQL数据库版本不能低于{$this->mysqlVersion},请将您的MySQL升级到{$this->mysqlVersion}及以上");
            }
            // 创建数据库并选中
            $create_sql = 'CREATE DATABASE IF NOT EXISTS ' . $db['database'] . ' DEFAULT CHARACTER SET '. $db["charset"].';';
            $link->query($create_sql) or exit('创建数据库失败');
            $link->select_db($db['database']);
//            $link->query('set global wait_timeout=2147480');
//            $link->query("set global interactive_timeout=2147480");
//            $link->query("set global max_allowed_packet=104857600");
            $link->query("USE `{$db['database']}`");//使用数据库
            $config = config('database');
            $config['default'] = 'mysql';
            $config['connections']['mysql'] = [
                'type'      => 'mysql',
                'hostname'  => $db['host'],
                'database'  => $db['database'],
                'username'  => $db['username'],
                'password'  => $db['password'],
                'hostport'  => $db['port'],
                'params'    => [],
                'charset'   => 'utf8mb4',
                'prefix'    => $db['prefix'],
                'fields_cache' => false
            ];
            config($config, 'database');

            $databaseTpl = @file_get_contents($this->databaseTpl);
            $this->output->highlight('修改数据配置中...');
            //替换数据库相关配置
            $putDatabase = str_replace(
                ['{{hostname}}', '{{database}}', '{{username}}', '{{password}}', '{{port}}', '{{prefix}}'],
                [$db['host'],$db['database'], $db['username'], $db['password'], $db['port'], $db['prefix']],
                file_get_contents($this->databaseTpl));
            $putConfig = @file_put_contents($this->databaseConfigFile, $putDatabase);
            if (!$putConfig) {
                $this->output->error('安装失败，请确认database.php有写权限！');
                exit();
            }
            if($this->app_debug){
                $putEnv = str_replace(
                    ['%debug%','%hostname%', '%database%', '%username%', '%password%', '%port%', '%prefix%'],
                    [$this->app_debug,$db['host'],$db['database'], $db['username'], $db['password'], $db['port'], $db['prefix']],
                    file_get_contents($this->envTpl));
                $putConfig = @file_put_contents($this->envFile, $putEnv);
                if (!$putConfig) {
                    $this->output->error('安装失败、请确定目录是否有写入权限');exit();
                }
            }
            try {
                $instance = Db::connect();
                $instance->execute("SELECT 1");     //如果是【数据】增删改查直接运行
                MigrationService::instance()->runDirectory($this->sqlFileDir, 'core');
                $password = password($admin['password']);
                Admin::where('id', 1)->update(['email' => $admin['email'], 'username' => $admin['username'], 'password' => $password]);
            } catch (\PDOException $e) {
                $this->output->error($e->getMessage());exit();
            }catch(\Exception $e){
                $this->output->error($e->getMessage());exit();
            }
            if (!@touch($this->lockFile)) {
                $this->output->error('安装完成但无法创建 install.lock，请检查 public 目录权限');
                exit();
            }
            $this->output->highlight('数据库安装完成...');

            $adminName = 'backend';
            if($standalone){
                //后台入口
                $putAdmin = @file_get_contents($this->adminTpl);
                $number = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $adminName = substr(str_shuffle($number), 0, 10) . '.php';
                $adminFile = public_path(). $adminName;
                if (!file_exists($adminFile)) {
                    @touch($adminFile);
                }
                @file_put_contents($adminFile, $putAdmin);
                $this->output->highlight('后台入口文件生成成功...');
            }
            $funadmin = @file_get_contents(config_path().'funadmin.php');
            $standalone = $standalone?1:0;
            // Match and replace standalone value (1 or 0) to boolean (true or false)
            $updatedContent = preg_replace_callback(
                "/('standalone'\s*=>\s*)(1|0|true|false)/",
                function ($matches) use ($standalone) {
                    return $matches[1] . $standalone;
                },
                $funadmin
            );
            @file_put_contents(config_path().'funadmin.php', $updatedContent);
            $this->output->highlight('配置修改成功...');

            $adminUser['username'] = $admin['username'];
            $adminUser['password'] = $admin['password'];
            $adminUser['backend'] = $adminName;
            $this->output->highlight('👉 恭喜您：系统已经安装完成... 通过域名+后台入口文件即可访问后台');
            $this->output->highlight('👉 管理员账号: '.$adminUser["username"].'，管理员密码:'.$adminUser['password'].',后台入口:'.request()->domain()."/".$adminName);
        } catch (\Exception $e) {
            $this->output->error($e->getMessage());
        }
        exit();
    }
}
