<?php

namespace app\backend\controller;

use app\BaseController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\model\AdminMenu;
use app\backend\model\Permission;
use app\backend\service\AuthService;
use think\App;
use think\captcha\facade\Captcha;
use think\Response;
use think\facade\Session;

/**
 * Admin Web Session 认证适配层。
 */
class AdminAuth extends BaseController
{
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
            'nickname' => (string) (($admin['realname'] ?? '') ?: ($admin['username'] ?? '')),
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
        $menu = AdminMenu::where('source_type', 'admin_web')
            ->where('source_name', 'dictionary')
            ->where('status', 1)
            ->find();
        if (!$menu || (!$auth->isSuperAdmin() && !in_array((int) $menu->permission_id, $permissionIds, true))) {
            return $this->ok([]);
        }

        return $this->ok([[
            'id' => (int) $menu->id,
            'parentId' => 0,
            'name' => 'SystemDict',
            'path' => '/system/dict',
            'component' => 'system/dict/index',
            'type' => 'C',
            'icon' => (string) ($menu->icon ?: 'i-ep-collection'),
            'title' => '字典管理',
            'sort' => (int) $menu->sort,
            'hidden' => false,
            'keepAlive' => true,
            'affix' => false,
            'permission' => 'system:dict:list',
            'children' => [],
        ]]);
    }

    public function logout(): Response
    {
        AuthService::instance()->logout();
        return $this->ok(null, '退出成功');
    }

    private function frontendPermissions(array $permissionCodes, bool $isSuperAdmin): array
    {
        if ($isSuperAdmin) {
            return ['system:dict:list', 'system:dict:add', 'system:dict:edit', 'system:dict:delete'];
        }

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
        ];
        $result = [];
        foreach ($permissionCodes as $code) {
            $frontendCode = $mapping[strtolower((string) $code)] ?? null;
            if ($frontendCode) {
                $result[$frontendCode] = true;
            }
        }
        return array_keys($result);
    }

    private function ok($data = null, string $msg = '操作成功'): Response
    {
        return $this->apiResponse(200, $msg, $data);
    }

    private function fail(string $msg, int $code): Response
    {
        return $this->apiResponse($code, $msg, null, $code);
    }

    private function apiResponse(int $code, string $msg, $data, int $httpCode = 200): Response
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ], $httpCode)->header(['X-CSRF-TOKEN' => (string) Session::get('__token__', '')]);
    }
}
