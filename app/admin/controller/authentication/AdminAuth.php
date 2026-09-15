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
        $menus = AdminMenu::whereIn('source_type', ['admin_web', 'generated'])
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select();
        $allowed = [];
        $allById = [];
        foreach ($menus as $menu) {
            $allById[(int) $menu->id] = $menu;
            if ($roleScope->isSuperAdmin() || (int) $menu->permission_id === 0 || in_array((int) $menu->permission_id, $permissionIds, true)) {
                $allowed[(int) $menu->id] = $this->menuData($menu);
            }
        }
        foreach (array_keys($allowed) as $id) {
            $parentId = (int) ($allById[$id]->pid ?? 0);
            while ($parentId > 0 && isset($allById[$parentId])) {
                $allowed[$parentId] = $this->menuData($allById[$parentId]);
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

    private function menuData(AdminMenu $menu): array
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
            'hidden' => filter_var($meta['hidden'] ?? false, FILTER_VALIDATE_BOOL),
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
            'admin/development.business:modules' => 'development:business:view',
            'admin/development.business:module' => 'development:business:view',
            'admin/development.business:createvisual' => 'development:business:save',
            'admin/development.business:inspectdatabase' => 'development:business:inspect',
            'admin/development.business:createfromdatabase' => 'development:business:save',
            'admin/development.business:validateschema' => 'development:business:save',
            'admin/development.business:saveschema' => 'development:business:save',
            'admin/development.business:compileschema' => 'development:business:save',
            'admin/development.business:exportschema' => 'development:business:view',
            'admin/development.business:schemaversions' => 'development:business:view',
            'admin/development.business:schemaversion' => 'development:business:view',
            'admin/development.business:schemadiff' => 'development:business:view',
            'admin/development.business:rollbackschema' => 'development:business:save',
            'admin/development.business:databasetables' => 'development:business:inspect',
            'admin/development.business:databasetableschema' => 'development:business:inspect',
            'admin/development.business:previewpublish' => 'development:business:publish',
            'admin/development.business:publish' => 'development:business:publish',
            'admin/development.business:runtimemeta' => 'development:business:view',
            'admin/development.business:previewformalgeneration' => 'development:business:generate',
            'admin/development.business:formalgeneration' => 'development:business:generate',
            'admin/development.business:generations' => 'development:business:records',
            'admin/development.business:generation' => 'development:business:records',
            'admin/development.business:recovergeneration' => 'development:business:recover',
            'admin/development.business:retryresources' => 'development:business:apply-resources',
            'admin/development.business:adoptresolvedbaseline' => 'development:business:save',
            'admin/development.business:fieldcapabilities' => 'development:business:view',
            'admin/systemdict:types' => 'system:dict:list',
            'admin/systemdict:items' => 'system:dict:list',
            'admin/systemdict:options' => 'system:dict:list',
            'admin/systemdict:batch' => 'system:dict:list',
            'admin/systemdict:createtype' => 'system:dict:add',
            'admin/systemdict:createitem' => 'system:dict:add',
            'admin/systemdict:updatetype' => 'system:dict:edit',
            'admin/systemdict:updateitem' => 'system:dict:edit',
            'admin/systemdict:deletetype' => 'system:dict:delete',
            'admin/systemdict:deletetypes' => 'system:dict:delete',
            'admin/systemdict:deleteitem' => 'system:dict:delete',
            'admin/systemdict:deleteitems' => 'system:dict:delete',
            'admin/systemrole:index' => 'system:role:list',
            'admin/systemrole:detail' => 'system:role:list',
            'admin/systemrole:create' => 'system:role:add',
            'admin/systemrole:update' => 'system:role:edit',
            'admin/systemrole:delete' => 'system:role:delete',
            'admin/systemrole:permissions' => 'system:role:perm',
            'admin/systemrole:authorization' => 'system:role:perm',
            'admin/systemrole:saveauthorization' => 'system:role:perm',
            'admin/systemrole:copyauthorization' => 'system:role:perm-copy',
            'admin/systemdepartment:tree' => 'system:dept:list',
            'admin/systemdepartment:detail' => 'system:dept:list',
            'admin/systemdepartment:create' => 'system:dept:add',
            'admin/systemdepartment:update' => 'system:dept:edit',
            'admin/systemdepartment:delete' => 'system:dept:delete',
            'admin/systemadmin:index' => 'system:user:list',
            'admin/systemadmin:detail' => 'system:user:list',
            'admin/systemadmin:create' => 'system:user:add',
            'admin/systemadmin:update' => 'system:user:edit',
            'admin/systemadmin:delete' => 'system:user:delete',
            'admin/systemadmin:resetpassword' => 'system:user:reset',
            'admin/systemadmin:status' => 'system:user:status',
            'admin/systemmenu:tree' => 'system:menu:list',
            'admin/systemmenu:detail' => 'system:menu:list',
            'admin/systemmenu:create' => 'system:menu:add',
            'admin/systemmenu:update' => 'system:menu:edit',
            'admin/systemmenu:delete' => 'system:menu:delete',
            'admin/systempermission:tree' => 'system:permission:list',
            'admin/systempermission:create' => 'system:permission:add',
            'admin/systempermission:update' => 'system:permission:edit',
            'admin/systempermission:delete' => 'system:permission:delete',
            'admin/systemblacklist:index' => 'system:blacklist:list',
            'admin/systemblacklist:detail' => 'system:blacklist:list',
            'admin/systemblacklist:create' => 'system:blacklist:add',
            'admin/systemblacklist:update' => 'system:blacklist:edit',
            'admin/systemblacklist:status' => 'system:blacklist:status',
            'admin/systemblacklist:delete' => 'system:blacklist:delete',
            'admin/systemblacklist:restore' => 'system:blacklist:restore',
            'admin/systemblacklist:destroy' => 'system:blacklist:destroy',
            'admin/systemblacklist:import' => 'system:blacklist:import',
            'admin/systemblacklist:export' => 'system:blacklist:export',
            'admin/systemlanguage:index' => 'system:language:list',
            'admin/systemlanguage:detail' => 'system:language:list',
            'admin/systemlanguage:create' => 'system:language:add',
            'admin/systemlanguage:update' => 'system:language:edit',
            'admin/systemlanguage:delete' => 'system:language:delete',
            'admin/systemmembergroup:index' => 'system:member-group:list',
            'admin/systemmembergroup:detail' => 'system:member-group:list',
            'admin/systemmembergroup:create' => 'system:member-group:add',
            'admin/systemmembergroup:update' => 'system:member-group:edit',
            'admin/systemmembergroup:status' => 'system:member-group:status',
            'admin/systemmembergroup:recycle' => 'system:member-group:delete',
            'admin/systemmembergroup:restore' => 'system:member-group:restore',
            'admin/systemmembergroup:destroy' => 'system:member-group:destroy',
            'admin/systemmembergroup:import' => 'system:member-group:import',
            'admin/systemmembergroup:export' => 'system:member-group:export',
            'admin/systemmemberlevel:index' => 'system:member-level:list',
            'admin/systemmemberlevel:detail' => 'system:member-level:list',
            'admin/systemmemberlevel:create' => 'system:member-level:add',
            'admin/systemmemberlevel:update' => 'system:member-level:edit',
            'admin/systemmemberlevel:status' => 'system:member-level:status',
            'admin/systemmemberlevel:recycle' => 'system:member-level:delete',
            'admin/systemmemberlevel:restore' => 'system:member-level:restore',
            'admin/systemmemberlevel:destroy' => 'system:member-level:destroy',
            'admin/systemmemberlevel:import' => 'system:member-level:import',
            'admin/systemmemberlevel:export' => 'system:member-level:export',
            'admin/systemmember:index' => 'system:member:list',
            'admin/systemmember:detail' => 'system:member:list',
            'admin/systemmember:options' => 'system:member:list',
            'admin/systemmember:create' => 'system:member:add',
            'admin/systemmember:update' => 'system:member:edit',
            'admin/systemmember:status' => 'system:member:status',
            'admin/systemmember:recycle' => 'system:member:delete',
            'admin/systemmember:restore' => 'system:member:restore',
            'admin/systemmember:destroy' => 'system:member:destroy',
            'admin/systemmember:import' => 'system:member:import',
            'admin/systemmember:export' => 'system:member:export',
            'admin/systemconfig:index' => 'system:config:list',
            'admin/systemconfig:detail' => 'system:config:list',
            'admin/systemconfig:options' => 'system:config:list',
            'admin/systemconfig:create' => 'system:config:add',
            'admin/systemconfig:update' => 'system:config:edit',
            'admin/systemconfig:value' => 'system:config:value',
            'admin/systemconfig:status' => 'system:config:status',
            'admin/systemconfig:delete' => 'system:config:delete',
            'admin/systemconfig:groups' => 'system:config-group:list',
            'admin/systemconfig:creategroup' => 'system:config-group:add',
            'admin/systemconfig:updategroup' => 'system:config-group:edit',
            'admin/systemconfig:deletegroup' => 'system:config-group:delete',
            'admin/systemattachment:index' => 'system:attachment:list',
            'admin/systemattachment:detail' => 'system:attachment:list',
            'admin/systemattachment:rename' => 'system:attachment:edit',
            'admin/systemattachment:move' => 'system:attachment:move',
            'admin/systemattachment:delete' => 'system:attachment:delete',
            'admin/systemattachmentgroup:tree' => 'system:attachment-group:list',
            'admin/systemattachmentgroup:detail' => 'system:attachment-group:list',
            'admin/systemattachmentgroup:create' => 'system:attachment-group:add',
            'admin/systemattachmentgroup:update' => 'system:attachment-group:edit',
            'admin/systemattachmentgroup:delete' => 'system:attachment-group:delete',
            'admin/adminupload:upload' => 'system:attachment:upload',
            'admin/systemstorage:index' => 'system:attachment:list',
            'admin/systemstorage:update' => 'system:attachment:storage',
            'admin/systemoperationlog:index' => 'system:log:operation:list',
            'admin/systemoperationlog:delete' => 'system:log:operation:delete',
            'admin/systemplugin:installed' => 'system:plugin:list',
            'admin/systemplugin:discovered' => 'system:plugin:list',
            'admin/systemplugin:localdetail' => 'system:plugin:list',
            'admin/systemplugin:enabledmodules' => 'system:plugin:list',
            'admin/systemplugin:accountlogin' => 'system:plugin:account',
            'admin/systemplugin:accountrefresh' => 'system:plugin:account-refresh',
            'admin/systemplugin:accountlogout' => 'system:plugin:account',
            'admin/systemplugin:currentaccount' => 'system:plugin:account',
            'admin/systemplugin:marketcategories' => 'system:plugin:list',
            'admin/systemplugin:marketsearch' => 'system:plugin:list',
            'admin/systemplugin:marketdetail' => 'system:plugin:list',
            'admin/systemplugin:marketversions' => 'system:plugin:list',
            'admin/systemplugin:checkupdates' => 'system:plugin:list',
            'admin/systemplugin:installlocal' => 'system:plugin:install',
            'admin/systemplugin:installdiscovered' => 'system:plugin:discovered-install',
            'admin/systemplugin:installcloud' => 'system:plugin:install',
            'admin/systemplugin:updatelocal' => 'system:plugin:local-update',
            'admin/systemplugin:update' => 'system:plugin:update',
            'admin/systemplugin:migrate' => 'system:plugin:migrate',
            'admin/systemplugin:enable' => 'system:plugin:enable',
            'admin/systemplugin:disable' => 'system:plugin:disable',
            'admin/systemplugin:getconfig' => 'system:plugin:config',
            'admin/systemplugin:saveconfig' => 'system:plugin:config',
            'admin/systemplugin:uninstall' => 'system:plugin:uninstall',
            'admin/systemplugin:purge' => 'system:plugin:purge',
            'admin/systemplugin:deletepackage' => 'system:plugin:package-delete',
            'admin/systemplugin:history' => 'system:plugin:history',
            'admin/systemplugin:downloadhistory' => 'system:plugin:history-download',
            'admin/systemplugin:redeployhistory' => 'system:plugin:history-redeploy',
            'admin/systemplugin:recoveryinfo' => 'system:plugin:recovery',
            'admin/systemplugin:operations' => 'system:plugin:history',
            'admin/systemupgrade:status' => 'system:upgrade:list',
            'admin/systemupgrade:check' => 'system:upgrade:check',
            'admin/systemupgrade:executeupgrade' => 'system:upgrade:execute',
            'admin/systemupgrade:upload' => 'system:upgrade:upload',
            'admin/systemupgrade:restore' => 'system:upgrade:restore',
            'admin/systemupgrade:recoverstale' => 'system:upgrade:restore',
        ];
        if ($isSuperAdmin) {
            return ['*'];
        }
        $result = [];
        foreach ($permissionCodes as $code) {
            // 保留业务独立动作供前端精确控制；合并别名不代表获得其他动作授权。
            $normalizedCode = strtolower((string) $code);
            if (str_starts_with($normalizedCode, 'admin/')) {
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
