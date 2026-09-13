<?php
// 临时本机只读诊断：不创建任务，不读取队列载荷。
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
require dirname(__DIR__) . '/vendor/autoload.php';
$app = new think\App(dirname(__DIR__) . '/');
$app->initialize();
$config = $app->config->get('queue.connections.ai-agent');
$result = ['php' => PHP_VERSION, 'pid' => getmypid(), 'redis_extension' => extension_loaded('redis'), 'cache' => $app->config->get('cache.default'), 'queue' => array_intersect_key($config, array_flip(['type', 'host', 'port', 'select', 'queue']))];
try {
    $connector = think\queue\connector\Redis::__make($config);
    $result['queue_size'] = $connector->size();
    $result['queue_connected'] = true;
} catch (Throwable $error) {
    $result['queue_connected'] = false;
    $result['error'] = in_array($error->getMessage(), ['redis扩展未安装', 'Connection refused'], true) ? $error->getMessage() : get_class($error);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
