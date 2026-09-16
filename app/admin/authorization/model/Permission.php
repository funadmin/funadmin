<?php

namespace app\admin\authorization\model;

use app\admin\model\BackendModel;
use app\admin\traits\AdminDataFormat;

class Permission extends BackendModel
{
    use AdminDataFormat;

    protected $name = 'permission';

    public const TYPE_GROUP = 'group';
    public const TYPE_ROUTE = 'route';
    public const TYPE_CAPABILITY = 'capability';

    public static function childIds(int $id): array
    {
        $result = [];
        $queue = [$id];
        while ($queue) {
            $children = self::whereIn('pid', $queue)->column('id');
            $queue = [];
            foreach ($children as $childId) {
                $childId = (int) $childId;
                if (!in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $result;
    }

    public static function validateAttributes(array $data): ?string
    {
        if ($data['name'] === '') {
            return '权限资源名称不能为空';
        }
        if ($data['app_name'] === '' || !preg_match('/^[a-z][a-z0-9_]{0,49}$/', $data['app_name'])) {
            return '应用标识格式不正确';
        }
        if (!in_array($data['resource_type'], [self::TYPE_GROUP, self::TYPE_ROUTE, self::TYPE_CAPABILITY], true)) {
            return '权限资源类型不正确';
        }
        if (in_array($data['resource_type'], [self::TYPE_ROUTE, self::TYPE_CAPABILITY], true)
            && ($data['obj'] === '' || $data['act'] === '')) {
            return '路由或能力资源必须填写资源对象和动作';
        }
        if ($data['resource_type'] === self::TYPE_GROUP && ($data['obj'] !== '' || $data['act'] !== '' || $data['code'] !== null)) {
            return '目录资源不能包含控制器或动作';
        }
        return null;
    }

    /**
     * 权限资源管理树行数据；generated/plugin 来源由后端强制只读。
     */
    public function toApiData(): array
    {
        $object = (string) $this->obj;
        $appNamePrefix = strtolower((string) $this->app_name) . '/';
        if ($object !== '' && str_starts_with(strtolower($object), $appNamePrefix)) {
            $object = substr($object, strlen($appNamePrefix));
        }
        return [
            'id' => (int) $this->id,
            'parentId' => (int) $this->pid,
            'appName' => (string) $this->app_name,
            'code' => (string) ($this->code ?? ''),
            'object' => $object,
            'action' => (string) $this->act,
            'name' => (string) $this->getAttr('name'),
            'resourceType' => (string) $this->resource_type,
            'status' => (int) $this->status,
            'isPublic' => (int) $this->is_public,
            'sort' => (int) $this->sort_order,
            'sourceType' => (string) $this->source_type,
            'sourceName' => (string) $this->source_name,
            'readOnly' => in_array((string) $this->source_type, ['generated', 'plugin'], true),
            'createdAt' => $this->formatTime($this->created_at),
            'updatedAt' => $this->formatTime($this->updated_at),
        ];
    }

    /**
     * 菜单编辑器权限选项行数据：只暴露绑定所需的稳定字段。
     */
    public function toOptionData(): array
    {
        return [
            'id' => (int) $this->id,
            'parentId' => (int) $this->pid,
            'appName' => (string) $this->app_name,
            'code' => (string) $this->code,
            'object' => (string) $this->obj,
            'action' => (string) $this->act,
            'name' => (string) $this->getAttr('name'),
            'resourceType' => (string) $this->resource_type,
            'status' => (int) $this->status,
            'isPublic' => (int) $this->is_public,
            'sort' => (int) $this->sort_order,
            'sourceType' => (string) $this->source_type,
            'sourceName' => (string) $this->source_name,
        ];
    }

    /**
     * 登录下发权限集：保留独立动作码，并按映射补充前端业务别名；合并别名不扩大授权。
     */
    public static function webPermissions(array $permissionCodes, bool $isSuperAdmin): array
    {
        $mapping = [
            'development.business:modules' => 'development:business:view',
            'development.business:module' => 'development:business:view',
            'development.business:createvisual' => 'development:business:save',
            'development.business:inspectdatabase' => 'development:business:inspect',
            'development.business:createfromdatabase' => 'development:business:save',
            'development.business:validateschema' => 'development:business:save',
            'development.business:saveschema' => 'development:business:save',
            'development.business:compileschema' => 'development:business:save',
            'development.business:exportschema' => 'development:business:view',
            'development.business:schemaversions' => 'development:business:view',
            'development.business:schemaversion' => 'development:business:view',
            'development.business:schemadiff' => 'development:business:view',
            'development.business:rollbackschema' => 'development:business:save',
            'development.business:databasetables' => 'development:business:inspect',
            'development.business:databasetableschema' => 'development:business:inspect',
            'development.business:previewpublish' => 'development:business:publish',
            'development.business:publish' => 'development:business:publish',
            'development.business:runtimemeta' => 'development:business:view',
            'development.business:previewformalgeneration' => 'development:business:generate',
            'development.business:formalgeneration' => 'development:business:generate',
            'development.business:generations' => 'development:business:records',
            'development.business:generation' => 'development:business:records',
            'development.business:recovergeneration' => 'development:business:recover',
            'development.business:retryresources' => 'development:business:apply-resources',
            'development.business:adoptresolvedbaseline' => 'development:business:save',
            'development.business:fieldcapabilities' => 'development:business:view',
            'systemdict:types' => 'system:dict:list',
            'systemdict:items' => 'system:dict:list',
            'systemdict:options' => 'system:dict:list',
            'systemdict:batch' => 'system:dict:list',
            'systemdict:createtype' => 'system:dict:add',
            'systemdict:createitem' => 'system:dict:add',
            'systemdict:updatetype' => 'system:dict:edit',
            'systemdict:updateitem' => 'system:dict:edit',
            'systemdict:deletetype' => 'system:dict:delete',
            'systemdict:deletetypes' => 'system:dict:delete',
            'systemdict:deleteitem' => 'system:dict:delete',
            'systemdict:deleteitems' => 'system:dict:delete',
            'systemrole:index' => 'system:role:list',
            'systemrole:detail' => 'system:role:list',
            'systemrole:create' => 'system:role:add',
            'systemrole:update' => 'system:role:edit',
            'systemrole:delete' => 'system:role:delete',
            'systemrole:permissions' => 'system:role:perm',
            'systemrole:authorization' => 'system:role:perm',
            'systemrole:saveauthorization' => 'system:role:perm',
            'systemrole:copyauthorization' => 'system:role:perm-copy',
            'systemdepartment:tree' => 'system:dept:list',
            'systemdepartment:detail' => 'system:dept:list',
            'systemdepartment:create' => 'system:dept:add',
            'systemdepartment:update' => 'system:dept:edit',
            'systemdepartment:delete' => 'system:dept:delete',
            'systemadmin:index' => 'system:user:list',
            'systemadmin:detail' => 'system:user:list',
            'systemadmin:create' => 'system:user:add',
            'systemadmin:update' => 'system:user:edit',
            'systemadmin:delete' => 'system:user:delete',
            'systemadmin:resetpassword' => 'system:user:reset',
            'systemadmin:status' => 'system:user:status',
            'systemmenu:tree' => 'systemmenu:tree',
            'systemmenu:detail' => 'systemmenu:detail',
            'systemmenu:create' => 'systemmenu:create',
            'systemmenu:update' => 'systemmenu:update',
            'systemmenu:delete' => 'systemmenu:delete',
            'systempermission:tree' => 'system:permission:list',
            'systempermission:create' => 'system:permission:add',
            'systempermission:update' => 'system:permission:edit',
            'systempermission:delete' => 'system:permission:delete',
            'systemblacklist:index' => 'system:blacklist:list',
            'systemblacklist:detail' => 'system:blacklist:list',
            'systemblacklist:create' => 'system:blacklist:add',
            'systemblacklist:update' => 'system:blacklist:edit',
            'systemblacklist:status' => 'system:blacklist:status',
            'systemblacklist:delete' => 'system:blacklist:delete',
            'systemblacklist:restore' => 'system:blacklist:restore',
            'systemblacklist:destroy' => 'system:blacklist:destroy',
            'systemblacklist:import' => 'system:blacklist:import',
            'systemblacklist:export' => 'system:blacklist:export',
            'systemlanguage:index' => 'system:language:list',
            'systemlanguage:detail' => 'system:language:list',
            'systemlanguage:create' => 'system:language:add',
            'systemlanguage:update' => 'system:language:edit',
            'systemlanguage:delete' => 'system:language:delete',
            'systemmembergroup:index' => 'system:member-group:list',
            'systemmembergroup:detail' => 'system:member-group:list',
            'systemmembergroup:create' => 'system:member-group:add',
            'systemmembergroup:update' => 'system:member-group:edit',
            'systemmembergroup:status' => 'system:member-group:status',
            'systemmembergroup:recycle' => 'system:member-group:delete',
            'systemmembergroup:restore' => 'system:member-group:restore',
            'systemmembergroup:destroy' => 'system:member-group:destroy',
            'systemmembergroup:import' => 'system:member-group:import',
            'systemmembergroup:export' => 'system:member-group:export',
            'systemmemberlevel:index' => 'system:member-level:list',
            'systemmemberlevel:detail' => 'system:member-level:list',
            'systemmemberlevel:create' => 'system:member-level:add',
            'systemmemberlevel:update' => 'system:member-level:edit',
            'systemmemberlevel:status' => 'system:member-level:status',
            'systemmemberlevel:recycle' => 'system:member-level:delete',
            'systemmemberlevel:restore' => 'system:member-level:restore',
            'systemmemberlevel:destroy' => 'system:member-level:destroy',
            'systemmemberlevel:import' => 'system:member-level:import',
            'systemmemberlevel:export' => 'system:member-level:export',
            'systemmember:index' => 'system:member:list',
            'systemmember:detail' => 'system:member:list',
            'systemmember:options' => 'system:member:list',
            'systemmember:create' => 'system:member:add',
            'systemmember:update' => 'system:member:edit',
            'systemmember:status' => 'system:member:status',
            'systemmember:recycle' => 'system:member:delete',
            'systemmember:restore' => 'system:member:restore',
            'systemmember:destroy' => 'system:member:destroy',
            'systemmember:import' => 'system:member:import',
            'systemmember:export' => 'system:member:export',
            'systemconfig:index' => 'system:config:list',
            'systemconfig:detail' => 'system:config:list',
            'systemconfig:options' => 'system:config:list',
            'systemconfig:create' => 'system:config:add',
            'systemconfig:update' => 'system:config:edit',
            'systemconfig:value' => 'system:config:value',
            'systemconfig:status' => 'system:config:status',
            'systemconfig:delete' => 'system:config:delete',
            'systemconfig:groups' => 'system:config-group:list',
            'systemconfig:creategroup' => 'system:config-group:add',
            'systemconfig:updategroup' => 'system:config-group:edit',
            'systemconfig:deletegroup' => 'system:config-group:delete',
            'systemattachment:index' => 'system:attachment:list',
            'systemattachment:detail' => 'system:attachment:list',
            'systemattachment:rename' => 'system:attachment:edit',
            'systemattachment:move' => 'system:attachment:move',
            'systemattachment:delete' => 'system:attachment:delete',
            'systemattachmentgroup:tree' => 'system:attachment-group:list',
            'systemattachmentgroup:detail' => 'system:attachment-group:list',
            'systemattachmentgroup:create' => 'system:attachment-group:add',
            'systemattachmentgroup:update' => 'system:attachment-group:edit',
            'systemattachmentgroup:delete' => 'system:attachment-group:delete',
            'adminupload:upload' => 'system:attachment:upload',
            'systemstorage:index' => 'system:attachment:list',
            'systemstorage:update' => 'system:attachment:storage',
            'systemoperationlog:index' => 'system:log:operation:list',
            'systemoperationlog:delete' => 'system:log:operation:delete',
            'systemplugin:installed' => 'system:plugin:list',
            'systemplugin:discovered' => 'system:plugin:list',
            'systemplugin:localdetail' => 'system:plugin:list',
            'systemplugin:enabledmodules' => 'system:plugin:list',
            'systemplugin:accountlogin' => 'system:plugin:account',
            'systemplugin:accountrefresh' => 'system:plugin:account-refresh',
            'systemplugin:accountlogout' => 'system:plugin:account',
            'systemplugin:currentaccount' => 'system:plugin:account',
            'systemplugin:marketcategories' => 'system:plugin:list',
            'systemplugin:marketsearch' => 'system:plugin:list',
            'systemplugin:marketdetail' => 'system:plugin:list',
            'systemplugin:marketversions' => 'system:plugin:list',
            'systemplugin:checkupdates' => 'system:plugin:list',
            'systemplugin:installlocal' => 'system:plugin:install',
            'systemplugin:installdiscovered' => 'system:plugin:discovered-install',
            'systemplugin:installcloud' => 'system:plugin:install',
            'systemplugin:updatelocal' => 'system:plugin:local-update',
            'systemplugin:update' => 'system:plugin:update',
            'systemplugin:migrate' => 'system:plugin:migrate',
            'systemplugin:enable' => 'system:plugin:enable',
            'systemplugin:disable' => 'system:plugin:disable',
            'systemplugin:getconfig' => 'system:plugin:config',
            'systemplugin:saveconfig' => 'system:plugin:config',
            'systemplugin:uninstall' => 'system:plugin:uninstall',
            'systemplugin:purge' => 'system:plugin:purge',
            'systemplugin:deletepackage' => 'system:plugin:package-delete',
            'systemplugin:history' => 'system:plugin:history',
            'systemplugin:downloadhistory' => 'system:plugin:history-download',
            'systemplugin:redeployhistory' => 'system:plugin:history-redeploy',
            'systemplugin:recoveryinfo' => 'system:plugin:recovery',
            'systemplugin:operations' => 'system:plugin:history',
            'systemupgrade:status' => 'system:upgrade:list',
            'systemupgrade:check' => 'system:upgrade:check',
            'systemupgrade:executeupgrade' => 'system:upgrade:execute',
            'systemupgrade:upload' => 'system:upgrade:upload',
            'systemupgrade:restore' => 'system:upgrade:restore',
            'systemupgrade:recoverstale' => 'system:upgrade:restore',
        ];
        if ($isSuperAdmin) {
            return ['*'];
        }
        $result = [];
        foreach ($permissionCodes as $code) {
            // 保留业务独立动作供前端精确控制；合并别名不代表获得其他动作授权。
            $normalizedCode = strtolower((string) $code);
            if ($normalizedCode !== '' && !str_starts_with($normalizedCode, 'console/') && !str_starts_with($normalizedCode, 'backend/')) {
                $result[$normalizedCode] = true;
            }
            $webCode = $mapping[$normalizedCode] ?? null;
            if ($webCode) {
                $result[$webCode] = true;
            }
        }
        return array_keys($result);
    }
}
