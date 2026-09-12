<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\crud\ConfirmationToken;
use app\console\ai\service\AiChangeSetService;
use app\console\ai\service\AiChangeSetTransactionService;
use app\console\ai\service\AiCrudProposalService;
use app\console\ai\service\AiChangeSetApplicationService;

function phase4Expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase4Reject(callable $operation, string $fragment): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        phase4Expect(str_contains($exception->getMessage(), $fragment), '异常信息不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝但操作成功：' . $fragment);
}

function phase4Remove(string $path): void
{
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (new FilesystemIterator($path) as $item) phase4Remove($item->getPathname());
        rmdir($path);
        return;
    }
    unlink($path);
}

$applicationRows = [12 => ['id'=>12,'created_by'=>7,'conversation_id'=>21,'task_id'=>31,'status'=>'proposed','recovery_version'=>0]];
$applicationAudits = [];
$application = new AiChangeSetApplicationService(
    static function (int $id) use (&$applicationRows): array { return $applicationRows[$id] ?? []; },
    static function (int $id, array $from, array $data, int $adminId, array $conditions = []) use (&$applicationRows): bool {
        if (!isset($applicationRows[$id]) || !in_array($applicationRows[$id]['status'], $from, true)) return false;
        foreach ($conditions as $field => $value) if (($applicationRows[$id][$field] ?? null) !== $value) return false;
        $applicationRows[$id] = array_replace($applicationRows[$id], $data);
        return true;
    },
    static function (string $event, array $payload) use (&$applicationAudits): void { $applicationAudits[] = [$event, $payload]; }
);
$application->recordPreview(12, 7, ['src/A.php'], str_repeat('a', 64));
phase4Expect(($applicationRows[12]['selection'] ?? null) === ['src/A.php'] && ($applicationRows[12]['plan_digest'] ?? '') === str_repeat('a', 64), 'ChangeSet preview 必须持久化 selection 与 plan_digest');
$approvalGuard = new AiChangeSetService(sys_get_temp_dir(), sys_get_temp_dir());
$trustedApproval = ['id'=>41,'status'=>'approved','operation'=>'apply_workspace','requested_by'=>7,'decided_by'=>7,'conversation_id'=>21,'task_id'=>31,'expires_at'=>'2099-01-01 00:00:00'];
foreach ([
    'pending' => array_replace($trustedApproval, ['status'=>'pending']),
    'denied' => array_replace($trustedApproval, ['status'=>'denied']),
    'expired' => array_replace($trustedApproval, ['status'=>'expired']),
    '已过有效期' => array_replace($trustedApproval, ['expires_at'=>'2000-01-01 00:00:00']),
    'decided_by 不匹配' => array_replace($trustedApproval, ['decided_by'=>8]),
] as $reason => $untrustedApproval) {
    phase4Reject(function () use ($approvalGuard, $application, $untrustedApproval): void {
        $approvalGuard->assertFinalApproval($untrustedApproval, 7, 21, 31);
        $application->run(12, 7, ['proposed'], static fn (): array => ['state'=>'completed'], ['final_approval_id'=>41]);
    }, '最终审批');
    phase4Expect(($applicationRows[12]['status'] ?? '') === 'proposed', "{$reason} 的审批不得让 ChangeSet 离开 proposed");
}
$approvalGuard->assertFinalApproval($trustedApproval, 7, 21, 31);
$applicationResult = $application->run(12, 7, ['proposed'], static fn (): array => ['state'=>'completed','transactionId'=>'t-1'], ['final_approval_id'=>41]);
phase4Expect(($applicationResult['state'] ?? '') === 'completed' && ($applicationRows[12]['status'] ?? '') === 'applied', 'ChangeSet application 必须 CAS applying 后收敛 applied');
phase4Expect(($applicationRows[12]['final_approval_id'] ?? 0) === 41, 'ChangeSet apply 必须持久化 final_approval_id');
phase4Expect(($applicationAudits[0][1]['task_id'] ?? null) !== null, 'ChangeSet application 审计必须携带 task_id');
$applicationRows[12]['status'] = 'applying';
$recoveryResult = $application->run(12, 7, ['applying'], static fn (): array => ['state'=>'recovery_required','transactionId'=>'t-2']);
phase4Expect(($recoveryResult['state'] ?? '') === 'recovery_required' && ($applicationRows[12]['status'] ?? '') === 'recovery_required', '不可证明事务必须 CAS 到 recovery_required');
$recovered = $application->recover(12, 7, 0, static fn (): array => ['state'=>'rolled_back','transaction_id'=>'t-2']);
phase4Expect(($recovered['state'] ?? '') === 'rolled_back' && ($applicationRows[12]['status'] ?? '') === 'failed', 'ChangeSet recover 必须 CAS recovering 后收敛回滚结果');
phase4Expect(($applicationRows[12]['recovery_version'] ?? 0) === 1 && ($applicationRows[12]['recovery_status'] ?? '') === 'recovered', 'ChangeSet recover 必须单调更新 recovery_version');
phase4Reject(fn () => $application->recover(12, 7, 0, static fn (): array => ['state'=>'rolled_back']), 'CAS');

$root = sys_get_temp_dir() . '/funadmin-ai-p4-' . bin2hex(random_bytes(5));
$private = $root . '/runtime/ai/private';
mkdir($private . '/exports/one', 0700, true);
mkdir($root . '/src', 0755, true);
file_put_contents($root . '/src/A.php', "local\ntwo\nthree\n");

$baselineFiles = ['src/A.php' => ['sha256' => hash('sha256', "one\ntwo\nthree\n"), 'size' => 14]];
$remoteFiles = ['src/A.php' => ['sha256' => hash('sha256', "one\ntwo\nremote\n"), 'size' => 15]];
$baselineManifest = ['algorithm'=>'sha256','files'=>$baselineFiles,'digest'=>hash('sha256', json_encode($baselineFiles, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
$remoteManifest = ['algorithm'=>'sha256','files'=>$remoteFiles,'digest'=>hash('sha256', json_encode($remoteFiles, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
$baseBundle = ['encoding'=>'base64','files'=>['src/A.php'=>base64_encode("one\ntwo\nthree\n")]];
$remoteBundle = ['encoding'=>'base64','files'=>['src/A.php'=>base64_encode("one\ntwo\nremote\n")]];
$paths = [];
foreach (['baseline-manifest.json'=>$baselineManifest,'manifest.json'=>$remoteManifest,'baseline-bundle.json'=>$baseBundle] as $name=>$payload) {
    $paths[$name] = $private . '/exports/one/' . $name;
    file_put_contents($paths[$name], json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
$paths['bundle.json'] = $private . '/exports/one/bundle.json';
file_put_contents($paths['bundle.json'], json_encode(['encoding'=>'base64-ndjson','version'=>1], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    . json_encode(['path'=>'src/A.php','content'=>base64_encode("one\ntwo\nremote\n")], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
$patchPath = $private . '/exports/one/changes.patch';
file_put_contents($patchPath, "diff --git a/src/A.php b/src/A.php\nindex 1111111..2222222 100644\nBinary files a/src/A.php and b/src/A.php differ\0proof");
$record = [
    'id'=>12,'conversation_id'=>21,'task_id'=>31,'created_by'=>7,'status'=>'proposed',
    'digest'=>str_repeat('d',64),'base_digest'=>$baselineManifest['digest'],'patch_path'=>$patchPath,
    'patch_sha256'=>hash_file('sha256',$patchPath),'base_file_hashes'=>$baselineFiles,
    'manifest'=>[
        'baseline_manifest_path'=>$paths['baseline-manifest.json'],'baseline_manifest_sha256'=>hash_file('sha256',$paths['baseline-manifest.json']),
        'remote_manifest_path'=>$paths['manifest.json'],'remote_manifest_sha256'=>hash_file('sha256',$paths['manifest.json']),
        'baseline_bundle_path'=>$paths['baseline-bundle.json'],'baseline_bundle_sha256'=>hash_file('sha256',$paths['baseline-bundle.json']),
        'bundle_path'=>$paths['bundle.json'],'bundle_sha256'=>hash_file('sha256',$paths['bundle.json']),
        'artifact'=>['admin_id'=>7, 'conversation_id'=>21, 'task_id'=>31],
    ],
];
$clock = 1000;
$service = new AiChangeSetService($root, $private, new ConfirmationToken($root, 'phase-four-token-secret', 30, function () use (&$clock): int { return $clock; }), static fn (): int => 106);
$boundRecord = $record;
$boundRecord['manifest']['artifact'] = ['admin_id'=>8, 'conversation_id'=>21, 'task_id'=>31];
phase4Reject(fn () => $service->preview($boundRecord, 7, ["src/A.php"], true, 21, 31), 'artifact 归属');
$unboundRecord = $record;
unset($unboundRecord['manifest']['artifact']);
phase4Reject(fn () => $service->preview($unboundRecord, 7, ["src/A.php"], true, 21, 31), 'artifact 绑定');
$foreignPatchRecord = $record;
file_put_contents($patchPath, "diff --git a/src/Other.php b/src/Other.php\n");
$foreignPatchRecord['patch_sha256'] = hash_file('sha256', $patchPath);
phase4Reject(fn () => $service->preview($foreignPatchRecord, 7, ["src/A.php"], true, 21, 31), 'patch 文件集合');
file_put_contents($patchPath, "diff --git a/src/A.php b/src/A.php\nindex 1111111..2222222 100644\nBinary files a/src/A.php and b/src/A.php differ\0proof");
$record['patch_sha256'] = hash_file('sha256', $patchPath);
$preview = $service->preview($record, 7, ["src/A.php"], true, 21, 31);
phase4Expect($preview['blocked'] === false && $preview['files'][0]['status'] === 'auto-merged', '非重叠文本变化必须自动三方合并');
phase4Expect(isset($preview['confirmToken']) && !str_contains(json_encode($preview), 'base64'), '公开计划可签 token 但不得暴露 bundle 内容');
phase4Expect($preview['files'][0]['baseHash'] === hash('sha256', "one\ntwo\nthree\n"), '计划必须记录 Base/Local/Remote hash');

$approval = ['id'=>41,'status'=>'approved','operation'=>'apply_workspace','requested_by'=>7,'decided_by'=>7,'conversation_id'=>21,'task_id'=>31,'expires_at'=>'2099-01-01 00:00:00'];
$transaction = new AiChangeSetTransactionService($root, $private);
$applied = $service->apply($record, 7, ["src/A.php"], $preview['confirmToken'], $approval, $transaction, 21, 31);
phase4Expect($applied['state'] === 'completed' && file_get_contents($root . '/src/A.php') === "local\ntwo\nremote\n", 'apply 必须写入确定性合并结果');
phase4Expect((glob($private . '/wal/' . $applied['transactionId'] . '-*.bak') ?: []) === [], 'completed 事务必须清理备份');
phase4Reject(fn () => $service->apply($record, 7, ["src/A.php"], $preview['confirmToken'], $approval, $transaction, 21, 31), '已使用');
$badTaskApproval = array_replace($approval, ['task_id'=>999]);
phase4Reject(fn () => $transaction->execute(99, 7, 21, 31, ['blocked'=>false,'files'=>[]], $badTaskApproval), '最终审批');

file_put_contents($root . '/src/A.php', "local\ntwo\nthree\n");
$toctouPreview = $service->preview($record, 7, ["src/A.php"], true, 21, 31);
file_put_contents($root . '/src/A.php', "changed after preview\n");
phase4Reject(fn () => $service->apply($record, 7, ["src/A.php"], $toctouPreview['confirmToken'], $approval, $transaction, 21, 31), 'conflict');
phase4Reject(fn () => $service->preview($record, 8, ["src/A.php"], true, 21, 31), '资源不存在');
$clock = 1032;
phase4Reject(fn () => $service->apply($record, 7, ["src/A.php"], $toctouPreview['confirmToken'], $approval, $transaction, 21, 31), '过期');

foreach (['../escape.php','.env','.git/config','runtime/private/x','vendor/cache/x','credentials'] as $forbidden) {
    $bad = $record;
    $bad['manifest']['remote_manifest'] = ['algorithm'=>'sha256','files'=>[$forbidden=>['sha256'=>str_repeat('a',64),'size'=>1]],'digest'=>str_repeat('b',64)];
    phase4Reject(fn () => $service->validatePath($forbidden), '禁止');
}
mkdir($root . '/outside', 0755, true);
symlink($root . '/outside', $root . '/linked');
phase4Reject(fn () => $service->validatePath('linked/file.php'), '符号链接');
phase4Reject(fn () => $service->validateMigrationPath('database/migrations/106_changed.sql'), '已登记');
phase4Reject(fn () => $service->validateMigrationPath('database/migrations/001_core_schema.sql', true), '修改或删除');
phase4Expect($service->validateMigrationPath('database/migrations/107_ai_change.sql') === 107, '新 migration 必须严格大于最大登记编号');

$badApproval = array_replace($approval, ['status'=>'pending']);
$clock = 1000;
file_put_contents($root . '/src/A.php', "local\ntwo\nthree\n");
$approvalPreview = $service->preview($record, 7, ["src/A.php"], true, 21, 31);
phase4Reject(fn () => $service->apply($record, 7, ["src/A.php"], $approvalPreview['confirmToken'], $badApproval, $transaction, 21, 31), '最终审批');
phase4Reject(fn () => $service->apply($record, 8, ["src/A.php"], $approvalPreview['confirmToken'], array_replace($approval,['requested_by'=>8,'decided_by'=>8]), $transaction, 21, 31), '资源不存在');

$proposal = new AiCrudProposalService();
$validProposal = ['schema_version'=>1,'proposal_type'=>'form_schema','module_id'=>9,'form_schema'=>[
    'schemaVersion'=>2,'key'=>'orders','title'=>'订单','model'=>[],'layout'=>[],'nodes'=>[[
        'id'=>'title','kind'=>'field','type'=>'input','field'=>'title','valueType'=>'string','children'=>[],
        'props'=>[],'attrs'=>[],'validation'=>[],'events'=>[],'conditions'=>[],
        'database'=>['columnType'=>'varchar(255)','nullable'=>false],'list'=>['show'=>true,'sort'=>false],
    ]],
    'dataSources'=>[],'actions'=>[],'form'=>[],'submit'=>[],'database'=>['table'=>'fun_orders','connection'=>'mysql'],
]];
$normalizedA = $proposal->validate($validProposal);
$normalizedB = $proposal->validate(array_reverse($validProposal, true));
phase4Expect($normalizedA['proposalDigest'] === $normalizedB['proposalDigest'], 'CRUD 提案规范化必须确定性');
foreach (['bundle'=>[],'confirmToken'=>'fake','files'=>[],'outputPath'=>'/tmp/pwn'] as $field=>$value) {
    phase4Reject(fn () => $proposal->validate($validProposal + [$field=>$value]), '禁止');
}
phase4Reject(fn () => $proposal->validate(array_replace($validProposal,['schema_version'=>2])), 'schema_version');
phase4Reject(fn () => $proposal->validate(array_replace_recursive($validProposal,['form_schema'=>['database'=>['table'=>'bad-name']]])), '表名');
phase4Reject(fn () => $proposal->validate(array_replace_recursive($validProposal,['form_schema'=>['nodes'=>[['type'=>'unregistered']]]])), '字段能力');
phase4Reject(fn () => $proposal->validate(array_replace_recursive($validProposal,['form_schema'=>['unexpected'=>'model-output']])), '未知顶层字段');
$crudDefinitionProposal = ['schema_version'=>1,'proposal_type'=>'crud_definition','module_id'=>9,'crud_definition'=>[
    'schemaVersion'=>1,'key'=>'orders','database'=>['table'=>'fun_orders'],'fields'=>[['name'=>'title','type'=>'string']]
]];
foreach (['generationTargets'=>['/tmp/pwn'],'templates'=>['../../evil'],'routePath'=>'/tmp/pwn'] as $field=>$value) {
    phase4Reject(fn () => $proposal->validate(array_replace_recursive($crudDefinitionProposal,['crud_definition'=>[$field=>$value]])), '禁止');
}
$crudCalls = [];
$proposalPipeline = new AiCrudProposalService(
    moduleReader: static fn (int $id): array => ['module'=>['id'=>$id,'code'=>'orders','table_name'=>'fun_orders']],
    schemaValidator: static function (int $moduleId, array $schema) use (&$crudCalls): array { $crudCalls[] = ['validate',$moduleId,$schema]; return ['document'=>$schema,'schemaHash'=>hash('sha256', json_encode($schema))]; },
    generationPreview: static function (int $moduleId, array $document, string $proposalType, bool $canApply, ?string $nonce) use (&$crudCalls): array { $crudCalls[] = ['preview',$moduleId,$document,$proposalType,$canApply,$nonce]; return ['generationId'=>77,'plan'=>['blocked'=>false],'sensitive'=>['confirmToken'=>'server-token']]; },
    generationApply: static function (int $moduleId, int $generationId, string $token) use (&$crudCalls): array { $crudCalls[] = ['apply',$moduleId,$generationId,$token]; return ['state'=>'completed','generationId'=>$generationId]; },
);
$crudPreview = $proposalPipeline->preview($validProposal, 9, 7, 21, true, 'nonce-1');
phase4Expect(($crudPreview['generationId'] ?? 0) === 77 && ($crudPreview['businessModuleId'] ?? 0) === 9, 'CRUD preview 必须关联 business module 与 generation');
phase4Expect(($crudPreview['confirmToken'] ?? '') === 'server-token' && !isset($crudPreview['proposal']['form_schema']), 'CRUD preview 只返回服务端 generation token，不回传完整提案');
phase4Expect(($crudCalls[1][2]['nodes'][0]['field'] ?? '') === 'title' && ($crudCalls[1][3] ?? '') === 'form_schema', 'CRUD preview 必须把规范化提案快照接入 managed generation，不能忽略提案读取旧 schema');
phase4Reject(fn () => $proposalPipeline->apply(['moduleId'=>9,'generationId'=>77,'confirmToken'=>'server-token'], 7, 21), '最终审批');
$crudApproval = ['status'=>'approved','operation'=>'apply_workspace','requested_by'=>7,'decided_by'=>7,'conversation_id'=>21,'task_id'=>31];
$crudApplied = $proposalPipeline->apply(['moduleId'=>9,'generationId'=>77,'confirmToken'=>'server-token','taskId'=>31], 7, 21, $crudApproval);
phase4Expect(($crudApplied['state'] ?? '') === 'completed' && count($crudCalls) === 3, 'CRUD apply 必须只凭 generationId/token 委托服务端可信重建');
phase4Reject(fn () => $proposalPipeline->apply(['moduleId'=>9,'generationId'=>77,'confirmToken'=>'server-token','taskId'=>32], 7, 21, $crudApproval), '最终审批');
phase4Reject(fn () => $proposalPipeline->apply(['moduleId'=>9,'generationId'=>77,'confirmToken'=>'fake','taskId'=>31,'bundle'=>[]], 7, 21, $crudApproval), '禁止');

$businessService = (string) file_get_contents(dirname(__DIR__) . '/app/console/development/service/BusinessDevelopmentService.php');
$crudProposalService = (string) file_get_contents(dirname(__DIR__) . '/app/console/ai/service/AiCrudProposalService.php');
phase4Expect(str_contains($businessService, 'previewStructuredProposal('), 'BusinessDevelopmentService 必须提供结构化提案可信预览入口');
phase4Expect(str_contains($crudProposalService, 'function production(') && str_contains($crudProposalService, 'BusinessDevelopmentService::production'), 'CRUD proposal 生产工厂必须复用 BusinessDevelopmentService');
$controller = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/ai/Ai.php');
foreach (['changeSetDetail','changeSetPreview','changeSetApply','changeSetRecover','crudProposalPreview','crudProposalApply'] as $method) phase4Expect(str_contains($controller, "function {$method}("), "控制器缺少 {$method}");
phase4Expect(!str_contains($controller, '尚未实现'), '阶段四 controller 不得保留 501 占位');
foreach (['AiApproval', 'AiChangeSetTransactionService', 'AiCrudProposalService', 'apply_workspace', 'securityAudit'] as $wiring) phase4Expect(str_contains($controller, $wiring), '阶段四 controller 缺少生产安全接线：' . $wiring);
phase4Expect(str_contains($controller, '[400, 401, 403, 404, 409]'), '阶段四 controller 必须保留 conflict HTTP 409');
phase4Expect(substr_count($controller, "'conversation_id'=>") >= 2, 'ChangeSet 审计必须绑定 conversation_id');
phase4Expect(substr_count($controller, "'task_id'=>") >= 4, 'ChangeSet 与 CRUD 审计必须绑定真实 task_id');
phase4Expect(str_contains($controller, "->where('conversation_id', (int) \$record['conversation_id'])->where('task_id', (int) \$record['task_id'])"), 'ChangeSet apply 查询最终审批时必须绑定当前 conversation/task，拒绝任意旧审批 ID');
phase4Expect(str_contains($controller, 'assertOwnedTask('), 'CRUD controller 必须验证 task 与当前管理员及 conversation 归属');
phase4Expect(str_contains($controller, 'recordPreview('), 'ChangeSet preview 必须持久化公开计划绑定');
phase4Expect(str_contains($controller, 'changeSetApplication()->recover(') && str_contains($controller, "['recoveryVersion']"), 'ChangeSet recover 必须由数据库 recovery_version CAS 编排');
phase4Expect(str_contains($controller, 'publicChangeSetRecord('), 'ChangeSet detail 必须通过公开记录脱敏');
phase4Expect(str_contains($controller, 'SystemMigration::where(') && str_contains($controller, 'databaseMaximumMigration('), 'ChangeSet 生产服务必须同时读取数据库最大登记 migration');
phase4Expect(str_contains((string)file_get_contents(dirname(__DIR__) . '/app/console/ai/model/AiChangeSet.php'), "'selection'"), 'ChangeSet selection 必须持久化为 JSON 字段');
phase4Expect(is_file(dirname(__DIR__) . '/database/migrations/109_ai_phase4_change_sets.sql'), '阶段四 migration 必须使用初始最大 108 加一的 109');
$permissionCompensation = dirname(__DIR__) . '/database/migrations/110_ai_phase4_preview_permission.sql';
phase4Expect(is_file($permissionCompensation), '109 执行后发现的 preview 权限缺口必须使用当前最大 109 加一的 110 补偿，不得修改已执行 migration');
phase4Expect(str_contains((string) file_get_contents($permissionCompensation), 'changesetpreview'), '补偿 migration 必须登记 ChangeSet preview 权限');
phase4Expect(str_contains((string)file_get_contents(dirname(__DIR__) . '/config/console.php'), "'ai:change-set-recover'"), '必须注册恢复命令');

file_put_contents($root . '/src/A.php', "before\n");
$beforeHash = hash('sha256', "before\n");
$failedPlan = ['blocked'=>false,'files'=>[['path'=>'src/A.php','status'=>'update','localHash'=>$beforeHash,'baseHash'=>$beforeHash,'remoteHash'=>hash('sha256', "after\n"),'mergedHash'=>str_repeat('f', 64),'contentKind'=>'text','content'=>"after\n"]]];
$failedApproval = $approval;
$walBefore = glob($private . '/wal/*.json') ?: [];
phase4Reject(fn () => $transaction->execute(99, 7, 21, 31, $failedPlan, $failedApproval), 'hash 验证');
$walFiles = array_values(array_diff(glob($private . '/wal/*.json') ?: [], $walBefore));
phase4Expect(count($walFiles) === 1, '失败事务必须持久化唯一私有 WAL');
$wal = json_decode((string)file_get_contents($walFiles[0]), true, 512, JSON_THROW_ON_ERROR);
phase4Expect(($wal['state'] ?? '') === 'rolled_back' && file_get_contents($root . '/src/A.php') === "before\n", '可证明的失败必须立即回滚并记录 rolled_back');
$replayedRecovery = $transaction->recover((string)($wal['transaction_id'] ?? ''));
phase4Expect(($replayedRecovery['state'] ?? '') === 'rolled_back', '恢复必须幂等');

file_put_contents($root . '/src/A.php', "interrupt-before\n");
$interruptPlan = ['blocked'=>false,'files'=>[['path'=>'src/A.php','status'=>'update','localHash'=>hash('sha256', "interrupt-before\n"),'baseHash'=>null,'remoteHash'=>hash('sha256', "interrupt-after\n"),'mergedHash'=>hash('sha256', "interrupt-after\n"),'contentKind'=>'text','content'=>"interrupt-after\n"]]];
$interrupted = new AiChangeSetTransactionService($root, $private, static function (string $stage): void { if ($stage === 'after_file_rename') throw new \app\console\ai\exception\AiChangeSetInterruptionException('模拟中断'); });
$interruptWalBefore = glob($private . '/wal/*.json') ?: [];
phase4Reject(fn () => $interrupted->execute(101, 7, 21, 31, $interruptPlan, $failedApproval), '模拟中断');
$interruptWalFiles = array_values(array_diff(glob($private . '/wal/*.json') ?: [], $interruptWalBefore));
$interruptWal = json_decode((string)file_get_contents($interruptWalFiles[0]), true, 512, JSON_THROW_ON_ERROR);
phase4Expect(($interruptWal['state'] ?? '') === 'recovery_required', '进程中断必须保留可恢复 WAL');
$interruptRecovered = $interrupted->recover((string)$interruptWal['transaction_id']);
phase4Expect(($interruptRecovered['state'] ?? '') === 'rolled_back' && file_get_contents($root . '/src/A.php') === "interrupt-before\n", '中断恢复必须按 hash 证明后回滚');

foreach (['after_prepared','after_staged'] as $stage) {
    file_put_contents($root . '/src/A.php', "{$stage}-before\n");
    $stagePlan = ['blocked'=>false,'files'=>[['path'=>'src/A.php','status'=>'update','localHash'=>hash('sha256', "{$stage}-before\n"),'baseHash'=>null,'remoteHash'=>hash('sha256', "{$stage}-after\n"),'mergedHash'=>hash('sha256', "{$stage}-after\n"),'contentKind'=>'text','content'=>"{$stage}-after\n"]]];
    $stageTransaction = new AiChangeSetTransactionService($root, $private, static function (string $actual) use ($stage): void { if ($actual === $stage) throw new \app\console\ai\exception\AiChangeSetInterruptionException('阶段中断'); });
    $stageWalBefore = glob($private . '/wal/*.json') ?: [];
    phase4Reject(fn () => $stageTransaction->execute(102, 7, 21, 31, $stagePlan, $approval), '阶段中断');
    $stageWalPath = array_values(array_diff(glob($private . '/wal/*.json') ?: [], $stageWalBefore))[0];
    $stageWal = json_decode((string)file_get_contents($stageWalPath), true, 512, JSON_THROW_ON_ERROR);
    $stageRecovered = $stageTransaction->recover((string)$stageWal['transaction_id']);
    phase4Expect(($stageRecovered['state'] ?? '') === 'rolled_back' && file_get_contents($root . '/src/A.php') === "{$stage}-before\n", "{$stage} 中断必须可证明回滚");
}

file_put_contents($root . '/src/A.php', "verified-before\n");
$verifiedPlan = ['blocked'=>false,'files'=>[['path'=>'src/A.php','status'=>'update','localHash'=>hash('sha256', "verified-before\n"),'baseHash'=>null,'remoteHash'=>hash('sha256', "verified-after\n"),'mergedHash'=>hash('sha256', "verified-after\n"),'contentKind'=>'text','content'=>"verified-after\n"]]];
$verifiedTransaction = new AiChangeSetTransactionService($root, $private, static function (string $stage): void { if ($stage === 'after_verified') throw new \app\console\ai\exception\AiChangeSetInterruptionException('verified 中断'); });
$verifiedWalBefore = glob($private . '/wal/*.json') ?: [];
phase4Reject(fn () => $verifiedTransaction->execute(103, 7, 21, 31, $verifiedPlan, $approval), 'verified 中断');
$verifiedWalPath = array_values(array_diff(glob($private . '/wal/*.json') ?: [], $verifiedWalBefore))[0];
$verifiedWal = json_decode((string)file_get_contents($verifiedWalPath), true, 512, JSON_THROW_ON_ERROR);
$verifiedRecovered = $verifiedTransaction->recover((string)$verifiedWal['transaction_id']);
phase4Expect(($verifiedRecovered['state'] ?? '') === 'completed' && file_get_contents($root . '/src/A.php') === "verified-after\n", 'verified 中断且目标 hash 全部匹配时必须证明完成');

file_put_contents($root . '/src/A.php', "first-before\n");
file_put_contents($root . '/src/B.php', "second-before\n");
$multiPlan = ['blocked'=>false,'files'=>[
    ['path'=>'src/A.php','status'=>'update','localHash'=>hash('sha256', "first-before\n"),'baseHash'=>null,'remoteHash'=>hash('sha256', "first-after\n"),'mergedHash'=>hash('sha256', "first-after\n"),'contentKind'=>'text','content'=>"first-after\n"],
    ['path'=>'src/B.php','status'=>'update','localHash'=>hash('sha256', "second-before\n"),'baseHash'=>null,'remoteHash'=>hash('sha256', "second-after\n"),'mergedHash'=>str_repeat('e', 64),'contentKind'=>'text','content'=>"second-after\n"],
]];
$multiWalBefore = glob($private . '/wal/*.json') ?: [];
phase4Reject(fn () => $transaction->execute(100, 7, 21, 31, $multiPlan, $failedApproval), 'hash 验证');
$multiWalFiles = array_values(array_diff(glob($private . '/wal/*.json') ?: [], $multiWalBefore));
$multiWal = json_decode((string)file_get_contents($multiWalFiles[0]), true, 512, JSON_THROW_ON_ERROR);
phase4Expect(($multiWal['files'][0]['write_hash'] ?? null) === hash('sha256', "first-after\n"), 'WAL 必须逐文件 checkpoint 正确的 write hash');
phase4Expect(($multiWal['schema_version'] ?? 0) === 1 && ($multiWal['version'] ?? 0) > 0, 'WAL 必须带 schema_version 与单调 CAS version');
phase4Expect(in_array('prepared', (array) ($multiWal['history'] ?? []), true) && in_array('staged', (array) ($multiWal['history'] ?? []), true) && in_array('writing', (array) ($multiWal['history'] ?? []), true), '验证失败前必须持久化 prepared/staged/writing checkpoint');
phase4Expect(($multiWal['state'] ?? '') === 'rolled_back' && file_get_contents($root . '/src/A.php') === "first-before\n" && file_get_contents($root . '/src/B.php') === "second-before\n", '多文件失败必须完整逆序回滚');

file_put_contents($root . '/src/A.php', "unprovable\n");
$unprovable = $interrupted->recover((string)$interruptWal['transaction_id']);
phase4Expect(($unprovable['state'] ?? '') === 'rolled_back', '已完成恢复必须幂等且不得覆盖后续 Local');

phase4Remove($root);
echo "AI phase 4 change set tests: PASS\n";
