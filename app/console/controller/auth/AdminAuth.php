<?php

namespace app\console\controller\auth;

use app\BaseController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\model\AdminMenu;
use app\console\model\Permission;
use app\console\service\AdminSessionService;
use app\console\service\RoleScopeService;
use app\console\traits\AdminTree;
use app\console\traits\AdminJsonResponse;
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
        $permission = $menu->permission_id > 0 ? Permission::find((int) $menu->permission_id) : null;
        return [
            'id' => (int) $menu->id,
            'parentId' => (int) $menu->pid,
            'routeName' => (string) ($meta['name'] ?? ('Menu_' . (int) $menu->id)),
            'path' => '/' . ltrim((string) $menu->href, '/'),
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
        ];
    }

    private function webPermissions(array $permissionCodes, bool $isSuperAdmin): array
    {
        $mapping = [
            'console/devcrud:tableschema' => 'development:crud:schema',
            'console/devcrud:preview' => 'development:crud:preview',
            'console/systemdict:types' => 'system:dict:list',
            'console/systemdict:items' => 'system:dict:list',
            'console/systemdict:options' => 'system:dict:list',
            'console/systemdict:batch' => 'system:dict:list',
            'console/systemdict:createtype' => 'system:dict:add',
            'console/systemdict:createitem' => 'system:dict:add',
            'console/systemdict:updatetype' => 'system:dict:edit',
            'console/systemdict:updateitem' => 'system:dict:edit',
            'console/systemdict:deletetype' => 'system:dict:delete',
            'console/systemdict:deletetypes' => 'system:dict:delete',
            'console/systemdict:deleteitem' => 'system:dict:delete',
            'console/systemdict:deleteitems' => 'system:dict:delete',
            'console/systemrole:index' => 'system:role:list',
            'console/systemrole:detail' => 'system:role:list',
            'console/systemrole:create' => 'system:role:add',
            'console/systemrole:update' => 'system:role:edit',
            'console/systemrole:delete' => 'system:role:delete',
            'console/systemrole:permissions' => 'system:role:perm',
            'console/systemdepartment:tree' => 'system:dept:list',
            'console/systemdepartment:detail' => 'system:dept:list',
            'console/systemdepartment:create' => 'system:dept:add',
            'console/systemdepartment:update' => 'system:dept:edit',
            'console/systemdepartment:delete' => 'system:dept:delete',
            'console/systemadmin:index' => 'system:user:list',
            'console/systemadmin:detail' => 'system:user:list',
            'console/systemadmin:create' => 'system:user:add',
            'console/systemadmin:update' => 'system:user:edit',
            'console/systemadmin:delete' => 'system:user:delete',
            'console/systemadmin:resetpassword' => 'system:user:reset',
            'console/systemadmin:status' => 'system:user:status',
            'console/systemmenu:tree' => 'system:menu:list',
            'console/systemmenu:detail' => 'system:menu:list',
            'console/systemmenu:create' => 'system:menu:add',
            'console/systemmenu:update' => 'system:menu:edit',
            'console/systemmenu:delete' => 'system:menu:delete',
            'console/systempermission:tree' => 'system:permission:list',
            'console/systempermission:create' => 'system:permission:add',
            'console/systempermission:update' => 'system:permission:edit',
            'console/systempermission:delete' => 'system:permission:delete',
            'console/systemblacklist:index' => 'system:blacklist:list',
            'console/systemblacklist:detail' => 'system:blacklist:list',
            'console/systemblacklist:create' => 'system:blacklist:add',
            'console/systemblacklist:update' => 'system:blacklist:edit',
            'console/systemblacklist:status' => 'system:blacklist:status',
            'console/systemblacklist:delete' => 'system:blacklist:delete',
            'console/systemblacklist:restore' => 'system:blacklist:restore',
            'console/systemblacklist:destroy' => 'system:blacklist:destroy',
            'console/systemblacklist:import' => 'system:blacklist:import',
            'console/systemblacklist:export' => 'system:blacklist:export',
            'console/systemlanguage:index' => 'system:language:list',
            'console/systemlanguage:detail' => 'system:language:list',
            'console/systemlanguage:create' => 'system:language:add',
            'console/systemlanguage:update' => 'system:language:edit',
            'console/systemlanguage:delete' => 'system:language:delete',
            'console/systemmembergroup:index' => 'system:member-group:list',
            'console/systemmembergroup:detail' => 'system:member-group:list',
            'console/systemmembergroup:create' => 'system:member-group:add',
            'console/systemmembergroup:update' => 'system:member-group:edit',
            'console/systemmembergroup:status' => 'system:member-group:status',
            'console/systemmembergroup:recycle' => 'system:member-group:delete',
            'console/systemmembergroup:restore' => 'system:member-group:restore',
            'console/systemmembergroup:destroy' => 'system:member-group:destroy',
            'console/systemmembergroup:import' => 'system:member-group:import',
            'console/systemmembergroup:export' => 'system:member-group:export',
            'console/systemmemberlevel:index' => 'system:member-level:list',
            'console/systemmemberlevel:detail' => 'system:member-level:list',
            'console/systemmemberlevel:create' => 'system:member-level:add',
            'console/systemmemberlevel:update' => 'system:member-level:edit',
            'console/systemmemberlevel:status' => 'system:member-level:status',
            'console/systemmemberlevel:recycle' => 'system:member-level:delete',
            'console/systemmemberlevel:restore' => 'system:member-level:restore',
            'console/systemmemberlevel:destroy' => 'system:member-level:destroy',
            'console/systemmemberlevel:import' => 'system:member-level:import',
            'console/systemmemberlevel:export' => 'system:member-level:export',
            'console/systemmember:index' => 'system:member:list',
            'console/systemmember:detail' => 'system:member:list',
            'console/systemmember:options' => 'system:member:list',
            'console/systemmember:create' => 'system:member:add',
            'console/systemmember:update' => 'system:member:edit',
            'console/systemmember:status' => 'system:member:status',
            'console/systemmember:recycle' => 'system:member:delete',
            'console/systemmember:restore' => 'system:member:restore',
            'console/systemmember:destroy' => 'system:member:destroy',
            'console/systemmember:import' => 'system:member:import',
            'console/systemmember:export' => 'system:member:export',
            'console/systemconfig:index' => 'system:config:list',
            'console/systemconfig:detail' => 'system:config:list',
            'console/systemconfig:options' => 'system:config:list',
            'console/systemconfig:create' => 'system:config:add',
            'console/systemconfig:update' => 'system:config:edit',
            'console/systemconfig:value' => 'system:config:value',
            'console/systemconfig:status' => 'system:config:status',
            'console/systemconfig:delete' => 'system:config:delete',
            'console/systemconfig:groups' => 'system:config-group:list',
            'console/systemconfig:creategroup' => 'system:config-group:add',
            'console/systemconfig:updategroup' => 'system:config-group:edit',
            'console/systemconfig:deletegroup' => 'system:config-group:delete',
            'console/systemattachment:index' => 'system:attachment:list',
            'console/systemattachment:detail' => 'system:attachment:list',
            'console/systemattachment:rename' => 'system:attachment:edit',
            'console/systemattachment:move' => 'system:attachment:move',
            'console/systemattachment:delete' => 'system:attachment:delete',
            'console/systemattachmentgroup:tree' => 'system:attachment-group:list',
            'console/systemattachmentgroup:detail' => 'system:attachment-group:list',
            'console/systemattachmentgroup:create' => 'system:attachment-group:add',
            'console/systemattachmentgroup:update' => 'system:attachment-group:edit',
            'console/systemattachmentgroup:delete' => 'system:attachment-group:delete',
            'console/adminupload:upload' => 'system:attachment:upload',
            'console/systemstorage:index' => 'system:attachment:list',
            'console/systemstorage:update' => 'system:attachment:storage',
            'console/systemoperationlog:index' => 'system:log:operation:list',
            'console/systemoperationlog:delete' => 'system:log:operation:delete',
            'console/systemplugin:installed' => 'system:plugin:list',
            'console/systemplugin:discovered' => 'system:plugin:list',
            'console/systemplugin:localdetail' => 'system:plugin:list',
            'console/systemplugin:enabledmodules' => 'system:plugin:list',
            'console/systemplugin:accountlogin' => 'system:plugin:account',
            'console/systemplugin:accountrefresh' => 'system:plugin:account-refresh',
            'console/systemplugin:accountlogout' => 'system:plugin:account',
            'console/systemplugin:currentaccount' => 'system:plugin:account',
            'console/systemplugin:marketcategories' => 'system:plugin:list',
            'console/systemplugin:marketsearch' => 'system:plugin:list',
            'console/systemplugin:marketdetail' => 'system:plugin:list',
            'console/systemplugin:marketversions' => 'system:plugin:list',
            'console/systemplugin:checkupdates' => 'system:plugin:list',
            'console/systemplugin:installlocal' => 'system:plugin:install',
            'console/systemplugin:installdiscovered' => 'system:plugin:discovered-install',
            'console/systemplugin:installcloud' => 'system:plugin:install',
            'console/systemplugin:updatelocal' => 'system:plugin:local-update',
            'console/systemplugin:update' => 'system:plugin:update',
            'console/systemplugin:migrate' => 'system:plugin:migrate',
            'console/systemplugin:enable' => 'system:plugin:enable',
            'console/systemplugin:disable' => 'system:plugin:disable',
            'console/systemplugin:getconfig' => 'system:plugin:config',
            'console/systemplugin:saveconfig' => 'system:plugin:config',
            'console/systemplugin:uninstall' => 'system:plugin:uninstall',
            'console/systemplugin:purge' => 'system:plugin:purge',
            'console/systemplugin:deletepackage' => 'system:plugin:package-delete',
            'console/systemplugin:history' => 'system:plugin:history',
            'console/systemplugin:downloadhistory' => 'system:plugin:history-download',
            'console/systemplugin:redeployhistory' => 'system:plugin:history-redeploy',
            'console/systemplugin:recoveryinfo' => 'system:plugin:recovery',
            'console/systemplugin:operations' => 'system:plugin:history',
            'console/systemupgrade:status' => 'system:upgrade:list',
            'console/systemupgrade:check' => 'system:upgrade:check',
            'console/systemupgrade:executeupgrade' => 'system:upgrade:execute',
            'console/systemupgrade:upload' => 'system:upgrade:upload',
            'console/systemupgrade:restore' => 'system:upgrade:restore',
            'console/systemupgrade:recoverstale' => 'system:upgrade:restore',
        ];
        if ($isSuperAdmin) {
            return ['*'];
        }
        $result = [];
        foreach ($permissionCodes as $code) {
            $webCode = $mapping[strtolower((string) $code)] ?? null;
            if ($webCode) {
                $result[$webCode] = true;
            }
        }
        return array_keys($result);
    }


}
