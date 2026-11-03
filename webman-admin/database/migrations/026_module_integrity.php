<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (Db::connection()->getDriverName() !== 'pgsql') return;
    $s = Db::schema();
    $foreign = static function (string $table, array $columns, string $target, array $references = ['id'], string $delete = 'restrict') use ($s): void {
        if (!$s->hasForeignKey($table, $columns)) $s->table($table, static function (Blueprint $t) use ($columns,$target,$references,$delete): void { $t->foreign($columns)->references($references)->on($target)->onDelete($delete)->onUpdate('cascade'); });
    };
    foreach (['identity_user','identity_admin_link','identity_member_link','identity_credential','identity_user_department','identity_login_attempt','identity_audit_log','identity_migration_audit','identity_sso_config','enterprise_application','oauth_client','scope','client_secret','client_grant','client_scope','redirect_uri','oauth_authorization','oauth_authorization_code','oauth_authorization_scope','oauth_token','oauth_token_scope','identity_consent','oidc_session','oidc_client_session','oidc_signing_key','application_assignment','application_database','application_domain','application_role','application_permission','application_user_role','application_user_permission','application_role_permission','backchannel_logout_delivery'] as $table) $foreign($table,['tenant_id'],'identity_tenant');
    foreach (['identity_admin_link','identity_member_link','identity_credential','identity_user_department','identity_consent','oidc_session','oidc_client_session','application_user_role','application_user_permission'] as $table) $foreign($table,['tenant_id','user_id'],'identity_user',['tenant_id','id'],'cascade');
    foreach (['identity_audit_log','identity_login_attempt','oauth_authorization','oauth_authorization_code','oauth_token'] as $table) $foreign($table,['tenant_id','user_id'],'identity_user',['tenant_id','id']);
    $foreign('identity_admin_link',['admin_id'],'admin',['id'],'cascade'); $foreign('identity_member_link',['member_id'],'member',['id'],'cascade'); $foreign('identity_user_department',['department_id'],'department');
    foreach (['oauth_client','application_assignment','application_database','application_domain','application_role','application_permission','application_user_role','application_user_permission','application_role_permission'] as $table) $foreign($table,['tenant_id','application_id'],'enterprise_application',['tenant_id','id'],'cascade');
    foreach (['client_secret','client_grant','client_scope','redirect_uri','oauth_authorization','oauth_authorization_code','oauth_token','identity_consent','oidc_client_session','backchannel_logout_delivery'] as $table) $foreign($table,['tenant_id','client_id'],'oauth_client',['tenant_id','id'],'cascade');
    foreach (['client_scope','oauth_authorization_scope','oauth_token_scope'] as $table) $foreign($table,['tenant_id','scope_id'],'scope',['tenant_id','id']);
    foreach (['oauth_authorization_code','oauth_authorization_scope','oauth_token'] as $table) $foreign($table,['tenant_id','authorization_id'],'oauth_authorization',['tenant_id','id']);
    $foreign('oauth_token_scope',['tenant_id','token_id'],'oauth_token',['tenant_id','id'],'cascade');
    $foreign('oauth_token',['parent_id'],'oauth_token',['id'],'set null');
    $foreign('oidc_client_session',['tenant_id','oidc_session_id'],'oidc_session',['tenant_id','id'],'cascade');
    $foreign('backchannel_logout_delivery',['tenant_id','oidc_session_id'],'oidc_session',['tenant_id','id'],'cascade');
    $foreign('backchannel_logout_delivery',['tenant_id','client_session_id'],'oidc_client_session',['tenant_id','id'],'cascade');
    foreach (['application_user_role','application_role_permission'] as $table) $foreign($table,['tenant_id','application_id','role_id'],'application_role',['tenant_id','application_id','id'],'cascade');
    foreach (['application_user_permission','application_role_permission'] as $table) $foreign($table,['tenant_id','application_id','permission_id'],'application_permission',['tenant_id','application_id','id'],'cascade');
    $check = static function (string $table, string $name, string $condition): void {
        $connection = Db::connection();
        $physical = $connection->getTablePrefix() . $table;
        $exists = $connection->selectOne('SELECT 1 FROM pg_constraint WHERE conrelid = ?::regclass AND conname = ?', [$physical,$name]);
        if (!$exists) $connection->statement('ALTER TABLE ' . $connection->getQueryGrammar()->wrapTable($table) . ' ADD CONSTRAINT "' . $name . '" CHECK (' . $condition . ')');
    };
    $check('oauth_client','oauth_client_policy_check',"(client_type='public' AND token_endpoint_auth_method='none' AND require_pkce=1) OR (client_type IN ('confidential','machine') AND token_endpoint_auth_method='client_secret_basic')");
    $check('oauth_authorization','oauth_authorization_pkce_check',"code_challenge_method='S256'");
    $check('oauth_token','oauth_token_subject_check',"(subject_type='user' AND user_id IS NOT NULL) OR (subject_type='client' AND user_id IS NULL)");
    $check('application_assignment','application_assignment_subject_check',"(subject_type='all' AND subject_id IS NULL) OR (subject_type<>'all' AND subject_id IS NOT NULL)");
    foreach (['ai_conversation','ai_conversation_group','ai_configuration_profile','ai_attachment'] as $table) $foreign($table,['admin_id'],'admin');
    $foreign('ai_conversation',['group_id'],'ai_conversation_group',['id'],'set null');
    foreach (['ai_message','ai_task','ai_tool_call','ai_approval','ai_change_set'] as $table) $foreign($table,['conversation_id'],'ai_conversation');
    foreach (['ai_task_event','ai_tool_call','ai_approval','ai_change_set'] as $table) $foreign($table,['task_id'],'ai_task');
    $foreign('ai_message',['parent_id'],'ai_message',['id'],'set null'); $foreign('ai_task',['message_id'],'ai_message',['id'],'set null'); $foreign('ai_task',['parent_id'],'ai_task',['id'],'set null');
    $foreign('ai_task',['change_set_id'],'ai_change_set',['id'],'set null'); $foreign('ai_tool_call',['message_id'],'ai_message',['id'],'set null'); $foreign('ai_tool_call',['approval_id'],'ai_approval',['id'],'set null');
    $foreign('ai_approval',['tool_call_id'],'ai_tool_call'); $foreign('ai_approval',['requested_by'],'admin'); $foreign('ai_approval',['decided_by'],'admin');
    $foreign('ai_change_set',['approval_id'],'ai_approval'); $foreign('ai_change_set',['final_approval_id'],'ai_approval'); $foreign('ai_change_set',['created_by'],'admin'); $foreign('ai_change_set',['applied_by'],'admin');
};
