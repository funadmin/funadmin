<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use app\admin\ai\service\AiAttachmentStorage;
function storageExpect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function storageReject(callable $call, string $message): void { try { $call(); } catch (InvalidArgumentException|RuntimeException) { return; } throw new LogicException($message); }
if (($argv[1] ?? '') === '--cleanup-worker') {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $calls = 0;
    $removed = (new AiAttachmentStorage($input['root']))->cleanupExpired($input['now'], function ($name, $remove) use ($input, &$calls) {
        $calls++;
        return !isset($input['bound'][$name]) && $remove();
    }, $input['limit']);
    echo json_encode(['removed'=>$removed, 'calls'=>$calls, 'php'=>PHP_VERSION_ID], JSON_THROW_ON_ERROR);
    exit;
}
function storageWorker(array $input): array
{
    $process = proc_open([PHP_BINARY, '-d', 'ffi.enable=false', __FILE__, '--cleanup-worker'], [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);
    storageExpect(is_resource($process), '独立 PHP 进程启动失败');
    fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR)); fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    storageExpect(proc_close($process) === 0 && $error === '', '独立 PHP 清理失败: ' . $error);
    $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    storageExpect($result['php'] === PHP_VERSION_ID, '子进程必须使用相同 PHP 二进制');
    return $result;
}
storageExpect(class_exists(AiAttachmentStorage::class), '缺少私有附件存储');
$root = dirname(__DIR__) . '/runtime/ai-attachment-test-' . bin2hex(random_bytes(6));
$storage = new AiAttachmentStorage($root);
$code = "<?php echo '仅为数据';\n";
$text = $storage->put('示例.php', $code);
storageExpect($text['kind'] === 'text' && $text['mime'] === 'text/plain' && $text['size'] === strlen($code), '代码按文本保存');
storageExpect($text['width'] === null && $text['height'] === null && $text['sha256'] === hash('sha256', $code), '文本元数据');
storageExpect(preg_match('/^[a-f0-9]{64}$/D', $text['storage_path']) === 1, '随机无扩展路径');
storageExpect($storage->read($text) === $code, '读回源码不得执行');
storageExpect((fileperms($root) & 0777) === 0700 && (fileperms($root . '/' . $text['storage_path']) & 0777) === 0600, '私有权限');
$again = $storage->put('示例.php', $code);
storageExpect($again['storage_path'] !== $text['storage_path'], '相同内容仍随机路径');
foreach (['pdf','docx','zip','svg','exe','gif','bin'] as $ext) storageReject(fn () => $storage->put('x.' . $ext, 'text'), '拒绝禁用格式');
foreach (["\xff", "MZ\0binary", '%PDF-1.7', "PK\x03\x04binary"] as $body) storageReject(fn () => $storage->put('x.txt', $body), '拒绝伪装文件');
storageReject(fn () => $storage->put('x.txt', str_repeat('a', 131073)), '文本 128KiB');
storageReject(fn () => $storage->put('x.png', str_repeat('a', 5242881)), '图片 5MiB');
storageReject(fn () => $storage->put('../x.txt', 'x'), '路径文件名');
storageReject(fn () => $storage->put("x\r\n.txt", 'x'), '头注入');
foreach (['png','jpeg','webp'] as $format) {
    $image = imagecreatetruecolor(2, 3);
    ob_start(); ('image' . $format)($image); $bytes = ob_get_clean(); imagedestroy($image);
    $record = $storage->put('image.' . $format, $bytes . 'trailing-secret');
    storageExpect($record['kind'] === 'image' && $record['width'] === 2 && $record['height'] === 3, '图片解码尺寸');
    storageExpect(!str_contains($storage->read($record), 'trailing-secret'), '重编码剥离附加内容');
    storageReject(fn () => $storage->put('wrong.txt', $bytes), '图片不得伪装文本');
}
$large = imagecreatetruecolor(8193, 1); ob_start(); imagepng($large); $largeBytes = ob_get_clean(); imagedestroy($large);
storageReject(fn () => $storage->put('wide.png', $largeBytes), '尺寸限制');
$animated = 'RIFF' . pack('V', 22) . 'WEBPVP8X' . pack('V', 10) . chr(2) . str_repeat("\0", 9);
storageReject(fn () => $storage->put('animated.webp', $animated), '拒绝动画 WebP');
file_put_contents($root . '/' . $text['storage_path'], 'tampered');
storageReject(fn () => $storage->read($text), 'sha256 读校验');
storageReject(fn () => $storage->read(array_replace($again, ['storage_path'=>'../escape'])), '读取路径穿越');
$headers = $storage->headers($again);
storageExpect($headers['X-Content-Type-Options'] === 'nosniff' && $headers['Cache-Control'] === 'no-store', '安全响应头');
storageExpect(str_starts_with($headers['Content-Disposition'], 'attachment;') && str_contains($headers['Content-Disposition'], "filename*=UTF-8''"), '安全下载文件名');
$scanRoot = $root . '/scan';
$scanner = new AiAttachmentStorage($scanRoot);
$paths = [];
for ($i = 0; $i < 230; $i++) $paths[] = $scanner->put('old.txt', 'keep-or-expire')['storage_path'];
// 按实际目录顺序保留前 120 个，避免依赖文件系统排序。
$ordered = [];
foreach (new DirectoryIterator($scanRoot) as $entry) if (preg_match('/^[a-f0-9]{64}$/D', $entry->getFilename())) $ordered[] = $entry->getFilename();
$boundPaths = array_fill_keys(array_slice($ordered, 0, 120), true);
$processRemoved = 0;
for ($round = 0; $round < 12; $round++) {
    $result = storageWorker(['root'=>$scanRoot, 'now'=>time()+100000, 'bound'=>$boundPaths, 'limit'=>100]);
    storageExpect($result['calls'] <= 100, '独立进程每轮必须保留候选处理上限（不是全目录读取上限）');
    $processRemoved += $result['removed'];
}
storageExpect($processRemoved === 110, '独立 PHP 进程必须最终越过前 120 个绑定文件，清理全部 110 个过期文件');
foreach ($boundPaths as $path => $_) storageExpect(is_file($scanRoot . '/' . $path), '跨进程清理不得删除绑定文件');
// 补回非绑定文件，继续验证原有同进程跨实例行为。
for ($i = 0; $i < 110; $i++) $scanner->put('old.txt', 'keep-or-expire');
$removed = 0;
for ($round = 0; $round < 12; $round++) {
    $calls = 0;
    $removed += (new AiAttachmentStorage($scanRoot))->cleanupExpired(time()+100000, function ($name, $remove) use ($boundPaths, &$calls) {
        $calls++;
        return !isset($boundPaths[$name]) && $remove();
    }, 100);
    storageExpect($calls <= 100, '每次调用必须保留候选处理上限');
}
storageExpect($removed === 110, '跨实例扫描进度必须最终越过前 120 个绑定文件');
foreach ($boundPaths as $path => $_) storageExpect(is_file($scanRoot . '/' . $path), '不得删除绑定文件');
// 字典序前缀超过单批预算；每轮禁用 FFI，且不共享任何进程内状态。
$lexRoot = $root . '/lexical';
$lexical = new AiAttachmentStorage($lexRoot);
$lexical->synchronized(fn () => null);
$lexBound = [];
for ($i = 1; $i <= 135; $i++) {
    $name = str_pad(dechex($i), 64, '0', STR_PAD_LEFT);
    file_put_contents($lexRoot . '/' . $name, 'old'); chmod($lexRoot . '/' . $name, 0600);
    if ($i <= 120) $lexBound[$name] = true;
}
$lexRemoved = 0;
for ($round = 0; $round < 25; $round++) {
    $result = storageWorker(['root'=>$lexRoot, 'now'=>time()+100000, 'bound'=>$lexBound, 'limit'=>7]);
    storageExpect($result['calls'] <= 7, '小批次也必须限制绑定检查数量');
    $lexRemoved += $result['removed'];
    $state = json_decode(file_get_contents($lexRoot . '/.attachment-cursor'), true);
    storageExpect(is_string($state['after'] ?? null) && ($state['after'] === '' || preg_match('/^[a-f0-9]{64}$/D', $state['after']) === 1), '持久游标只保存文件名，不保存 OS offset');
    storageExpect(filesize($lexRoot . '/.attachment-cursor') < 1024, '游标状态大小固定有界');
}
storageExpect($lexRemoved === 15, '绑定字典序前缀不能使独立进程永久饥饿');
// 游标对应的文件已删除也能继续；新增较小名称在回绕后被发现。
$lateName = str_repeat('0', 64);
file_put_contents($lexRoot . '/' . $lateName, 'late'); chmod($lexRoot . '/' . $lateName, 0600);
for ($round = 0; $round < 22; $round++) storageWorker(['root'=>$lexRoot, 'now'=>time()+100000, 'bound'=>$lexBound, 'limit'=>7]);
storageExpect(!file_exists($lexRoot . '/' . $lateName), '回绕必须发现游标之前新增的文件');
foreach ($lexBound as $name => $_) storageExpect(is_file($lexRoot . '/' . $name), '字典序扫描保留所有绑定项');
// 旧 native 状态或损坏状态应安全重启，不能用于寻址。
foreach (['{"position":999999,"pending":["../escape"]}', '{broken'] as $invalidState) {
    file_put_contents($lexRoot . '/.attachment-cursor', $invalidState);
    $result = storageWorker(['root'=>$lexRoot, 'now'=>time()+100000, 'bound'=>$lexBound, 'limit'=>7]);
    storageExpect($result['calls'] === 7 && $result['removed'] === 0, '旧或损坏游标安全重启');
}
$unsafeRoot = $root . '/unsafe';
$unsafe = new AiAttachmentStorage($unsafeRoot);
$unsafe->put('safe.txt','safe');
$target = $root . '/cursor-target'; file_put_contents($target, 'unchanged'); chmod($target,0600);
symlink($target, $unsafeRoot . '/.attachment-cursor');
storageReject(fn () => $unsafe->cleanupExpired(time()+100000, fn ($name,$remove) => $remove()), '游标符号链接必须拒绝');
storageExpect(file_get_contents($target) === 'unchanged', '游标不得写出根路径');
$hardRoot = $root . '/hard-lock';
$hard = new AiAttachmentStorage($hardRoot); $hard->put('safe.txt','safe');
link($target, $hardRoot . '/.attachment-lock');
storageReject(fn () => $hard->synchronized(fn () => true), '锁硬链接必须拒绝');
$hardCursorRoot = $root . '/hard-cursor';
$hardCursor = new AiAttachmentStorage($hardCursorRoot); $hardCursor->put('safe.txt', 'safe');
link($target, $hardCursorRoot . '/.attachment-cursor');
storageReject(fn () => $hardCursor->cleanupExpired(time()+100000, fn ($name,$remove) => $remove()), '游标硬链接必须拒绝');
storageExpect(file_get_contents($target) === 'unchanged', '硬链接目标不得改写');
$linksRoot = $root . '/links';
$links = new AiAttachmentStorage($linksRoot);
$fresh = $links->put('fresh.txt', 'fresh');
symlink($target, $linksRoot . '/' . str_repeat('a',64));
link($target, $linksRoot . '/' . str_repeat('b',64));
$callbacks = 0;
$links->cleanupExpired(time(), function ($name,$remove) use (&$callbacks) { $callbacks++; return $remove(); });
storageExpect($callbacks === 0 && $links->read($fresh) === 'fresh' && file_get_contents($target) === 'unchanged', '跳过新文件、符号链接和硬链接，不交给删除回调');
foreach (['.attachment-lock', '.attachment-cursor'] as $control) storageExpect((fileperms($scanRoot . '/' . $control) & 0777) === 0600, '控制文件保持私有权限');
echo "AI attachment storage: PASS\n";
