<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (Db::connection()->getDriverName() !== 'pgsql') return;
    $schema = Db::schema();
    $modes = ['request_approval','agent_approval','full_access'];
    if (!$schema->hasTable('ai_conversation_group')) $schema->create('ai_conversation_group', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('admin_id'); $t->string('name',100); $t->timestamps(); $t->softDeletes();
        $t->string('active_name',100)->nullable()->storedAs('CASE WHEN deleted_at IS NULL THEN name ELSE NULL END'); $t->unique(['admin_id','active_name']); $t->index(['admin_id','deleted_at']);
    });
    if (!$schema->hasTable('ai_configuration_profile')) $schema->create('ai_configuration_profile', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('admin_id'); $t->string('name',100); $t->json('configuration'); $t->text('secret_ciphertext')->nullable(); $t->boolean('is_default')->default(false);
        $t->bigInteger('default_admin_id')->nullable()->storedAs('CASE WHEN is_default THEN admin_id ELSE NULL END'); $t->timestamps(); $t->unique('default_admin_id'); $t->index(['admin_id','id']);
    });
    if (!$schema->hasTable('ai_conversation')) $schema->create('ai_conversation', static function (Blueprint $t) use ($modes): void {
        $t->id(); $t->bigInteger('admin_id'); $t->bigInteger('group_id')->nullable(); $t->char('uuid',36)->unique(); $t->string('title')->default('');
        $t->enum('status',['draft','planning','awaiting_approval','running','reviewing','applying','completed','paused','cancelled','failed','conflict','recovery_required'])->default('draft');
        $t->boolean('is_archived')->default(false); $t->boolean('is_unread')->default(false); $t->enum('approval_mode',$modes)->default('request_approval'); $t->string('provider',64)->default(''); $t->string('model',128)->default(''); $t->json('context')->nullable(); $t->bigInteger('profile_id')->nullable(); $t->string('reasoning_effort',10)->nullable(); $t->timestamps(); $t->softDeletes(); $t->index(['admin_id','status']); $t->index(['admin_id','group_id','is_archived','updated_at']);
    });
    if (!$schema->hasTable('ai_message')) $schema->create('ai_message', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('conversation_id'); $t->bigInteger('parent_id')->nullable(); $t->integer('sequence'); $t->enum('role',['system','user','assistant','tool']); $t->json('content'); $t->json('metadata')->nullable(); $t->json('usage')->nullable(); $t->string('idempotency_key',128)->nullable(); $t->char('payload_digest',64)->nullable(); $t->timestamps(); $t->softDeletes(); $t->unique(['conversation_id','sequence']); $t->unique(['conversation_id','idempotency_key']);
    });
    if (!$schema->hasTable('ai_task')) $schema->create('ai_task', static function (Blueprint $t) use ($modes): void {
        $t->id(); $t->bigInteger('conversation_id'); foreach (['message_id','parent_id','change_set_id'] as $name) $t->bigInteger($name)->nullable();
        $t->string('idempotency_key',128); $t->char('operation_token',64)->unique(); $t->enum('type',['chat','crud','code_change','fix','test','migration']); $t->string('stage',64)->default('');
        $t->enum('status',['pending','running','resume_pending','succeeded','failed','cancelled','paused','recovery_required'])->default('pending'); $t->enum('approval_mode',$modes);
        $t->string('provider',64)->default(''); $t->string('model',128)->default(''); $t->string('container_task_id',128)->nullable(); $t->string('sandbox_volume')->nullable(); $t->string('workspace_path',1024)->nullable(); $t->enum('sandbox_status',['pending','created','running','exported','cleaning','cleaned','failed'])->default('pending'); $t->boolean('sandbox_retained')->default(false);
        foreach (['max_rounds','timeout_seconds'] as $name) $t->integer($name)->default(0); $t->decimal('max_cost',12,4)->default(0);
        foreach (['input_token_budget','output_token_budget','total_token_budget'] as $name) $t->bigInteger($name)->default(0);
        foreach (['usage','input','output','error','test_result'] as $name) $t->json($name)->nullable(); $t->text('result_summary')->nullable();
        $t->enum('recovery_status',['none','recovery_required','recovering','recovered','failed'])->default('none');
        foreach (['heartbeat_at','started_at','completed_at','cleanup_at','cleanup_lease_expires_at'] as $name) $t->dateTime($name)->nullable(); $t->string('cleanup_lease_owner',128)->nullable(); $t->timestamps(); $t->softDeletes(); $t->unique(['conversation_id','idempotency_key']); $t->index(['conversation_id','status']); $t->index(['status','sandbox_status','completed_at','sandbox_retained']);
    });
    if (!$schema->hasTable('ai_outbox')) $schema->create('ai_outbox', static function (Blueprint $t): void {
        $t->id(); $t->string('aggregate_type',64); $t->bigInteger('aggregate_id'); $t->string('event_type',128); $t->string('idempotency_key',191)->unique(); $t->json('payload'); $t->enum('status',['pending','processing','dispatched','failed'])->default('pending'); $t->string('lease_owner',128)->nullable(); $t->dateTime('lease_expires_at')->nullable(); $t->integer('attempts')->default(0); $t->dateTime('available_at'); $t->text('last_error')->nullable(); $t->dateTime('dispatched_at')->nullable(); $t->timestamps(); $t->index(['status','available_at','lease_expires_at','id']);
    });
    if (!$schema->hasTable('ai_task_event')) $schema->create('ai_task_event', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('task_id'); $t->bigInteger('sequence'); $t->string('type',64); $t->json('payload'); $t->dateTime('created_at'); $t->unique(['task_id','sequence']); $t->index(['task_id','id']);
    });
    if (!$schema->hasTable('ai_tool_call')) $schema->create('ai_tool_call', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('conversation_id'); $t->bigInteger('task_id'); $t->bigInteger('message_id')->nullable(); $t->bigInteger('approval_id')->nullable(); $t->string('idempotency_key',128); $t->string('tool_name',128); $t->string('operation',64); $t->enum('risk_level',['low','medium','high','critical'])->default('low'); $t->enum('status',['pending','awaiting_approval','running','succeeded','failed','denied'])->default('pending'); $t->json('redacted_arguments'); $t->enum('approval_decision',['not_required','pending','approved','denied','expired','cancelled'])->default('not_required');
        foreach (['result','error','side_effects'] as $name) $t->json($name)->nullable(); $t->integer('exit_code')->nullable();
        foreach (['stdout','stderr'] as $name) { $t->text($name . '_summary')->nullable(); $t->char($name . '_hash',64)->nullable(); $t->string($name . '_path',1024)->nullable(); }
        $t->bigInteger('duration_ms')->nullable(); $t->dateTime('started_at')->nullable(); $t->dateTime('completed_at')->nullable(); $t->timestamps(); $t->unique(['conversation_id','idempotency_key']); $t->index(['task_id','status']);
    });
    if (!$schema->hasTable('ai_approval')) $schema->create('ai_approval', static function (Blueprint $t) use ($modes): void {
        $t->id(); foreach (['conversation_id','task_id','tool_call_id','requested_by'] as $name) $t->bigInteger($name); $t->bigInteger('decided_by')->nullable(); $t->enum('scope',['once','session_operation'])->default('once'); $t->text('risk_reason'); $t->json('impact'); $t->bigInteger('cas_version')->default(0); $t->char('nonce',64)->unique(); $t->char('digest',64); $t->string('operation',64); $t->enum('mode_snapshot',$modes); $t->enum('status',['pending','approved','consumed','denied','expired','cancelled'])->default('pending'); $t->json('request'); $t->json('decision')->nullable(); $t->dateTime('expires_at')->nullable(); $t->dateTime('decided_at')->nullable(); $t->timestamps(); $t->softDeletes(); $t->unique(['conversation_id','digest']); $t->index(['conversation_id','status']);
    });
    if (!$schema->hasTable('ai_change_set')) $schema->create('ai_change_set', static function (Blueprint $t): void {
        $t->id(); foreach (['conversation_id','task_id','created_by'] as $name) $t->bigInteger($name); foreach (['approval_id','final_approval_id','applied_by'] as $name) $t->bigInteger($name)->nullable();
        $t->string('idempotency_key',128); $t->char('digest',64); $t->char('base_digest',64)->default(''); $t->char('plan_digest',64)->default(''); $t->string('patch_path',1024); $t->char('patch_sha256',64); $t->json('base_file_hashes'); $t->json('selection')->nullable();
        foreach (['added_count','modified_count','deleted_count','renamed_count','recovery_version'] as $name) $t->integer($name)->default(0);
        foreach (['test','security'] as $name) { $t->enum($name . '_status',['pending','running','passed','failed','skipped'])->default('pending'); $t->json($name . '_result')->nullable(); }
        $t->string('transaction_id',128)->nullable()->unique(); $t->enum('recovery_status',['none','recovery_required','recovering','recovered','failed'])->default('none'); $t->enum('status',['proposed','approved','applying','applied','rejected','failed','conflict','recovery_required'])->default('proposed'); $t->json('manifest'); $t->json('summary')->nullable(); $t->string('base_revision',128)->default(''); $t->string('applied_revision',128)->default(''); $t->dateTime('applied_at')->nullable(); $t->timestamps(); $t->softDeletes(); $t->unique(['conversation_id','idempotency_key']); $t->unique(['conversation_id','digest']); $t->index(['created_by','conversation_id','status','id']);
    });
    if (!$schema->hasTable('ai_attachment')) $schema->create('ai_attachment', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('conversation_id'); $t->bigInteger('admin_id'); $t->bigInteger('message_id')->nullable(); $t->string('kind',10); $t->string('name'); $t->string('mime',64); $t->bigInteger('size'); $t->integer('width')->nullable(); $t->integer('height')->nullable(); $t->char('sha256',64); $t->char('storage_path',64)->unique(); $t->dateTime('expires_at'); $t->timestamps(); $t->softDeletes(); $t->index(['admin_id','conversation_id']); $t->index(['message_id','expires_at','deleted_at']);
    });
    if (!$schema->hasTable('ai_stream_nonce')) $schema->create('ai_stream_nonce', static function (Blueprint $t): void { $t->id(); $t->char('nonce_hash',64)->unique(); $t->dateTime('expires_at'); $t->dateTime('consumed_at'); $t->dateTime('created_at'); $t->index('expires_at'); });
};
