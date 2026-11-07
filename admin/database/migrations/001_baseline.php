<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionSyncService;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

/**
 * PHP 迁移基线（非 MySQL 驱动与 SQLite 测试使用；MySQL 走 database/core/migrations/001_baseline.sql）。
 * 2026-11-07 由原 001-037 合并：结构为最终态，种子为当时链路终态（已剔除停用的权限墓碑行），
 * 末尾按当前代码同步宿主与插件权限，后续新增的控制器方法仍会在全新安装时登记。旧文件见 archive/。
 */
return static function (): void {
    $schema = Db::schema();
    $create = static function (string $name, callable $definition) use ($schema): void {
        if (!$schema->hasTable($name)) $schema->create($name, $definition);
    };

    // 管理员与认证
    $create('admin', static function (Blueprint $table): void {
        $table->id();
        $table->string('username', 20)->unique();
        $table->unsignedBigInteger('dept_id')->default(0);
        $table->string('password', 200);
        $table->string('email', 60)->nullable();
        $table->string('real_name', 50)->nullable();
        $table->string('mobile', 30)->nullable();
        $table->string('ip', 45)->nullable();
        $table->string('last_login_ip', 45)->default('');
        $table->string('token', 100)->nullable();
        $table->boolean('status')->default(true);
        $table->string('avatar', 255)->default('');
        $table->timestamps();
        $table->softDeletes();
    });
    $create('login_attempts', static function (Blueprint $table): void {
        $table->string('key', 64)->primary();
        $table->unsignedInteger('attempts')->default(0);
        $table->unsignedBigInteger('window_start');
    });
    $create('browser_sessions', static function (Blueprint $table): void {
        $table->string('session_hash', 64)->primary();
        $table->string('csrf_token', 64);
        $table->dateTime('expires_at')->index();
    });
    $create('admin_auth_tokens', static function (Blueprint $table): void {
        $table->string('token_hash', 64)->primary();
        $table->unsignedBigInteger('admin_id')->index();
        $table->string('purpose', 16);
        $table->string('credential_version', 64);
        $table->dateTime('expires_at')->index();
        $table->dateTime('created_at');
    });

    // 角色、权限、菜单、部门
    $create('auth_group', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('pid')->default(0);
        $table->string('name', 100);
        $table->string('code', 50)->unique();
        $table->unsignedInteger('level')->default(100);
        $table->string('data_scope', 30)->default('self');
        $table->string('remark', 255)->default('');
        $table->boolean('status')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
    $create('permission', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('pid')->default(0);
        $table->string('app_name', 50)->default('admin');
        $table->string('code', 255)->nullable()->unique();
        $table->string('obj', 190)->default('');
        $table->string('act', 100)->default('');
        $table->string('name', 100)->default('');
        $table->string('resource_type', 20)->default('route');
        $table->boolean('status')->default(true);
        $table->boolean('is_public')->default(false);
        $table->string('source_type', 20)->default('system');
        $table->string('source_name', 100)->default('');
        $table->timestamps();
        $table->softDeletes();
        $table->unsignedInteger('sort_order')->default(999);
    });
    $create('admin_menu', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('pid')->default(0);
        $table->unsignedBigInteger('permission_id')->default(0);
        $table->string('app_name', 50)->default('admin');
        $table->string('name', 100)->default('');
        $table->string('href', 255)->default('');
        $table->string('query', 250)->default('');
        $table->string('target', 20)->default('_self');
        $table->string('icon', 100)->default('');
        $table->boolean('status')->default(true);
        $table->string('source_type', 20)->default('admin_web');
        $table->string('source_name', 100)->default('');
        $table->unsignedInteger('sort_order')->default(999);
        $table->timestamps();
        $table->softDeletes();
    });
    $create('casbin_rule', static function (Blueprint $table): void {
        $table->id();
        $table->string('ptype', 10);
        foreach (range(0, 5) as $index) $table->string('v' . $index, 190)->default('');
        $table->string('rule_hash', 64)->unique();
    });
    $create('admin_department', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('admin_id');
        $table->unsignedBigInteger('dept_id');
        $table->timestamps();
        $table->unique(['admin_id', 'dept_id']);
    });
    $create('department', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('pid')->default(0);
        $table->string('name', 100);
        $table->string('leader', 100)->default('');
        $table->string('phone', 30)->default('');
        $table->string('email', 100)->default('');
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
    foreach (['auth_group_inherit' => 'parent_role_id', 'auth_group_department' => 'dept_id'] as $name => $target) {
        $create($name, static function (Blueprint $table) use ($target): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger($target);
            $table->timestamps();
            $table->unique(['role_id', $target]);
        });
    }
    $create('permission_field', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('permission_id')->index();
        $table->string('resource', 190);
        $table->string('field', 100);
        $table->string('name', 100);
        $table->boolean('status')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
        $table->unique(['permission_id', 'field']);
    });
    $create('auth_group_field_permission', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('field_id');
        $table->boolean('can_view')->default(false);
        $table->boolean('can_edit')->default(false);
        $table->timestamps();
        $table->unique(['role_id', 'field_id']);
    });

    // 字典
    $create('dict_type', static function (Blueprint $table): void {
        $table->id();
        $table->string('code', 60)->unique();
        $table->string('name', 100);
        $table->boolean('status')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
        $table->string('remark', 255)->default('');
        $table->timestamps();
        $table->softDeletes();
        $table->index(['status', 'sort_order']);
    });
    $create('dict_item', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('type_id');
        $table->string('label', 100);
        $table->string('value', 100);
        $table->string('css_class', 30)->default('');
        $table->boolean('status')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
        $table->string('remark', 255)->default('');
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['type_id', 'value']);
        $table->index(['type_id', 'status', 'sort_order']);
    });

    // 操作日志
    $create('admin_log', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('admin_id')->default(0)->index();
        foreach (['name', 'username', 'url', 'app_name', 'source_type', 'source_name', 'controller', 'action', 'agent', 'ip', 'method', 'request_id', 'error_message'] as $column) {
            $table->string($column, $column === 'error_message' ? 500 : ($column === 'url' || $column === 'agent' ? 2048 : 255))->default('');
        }
        foreach (['get_data', 'post_data', 'header_data'] as $column) $table->longText($column)->nullable();
        $table->boolean('status')->default(false)->index();
        $table->unsignedSmallInteger('response_code')->default(0);
        $table->unsignedInteger('duration_ms')->default(0);
        $table->timestamps();
        $table->softDeletes();
        $table->index('created_at');
    });

    // 多语言
    $create('language', static function (Blueprint $table): void {
        $table->id();
        $table->string('name', 20)->unique();
        $table->boolean('is_default')->default(false);
        $table->boolean('status')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
    $create('language_line', static function (Blueprint $table): void {
        $table->id();
        $table->string('locale', 20);
        $table->string('key', 190);
        $table->text('value');
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['locale', 'key']);
    });

    // 配置
    $create('config_group', static function (Blueprint $table): void {
        $table->id(); $table->string('name', 30)->unique(); $table->string('title', 60);
        $table->boolean('status')->default(true); $table->timestamps(); $table->softDeletes();
    });
    $create('config', static function (Blueprint $table): void {
        $table->id(); $table->string('code', 30)->unique(); $table->text('value')->nullable();
        $table->string('extra', 255)->default(''); $table->string('remark', 100)->default('');
        $table->string('verify', 50)->default(''); $table->string('type', 30)->default('text');
        $table->string('group', 80)->default('site'); $table->boolean('status')->default(true);
        $table->boolean('is_system')->default(false); $table->timestamps(); $table->softDeletes();
    });
    $create('field_type', static function (Blueprint $table): void {
        $table->id(); $table->string('name', 32); $table->string('title', 64)->default('');
        $table->string('default_define', 128)->default(''); $table->boolean('isoption')->default(false);
        $table->string('rules', 256)->default(''); $table->boolean('status')->default(true);
        $table->integer('sort_order')->default(0); $table->timestamps(); $table->softDeletes();
    });
    $create('field_verify', static function (Blueprint $table): void {
        $table->id(); $table->string('verify', 50)->unique(); $table->string('title', 100)->default('');
        $table->timestamps(); $table->softDeletes();
    });

    // 黑名单
    $create('blacklist', static function (Blueprint $table): void {
        $table->id();
        $table->string('ip', 50)->index();
        $table->string('remark', 200)->default('');
        $table->boolean('status')->default(true)->index();
        $table->timestamps();
        $table->softDeletes();
        $table->index(['ip', 'status', 'deleted_at']);
    });

    // 附件
    $create('attach_group', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('pid')->default(0);
        $table->string('name', 100);
        $table->string('thumb', 255)->default('');
        $table->boolean('status')->default(true);
        $table->unsignedInteger('sort_order')->default(999);
        $table->timestamps();
        $table->softDeletes();
        $table->index(['pid', 'status']);
    });
    $create('attach', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('admin_id')->default(0);
        $table->unsignedBigInteger('member_id')->default(0);
        $table->unsignedBigInteger('group_id')->default(1);
        $table->string('original_name', 255)->default('');
        $table->string('name', 255)->default('');
        $table->string('thumb', 1024)->default('');
        $table->string('path', 768)->default('');
        $table->string('url', 1024)->default('');
        $table->string('storage_key', 500)->default('');
        $table->string('ext', 20)->default('');
        $table->unsignedInteger('size')->default(0);
        $table->string('width', 30)->default('0');
        $table->string('height', 30)->default('0');
        $table->char('md5', 32)->default('');
        $table->string('sha1', 40)->default('');
        $table->string('mime', 80)->default('');
        $table->string('duration', 50)->default('0');
        $table->string('driver', 30)->default('local');
        $table->boolean('status')->default(true);
        $table->unsignedInteger('sort_order')->default(50);
        $table->timestamps();
        $table->softDeletes();
        $table->index(['admin_id', 'member_id']);
        $table->index(['group_id', 'status']);
        $table->index('md5');
    });
    // storage_key 超出 MySQL 索引长度，需要前缀索引，Blueprint 无法表达。
    $attach = Db::connection()->getTablePrefix() . 'attach';
    $driver = Db::connection()->getDriverName();
    if ($driver === 'mysql') {
        if (Db::select("SHOW INDEX FROM `{$attach}` WHERE Key_name = 'attach_driver_storage_key_index'") === []) {
            Db::statement("ALTER TABLE `{$attach}` ADD INDEX `attach_driver_storage_key_index` (`driver`, `storage_key`(500))");
        }
    } elseif ($driver === 'sqlite') {
        if (!array_filter(Db::select("PRAGMA index_list('{$attach}')"), static fn (object $index): bool => (string) ($index->name ?? '') === 'attach_driver_storage_key_index')) {
            Db::statement("CREATE INDEX attach_driver_storage_key_index ON {$attach} (driver, storage_key)");
        }
    }

    // 会员
    $create('member_group', static function (Blueprint $table): void {
        $table->id(); $table->string('name', 50); $table->string('icon', 50)->default(''); $table->boolean('status')->default(true);
        $table->timestamps(); $table->softDeletes(); $table->unique('name');
    });
    $create('member_level', static function (Blueprint $table): void {
        $table->id(); $table->string('name', 30); $table->decimal('amount', 10, 2)->default(0); $table->unsignedSmallInteger('discount')->default(100);
        $table->string('thumb', 255)->default(''); $table->boolean('status')->default(true); $table->unsignedInteger('sort_order')->default(0); $table->string('description', 200)->default('');
        $table->timestamps(); $table->softDeletes(); $table->unique('name');
    });
    $create('member_tag', static function (Blueprint $table): void {
        $table->id(); $table->string('name', 50); $table->boolean('status')->default(true); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->softDeletes(); $table->unique('name');
    });
    $create('member', static function (Blueprint $table): void {
        $table->id(); $table->string('username', 80); $table->string('mobile', 20)->nullable(); $table->string('email', 60)->nullable(); $table->string('sex', 1)->default('0');
        $table->string('password', 100)->default(''); $table->unsignedBigInteger('level_id')->default(1); $table->string('avatar', 255)->default(''); $table->boolean('status')->default(true);
        $table->unsignedInteger('login_num')->default(0); $table->dateTime('last_login')->nullable(); $table->string('last_ip', 45)->default(''); $table->timestamps(); $table->softDeletes();
        $table->unique('username'); $table->unique('mobile'); $table->unique('email'); $table->index(['level_id', 'status']);
    });
    foreach (['member_group_relation' => 'group_id', 'member_tag_relation' => 'tag_id'] as $name => $related) {
        $create($name, static function (Blueprint $table) use ($related): void {
            $table->unsignedBigInteger('member_id'); $table->unsignedBigInteger($related); $table->dateTime('created_at')->nullable(); $table->unique(['member_id', $related]);
        });
    }
    $create('member_api_session', static function (Blueprint $table): void {
        $table->id();
        $table->char('session_id', 32)->unique();
        $table->unsignedBigInteger('member_id');
        $table->char('refresh_hash', 64)->unique();
        $table->bigInteger('refresh_expires_at');
        $table->string('ip', 45)->default('');
        $table->dateTime('created_at');
        $table->dateTime('rotated_at')->nullable();
        $table->dateTime('revoked_at')->nullable();
        $table->index(['member_id', 'revoked_at']);
    });
    $create('member_api_session_used', static function (Blueprint $table): void {
        $table->char('refresh_hash', 64)->primary();
        $table->char('session_id', 32)->index();
        $table->dateTime('used_at');
    });

    // 系统升级
    $create('upgrade_manifest', static function (Blueprint $table): void {
        $table->id(); $table->string('manifest_id', 96)->unique(); $table->json('payload'); $table->dateTime('expires_at'); $table->dateTime('consumed_at')->nullable(); $table->timestamps();
        $table->index(['expires_at', 'consumed_at']);
    });
    $create('upgrade_task', static function (Blueprint $table): void {
        $table->id(); $table->string('from_version', 64)->default(''); $table->string('to_version', 64)->default(''); $table->string('status', 40)->default('pending'); $table->string('stage', 80)->default(''); $table->unsignedTinyInteger('progress')->default(0); $table->string('idempotency_token', 128)->unique(); $table->string('active_slot', 128)->nullable(); $table->dateTime('lease_expires_at')->nullable(); $table->string('backup_path', 1024)->default(''); $table->string('backup_hash', 64)->default(''); $table->string('db_backup_path', 1024)->default(''); $table->string('db_backup_hash', 64)->default(''); $table->text('error_message')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        $table->string('manifest_id', 96)->nullable(); $table->string('package_hash', 64)->nullable(); $table->string('db_backup_schema', 128)->nullable();
        $table->index(['status', 'active_slot']);
    });

    // 插件
    $create('plugin', static function (Blueprint $table): void {
        $table->id(); $table->string('code', 64)->unique(); $table->string('name', 120)->default(''); $table->string('version', 64)->default(''); $table->boolean('status')->default(false);
        $table->string('lifecycle_state', 40)->default('discovered'); $table->boolean('needs_reinstall')->default(false); $table->string('operation_token', 128)->nullable(); $table->text('manifest')->nullable(); $table->text('config')->nullable(); $table->string('source', 20)->default('local'); $table->string('code_version', 64)->default(''); $table->string('requires', 255)->default(''); $table->string('last_error', 500)->default(''); $table->string('error_stage', 80)->default(''); $table->string('recovery_path', 1024)->default(''); $table->dateTime('installed_at')->nullable(); $table->dateTime('state_changed_at')->nullable(); $table->timestamps(); $table->softDeletes();
        $table->string('composer_conflict_ack', 64)->default('');
        $table->index(['lifecycle_state', 'status']);
    });
    $create('plugin_operation', static function (Blueprint $table): void {
        $table->id(); $table->string('plugin_code', 64); $table->string('operation', 40); $table->string('status', 40)->default('running'); $table->string('stage', 80)->default(''); $table->unsignedTinyInteger('progress')->default(0); $table->string('operation_token', 128)->unique(); $table->string('from_version', 64)->default(''); $table->string('to_version', 64)->default(''); $table->text('error_message')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->index(['plugin_code', 'status']);
    });
    $create('plugin_resource', static function (Blueprint $table): void {
        $table->id(); $table->string('plugin_code', 64); $table->string('version', 64)->default(''); $table->string('resource_type', 40); $table->string('publication_unit', 80)->nullable(); $table->string('operation_token', 128)->default(''); $table->string('source_path', 1024)->default(''); $table->string('target_path', 1024)->default(''); $table->string('sha256', 64)->default(''); $table->string('tree_hash', 64)->nullable(); $table->timestamps(); $table->index(['plugin_code', 'version']);
    });
    $create('plugin_version_history', static function (Blueprint $table): void {
        $table->id(); $table->string('plugin_code', 64); $table->string('version', 64); $table->string('package_path', 1024)->default(''); $table->string('package_hash', 64)->default(''); $table->unsignedBigInteger('package_size')->default(0); $table->text('manifest')->nullable(); $table->dateTime('created_at')->nullable(); $table->dateTime('updated_at')->nullable(); $table->index(['plugin_code', 'version']);
    });

    // 种子数据：只在表为空时写入（$now 为写入时间，原本为空的时间列保持为空）。
    $now = date('Y-m-d H:i:s');
    foreach ([
        'admin_menu' => [['id', 'pid', 'permission_id', 'app_name', 'name', 'href', 'query', 'target', 'icon', 'status', 'source_type', 'source_name', 'sort_order', 'created_at', 'updated_at', 'deleted_at'], [
            [1, 17, 1, 'admin', '角色管理', '/admin/system/role', 'name=SystemRole&type=C&component=system%2Frole%2Findex&permission=authorization:role:index', '_self', 'i-ep-key', 1, 'admin_web', 'authorization', 10, $now, $now, null],
            [2, 17, 14, 'admin', '管理员', '/admin/system/user', 'name=SystemAdmin&type=C&component=system%2Fuser%2Findex&permission=authorization%3Aadmin%3Aindex', '_self', 'i-ep-user', 1, 'admin_web', 'authorization', 11, $now, $now, null],
            [3, 17, 22, 'admin', '部门管理', '/admin/system/dept', 'name=SystemDepartment&type=C&component=system%2Fdept%2Findex&permission=authorization%3Adepartment%3Atree', '_self', 'i-ep-office-building', 1, 'admin_web', 'authorization', 12, $now, $now, null],
            [4, 17, 28, 'admin', '菜单管理', '/admin/system/menu', 'name=SystemMenu&type=C&component=system%2Fmenu%2Findex&permission=authorization:menu:tree', '_self', 'i-ep-menu', 1, 'admin_web', 'authorization', 13, $now, $now, null],
            [5, 17, 35, 'admin', '权限资源', '/admin/system/permission', 'name=SystemPermission&type=C&component=system%2Fpermission%2Findex&permission=authorization:permission:tree', '_self', 'i-ep-lock', 1, 'admin_web', 'authorization', 14, $now, $now, null],
            [6, 17, 41, 'admin', '字典管理', '/admin/system/dict', 'name=SystemDict&type=C&component=system%2Fdict%2Findex&permission=system:dict:types', '_self', 'i-ep-collection', 1, 'admin_web', 'dictionary', 30, $now, $now, null],
            [7, 17, 53, 'admin', '操作日志', '/admin/system/log/operation', 'name=SystemLogOperation&type=C&component=system%2Flog%2Foperation&permission=system:operation-log:index', '_self', 'i-ep-document', 1, 'admin_web', 'operation_log', 40, $now, $now, null],
            [8, 17, 57, 'admin', '多语言', '/admin/system/language', 'name=SystemLanguage&type=C&component=system%2Flanguage%2Findex&permission=system:language:index', '_self', 'i-ep-chat-dot-round', 1, 'admin_web', 'language', 50, $now, $now, null],
            [9, 17, 68, 'admin', '配置管理', '/admin/system/config', 'name=SystemConfig&type=C&component=system%2Fconfig%2Findex&permission=system:config:index', '_self', 'i-ep-setting', 1, 'admin_web', 'config', 60, $now, $now, null],
            [10, 17, 80, 'admin', '黑名单', '/admin/system/blacklist', 'name=SystemBlacklist&type=C&component=system%2Fblacklist%2Findex&permission=system:blacklist:index', '_self', 'i-ep-circle-close', 1, 'admin_web', 'blacklist', 55, $now, $now, null],
            [11, 17, 90, 'admin', '附件库', '/admin/system/attachment', 'name=SystemAttachment&type=C&component=system%2Fattachment%2Findex&permission=system:attachment:index', '_self', 'i-ep-picture-filled', 1, 'admin_web', 'attachment', 50, $now, $now, null],
            [12, 18, 101, 'admin', '会员管理', '/admin/system/member', 'name=SystemMember&type=C&component=system%2Fmember%2Findex&permission=system:member:index', '_self', 'i-ep-user', 1, 'admin_web', 'member', 90, $now, $now, null],
            [13, 18, 112, 'admin', '会员组', '/admin/system/member-group', 'name=SystemMemberGroup&type=C&component=system%2Fmember-group%2Findex&permission=system:member-group:index', '_self', 'i-ep-user-filled', 1, 'admin_web', 'member', 90, $now, $now, null],
            [14, 18, 122, 'admin', '会员等级', '/admin/system/member-level', 'name=SystemMemberLevel&type=C&component=system%2Fmember-level%2Findex&permission=system:member-level:index', '_self', 'i-ep-medal', 1, 'admin_web', 'member', 90, $now, $now, null],
            [15, 17, 132, 'admin', '系统升级', '/admin/system/upgrade', 'name=SystemUpgrade&type=C&component=system%2Fupgrade%2Findex&permission=system:upgrade:status', '_self', 'i-ep-upload-filled', 1, 'admin_web', 'system_upgrade', 15, $now, $now, null],
            [16, 19, 142, 'admin', '插件中心', '/plugin', 'name=SystemPlugin&type=C&component=system%2Fplugin%2Findex&permission=plugin:plugin:installed', '_self', 'i-ep-box', 1, 'admin_web', 'plugin_center', 13, $now, $now, null],
            [17, 0, 0, 'admin', '系统管理', '/system', 'name=System_management&type=M&component=Layout', '_self', 'i-ep-setting', 1, 'admin_web', 'system_management', 10, $now, $now, null],
            [18, 0, 0, 'admin', '用户管理', '/users', 'name=User_management&type=M&component=Layout', '_self', 'i-ep-user', 1, 'admin_web', 'user_management', 20, $now, $now, null],
            [19, 0, 0, 'admin', '插件管理', '/plugins', 'name=Plugin_management&type=M&component=Layout', '_self', 'i-ep-box', 1, 'admin_web', 'plugin_management', 30, $now, $now, null],
        ]],
        'attach_group' => [['id', 'pid', 'name', 'thumb', 'status', 'sort_order', 'created_at', 'updated_at', 'deleted_at'], [
            [1, 0, '默认', '', 1, 999, $now, $now, null],
        ]],
        'config_group' => [['id', 'name', 'title', 'status', 'created_at', 'updated_at', 'deleted_at'], [
            [1, 'site', '网站', 1, $now, $now, null],
            [2, 'sms', '短信', 1, $now, $now, null],
            [3, 'upload', '上传', 1, $now, $now, null],
            [4, 'mobile', '手机', 1, $now, $now, null],
        ]],
        'dict_item' => [['id', 'type_id', 'label', 'value', 'css_class', 'status', 'sort_order', 'remark', 'created_at', 'updated_at', 'deleted_at'], [
            [1, 1, '正常', 1, 'success', 1, 10, '', $now, $now, null],
            [2, 1, '停用', 0, 'danger', 1, 20, '', $now, $now, null],
            [3, 2, '未知', 0, 'info', 1, 10, '', $now, $now, null],
            [4, 2, '男', 1, 'primary', 1, 20, '', $now, $now, null],
            [5, 2, '女', 2, 'danger', 1, 30, '', $now, $now, null],
            [6, 3, '是', 1, 'success', 1, 10, '', $now, $now, null],
            [7, 3, '否', 0, 'info', 1, 20, '', $now, $now, null],
        ]],
        'dict_type' => [['id', 'code', 'name', 'status', 'sort_order', 'remark', 'created_at', 'updated_at', 'deleted_at'], [
            [1, 'sys_normal_disable', '系统状态', 1, 10, '', $now, $now, null],
            [2, 'sys_user_sex', '用户性别', 1, 20, '', $now, $now, null],
            [3, 'yes_no', '是否', 1, 30, '', $now, $now, null],
        ]],
        'field_type' => [['id', 'name', 'title', 'default_define', 'isoption', 'rules', 'status', 'sort_order', 'created_at', 'updated_at', 'deleted_at'], [
            [1, 'text', '文本', '', 0, '', 1, 0, $now, $now, null],
            [2, 'password', '密码', '', 0, '', 1, 0, $now, $now, null],
            [3, 'textarea', '多行文本', '', 0, '', 1, 0, $now, $now, null],
            [4, 'number', '整数', '', 0, '', 1, 0, $now, $now, null],
            [5, 'float', '小数', '', 0, '', 1, 0, $now, $now, null],
            [6, 'select', '选择', '', 1, '', 1, 0, $now, $now, null],
            [7, 'radio', '单选', '', 1, '', 1, 0, $now, $now, null],
            [8, 'checkbox', '多选', '', 1, '', 1, 0, $now, $now, null],
            [9, 'switch', '开关', '', 0, '', 1, 0, $now, $now, null],
            [10, 'tags', '标签', '', 0, '', 1, 0, $now, $now, null],
            [11, 'array', '数组', '', 0, '', 1, 0, $now, $now, null],
            [12, 'date', '日期', '', 0, '', 1, 0, $now, $now, null],
            [13, 'datetime', '日期时间', '', 0, '', 1, 0, $now, $now, null],
            [14, 'range', '日期范围', '', 0, '', 1, 0, $now, $now, null],
            [15, 'json', 'JSON', '', 0, '', 1, 0, $now, $now, null],
            [16, 'image', '图片', '', 0, '', 1, 0, $now, $now, null],
            [17, 'images', '多图', '', 0, '', 1, 0, $now, $now, null],
            [18, 'file', '文件', '', 0, '', 1, 0, $now, $now, null],
            [19, 'files', '多文件', '', 0, '', 1, 0, $now, $now, null],
            [20, 'hidden', '隐藏域', '', 0, '', 1, 0, $now, $now, null],
        ]],
        'field_verify' => [['id', 'verify', 'title', 'created_at', 'updated_at', 'deleted_at'], [
            [1, 'required', '必须', $now, $now, null],
            [2, 'email', '邮箱', $now, $now, null],
            [3, 'url', '地址', $now, $now, null],
            [4, 'number', '整数', $now, $now, null],
        ]],
        'language' => [['id', 'name', 'is_default', 'status', 'created_at', 'updated_at', 'deleted_at'], [
            [1, 'zh-cn', 1, 1, $now, $now, null],
            [2, 'en-us', 0, 1, $now, $now, null],
        ]],
        'member_group' => [['id', 'name', 'icon', 'status', 'created_at', 'updated_at', 'deleted_at'], [
            [1, '默认组', '', 1, $now, $now, null],
        ]],
        'member_level' => [['id', 'name', 'amount', 'discount', 'thumb', 'status', 'sort_order', 'description', 'created_at', 'updated_at', 'deleted_at'], [
            [1, '默认等级', 0, 100, '', 1, 0, '', $now, $now, null],
        ]],
        'permission' => [['id', 'pid', 'app_name', 'code', 'obj', 'act', 'name', 'resource_type', 'status', 'is_public', 'source_type', 'source_name', 'created_at', 'updated_at', 'deleted_at', 'sort_order'], [
            [1, 0, 'admin', 'authorization:role:index', 'authorization/role', 'index', '角色管理 / index', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [2, 0, 'admin', 'authorization:role:all', 'authorization/role', 'all', '角色管理 / all', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [3, 0, 'admin', 'authorization:role:parent-options', 'authorization/role', 'parent-options', '角色管理 / parentOptions', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [4, 0, 'admin', 'authorization:role:permission-tree', 'authorization/role', 'permission-tree', '角色管理 / permissionTree', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [5, 0, 'admin', 'authorization:role:detail', 'authorization/role', 'detail', '角色管理 / detail', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [6, 0, 'admin', 'authorization:role:create', 'authorization/role', 'create', '角色管理 / create', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [7, 0, 'admin', 'authorization:role:update', 'authorization/role', 'update', '角色管理 / update', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [8, 0, 'admin', 'authorization:role:delete', 'authorization/role', 'delete', '角色管理 / delete', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [9, 0, 'admin', 'authorization:role:delete-by-id', 'authorization/role', 'delete-by-id', '角色管理 / deleteById', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [10, 0, 'admin', 'authorization:role:authorization', 'authorization/role', 'authorization', '角色管理 / authorization', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [11, 0, 'admin', 'authorization:role:save-authorization', 'authorization/role', 'save-authorization', '角色管理 / saveAuthorization', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [12, 0, 'admin', 'authorization:role:copy-authorization', 'authorization/role', 'copy-authorization', '角色管理 / copyAuthorization', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [13, 0, 'admin', 'authorization:role:permissions', 'authorization/role', 'permissions', '角色管理 / permissions', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [14, 0, 'admin', 'authorization:admin:index', 'authorization/admin', 'index', '管理员 / index', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [15, 0, 'admin', 'authorization:admin:detail', 'authorization/admin', 'detail', '管理员 / detail', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [16, 0, 'admin', 'authorization:admin:create', 'authorization/admin', 'create', '管理员 / create', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [17, 0, 'admin', 'authorization:admin:update', 'authorization/admin', 'update', '管理员 / update', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [18, 0, 'admin', 'authorization:admin:delete', 'authorization/admin', 'delete', '管理员 / delete', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [19, 0, 'admin', 'authorization:admin:delete-by-id', 'authorization/admin', 'delete-by-id', '管理员 / deleteById', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [20, 0, 'admin', 'authorization:admin:reset-password', 'authorization/admin', 'reset-password', '管理员 / resetPassword', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [21, 0, 'admin', 'authorization:admin:status', 'authorization/admin', 'status', '管理员 / status', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [22, 0, 'admin', 'authorization:department:tree', 'authorization/department', 'tree', '部门管理 / tree', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [23, 0, 'admin', 'authorization:department:detail', 'authorization/department', 'detail', '部门管理 / detail', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [24, 0, 'admin', 'authorization:department:create', 'authorization/department', 'create', '部门管理 / create', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [25, 0, 'admin', 'authorization:department:update', 'authorization/department', 'update', '部门管理 / update', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [26, 0, 'admin', 'authorization:department:delete', 'authorization/department', 'delete', '部门管理 / delete', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [27, 0, 'admin', 'authorization:department:delete-by-id', 'authorization/department', 'delete-by-id', '部门管理 / deleteById', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [28, 0, 'admin', 'authorization:menu:tree', 'authorization/menu', 'tree', '菜单管理 / tree', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [29, 0, 'admin', 'authorization:menu:permission-options', 'authorization/menu', 'permission-options', '菜单管理 / permissionOptions', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [30, 0, 'admin', 'authorization:menu:detail', 'authorization/menu', 'detail', '菜单管理 / detail', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [31, 0, 'admin', 'authorization:menu:create', 'authorization/menu', 'create', '菜单管理 / create', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [32, 0, 'admin', 'authorization:menu:update', 'authorization/menu', 'update', '菜单管理 / update', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [33, 0, 'admin', 'authorization:menu:delete', 'authorization/menu', 'delete', '菜单管理 / delete', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [34, 0, 'admin', 'authorization:menu:delete-by-id', 'authorization/menu', 'delete-by-id', '菜单管理 / deleteById', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [35, 0, 'admin', 'authorization:permission:tree', 'authorization/permission', 'tree', '权限资源 / tree', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [36, 0, 'admin', 'authorization:permission:detail', 'authorization/permission', 'detail', '权限资源 / detail', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [37, 0, 'admin', 'authorization:permission:create', 'authorization/permission', 'create', '权限资源 / create', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [38, 0, 'admin', 'authorization:permission:update', 'authorization/permission', 'update', '权限资源 / update', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [39, 0, 'admin', 'authorization:permission:delete', 'authorization/permission', 'delete', '权限资源 / delete', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [40, 0, 'admin', 'authorization:permission:delete-by-id', 'authorization/permission', 'delete-by-id', '权限资源 / deleteById', 'route', 1, 0, 'system', 'authorization', $now, $now, null, 999],
            [41, 0, 'admin', 'system:dict:types', 'system/dict', 'types', '字典类型列表', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [42, 0, 'admin', 'system:dict:items', 'system/dict', 'items', '字典项列表', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [43, 0, 'admin', 'system:dict:options', 'system/dict', 'options', '字典选项', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [44, 0, 'admin', 'system:dict:batch', 'system/dict', 'batch', '批量字典选项', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [45, 0, 'admin', 'system:dict:create-type', 'system/dict', 'create-type', '创建字典类型', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [46, 0, 'admin', 'system:dict:update-type', 'system/dict', 'update-type', '更新字典类型', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [47, 0, 'admin', 'system:dict:delete-type', 'system/dict', 'delete-type', '删除字典类型', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [48, 0, 'admin', 'system:dict:delete-types', 'system/dict', 'delete-types', '批量删除字典类型', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [49, 0, 'admin', 'system:dict:create-item', 'system/dict', 'create-item', '创建字典项', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [50, 0, 'admin', 'system:dict:update-item', 'system/dict', 'update-item', '更新字典项', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [51, 0, 'admin', 'system:dict:delete-item', 'system/dict', 'delete-item', '删除字典项', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [52, 0, 'admin', 'system:dict:delete-items', 'system/dict', 'delete-items', '批量删除字典项', 'route', 1, 0, 'system', 'dictionary', $now, $now, null, 999],
            [53, 0, 'admin', 'system:operation-log:index', 'system/operation-log', 'index', '操作日志 / index', 'route', 1, 0, 'system', 'operation_log', $now, $now, null, 999],
            [54, 0, 'admin', 'system:operation-log:detail', 'system/operation-log', 'detail', '操作日志 / detail', 'route', 1, 0, 'system', 'operation_log', $now, $now, null, 999],
            [55, 0, 'admin', 'system:operation-log:delete', 'system/operation-log', 'delete', '操作日志 / delete', 'route', 1, 0, 'system', 'operation_log', $now, $now, null, 999],
            [56, 0, 'admin', 'system:operation-log:delete-by-id', 'system/operation-log', 'delete-by-id', '操作日志 / deleteById', 'route', 1, 0, 'system', 'operation_log', $now, $now, null, 999],
            [57, 0, 'admin', 'system:language:index', 'system/language', 'index', '多语言 / index', 'route', 1, 0, 'system', 'language', $now, $now, null, 999],
            [58, 0, 'admin', 'system:language:detail', 'system/language', 'detail', '多语言 / detail', 'route', 1, 0, 'system', 'language', $now, $now, null, 999],
            [59, 0, 'admin', 'system:language:create', 'system/language', 'create', '多语言 / create', 'route', 1, 0, 'system', 'language', $now, $now, null, 999],
            [60, 0, 'admin', 'system:language:update', 'system/language', 'update', '多语言 / update', 'route', 1, 0, 'system', 'language', $now, $now, null, 999],
            [61, 0, 'admin', 'system:language:delete', 'system/language', 'delete', '多语言 / delete', 'route', 1, 0, 'system', 'language', $now, $now, null, 999],
            [62, 0, 'admin', 'system:language:delete-by-id', 'system/language', 'delete-by-id', '多语言 / deleteById', 'route', 1, 0, 'system', 'language', $now, $now, null, 999],
            [63, 0, 'admin', 'system:language:pack', 'system/language', 'pack', '多语言 / pack', 'route', 1, 1, 'system', 'language', $now, $now, null, 999],
            [64, 0, 'admin', 'system:language:namespaces', 'system/language', 'namespaces', '多语言 / namespaces', 'route', 1, 0, 'system', 'language', $now, $now, null, 999],
            [65, 0, 'admin', 'system:language:lines', 'system/language', 'lines', '多语言 / lines', 'route', 1, 0, 'system', 'language', $now, $now, null, 999],
            [66, 0, 'admin', 'system:language:save-line', 'system/language', 'save-line', '多语言 / saveLine', 'route', 1, 0, 'system', 'language', $now, $now, null, 999],
            [67, 0, 'admin', 'system:language:remove-line', 'system/language', 'remove-line', '多语言 / removeLine', 'route', 1, 0, 'system', 'language', $now, $now, null, 999],
            [68, 0, 'admin', 'system:config:index', 'system/config', 'index', '配置 / index', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [69, 0, 'admin', 'system:config:detail', 'system/config', 'detail', '配置 / detail', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [70, 0, 'admin', 'system:config:options', 'system/config', 'options', '配置 / options', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [71, 0, 'admin', 'system:config:create', 'system/config', 'create', '配置 / create', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [72, 0, 'admin', 'system:config:update', 'system/config', 'update', '配置 / update', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [73, 0, 'admin', 'system:config:value', 'system/config', 'value', '配置 / value', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [74, 0, 'admin', 'system:config:status', 'system/config', 'status', '配置 / status', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [75, 0, 'admin', 'system:config:delete', 'system/config', 'delete', '配置 / delete', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [76, 0, 'admin', 'system:config:groups', 'system/config', 'groups', '配置 / groups', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [77, 0, 'admin', 'system:config:create-group', 'system/config', 'create-group', '配置 / createGroup', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [78, 0, 'admin', 'system:config:update-group', 'system/config', 'update-group', '配置 / updateGroup', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [79, 0, 'admin', 'system:config:delete-group', 'system/config', 'delete-group', '配置 / deleteGroup', 'route', 1, 0, 'system', 'config', $now, $now, null, 999],
            [80, 0, 'admin', 'system:blacklist:index', 'system/blacklist', 'index', '黑名单 / index', 'route', 1, 0, 'system', 'blacklist', $now, $now, null, 999],
            [81, 0, 'admin', 'system:blacklist:detail', 'system/blacklist', 'detail', '黑名单 / detail', 'route', 1, 0, 'system', 'blacklist', $now, $now, null, 999],
            [82, 0, 'admin', 'system:blacklist:create', 'system/blacklist', 'create', '黑名单 / create', 'route', 1, 0, 'system', 'blacklist', $now, $now, null, 999],
            [83, 0, 'admin', 'system:blacklist:update', 'system/blacklist', 'update', '黑名单 / update', 'route', 1, 0, 'system', 'blacklist', $now, $now, null, 999],
            [84, 0, 'admin', 'system:blacklist:status', 'system/blacklist', 'status', '黑名单 / status', 'route', 1, 0, 'system', 'blacklist', $now, $now, null, 999],
            [85, 0, 'admin', 'system:blacklist:delete', 'system/blacklist', 'delete', '黑名单 / delete', 'route', 1, 0, 'system', 'blacklist', $now, $now, null, 999],
            [86, 0, 'admin', 'system:blacklist:restore', 'system/blacklist', 'restore', '黑名单 / restore', 'route', 1, 0, 'system', 'blacklist', $now, $now, null, 999],
            [87, 0, 'admin', 'system:blacklist:destroy', 'system/blacklist', 'destroy', '黑名单 / destroy', 'route', 1, 0, 'system', 'blacklist', $now, $now, null, 999],
            [88, 0, 'admin', 'system:blacklist:import', 'system/blacklist', 'import', '黑名单 / import', 'route', 1, 0, 'system', 'blacklist', $now, $now, null, 999],
            [89, 0, 'admin', 'system:blacklist:export', 'system/blacklist', 'export', '黑名单 / export', 'route', 1, 0, 'system', 'blacklist', $now, $now, null, 999],
            [90, 0, 'admin', 'system:attachment:index', 'system/attachment', 'index', '附件 / index', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [91, 0, 'admin', 'system:attachment:detail', 'system/attachment', 'detail', '附件 / detail', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [92, 0, 'admin', 'system:attachment:rename', 'system/attachment', 'rename', '附件 / rename', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [93, 0, 'admin', 'system:attachment:move', 'system/attachment', 'move', '附件 / move', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [94, 0, 'admin', 'system:attachment:delete', 'system/attachment', 'delete', '附件 / delete', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [95, 0, 'admin', 'system:attachment-group:tree', 'system/attachment-group', 'tree', '附件 / tree', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [96, 0, 'admin', 'system:attachment-group:create', 'system/attachment-group', 'create', '附件 / create', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [97, 0, 'admin', 'system:attachment-group:update', 'system/attachment-group', 'update', '附件 / update', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [98, 0, 'admin', 'systemattachmentgroup:deletegroup', 'admin/systemattachmentgroup', 'deletegroup', '附件 / deleteGroup', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [99, 0, 'admin', 'system:storage:index', 'system/storage', 'index', '附件 / index', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [100, 0, 'admin', 'system:storage:update', 'system/storage', 'update', '附件 / update', 'route', 1, 0, 'system', 'attachment', $now, $now, null, 999],
            [101, 0, 'admin', 'system:member:index', 'system/member', 'index', '会员 / index', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [102, 0, 'admin', 'system:member:detail', 'system/member', 'detail', '会员 / detail', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [103, 0, 'admin', 'system:member:options', 'system/member', 'options', '会员 / options', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [104, 0, 'admin', 'system:member:create', 'system/member', 'create', '会员 / create', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [105, 0, 'admin', 'system:member:update', 'system/member', 'update', '会员 / update', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [106, 0, 'admin', 'system:member:status', 'system/member', 'status', '会员 / status', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [107, 0, 'admin', 'systemmember:delete', 'admin/systemmember', 'delete', '会员 / delete', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [108, 0, 'admin', 'system:member:restore', 'system/member', 'restore', '会员 / restore', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [109, 0, 'admin', 'system:member:destroy', 'system/member', 'destroy', '会员 / destroy', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [110, 0, 'admin', 'system:member:import', 'system/member', 'import', '会员 / import', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [111, 0, 'admin', 'system:member:export', 'system/member', 'export', '会员 / export', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [112, 0, 'admin', 'system:member-group:index', 'system/member-group', 'index', '会员 / index', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [113, 0, 'admin', 'system:member-group:detail', 'system/member-group', 'detail', '会员 / detail', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [114, 0, 'admin', 'system:member-group:create', 'system/member-group', 'create', '会员 / create', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [115, 0, 'admin', 'system:member-group:update', 'system/member-group', 'update', '会员 / update', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [116, 0, 'admin', 'system:member-group:status', 'system/member-group', 'status', '会员 / status', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [117, 0, 'admin', 'systemmembergroup:delete', 'admin/systemmembergroup', 'delete', '会员 / delete', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [118, 0, 'admin', 'system:member-group:restore', 'system/member-group', 'restore', '会员 / restore', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [119, 0, 'admin', 'system:member-group:destroy', 'system/member-group', 'destroy', '会员 / destroy', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [120, 0, 'admin', 'system:member-group:import', 'system/member-group', 'import', '会员 / import', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [121, 0, 'admin', 'system:member-group:export', 'system/member-group', 'export', '会员 / export', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [122, 0, 'admin', 'system:member-level:index', 'system/member-level', 'index', '会员 / index', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [123, 0, 'admin', 'system:member-level:detail', 'system/member-level', 'detail', '会员 / detail', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [124, 0, 'admin', 'system:member-level:create', 'system/member-level', 'create', '会员 / create', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [125, 0, 'admin', 'system:member-level:update', 'system/member-level', 'update', '会员 / update', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [126, 0, 'admin', 'system:member-level:status', 'system/member-level', 'status', '会员 / status', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [127, 0, 'admin', 'systemmemberlevel:delete', 'admin/systemmemberlevel', 'delete', '会员 / delete', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [128, 0, 'admin', 'system:member-level:restore', 'system/member-level', 'restore', '会员 / restore', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [129, 0, 'admin', 'system:member-level:destroy', 'system/member-level', 'destroy', '会员 / destroy', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [130, 0, 'admin', 'system:member-level:import', 'system/member-level', 'import', '会员 / import', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [131, 0, 'admin', 'system:member-level:export', 'system/member-level', 'export', '会员 / export', 'route', 1, 0, 'system', 'member', $now, $now, null, 999],
            [132, 0, 'admin', 'system:upgrade:status', 'system/upgrade', 'status', '系统升级 / status', 'route', 1, 0, 'system', 'system_upgrade', $now, $now, null, 999],
            [133, 0, 'admin', 'system:upgrade:check', 'system/upgrade', 'check', '系统升级 / check', 'route', 1, 0, 'system', 'system_upgrade', $now, $now, null, 999],
            [134, 0, 'admin', 'systemupgrade:execute', 'admin/systemupgrade', 'execute', '系统升级 / execute', 'route', 1, 0, 'system', 'system_upgrade', $now, $now, null, 999],
            [135, 0, 'admin', 'system:upgrade:upload', 'system/upgrade', 'upload', '系统升级 / upload', 'route', 1, 0, 'system', 'system_upgrade', $now, $now, null, 999],
            [136, 0, 'admin', 'system:upgrade:restore', 'system/upgrade', 'restore', '系统升级 / restore', 'route', 1, 0, 'system', 'system_upgrade', $now, $now, null, 999],
            [137, 0, 'admin', 'system:upgrade:recover-stale', 'system/upgrade', 'recover-stale', '系统升级 / recoverStale', 'route', 1, 0, 'system', 'system_upgrade', $now, $now, null, 999],
            [138, 0, 'admin', 'systemplugin:installed', 'admin/systemplugin', 'installed', '插件中心 / installed', 'route', 1, 0, 'system', 'plugin_center', $now, $now, null, 999],
            [139, 0, 'admin', 'systemplugin:discovered', 'admin/systemplugin', 'discovered', '插件中心 / discovered', 'route', 1, 0, 'system', 'plugin_center', $now, $now, null, 999],
            [140, 0, 'admin', 'systemplugin:localdetail', 'admin/systemplugin', 'localdetail', '插件中心 / localdetail', 'route', 1, 0, 'system', 'plugin_center', $now, $now, null, 999],
            [141, 0, 'admin', 'systemplugin:enabledmodules', 'admin/systemplugin', 'enabledmodules', '插件中心 / enabledmodules', 'route', 1, 0, 'system', 'plugin_center', $now, $now, null, 999],
            [142, 0, 'admin', 'plugin:plugin:installed', 'plugin/plugin', 'installed', '插件中心 / installed', 'route', 1, 0, 'system', 'plugin_center', $now, $now, null, 999],
            [143, 0, 'admin', 'plugin:plugin:discovered', 'plugin/plugin', 'discovered', '插件中心 / discovered', 'route', 1, 0, 'system', 'plugin_center', $now, $now, null, 999],
            [144, 0, 'admin', 'plugin:plugin:local-detail', 'plugin/plugin', 'local-detail', '插件中心 / localdetail', 'route', 1, 0, 'system', 'plugin_center', $now, $now, null, 999],
            [145, 0, 'admin', 'plugin.systemplugin:enabledmodules', 'admin/plugin.systemplugin', 'enabledmodules', '插件中心 / enabledmodules', 'route', 1, 0, 'system', 'plugin_center', $now, $now, null, 999],
            [156, 0, 'admin', 'example:test:detail', 'example/test', 'detail', '示例插件 / test:detail', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [157, 0, 'admin', 'example:test:create', 'example/test', 'create', '示例插件 / test:create', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [158, 0, 'admin', 'example:test:update', 'example/test', 'update', '示例插件 / test:update', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [160, 0, 'admin', 'example:test:restore', 'example/test', 'restore', '示例插件 / test:restore', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [161, 0, 'admin', 'example:test:destroy', 'example/test', 'destroy', '示例插件 / test:destroy', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [165, 0, 'admin', 'example:test:import', 'example/test', 'import', '示例插件 / test:import', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [166, 0, 'admin', 'example:test:export', 'example/test', 'export', '示例插件 / test:export', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [167, 0, 'admin', 'cms:access:assign', 'cms/access', 'assign', 'cms:access:assign', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [168, 0, 'admin', 'cms:access:candidates', 'cms/access', 'candidates', 'cms:access:candidates', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [169, 0, 'admin', 'cms:access:index', 'cms/access', 'index', 'cms:access:index', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [170, 0, 'admin', 'cms:access:remove', 'cms/access', 'remove', 'cms:access:remove', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [171, 0, 'admin', 'cms:access:save-role', 'cms/access', 'save-role', 'cms:access:save-role', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [172, 0, 'admin', 'cms:categories:index', 'cms/categories', 'index', 'cms:categories:index', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [173, 0, 'admin', 'cms:categories:save', 'cms/categories', 'save', 'cms:categories:save', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [174, 0, 'admin', 'cms:categories:status', 'cms/categories', 'status', 'cms:categories:status', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [175, 0, 'admin', 'cms:content:attachments', 'cms/content', 'attachments', 'cms:content:attachments', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [176, 0, 'admin', 'cms:content:detail', 'cms/content', 'detail', 'cms:content:detail', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [177, 0, 'admin', 'cms:content:index', 'cms/content', 'index', 'cms:content:index', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [178, 0, 'admin', 'cms:content:lookups', 'cms/content', 'lookups', 'cms:content:lookups', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [179, 0, 'admin', 'cms:content:offline', 'cms/content', 'offline', 'cms:content:offline', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [180, 0, 'admin', 'cms:content:preview', 'cms/content', 'preview', 'cms:content:preview', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [181, 0, 'admin', 'cms:content:publish', 'cms/content', 'publish', 'cms:content:publish', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [182, 0, 'admin', 'cms:content:revisions', 'cms/content', 'revisions', 'cms:content:revisions', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [183, 0, 'admin', 'cms:content:save', 'cms/content', 'save', 'cms:content:save', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [184, 0, 'admin', 'cms:content:translation', 'cms/content', 'translation', 'cms:content:translation', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [185, 0, 'admin', 'cms:content:upload', 'cms/content', 'upload', 'cms:content:upload', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [186, 0, 'admin', 'cms:contributions:index', 'cms/contributions', 'index', 'cms:contributions:index', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [187, 0, 'admin', 'cms:contributions:review', 'cms/contributions', 'review', 'cms:contributions:review', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [188, 0, 'admin', 'cms:contributions:save-settings', 'cms/contributions', 'save-settings', 'cms:contributions:save-settings', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [189, 0, 'admin', 'cms:contributions:settings', 'cms/contributions', 'settings', 'cms:contributions:settings', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [190, 0, 'admin', 'cms:menus:index', 'cms/menus', 'index', 'cms:menus:index', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [191, 0, 'admin', 'cms:menus:remove', 'cms/menus', 'remove', 'cms:menus:remove', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [192, 0, 'admin', 'cms:menus:save', 'cms/menus', 'save', 'cms:menus:save', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [193, 0, 'admin', 'cms:menus:status', 'cms/menus', 'status', 'cms:menus:status', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [194, 0, 'admin', 'cms:menus:targets', 'cms/menus', 'targets', 'cms:menus:targets', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [195, 0, 'admin', 'cms:models:bindings', 'cms/models', 'bindings', 'cms:models:bindings', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [196, 0, 'admin', 'cms:models:index', 'cms/models', 'index', 'cms:models:index', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [197, 0, 'admin', 'cms:models:save', 'cms/models', 'save', 'cms:models:save', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [198, 0, 'admin', 'cms:sites:configuration', 'cms/sites', 'configuration', 'cms:sites:configuration', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [199, 0, 'admin', 'cms:sites:configuration-save', 'cms/sites', 'configuration-save', 'cms:sites:configuration-save', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [200, 0, 'admin', 'cms:sites:context', 'cms/sites', 'context', 'cms:sites:context', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [201, 0, 'admin', 'cms:sites:domain-save', 'cms/sites', 'domain-save', 'cms:sites:domain-save', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [202, 0, 'admin', 'cms:sites:grant', 'cms/sites', 'grant', 'cms:sites:grant', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [203, 0, 'admin', 'cms:sites:index', 'cms/sites', 'index', 'cms:sites:index', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [204, 0, 'admin', 'cms:sites:locale-save', 'cms/sites', 'locale-save', 'cms:sites:locale-save', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [205, 0, 'admin', 'cms:sites:model-save', 'cms/sites', 'model-save', 'cms:sites:model-save', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [206, 0, 'admin', 'cms:sites:options', 'cms/sites', 'options', 'cms:sites:options', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [207, 0, 'admin', 'cms:sites:save', 'cms/sites', 'save', 'cms:sites:save', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [208, 0, 'admin', 'cms:sites:settings', 'cms/sites', 'settings', 'cms:sites:settings', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [209, 0, 'admin', 'cms:tags:index', 'cms/tags', 'index', 'cms:tags:index', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [210, 0, 'admin', 'cms:tags:options', 'cms/tags', 'options', 'cms:tags:options', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [211, 0, 'admin', 'cms:tags:preview', 'cms/tags', 'preview', 'cms:tags:preview', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [212, 0, 'admin', 'cms:tags:providers', 'cms/tags', 'providers', 'cms:tags:providers', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [213, 0, 'admin', 'cms:tags:publish', 'cms/tags', 'publish', 'cms:tags:publish', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [214, 0, 'admin', 'cms:tags:save', 'cms/tags', 'save', 'cms:tags:save', 'route', 1, 0, 'plugin', 'cms', $now, $now, null, 999],
            [215, 0, 'admin', 'example:index:index', 'example/index', 'index', '返回示例插件后台入口响应', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [216, 0, 'admin', 'example:test:destroy-many', 'example/test', 'destroy-many', '批量永久删除示例插件测试记录，删除后不可通过回收站恢复', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [217, 0, 'admin', 'example:test:index', 'example/test', 'index', '获取示例插件测试记录列表', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [218, 0, 'admin', 'example:test:recycle', 'example/test', 'recycle', '将选中的示例插件测试记录移入回收站', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [219, 0, 'admin', 'example:test:remove', 'example/test', 'remove', '删除指定示例插件测试记录', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [220, 0, 'admin', 'example:test:restore-many', 'example/test', 'restore-many', '批量恢复回收站中的示例插件测试记录', 'route', 1, 0, 'plugin', 'example', $now, $now, null, 999],
            [221, 0, 'admin', 'qa:moderation:index', 'qa/moderation', 'index', 'qa:moderation:index', 'route', 1, 0, 'plugin', 'qa', $now, $now, null, 999],
            [222, 0, 'admin', 'qa:moderation:review', 'qa/moderation', 'review', 'qa:moderation:review', 'route', 1, 0, 'plugin', 'qa', $now, $now, null, 999],
            [223, 0, 'admin', 'system:operation-log:clear', 'system/operation-log', 'clear', '操作日志 / delete', 'route', 1, 0, 'system', 'operation_log', null, null, null, 999],
            [224, 0, 'admin', 'ai:ai:approval-decide', 'ai/ai', 'approval-decide', '校验审批上下文后批准或拒绝工具调用，并处理任务续跑与沙箱收尾', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [225, 0, 'admin', 'ai:ai:approval-index', 'ai/ai', 'approval-index', '获取当前管理员待处理的 AI 审批', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [226, 0, 'admin', 'ai:ai:attachment-content', 'ai/ai', 'attachment-content', '验证会话归属后返回私有附件内容，并设置禁止缓存响应头', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [227, 0, 'admin', 'ai:ai:attachment-create', 'ai/ai', 'attachment-create', '上传单个 multipart file 到当前会话的私有附件存储', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [228, 0, 'admin', 'ai:ai:attachment-delete', 'ai/ai', 'attachment-delete', '删除当前会话中的私有附件', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [229, 0, 'admin', 'ai:ai:change-set-apply', 'ai/ai', 'change-set-apply', '验证应用权限、最终审批及确认令牌后应用变更集', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [230, 0, 'admin', 'ai:ai:change-set-detail', 'ai/ai', 'change-set-detail', '读取当前管理员可见的变更集信息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [231, 0, 'admin', 'ai:ai:change-set-preview', 'ai/ai', 'change-set-preview', '检查预览权限后生成变更计划，并记录选择范围与计划摘要', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [232, 0, 'admin', 'ai:ai:change-set-recover', 'ai/ai', 'change-set-recover', '验证恢复权限及 recoveryVersion 后恢复变更集事务', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [233, 0, 'admin', 'ai:ai:conversation-create', 'ai/ai', 'conversation-create', '创建 AI 会话，并检查审批模式所需权限', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [234, 0, 'admin', 'ai:ai:conversation-delete', 'ai/ai', 'conversation-delete', '删除当前管理员所属的 AI 会话', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [235, 0, 'admin', 'ai:ai:conversation-group-create', 'ai/ai', 'conversation-group-create', '为当前管理员创建会话分组', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [236, 0, 'admin', 'ai:ai:conversation-group-delete', 'ai/ai', 'conversation-group-delete', '删除当前管理员的会话分组', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [237, 0, 'admin', 'ai:ai:conversation-group-index', 'ai/ai', 'conversation-group-index', '获取当前管理员的会话分组', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [238, 0, 'admin', 'ai:ai:conversation-group-update', 'ai/ai', 'conversation-group-update', '更新当前管理员的会话分组', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [239, 0, 'admin', 'ai:ai:conversation-index', 'ai/ai', 'conversation-index', '分页查询当前管理员的 AI 会话', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [240, 0, 'admin', 'ai:ai:conversation-read', 'ai/ai', 'conversation-read', '读取当前管理员所属的 AI 会话详情', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [241, 0, 'admin', 'ai:ai:conversation-state-update', 'ai/ai', 'conversation-state-update', '部分更新 AI 会话的展示状态', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [242, 0, 'admin', 'ai:ai:conversation-update', 'ai/ai', 'conversation-update', '更新 AI 会话配置，并检查审批模式所需权限', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [243, 0, 'admin', 'ai:ai:crud-proposal-apply', 'ai/ai', 'crud-proposal-apply', '检查提案应用权限及审批后执行 CRUD 生成提案', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [244, 0, 'admin', 'ai:ai:crud-proposal-preview', 'ai/ai', 'crud-proposal-preview', '验证会话和任务归属后预览 AI 提交的 CRUD 生成提案', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [245, 0, 'admin', 'ai:ai:event-stream', 'ai/ai', 'event-stream', '使用事件票据和游标读取任务事件，返回 SSE 格式响应', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [246, 0, 'admin', 'ai:ai:event-ticket', 'ai/ai', 'event-ticket', '验证任务归属后签发事件读取票据', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [247, 0, 'admin', 'ai:ai:message-create', 'ai/ai', 'message-create', '向指定会话追加用户消息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [248, 0, 'admin', 'ai:ai:message-index', 'ai/ai', 'message-index', '按游标读取指定会话的消息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [249, 0, 'admin', 'ai:ai:sandbox-cleanup', 'ai/ai', 'sandbox-cleanup', '清理当前管理员已结束且超过保留期的任务沙箱', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [250, 0, 'admin', 'ai:ai:sandbox-status', 'ai/ai', 'sandbox-status', '查询 AI 沙箱运行环境状态', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [251, 0, 'admin', 'ai:ai:settings-read', 'ai/ai', 'settings-read', '读取 AI 供应商设置及运行限制', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [252, 0, 'admin', 'ai:ai:settings-test', 'ai/ai', 'settings-test', '使用提交的临时供应商配置测试连接', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [253, 0, 'admin', 'ai:ai:task-cancel', 'ai/ai', 'task-cancel', '请求取消当前管理员所属的 AI 任务', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [254, 0, 'admin', 'ai:ai:task-execute', 'ai/ai', 'task-execute', '创建 AI 任务并投递至 ai-agent 队列', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [255, 0, 'admin', 'ai:ai:task-read', 'ai/ai', 'task-read', '获取当前管理员所属的 AI 任务详情', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [256, 0, 'admin', 'ai:ai:task-tool-calls', 'ai/ai', 'task-tool-calls', '验证任务归属后获取工具调用记录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [257, 0, 'admin', 'ai:ai:tool-call-log', 'ai/ai', 'tool-call-log', '验证任务归属及日志路径后读取 stdout 或 stderr 日志', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [258, 0, 'admin', 'ai:profiles:copy', 'ai/profiles', 'copy', '只接受新 name；复制配置，不复制密钥和默认状态', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [259, 0, 'admin', 'ai:profiles:create', 'ai/profiles', 'create', '创建，必填 name/provider/protocol/base_url/model', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [260, 0, 'admin', 'ai:profiles:default-read', 'ai/profiles', 'default-read', '返回默认档案，无默认时返回 null', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [261, 0, 'admin', 'ai:profiles:default-set', 'ai/profiles', 'default-set', '将指定档案设为唯一默认；返回档案，不改变任何 Job', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [262, 0, 'admin', 'ai:profiles:delete', 'ai/profiles', 'delete', '硬删除档案及密文；默认档案被删除后不自动选择其他档案', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [263, 0, 'admin', 'ai:profiles:index', 'ai/profiles', 'index', '返回当前管理员档案数组', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [264, 0, 'admin', 'ai:profiles:models', 'ai/profiles', 'models', '以保存档案查询真实模型 ID；不返回密钥或推测能力', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [265, 0, 'admin', 'ai:profiles:read', 'ai/profiles', 'read', '返回单个档案；跨管理员与不存在均为 404', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [266, 0, 'admin', 'ai:profiles:update', 'ai/profiles', 'update', '部分更新；api_key 缺省保留，空字符串清空，非空加密替换', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [267, 0, 'admin', 'authentication:admin-auth:csrf', 'authentication/admin-auth', 'csrf', '建立或读取浏览器会话，返回 CSRF 令牌并设置会话 Cookie', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [268, 0, 'admin', 'authentication:admin-auth:login', 'authentication/admin-auth', 'login', '验证账号、密码和 CSRF 令牌后建立后台浏览器登录会话', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [269, 0, 'admin', 'authentication:admin-auth:logout', 'authentication/admin-auth', 'logout', '验证 CSRF 令牌后注销浏览器会话，并清除登录 Cookie', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [270, 0, 'admin', 'authentication:admin-auth:me', 'authentication/admin-auth', 'me', '根据浏览器会话获取当前管理员及其权限信息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [271, 0, 'admin', 'authentication:admin-auth:menus', 'authentication/admin-auth', 'menus', '根据浏览器会话获取当前管理员可访问的菜单', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [272, 0, 'admin', 'authentication:admin-profile:index', 'authentication/admin-profile', 'index', '读取当前浏览器会话对应的管理员个人资料', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [273, 0, 'admin', 'authentication:admin-profile:password', 'authentication/admin-profile', 'password', '校验原密码后修改密码，撤销全部会话并清除浏览器登录 Cookie', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [274, 0, 'admin', 'authentication:admin-profile:update', 'authentication/admin-profile', 'update', '更新当前管理员的昵称、头像、邮箱及手机号', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [275, 0, 'admin', 'authentication:api-auth:login', 'authentication/api-auth', 'login', '验证管理员账号和密码，返回令牌登录信息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [276, 0, 'admin', 'authentication:api-auth:logout', 'authentication/api-auth', 'logout', '验证并注销当前请求携带的管理员令牌', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [277, 0, 'admin', 'authentication:api-auth:me', 'authentication/api-auth', 'me', '验证请求令牌，返回当前管理员身份信息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [278, 0, 'admin', 'development:business:adopt-resolved-baseline', 'development/business', 'adopt-resolved-baseline', '校验文件哈希后采纳已解决冲突的远端基线', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [279, 0, 'admin', 'development:business:compile-schema', 'development/business', 'compile-schema', '编译提交的 Schema，返回编译结果', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [280, 0, 'admin', 'development:business:create-from-database', 'development/business', 'create-from-database', '根据现有数据表创建业务模块', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [281, 0, 'admin', 'development:business:create-visual', 'development/business', 'create-visual', '创建可视化业务模块草稿', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [282, 0, 'admin', 'development:business:database-table-schema', 'development/business', 'database-table-schema', '读取指定数据表的结构元数据', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [283, 0, 'admin', 'development:business:database-tables', 'development/business', 'database-tables', '获取指定数据库连接中的数据表列表', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [284, 0, 'admin', 'development:business:delete-module', 'development/business', 'delete-module', '检查模块管理权限后删除业务模块', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [285, 0, 'admin', 'development:business:design-action-catalog', 'development/business', 'design-action-catalog', '获取当前管理员有权使用的设计器动作目录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [286, 0, 'admin', 'development:business:export-schema', 'development/business', 'export-schema', '导出业务模块的 Schema 定义', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [287, 0, 'admin', 'development:business:field-capabilities', 'development/business', 'field-capabilities', '获取字段能力、可用列表资源及其目录哈希', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [288, 0, 'admin', 'development:business:formal-generation', 'development/business', 'formal-generation', '检查代码生成及资源应用权限，确认预览令牌后执行正式生成', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [289, 0, 'admin', 'development:business:generation', 'development/business', 'generation', '读取代码生成记录详情', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [290, 0, 'admin', 'development:business:generations', 'development/business', 'generations', '按模块和状态分页查询代码生成记录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [291, 0, 'admin', 'development:business:inspect-database', 'development/business', 'inspect-database', '检查指定连接和数据表，返回数据库建模信息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [292, 0, 'admin', 'development:business:module', 'development/business', 'module', '读取业务模块详情', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [293, 0, 'admin', 'development:business:modules', 'development/business', 'modules', '查询业务模块；source 模式返回有权读取的来源候选', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [294, 0, 'admin', 'development:business:preview-formal-generation', 'development/business', 'preview-formal-generation', '预览正式代码生成计划，并传递生成权限与请求 nonce', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [295, 0, 'admin', 'development:business:preview-publish', 'development/business', 'preview-publish', '预览动态发布计划，返回待确认的发布信息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [296, 0, 'admin', 'development:business:publish', 'development/business', 'publish', '执行业务模块动态发布', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [297, 0, 'admin', 'development:business:recover-generation', 'development/business', 'recover-generation', '检查恢复权限及预期恢复状态后恢复生成事务', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [298, 0, 'admin', 'development:business:retry-resources', 'development/business', 'retry-resources', '检查资源应用权限后重试生成记录的资源发布', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [299, 0, 'admin', 'development:business:rollback-schema', 'development/business', 'rollback-schema', '将指定历史 Schema 作为新版本保存，并检查当前版本哈希', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [300, 0, 'admin', 'development:business:runtime-meta', 'development/business', 'runtime-meta', '读取业务模块的运行元数据', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [301, 0, 'admin', 'development:business:save-publish-config', 'development/business', 'save-publish-config', '保存业务模块的发布配置', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [302, 0, 'admin', 'development:business:save-schema', 'development/business', 'save-schema', '校验 expectedSchemaHash 后保存新的 Schema 版本', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [303, 0, 'admin', 'development:business:schema-diff', 'development/business', 'schema-diff', '比较 fromVersion 与 toVersion 的 Schema 差异', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [304, 0, 'admin', 'development:business:schema-version', 'development/business', 'schema-version', '读取指定 Schema 历史版本', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [305, 0, 'admin', 'development:business:schema-versions', 'development/business', 'schema-versions', '获取业务模块的 Schema 版本列表', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [306, 0, 'admin', 'development:business:targets', 'development/business', 'targets', '获取业务生成可选的宿主与插件目标', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [307, 0, 'admin', 'development:business:validate-schema', 'development/business', 'validate-schema', '校验提交的表单 Schema，返回校验结果', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [308, 0, 'admin', 'form:data:action', 'form/data', 'action', '执行注册的表单动作，传递幂等键及动作调用深度', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [309, 0, 'admin', 'form:data:batch-remove', 'form/data', 'batch-remove', '批量删除请求体 ids 指定的表单记录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [310, 0, 'admin', 'form:data:create', 'form/data', 'create', '依据表单定义新增记录，并校验提交的 Schema 版本', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [311, 0, 'admin', 'form:data:destroy', 'form/data', 'destroy', '批量永久删除表单记录，删除后不可通过回收站恢复', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [312, 0, 'admin', 'form:data:detail', 'form/data', 'detail', '读取表单记录详情；copyCreate 模式返回用于复制新增的数据', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [313, 0, 'admin', 'form:data:export', 'form/data', 'export', '按当前筛选条件获取表单导出数据', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [314, 0, 'admin', 'form:data:import', 'form/data', 'import', '校验 rows 数组及 Schema 版本后导入表单记录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [315, 0, 'admin', 'form:data:index', 'form/data', 'index', '按筛选、排序、分页和 normal/recycle 范围读取表单记录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [316, 0, 'admin', 'form:data:left-tree', 'form/data', 'left-tree', '读取当前表单的左侧分类树', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [317, 0, 'admin', 'form:data:left-tree-form', 'form/data', 'left-tree-form', '读取分类节点新增、添加子节点或编辑所需的表单定义', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [318, 0, 'admin', 'form:data:list-action', 'form/data', 'list-action', '执行列表按钮动作，并隐藏审计日志中的输入载荷', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [319, 0, 'admin', 'form:data:list-actions', 'form/data', 'list-actions', '获取指定按钮位置可用的列表动作目录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [320, 0, 'admin', 'form:data:meta', 'form/data', 'meta', '读取表单运行元数据；source 模式读取来源定义，普通模式支持 ETag 缓存校验', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [321, 0, 'admin', 'form:data:mutate-left-tree', 'form/data', 'mutate-left-tree', '新增、修改或删除分类节点，并对审计日志中的业务数据脱敏', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [322, 0, 'admin', 'form:data:options', 'form/data', 'options', '查询字段选项；draft 模式允许读取设计器草稿定义', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [323, 0, 'admin', 'form:data:remove', 'form/data', 'remove', '删除请求体 id 指定的表单记录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [324, 0, 'admin', 'form:data:restore', 'form/data', 'restore', '批量恢复表单回收站中的记录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [325, 0, 'admin', 'form:data:sub', 'form/data', 'sub', '分页读取指定主记录的关联子表数据', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [326, 0, 'admin', 'form:data:update', 'form/data', 'update', '依据表单定义更新记录，并校验提交的 Schema 版本', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [327, 0, 'admin', 'form:data:validate-async', 'form/data', 'validate-async', '调用指定字段的异步校验器，校验当前值及关联字段值', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [328, 0, 'admin', 'plugin:dev-plugin:create', 'plugin/dev-plugin', 'create', '按提交的模块选项创建插件骨架', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [329, 0, 'admin', 'plugin:dev-plugin:options', 'plugin/dev-plugin', 'options', '获取插件开发工具可选配置', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [330, 0, 'admin', 'plugin:dev-plugin:package', 'plugin/dev-plugin', 'package', '将指定插件打包到输出目录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [331, 0, 'admin', 'plugin:dev-plugin:package-download', 'plugin/dev-plugin', 'package-download', '下载指定插件生成的安装包', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [332, 0, 'admin', 'plugin:dev-plugin:preview-create', 'plugin/dev-plugin', 'preview-create', '预览插件脚手架创建计划', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [333, 0, 'admin', 'plugin:dev-plugin:validate', 'plugin/dev-plugin', 'validate', '校验指定插件的清单与目录结构', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [334, 0, 'admin', 'plugin:plugin:account-login', 'plugin/plugin', 'account-login', '登录插件市场云账号，返回账号会话信息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [335, 0, 'admin', 'plugin:plugin:account-logout', 'plugin/plugin', 'account-logout', '退出插件市场云账号', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [336, 0, 'admin', 'plugin:plugin:account-refresh', 'plugin/plugin', 'account-refresh', '刷新插件市场云账号令牌', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [337, 0, 'admin', 'plugin:plugin:check-updates', 'plugin/plugin', 'check-updates', '检查已安装插件的可用更新', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [338, 0, 'admin', 'plugin:plugin:current-account', 'plugin/plugin', 'current-account', '获取当前插件市场云账号信息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [339, 0, 'admin', 'plugin:plugin:delete-package', 'plugin/plugin', 'delete-package', '删除指定插件的本地包', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [340, 0, 'admin', 'plugin:plugin:disable', 'plugin/plugin', 'disable', '禁用插件并更新激活状态', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [341, 0, 'admin', 'plugin:plugin:download-history', 'plugin/plugin', 'download-history', '下载指定插件的历史版本包', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [342, 0, 'admin', 'plugin:plugin:enable', 'plugin/plugin', 'enable', '启用插件并更新激活状态', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [343, 0, 'admin', 'plugin:plugin:enabled-modules', 'plugin/plugin', 'enabled-modules', '获取当前管理员可访问的已启用插件前端模块', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [344, 0, 'admin', 'plugin:plugin:get-config', 'plugin/plugin', 'get-config', '读取指定插件的配置', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [345, 0, 'admin', 'plugin:plugin:history', 'plugin/plugin', 'history', '获取插件版本历史', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [346, 0, 'admin', 'plugin:plugin:install-cloud', 'plugin/plugin', 'install-cloud', '下载并安装指定市场插件', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [347, 0, 'admin', 'plugin:plugin:install-discovered', 'plugin/plugin', 'install-discovered', '安装已经发现的本地插件', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [348, 0, 'admin', 'plugin:plugin:install-local', 'plugin/plugin', 'install-local', '接收本地插件 ZIP 包并执行安装', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [349, 0, 'admin', 'plugin:plugin:market-categories', 'plugin/plugin', 'market-categories', '获取插件市场分类', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [350, 0, 'admin', 'plugin:plugin:market-detail', 'plugin/plugin', 'market-detail', '读取插件市场中指定插件的详情', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [351, 0, 'admin', 'plugin:plugin:market-search', 'plugin/plugin', 'market-search', '按查询条件搜索插件市场', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [352, 0, 'admin', 'plugin:plugin:market-versions', 'plugin/plugin', 'market-versions', '获取指定市场插件的可用版本', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [353, 0, 'admin', 'plugin:plugin:migrate', 'plugin/plugin', 'migrate', '执行指定插件的数据库迁移', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [354, 0, 'admin', 'plugin:plugin:operations', 'plugin/plugin', 'operations', '获取插件生命周期操作记录', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [355, 0, 'admin', 'plugin:plugin:purge', 'plugin/plugin', 'purge', '执行插件显式业务数据清理操作', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [356, 0, 'admin', 'plugin:plugin:recovery-info', 'plugin/plugin', 'recovery-info', '读取插件操作失败后的恢复信息', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [357, 0, 'admin', 'plugin:plugin:redeploy-history', 'plugin/plugin', 'redeploy-history', '重新部署指定插件历史版本', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [358, 0, 'admin', 'plugin:plugin:save-config', 'plugin/plugin', 'save-config', '保存指定插件的配置', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [359, 0, 'admin', 'plugin:plugin:uninstall', 'plugin/plugin', 'uninstall', '卸载插件，执行插件生命周期卸载流程', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [360, 0, 'admin', 'plugin:plugin:update', 'plugin/plugin', 'update', '更新指定插件', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [361, 0, 'admin', 'plugin:plugin:update-local', 'plugin/plugin', 'update-local', '接收本地插件 ZIP 包并更新指定插件', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [362, 0, 'admin', 'system:admin-upload:upload', 'system/admin-upload', 'upload', '处理通用文件上传，返回附件信息并执行上传结果扩展钩子', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [363, 0, 'admin', 'system:attachment-group:delete', 'system/attachment-group', 'delete', '删除指定附件分组', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [364, 0, 'admin', 'system:attachment-group:detail', 'system/attachment-group', 'detail', '获取指定附件分组的详情', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [365, 0, 'admin', 'system:member-group:recycle', 'system/member-group', 'recycle', '将选中的会员组移入回收站', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [366, 0, 'admin', 'system:member-level:recycle', 'system/member-level', 'recycle', '将选中的会员等级移入回收站', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [367, 0, 'admin', 'system:member:recycle', 'system/member', 'recycle', '将选中的会员移入回收站', 'route', 1, 0, 'system', '', $now, $now, null, 999],
            [368, 0, 'admin', 'system:upgrade:execute-upgrade', 'system/upgrade', 'execute-upgrade', '根据请求参数执行系统升级', 'route', 1, 0, 'system', '', $now, $now, null, 999],
        ]],
    ] as $tableName => [$columns, $rows]) {
        if (Db::table($tableName)->exists()) continue;
        $records = array_map(static fn (array $row): array => array_combine($columns, $row), $rows);
        foreach (array_chunk($records, 40) as $chunk) Db::table($tableName)->insert($chunk);
    }

    // 按当前代码补登记权限：插件与宿主控制器新增的方法在全新安装时也会出现（对上面的种子是幂等的）。
    $sync = new PermissionSyncService();
    foreach (glob(app_path() . '/admin/plugins/*', GLOB_ONLYDIR) ?: [] as $directory) $sync->sync(basename($directory));
    $sync->sync();
};
