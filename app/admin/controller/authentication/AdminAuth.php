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
            'permissions' => Permission::webPermissions($permissionCodes, $roleScope->isSuperAdmin()),
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
                $allowed[(int) $menu->id] = $menu->toWebMenuData($isAllowed);
            }
        }
        foreach (array_keys($allowed) as $id) {
            $parentId = (int) ($allById[$id]->pid ?? 0);
            while ($parentId > 0 && isset($allById[$parentId])) {
                $allowed[$parentId] = $allById[$parentId]->toWebMenuData(true);
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

}
