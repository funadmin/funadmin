<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function phase5TaskExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/app/console/controller/ai/Ai.php');
$api = (string) file_get_contents($root . '/admin-web/src/api/development/ai.ts');
$migration = (string) file_get_contents($root . '/database/migrations/112_ai_admin_web_menu.sql');

phase5TaskExpect(str_contains($controller, "#[Get('tasks/:id')]"), '缺少管理员任务详情 GET 路由');
phase5TaskExpect(str_contains($controller, 'function taskRead('), '缺少管理员任务详情控制器方法');
phase5TaskExpect(str_contains($controller, '$this->ai->getTask($id, $this->adminId())'), '任务详情必须通过服务校验管理员归属');
phase5TaskExpect(str_contains($api, 'task: (id: number)'), 'Admin Web API 缺少任务详情方法');
phase5TaskExpect(str_contains($api, '`${PREFIX}/tasks/${id}`'), 'Admin Web 任务详情路径错误');
phase5TaskExpect(str_contains($migration, "'console/development.ai:taskread'"), '阶段五 migration 缺少 taskRead 路由权限');
phase5TaskExpect(str_contains($migration, "'taskread'"), 'taskRead 权限 action 命名错误');

echo "AI phase 5 task detail tests: PASS\n";
