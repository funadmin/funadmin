<?php

namespace app\admin\controller\authentication;

use app\BaseController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckAdminApiRole;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authorization\model\AdminMenu;
use app\admin\authorization\model\Permission;
use app\admin\authorization\service\RoleScopeService;
use app\admin\traits\AdminTree;
use app\admin\traits\AdminJsonResponse;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\App;
use think\captcha\facade\Captcha;
use think\Response;
use think\facade\Session;

/**
 * Admin Web Session 认证适配层。
 */
#[Group('auth')]
class AdminAuth extends BaseController
{
    use AdminJsonResponse;
    use AdminTree;

    protected array $middleware = [
        CheckAdminApiCsrf::class => ['only' => ['login', 'logout']],
        CheckAdminApiRole::class => ['only' => ['me', 'menus', 'logout']],
    ];

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    #[Get('csrf')]
    public function csrf(): Response
    {
        return $this->ok(data: [
            'csrfToken' => $this->request->buildToken(),
            'captchaEnabled' => (bool) config('captcha.check'),
        ]);
    }

    #[Get('captcha')]
    public function captcha(): Response
    {
        return Captcha::create();
    }

    #[Post('login')]
    public function login(): Response
    {
        $username = trim(strip_tags((string) $this->request->post('username', '')));
        $password = (string) $this->request->post('password', '');
        $captcha = trim((string) $this->request->post('captcha', ''));
        $remember = (bool) $this->request->post('remember', false);

        if ($username === '' || $password === '') {
            return $this->fail(msg: '请输入用户名和密码', code: 422);
        }
        if (config('captcha.check') && ($captcha === '' || !Captcha::check($captcha))) {
            return $this->fail(msg: '验证码错误或已过期', code: 422);
        }

        try {
            (new AdminSessionService())->checkLogin($username, $password, $remember);
        } catch (\Throwable $e) {
            return $this->fail(msg: $e->getMessage(), code: 400);
        }

        return $this->ok('登录成功', ['authenticated' => true]);
    }

    #[Get('me')]
    public function me(): Response
    {
        $admin = Session::get('admin', []);
        $roleScope = new RoleScopeService();
        $roleIds = $roleScope->currentRoleIds();
        $permissionIds = $roleScope->permissionIdsForRoles($roleIds);
        $permissionCodes = Permission::whereIn('id', $permissionIds)
            ->where('status', 1)
            ->where('code', '<>', '')
            ->column('code');

        return $this->ok(data: [
            'id' => (int) ($admin['id'] ?? 0),
            'username' => (string) ($admin['username'] ?? ''),
            'nickname' => (string) (($admin['real_name'] ?? '') ?: ($admin['username'] ?? '')),
            'avatar' => (string) ($admin['avatar'] ?? ''),
            'email' => (string) ($admin['email'] ?? ''),
            'mobile' => (string) ($admin['mobile'] ?? ''),
            'roles' => array_map(static fn ($id) => 'role:' . (int) $id, $roleIds),
            'permissions' => $this->webPermissions($permissionCodes, $roleScope->isSuperAdmin()),
        ]);
    }

    #[Get('menus')]
    public function menus(): Response
    {
        $roleScope = new RoleScopeService();
        $permissionIds = $roleScope->permissionIdsForRoles($roleScope->currentRoleIds());
        $menus = AdminMenu::whereIn('source_type', ['admin_web', 'generated', 'plugin'])
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select();
        $allowed = [];
        $allById = [];
        foreach ($menus as $menu) {
            $allById[(int) $menu->id] = $menu;
            $isAllowed = $roleScope->isSuperAdmin() || (int) $menu->permission_id === 0 || in_array((int) $menu->permission_id, $permissionIds, true);
            if ($isAllowed || (string) $menu->href !== '') {
                $allowed[(int) $menu->id] = $this->menuData($menu, $isAllowed);
            }
        }
        foreach (array_keys($allowed) as $id) {
            $parentId = (int) ($allById[$id]->pid ?? 0);
            while ($parentId > 0 && isset($allById[$parentId])) {
                $allowed[$parentId] = $this->menuData($allById[$parentId], true);
                $parentId = (int) $allById[$parentId]->pid;
            }
        }
        return $this->ok(data: $this->buildTree(array_values($allowed)));
    }

    #[Post('logout')]
    public function logout(): Response
    {
        (new AdminSessionService())->logout();
        return $this->ok('退出成功');
    }

    private function menuData(AdminMenu $menu, bool $isAllowed = true): array
    {
        parse_str((string) $menu->query, $meta);
        // 旧受管生成曾漏写 query；仅恢复身份完全匹配且元数据为空的生成菜单，不写库、不覆盖二开。
        $source = (string) $menu->source_name;
        if ((string) $menu->source_type === 'generated' && (string) $menu->query === ''
            && preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $source) === 1
            && (string) $menu->href === '/generated/' . $source) {
            $meta = [
                'component' => 'generated/' . $source . '/index',
                'name' => str_replace(' ', '', ucwords(str_replace('-', ' ', $source))),
                'type' => 'C', 'formKey' => str_replace('-', '_', $source),
            ];
        }
        $permission = $menu->permission_id > 0 ? Permission::find((int) $menu->permission_id) : null;
        return [
            'id' => (int) $menu->id,
            'parentId' => (int) $menu->pid,
            'routeName' => (string) ($meta['name'] ?? ('Menu_' . (int) $menu->id)),
            'path' => $this->menuPath($menu),
            'component' => (string) ($meta['component'] ?? ''),
            'redirect' => (string) ($meta['redirect'] ?? ''),
            'type' => in_array(($meta['type'] ?? ''), ['M', 'C'], true) ? (string) $meta['type'] : 'C',
            'icon' => (string) $menu->icon,
            'name' => (string) $menu->name,
            'sort' => (int) $menu->sort_order,
            'hidden' => !$isAllowed || filter_var($meta['hidden'] ?? false, FILTER_VALIDATE_BOOL),
            'keepAlive' => filter_var($meta['keepAlive'] ?? false, FILTER_VALIDATE_BOOL),
            'affix' => filter_var($meta['affix'] ?? false, FILTER_VALIDATE_BOOL),
            'permission' => (string) ($permission->code ?? ''),
            'formKey' => (string) ($meta['formKey'] ?? ''),
        ];
    }

    private function menuPath(AdminMenu $menu): string
    {
        $href = (string) $menu->href;
        return (int) $menu->pid === 0 ? '/' . ltrim($href, '/') : $href;
    }

    private function webPermissions(array $permissionCodes, bool $isSuperAdmin): array
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
