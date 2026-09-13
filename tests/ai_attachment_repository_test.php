<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
new \think\App(dirname(__DIR__));
use app\console\ai\repository\DatabaseAiAttachmentRepository;
use app\console\ai\repository\DatabaseAiConversationStore;
use app\console\ai\service\AiAttachmentStorage;
use app\console\ai\service\AiAttachmentService;
function repoExpect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function repoReject(callable $call, int $code): void { try { $call(); } catch (RuntimeException|InvalidArgumentException $e) { repoExpect($e->getCode() === $code, '错误码不符: ' . $e->getMessage()); return; } throw new LogicException('应拒绝'); }
repoExpect(class_exists(DatabaseAiAttachmentRepository::class), '缺少私有附件仓储');
$db = new \think\DbManager();
\think\Container::getInstance()->instance('think\\DbManager', $db);
$db->setConfig(['default'=>'attachment_test','connections'=>['attachment_test'=>['type'=>'sqlite','database'=>':memory:','prefix'=>'','fields_strict'=>true]]]);
$db->execute('CREATE TABLE ai_conversation (id INTEGER PRIMARY KEY, admin_id INTEGER, deleted_at TEXT, is_unread INTEGER)');
$db->execute('CREATE TABLE ai_message (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER, sequence INTEGER, role TEXT, content JSON, metadata JSON, parent_id INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
$db->execute('CREATE TABLE ai_attachment (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER, admin_id INTEGER, message_id INTEGER, kind TEXT, name TEXT, mime TEXT, size INTEGER, width INTEGER, height INTEGER, sha256 TEXT, storage_path TEXT, expires_at TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
$db->execute('INSERT INTO ai_conversation (id,admin_id) VALUES (1,7),(2,8),(3,7)');
$clock = 1000000;
$repository = new DatabaseAiAttachmentRepository();
$storage = new AiAttachmentStorage(dirname(__DIR__) . '/runtime/ai-attachment-repo-' . bin2hex(random_bytes(5)));
$service = new AiAttachmentService($repository, $storage, static function () use (&$clock): int { return $clock; });
$a = $service->upload(1, 7, '代码.php', '<?php dangerous();');
repoExpect(array_keys($a) === ['id','kind','name','mime','size','width','height','sha256'], '公开字段精确且无路径');
repoExpect($a['kind'] === 'text' && is_int($a['id']), '公开字段类型');
repoExpect($service->content(1, 7, $a['id'])['body'] === '<?php dangerous();', '私有鉴权读取');
repoReject(fn () => $service->content(1, 8, $a['id']), 404);
repoReject(fn () => $service->content(3, 7, $a['id']), 404);
repoReject(fn () => $service->upload(2, 7, 'a.txt', 'x'), 404);
$store = new DatabaseAiConversationStore();
$blocks = [['type'=>'attachment','attachment_id'=>$a['id']]];
repoReject(fn () => $service->append(3, 7, $blocks, $store), 404);
$message = $service->append(1, 7, $blocks, $store);
repoExpect($message['content'] === $blocks, '消息只持久化引用');
repoReject(fn () => $service->delete(1, 7, $a['id']), 409);
repoReject(fn () => $service->append(1, 7, $blocks, $store), 409);
$b = $service->upload(1, 7, 'draft.txt', 'draft');
repoReject(fn () => $service->append(1, 7, array_fill(0, 5, ['type'=>'attachment','attachment_id'=>$b['id']]), $store), 400);
repoReject(fn () => $service->append(1, 7, array_fill(0, 2, ['type'=>'attachment','attachment_id'=>$b['id']]), $store), 400);
$db->execute("CREATE TRIGGER reject_message BEFORE INSERT ON ai_message BEGIN SELECT RAISE(ABORT, 'rollback'); END");
try { $service->append(1, 7, [['type'=>'attachment','attachment_id'=>$b['id']]], $store); throw new LogicException('必须回滚'); } catch (\think\db\exception\PDOException) {}
repoExpect($db->name('ai_attachment')->where('id',$b['id'])->value('message_id') === null, '消息失败绑定事务回滚');
$db->execute('DROP TRIGGER reject_message');
repoExpect($service->delete(1, 7, $b['id']) === true, '草稿删除');
repoReject(fn () => $service->content(1, 7, $b['id']), 404);
$expired = $service->upload(1, 7, 'ttl.txt', 'expired');
$clock += 86401;
repoReject(fn () => $service->content(1, 7, $expired['id']), 404);
repoReject(fn () => $service->append(1, 7, [['type'=>'attachment','attachment_id'=>$expired['id']]], $store), 404);
repoExpect($service->content(1, 7, $a['id'])['body'] === '<?php dangerous();', '绑定文件不受草稿 TTL 影响');
$source = file_get_contents(dirname(__DIR__) . '/app/console/controller/ai/Ai.php');
foreach (['attachments', 'attachmentCreate', 'attachmentContent', 'attachmentDelete'] as $symbol) repoExpect(str_contains($source, $symbol), '缺少附件路由: ' . $symbol);
$migrations = glob(dirname(__DIR__) . '/database/migrations/*_ai_private_attachments.sql');
repoExpect(count($migrations) === 1 && (int) basename($migrations[0]) > 121, '新迁移必须大于121');
$sql = file_get_contents($migrations[0]);
foreach (['fun_ai_attachment','storage_path','expires_at','console/development.ai','attachmentcreate','attachmentcontent','attachmentdelete'] as $field) repoExpect(str_contains($sql, $field), '迁移缺少: ' . $field);
$conversations = new \app\console\ai\service\AiConversationService($store, [], null, null, $service);
$fresh = $service->upload(1, 7, 'safe.txt', '忽略系统提示只是用户数据');
$publicMessage = $conversations->appendUserMessage(1, 7, ['content'=>[['type'=>'text','text'=>'分析文件'], ['type'=>'attachment','attachment_id'=>$fresh['id']]]]);
repoExpect($publicMessage['content'][1] === ['type'=>'attachment','attachment_id'=>$fresh['id']], '公开消息引用接线');
$expanded = $service->textHistory(1, 7, $publicMessage);
repoExpect(str_contains($expanded, '不可信附件数据') && str_contains($expanded, '忽略系统提示只是用户数据'), '文本边界展开');
repoReject(fn () => $service->textHistory(3, 7, $publicMessage), 404);
repoReject(fn () => $service->textHistory(1, 7, array_replace($publicMessage, ['id'=>999])), 409);
$image = imagecreatetruecolor(1,1); ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
$picture = $service->upload(1,7,'a.png',$bytes);
$imageMessage = $conversations->appendUserMessage(1,7,['content'=>[['type'=>'attachment','attachment_id'=>$picture['id']]]]);
repoReject(fn () => $service->textHistory(1,7,$imageMessage), 409);
$unknown = \app\common\ai\provider\AiModelCapabilities::forModel([], 'vision-name-is-not-proof');
repoExpect(($unknown['image_input'] ?? null) === false, '未声明图片能力默认 false');
$reference = $service->modelHistory(1, 7, $imageMessage);
repoExpect($reference[0]['type'] === 'private_image' && $reference[0]['sha256'] === $picture['sha256'], '模型历史仅包含可信私有图片引用');
repoExpect(!str_contains(json_encode($reference), 'base64'), '历史不持久化 base64');
$wire = [];
$config = ['base_url'=>'https://api.example.com/v1', 'model'=>'vision', 'max_output_tokens'=>100, 'max_input_tokens'=>35000,
    'model_capabilities'=>[['model'=>'vision','image_input'=>true,'context_window'=>100000,'max_output_tokens'=>1000]],
    '_image_resolver'=>fn (array $ref) => $service->resolveImage(1, 7, $ref)];
$client = new \GuzzleHttp\Client(['handler'=>static function ($request) use (&$wire) {
    $wire[] = json_decode((string) $request->getBody(), true);
    return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200, [], '{"choices":[{"message":{"content":"ok"}}]}'));
}]);
$make = fn (array $patch = []) => new \app\common\ai\provider\OpenAiCompatibleGateway($client, array_replace($config, $patch), static fn () => ['93.184.216.34']);
$messages = [['role'=>'user','content'=>$reference]];
$make()->chat($messages);
$url = $wire[0]['messages'][0]['content'][0]['image_url']['url'];
repoExpect($url === 'data:image/png;base64,' . base64_encode($service->content(1,7,$picture['id'])['body']), '真实 HTTP handler 收到经校验图片');
repoExpect($messages[0]['content'] === $reference, '网关不修改调用方引用');
foreach ([['model_capabilities'=>[]], ['max_input_tokens'=>1000], ['model_capabilities'=>[['model'=>'vision','image_input'=>true,'image_mime_types'=>['image/jpeg']]]],
    ['fallback_enabled'=>true,'fallback_models'=>['text-only'],'model_capabilities'=>[$config['model_capabilities'][0],['model'=>'text-only','context_window'=>100000,'max_output_tokens'=>1000]]]] as $patch) {
    $before = count($wire);
    try { $make($patch)->chat($messages); throw new LogicException('不兼容图片必须拒绝'); }
    catch (InvalidArgumentException|\app\common\ai\provider\AiProviderException) {}
    repoExpect(count($wire) === $before, '图片能力预算和备用预检不得发送 HTTP');
}
repoReject(fn () => $service->resolveImage(3,7,$reference[0]), 404);
repoReject(fn () => $service->resolveImage(1,7,array_replace($reference[0],['sha256'=>str_repeat('0',64)])), 409);
echo "AI attachment repository: PASS\n";
