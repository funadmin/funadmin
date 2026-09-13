<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use app\console\ai\service\AiAttachmentStorage;
function storageExpect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function storageReject(callable $call, string $message): void { try { $call(); } catch (InvalidArgumentException|RuntimeException) { return; } throw new LogicException($message); }
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
echo "AI attachment storage: PASS\n";
