<?php
//文件格式
header("Content-type: text/html; charset=utf-8");
//错误级别
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
//初始化
ini_set('display_errors', '1');
//定义web根目录
define('WWW_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
$runtimePath = str_replace(DIRECTORY_SEPARATOR . 'public', DIRECTORY_SEPARATOR . 'runtime', WWW_ROOT);
//定义后台名称
$config = [
    'siteName' => "FunAdmin",
    'siteVersion' => "V1.1",
    'tablePrefix' => "fun_",
    'runtimePath' => $runtimePath,
];
//错误信息
$msg = '';
//安装文件
$lockFile = "." . DIRECTORY_SEPARATOR . "install.lock";
//数据库
$databaseConfigFile = "../config" . DIRECTORY_SEPARATOR . "database.php";
//后台入口配置
$entranceConfigFile = "../config" . DIRECTORY_SEPARATOR . "entrance.php";

session_start();

// 判断文件或目录是否有写的权限
function is_really_writable($file)
{
    if (DIRECTORY_SEPARATOR === '/' and @ ini_get("safe_mode") == false) {
        return is_writable($file);
    }
    if (!is_file($file) or ($fp = @fopen($file, "r+")) === false) {
        return false;
    }
    fclose($fp);
    return true;
}
if (is_file($lockFile)) {
    $msg = "当前已经安装{$config['siteName']}，如果需要重新安装，请手动移除FunAdmin/public/install.lock文件";
}
// 同意协议页面
if (@!isset($_GET['s']) || @$_GET['s'] === 'step1') {
    require_once './install/step1.html';
}
// 检测环境页面
if (@$_GET['s'] === 'step2') {
    if (version_compare(PHP_VERSION, '7.2.0', '<')) {
        $msg = "当前版本(" . PHP_VERSION . ")过低，请使用PHP7.2.0以上版本";
    } else {
        if (!extension_loaded("PDO")) {
            $msg = "当前未开启PDO，无法进行安装";
        } else {
            if (!is_really_writable($databaseConfigFile)) {
                $open_basedir = ini_get('open_basedir');
                if ($open_basedir) {
                    $dirArr = explode(PATH_SEPARATOR, $open_basedir);
                    if ($dirArr && in_array(__DIR__, $dirArr)) {
                        $msg = '当前服务器因配置了open_basedir，导致无法读取父目录<br>';
                    }
                }
                if (!$msg) {
                    $msg = '当前权限不足，无法写入配置文件config/database.php<br>';
                }
            }
        }
    }
    require_once './install/step2.html';
}
// 安装
if (@$_GET['s'] === 'step3') {
    if ($_GET['s'] === 'step3' && $_SERVER['REQUEST_METHOD'] === 'GET') require_once './install/step3.html';
    if ($_GET['s'] === 'step3' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($msg) {echo $msg;exit;}
        //执行安装
        $host = isset($_POST['hostname']) ? $_POST['hostname'] : '127.0.0.1';
        $port = isset($_POST['port']) ? $_POST['port'] : '3306';
        //判断是否在主机头后面加上了端口号
        $hostData = explode(":", $host);
        if (isset($hostData) && $hostData && is_array($hostData) && count($hostData) > 1) {
            $host = $hostData[0];
            $port = $hostData[1];
        }
        //mysql的账户相关
        $mysqlUserName = isset($_POST['username']) ? $_POST['username'] : 'root';
        $mysqlPassword = isset($_POST['password']) ? $_POST['password'] : 'root';
        $mysqlDatabase = isset($_POST['database']) ? $_POST['database'] : 'FunAdmin';
        $mysqlPreFix = isset($_POST['prefix']) ? $_POST['prefix'] : $config['tablePrefix'];
        $mysqlPreFix = rtrim($mysqlPreFix, "_") . "_";
        $adminUserName = isset($_POST['adminUserName']) ? $_POST['adminUserName'] : 'admin';
        $adminPassword = isset($_POST['adminPassword']) ? $_POST['adminPassword'] : '123456';
        $rePassword = isset($_POST['rePassword']) ? $_POST['rePassword'] : '123456';
        $email = isset($_POST['email']) ? $_POST['email'] : 'admin@admin.com';
        //php 版本
        if (version_compare(PHP_VERSION, '7.2.0', '<')) {
            die("当前版本(" . PHP_VERSION . ")过低，请使用PHP7.2.0以上版本");
        }
        if (!extension_loaded("PDO")) {
            die ("当前未开启PDO，无法进行安装");
        }
        //判断两次输入是否一致
        if ($adminPassword != $rePassword) {
            die('两次输入密码不一致！');
        }
        if(!preg_match('/^[0-9a-z_$]{6,16}$/i', $adminPassword)){
            die('密码必须6-16位,且必须包含字母和数字,不能有中文和空格');
        }
        if (!preg_match("/^\w+$/", $adminUserName)) {
            die('用户名只能输入字母、数字、下划线！');
        }
        if (strlen($adminUserName) < 3 || strlen($adminUserName) > 12) {
            die('用户名请输入3~12位字符！');
        }
        if (strlen($adminPassword) < 5 || strlen($adminPassword) > 16) {
            die('密码请输入5~16位字符！');
        }
        //检测能否读取安装文件
        $sql = @file_get_contents(WWW_ROOT . DIRECTORY_SEPARATOR . "install" . DIRECTORY_SEPARATOR . 'funadmin.sql');
        if (!$sql) {
            throw new Exception("无法读取/public/install/funadmin.sql文件，请检查是否有读权限");
        }
        try {
            // 连接数据库
            $link = @new mysqli("{$host}:{$port}", $mysqlUserName, $mysqlPassword);
            $error = $link->connect_error;
            if (!is_null($error)) {// 转义防止和alert中的引号冲突
                $error = addslashes($error);
                exit("数据库链接失败:$error");
            }
            $link->query('set global wait_timeout=2147480');
            $link->query("set global interactive_timeout=2147480");
            $link->query("set global max_allowed_packet=104857600");
            $link->query("SET NAMES 'utf8mb4'");
            if ($link->server_info < 5.5) {
                exit("MySQL数据库版本不能低于5.5,请将您的MySQL升级到5.5及以上");
            }
            // 创建数据库并选中
            if (!$link->select_db($mysqlDatabase)) {
                $create_sql = 'CREATE DATABASE IF NOT EXISTS ' . $mysqlDatabase . ' DEFAULT CHARACTER SET utf8mb4;';
                $link->query($create_sql) or exit('创建数据库失败');
                $link->select_db($mysqlDatabase);
            }
            $link->query("USE `{$mysqlDatabase}`");//使用数据库
            // 写入数据库
            $sqlArr = file(WWW_ROOT . DIRECTORY_SEPARATOR . "install" . DIRECTORY_SEPARATOR . 'funadmin.sql');
            $sql = '';
            foreach ($sqlArr as $value) {
                if (substr($value, 0, 2) == '--' || $value == '' || substr($value, 0, 2) == '/*')
                    continue;
                $sql .= $value;
                if (substr(trim($value), -1, 1) == ';' and $value != 'COMMIT;') {
                    $sql = str_ireplace("`fun_", "`{$mysqlPreFix}", $sql);
                    $sql = str_ireplace('INSERT INTO ', 'INSERT IGNORE INTO ', $sql);
                    try {
                        $link->query($sql);
                    } catch (\PDOException $e) {
                        exit($e->getMessage());
                    }
                    $sql = '';
                }
            }
            sleep(2);
            $password = password_hash($adminPassword, PASSWORD_BCRYPT);
            $result = $link->query("UPDATE {$mysqlPreFix}admin SET `email`='{$email}',`username` = '{$adminUserName}',`password` = '{$password}' WHERE `username` = 'admin'");
            $result2 = $link->query("UPDATE {$mysqlPreFix}member SET `email`='{$email}',`username` = '{$adminUserName}',`password` = '{$password}' WHERE `username` = 'admin'");
            $databaseConfig = @file_get_contents($databaseConfigFile);
            //替换数据库相关配置
            $config = <<<Fun
<?php
use think\\facade\Env;
return [
    // 默认使用的数据库连接配置
    'default'         => Env::get('database.driver', 'mysql'),
    // 自定义时间查询规则
    'time_query_rule' => [],
    // 自动写入时间戳字段
    // true为自动识别类型 false关闭
    // 字符串则明确指定时间字段类型 支持 int timestamp datetime date
    'auto_timestamp'  => true,
    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',
    // 数据库连接配置信息
    'connections'     => [
        'mysql' => [
            // 数据库类型
            'type'              => Env::get('database.type', 'mysql'),
            // 服务器地址
            'hostname'          => Env::get('database.hostname', '{$host}'),
            // 数据库名
            'database'          => Env::get('database.database', '{$mysqlDatabase}'),
            // 用户名
            'username'          => Env::get('database.username', '{$mysqlUserName}'),
            // 密码
            'password'          => Env::get('database.password', '{$mysqlPassword}'),
            // 端口
            'hostport'          => Env::get('database.hostport', '{$port}'),
            // 数据库连接参数
            'params'            => [],
            // 数据库编码默认采用utf8
            'charset'           => Env::get('database.charset', 'utf8mb4'),
            // 数据库表前缀
            'prefix'            => Env::get('database.prefix', '{$mysqlPreFix}'),
            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy'            => 0,
            // 数据库读写是否分离 主从式有效
            'rw_separate'       => false,
            // 读写分离后 主服务器数量
            'master_num'        => 1,
            // 指定从服务器序号
            'slave_no'          => '',
            // 是否严格检查字段是否存在
            'fields_strict'     => true,
            // 是否需要断线重连
            'break_reconnect'   => false,
            // 监听SQL
            'trigger_sql'       => true,
            // 开启字段缓存
            'fields_cache'      => false,
            // 字段缓存路径
            'schema_cache_path' => app()->getRuntimePath() . 'schema' . DIRECTORY_SEPARATOR,
        ],
        // 更多的数据库配置信息
    ],
];
Fun;
            $putConfig = @file_put_contents($databaseConfigFile, $config);
            if (!$putConfig) {
                exit('安装失败、请确定database.php是否有写入权限！:' . $error);
            }
            $adminStr = <<<Fun
<?php
// [ 应用入口文件 ]
namespace think;
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    header("Content-type: text/html; charset=utf-8");
    exit('PHP 7.2.0 及以上版本系统才可运行~ ');
}
if (!is_file(\$_SERVER['DOCUMENT_ROOT'].'/install.lock'))
{
    header("location:/install.php");exit;
}
require __DIR__ . '/../vendor/autoload.php';
// 执行HTTP应用并响应
\$http = (new  App())->http;
\$response = \$http->name('backend')->run();
\$response->send();
\$http->end(\$response);
?>
Fun;
            $adminName = '';
            $x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $adminName = substr(str_shuffle($x), 0, 10) . '.php';
            $backendFile = "." . DIRECTORY_SEPARATOR . $adminName;
            if (!file_exists($backendFile)) {
                @touch($backendFile);
            }
            @file_put_contents($backendFile, $adminStr);
            if (!file_exists($entranceConfigFile)) {
                @touch($entranceConfigFile);
            }
            $entrance = <<<Fun
<?php
return [ 
    'backendEntrance'=>'/{$adminName}/',
];
?>
Fun;
            $entranceConfig = @file_put_contents($entranceConfigFile, $entrance);
            $result = @file_put_contents($lockFile, 'ok');
            if (!$result) {
                exit("安装失败、请确定install.lock是否有写入权限！:$error");
            }
            $_SESSION['admin'] = $adminUserName;
            $_SESSION['password'] = $adminPassword;
            $_SESSION['backend'] = $adminName;
            echo $msg = 'success|' . $adminName;exit();
        } catch (\Exception $e) {
            $errMsg = $e->getMessage();
        }
        echo $errMsg;
        exit();
    }
}
//完成安装
if (@$_GET['s'] === 'step4') {
    require_once './install/step4.html';
}

?>

