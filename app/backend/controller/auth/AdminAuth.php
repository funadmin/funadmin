<?php

namespace app\backend\controller\auth;

use app\BaseController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\model\AdminMenu;
use app\backend\model\Permission;
use app\backend\service\AuthService;
use app\backend\traits\AdminDataFormat;
use app\backend\traits\AdminJsonResponse;
use app\backend\traits\AdminTree;
use think\App;
use think\captcha\facade\Captcha;
use think\Response;
use think\facade\Session;

/**
 * Admin Web Session 认证适配层。
 */
class AdminAuth extends BaseController
{
    use AdminDataFormat;
    use AdminJsonResponse;
    use AdminTree;

    protected $middleware = [
        CheckAdminApiCsrf::class => ['only' => ['login', 'logout']],
        CheckAdminApiRole::class => ['only' => ['me', 'menus', 'logout']],
    ];

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function csrf(): Response
    {
        return $this->ok([
            'csrfToken' => $this->request->buildToken(),
            'captchaEnabled' => (bool) config('captcha.check'),
        ]);
    }

    public function captcha(): Response
    {
        return Captcha::create();
    }

    public function login(): Response
    {
        $username = trim(strip_tags((string) $this->request->post('username', '')));
        $password = (string) $this->request->post('password', '');
        $captcha = trim((string) $this->request->post('captcha', ''));
        $remember = (bool) $this->request->post('remember', false);

        if ($username === '' || $password === '') {
            return $this->fail('请输入用户名和密码', 422);
        }
        if (config('captcha.check') && ($captcha === '' || !Captcha::check($captcha))) {
            return $this->fail('验证码错误或已过期', 422);
        }

        try {
            AuthService::instance()->checkLogin($username, $password, $remember);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 400);
        }

        return $this->ok(['authenticated' => true], '登录成功');
    }

    public function me(): Response
    {
        $admin = Session::get('admin', []);
        $auth = AuthService::instance();
        $roleIds = $auth->currentRoleIds();
        $permissionIds = $auth->permissionIdsForRoles($roleIds);
        $permissionCodes = Permission::whereIn('id', $permissionIds)
            ->where('status', 1)
            ->where('code', '<>', '')
            ->column('code');

        return $this->ok([
            'id' => (int) ($admin['id'] ?? 0),
            'username' => (string) ($admin['username'] ?? ''),
            'nickname' => (string) (($admin['real_name'] ?? '') ?: ($admin['username'] ?? '')),
            'avatar' => (string) ($admin['avatar'] ?? ''),
            'email' => (string) ($admin['email'] ?? ''),
            'mobile' => (string) ($admin['mobile'] ?? ''),
            'roles' => array_map(static fn ($id) => 'role:' . (int) $id, $roleIds),
            'permissions' => $this->frontendPermissions($permissionCodes, $auth->isSuperAdmin()),
        ]);
    }

    public function menus(): Response
    {
        $auth = AuthService::instance();
        $permissionIds = $auth->permissionIdsForRoles($auth->currentRoleIds());
        $menus = AdminMenu::where('source_type', 'admin_web')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select();
        $allowed = [];
        foreach ($menus as $menu) {
            if ($auth->isSuperAdmin() || (int) $menu->permission_id === 0 || in_array((int) $menu->permission_id, $permissionIds, true)) {
                $allowed[(int) $menu->id] = $this->menuData($menu);
            }
        }
        if (!$allowed) {
            return $this->ok([]);
        }

        $allById = [];
        foreach ($menus as $menu) {
            $allById[(int) $menu->id] = $menu;
        }
        foreach (array_keys($allowed) as $id) {
            $parentId = (int) ($allById[$id]->pid ?? 0);
            while ($parentId > 0 && isset($allById[$parentId])) {
                $allowed[$parentId] = $this->menuData($allById[$parentId]);
                $parentId = (int) $allById[$parentId]->pid;
            }
        }

        return $this->ok($this->buildTree(array_values($allowed)));
    }

    public function logout(): Response
    {
        AuthService::instance()->logout();
        return $this->ok(null, '退出成功');
    }

    private function frontendPermissions(array $permissionCodes, bool $isSuperAdmin): array
    {
        $mapping = [
            'backend/systemdict:types' => 'system:dict:list',
            'backend/systemdict:items' => 'system:dict:list',
            'backend/systemdict:options' => 'system:dict:list',
            'backend/systemdict:batch' => 'system:dict:list',
            'backend/systemdict:createtype' => 'system:dict:add',
            'backend/systemdict:createitem' => 'system:dict:add',
            'backend/systemdict:updatetype' => 'system:dict:edit',
            'backend/systemdict:updateitem' => 'system:dict:edit',
            'backend/systemdict:deletetype' => 'system:dict:delete',
            'backend/systemdict:deletetypes' => 'system:dict:delete',
            'backend/systemdict:deleteitem' => 'system:dict:delete',
            'backend/systemdict:deleteitems' => 'system:dict:delete',
            'backend/systemrole:index' => 'system:role:list',
            'backend/systemrole:detail' => 'system:role:list',
            'backend/systemrole:create' => 'system:role:add',
            'backend/systemrole:update' => 'system:role:edit',
            'backend/systemrole:delete' => 'system:role:delete',
            'backend/systemrole:permissions' => 'system:role:perm',
            'backend/systemdepartment:tree' => 'system:dept:list',
            'backend/systemdepartment:detail' => 'system:dept:list',
            'backend/systemdepartment:create' => 'system:dept:add',
            'backend/systemdepartment:update' => 'system:dept:edit',
            'backend/systemdepartment:delete' => 'system:dept:delete',
            'backend/systemadmin:index' => 'system:user:list',
            'backend/systemadmin:detail' => 'system:user:list',
            'backend/systemadmin:create' => 'system:user:add',
            'backend/systemadmin:update' => 'system:user:edit',
            'backend/systemadmin:delete' => 'system:user:delete',
            'backend/systemadmin:resetpassword' => 'system:user:reset',
            'backend/systemadmin:status' => 'system:user:status',
            'backend/systemmenu:tree' => 'system:menu:list',
            'backend/systemmenu:detail' => 'system:menu:list',
            'backend/systemmenu:create' => 'system:menu:add',
            'backend/systemmenu:update' => 'system:menu:edit',
            'backend/systemmenu:delete' => 'system:menu:delete',
            'backend/systempermission:tree' => 'system:permission:list',
            'backend/systempermission:create' => 'system:permission:add',
            'backend/systempermission:update' => 'system:permission:edit',
            'backend/systempermission:delete' => 'system:permission:delete',
            'backend/systemblacklist:index' => 'system:blacklist:list',
            'backend/systemblacklist:detail' => 'system:blacklist:list',
            'backend/systemblacklist:create' => 'system:blacklist:add',
            'backend/systemblacklist:update' => 'system:blacklist:edit',
            'backend/systemblacklist:status' => 'system:blacklist:status',
            'backend/systemblacklist:delete' => 'system:blacklist:delete',
            'backend/systemblacklist:restore' => 'system:blacklist:restore',
            'backend/systemblacklist:destroy' => 'system:blacklist:destroy',
            'backend/systemblacklist:import' => 'system:blacklist:import',
            'backend/systemblacklist:export' => 'system:blacklist:export',
            'backend/systemlanguage:index' => 'system:language:list',
            'backend/systemlanguage:detail' => 'system:language:list',
            'backend/systemlanguage:create' => 'system:language:add',
            'backend/systemlanguage:update' => 'system:language:edit',
            'backend/systemlanguage:delete' => 'system:language:delete',
            'backend/systemmembergroup:index' => 'system:member-group:list',
            'backend/systemmembergroup:detail' => 'system:member-group:list',
            'backend/systemmembergroup:create' => 'system:member-group:add',
            'backend/systemmembergroup:update' => 'system:member-group:edit',
            'backend/systemmembergroup:status' => 'system:member-group:status',
            'backend/systemmembergroup:recycle' => 'system:member-group:delete',
            'backend/systemmembergroup:restore' => 'system:member-group:restore',
            'backend/systemmembergroup:destroy' => 'system:member-group:destroy',
            'backend/systemmembergroup:export' => 'system:member-group:export',
            'backend/systemmemberlevel:index' => 'system:member-level:list',
            'backend/systemmemberlevel:detail' => 'system:member-level:list',
            'backend/systemmemberlevel:create' => 'system:member-level:add',
            'backend/systemmemberlevel:update' => 'system:member-level:edit',
            'backend/systemmemberlevel:status' => 'system:member-level:status',
            'backend/systemmemberlevel:recycle' => 'system:member-level:delete',
            'backend/systemmemberlevel:restore' => 'system:member-level:restore',
            'backend/systemmemberlevel:destroy' => 'system:member-level:destroy',
            'backend/systemmemberlevel:export' => 'system:member-level:export',
            'backend/systemmember:index' => 'system:member:list',
            'backend/systemmember:detail' => 'system:member:list',
            'backend/systemmember:options' => 'system:member:list',
            'backend/systemmember:create' => 'system:member:add',
            'backend/systemmember:update' => 'system:member:edit',
            'backend/systemmember:status' => 'system:member:status',
            'backend/systemmember:recycle' => 'system:member:delete',
            'backend/systemmember:restore' => 'system:member:restore',
            'backend/systemmember:destroy' => 'system:member:destroy',
            'backend/systemmember:import' => 'system:member:import',
            'backend/systemmember:export' => 'system:member:export',
            'backend/systemconfig:index' => 'system:config:list',
            'backend/systemconfig:detail' => 'system:config:list',
            'backend/systemconfig:options' => 'system:config:list',
            'backend/systemconfig:create' => 'system:config:add',
            'backend/systemconfig:update' => 'system:config:edit',
            'backend/systemconfig:value' => 'system:config:value',
            'backend/systemconfig:status' => 'system:config:status',
            'backend/systemconfig:delete' => 'system:config:delete',
            'backend/systemconfig:groups' => 'system:config-group:list',
            'backend/systemconfig:creategroup' => 'system:config-group:add',
            'backend/systemconfig:updategroup' => 'system:config-group:edit',
            'backend/systemconfig:deletegroup' => 'system:config-group:delete',
            'backend/systemattachment:index' => 'system:attachment:list',
            'backend/systemattachment:detail' => 'system:attachment:list',
            'backend/systemattachment:rename' => 'system:attachment:edit',
            'backend/systemattachment:move' => 'system:attachment:move',
            'backend/systemattachment:delete' => 'system:attachment:delete',
            'backend/systemattachmentgroup:tree' => 'system:attachment-group:list',
            'backend/systemattachmentgroup:detail' => 'system:attachment-group:list',
            'backend/systemattachmentgroup:create' => 'system:attachment-group:add',
            'backend/systemattachmentgroup:update' => 'system:attachment-group:edit',
            'backend/systemattachmentgroup:delete' => 'system:attachment-group:delete',
            'backend/adminupload:upload' => 'system:attachment:upload',
            'backend/systemstorage:index' => 'system:attachment:list',
            'backend/systemstorage:update' => 'system:attachment:storage',
            'backend/systemoperationlog:index' => 'system:log:operation:list',
            'backend/systemoperationlog:delete' => 'system:log:operation:delete',
            'backend/systemplugin:installed' => 'system:plugin:list',
            'backend/systemplugin:discovered' => 'system:plugin:list',
            'backend/systemplugin:localdetail' => 'system:plugin:list',
            'backend/systemplugin:enabledmodules' => 'system:plugin:list',
            'backend/systemplugin:accountlogin' => 'system:plugin:account',
            'backend/systemplugin:accountlogout' => 'system:plugin:account',
            'backend/systemplugin:currentaccount' => 'system:plugin:account',
            'backend/systemplugin:marketcategories' => 'system:plugin:list',
            'backend/systemplugin:marketsearch' => 'system:plugin:list',
            'backend/systemplugin:marketdetail' => 'system:plugin:list',
            'backend/systemplugin:marketversions' => 'system:plugin:list',
            'backend/systemplugin:checkupdates' => 'system:plugin:list',
            'backend/systemplugin:installlocal' => 'system:plugin:install',
            'backend/systemplugin:installcloud' => 'system:plugin:install',
            'backend/systemplugin:update' => 'system:plugin:update',
            'backend/systemplugin:migrate' => 'system:plugin:migrate',
            'backend/systemplugin:enable' => 'system:plugin:enable',
            'backend/systemplugin:disable' => 'system:plugin:disable',
            'backend/systemplugin:getconfig' => 'system:plugin:config',
            'backend/systemplugin:saveconfig' => 'system:plugin:config',
            'backend/systemplugin:uninstall' => 'system:plugin:uninstall',
            'backend/systemplugin:deletepackage' => 'system:plugin:package-delete',
            'backend/systemplugin:history' => 'system:plugin:history',
            'backend/systemplugin:operations' => 'system:plugin:history',
        ];
        if ($isSuperAdmin) {
            return ['*'];
        }
        $result = [];
        foreach ($permissionCodes as $code) {
            $frontendCode = $mapping[strtolower((string) $code)] ?? null;
            if ($frontendCode) {
                $result[$frontendCode] = true;
            }
        }
        return array_keys($result);
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
            'hidden' => $this->booleanValue($meta['hidden'] ?? false),
            'keepAlive' => $this->booleanValue($meta['keepAlive'] ?? false),
            'affix' => $this->booleanValue($meta['affix'] ?? false),
            'permission' => (string) ($permission->code ?? ''),
        ];
    }

}
