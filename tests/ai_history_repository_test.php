<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// 在模型边界记录真实 repository 构造的查询；不启动应用、不读取环境、不连接数据库。
final class HistoryQuery
{
    public static array $calls = [];
    public static function where(...$args): self { $query = new self(); return $query->__call('where', $args); }
    public function __call(string $name, array $args): mixed
    {
        self::$calls[] = [$name, $args];
        foreach ($args as $arg) if ($arg instanceof Closure) $arg($this);
        return $name === 'toArray' ? [] : $this;
    }
}
class_alias(HistoryQuery::class, 'app\\console\\ai\\model\\AiConversation');
class_alias(HistoryQuery::class, 'app\\console\\ai\\model\\AiMessage');
$repository = new app\console\ai\repository\DatabaseAiConversationStore();
$check = static function (bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); };
$repository->conversationPageRows(7, ['is_archived'=>0,'is_unread'=>1,'group_id'=>0,'search'=>'100%_'], 90, 31);
$calls = HistoryQuery::$calls;
$check(in_array(['where',['admin_id',7]], $calls, true), '列表按管理员隔离');
$check(in_array(['where',['id','<',90]], $calls, true), '稳定 ID 游标');
$check(in_array(['whereNull',['group_id']], $calls, true), '未分组查询');
$check(in_array(['where',['is_unread',1]], $calls, true), '未读服务端筛选');
$check(in_array(['whereLike',['title','%100\\%\\_%']], $calls, true), '搜索通配符转义');
$check(in_array(['limit',[31]], $calls, true), '数据库 limit+1');
foreach ([false,true] as $forward) {
    HistoryQuery::$calls = [];
    $repository->messagePageRows(9, [100,200], $forward, 51);
    $calls = HistoryQuery::$calls; $op = $forward ? '>' : '<'; $order = $forward ? 'asc' : 'desc';
    $check(in_array(['where',['conversation_id',9]], $calls, true), '消息会话边界');
    $check(in_array(['where',['sequence',$op,100]], $calls, true), 'sequence 游标');
    $check(in_array(['where',['id',$op,200]], $calls, true), '同 sequence 按 id 打破平局');
    $check(in_array(['order',[['sequence'=>$order,'id'=>$order]]], $calls, true), '双列稳定排序');
    $check(in_array(['limit',[51]], $calls, true), '消息查询有界');
}
echo "AI history repository query tests passed\n";
