<?php

declare(strict_types=1);

namespace app\backend\controller\auth;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\Admin;
use app\backend\service\AuthService;
use fun\helper\SignHelper;
use think\Response;
use think\facade\Cache;
use think\facade\Session;

/**
 * 当前登录管理员的个人资料与密码。
 */
class AdminProfile extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function index(): Response
    {
        $admin = $this->currentAdmin();
        return $admin ? $this->ok($this->profileData($admin)) : $this->fail('管理员不存在', 404);
    }

    public function update(): Response
    {
        $admin = $this->currentAdmin();
        if (!$admin) {
            return $this->fail('管理员不存在', 404);
        }

        $input = $this->request->param();
        $data = [];
        $fieldMap = [
            'nickname' => 'real_name',
            'avatar' => 'avatar',
            'email' => 'email',
            'mobile' => 'mobile',
        ];
        foreach ($fieldMap as $inputField => $modelField) {
            if (array_key_exists($inputField, $input)) {
                $data[$modelField] = trim(strip_tags((string) $input[$inputField]));
            }
        }
        $data['real_name'] = $data['real_name'] ?? (string) $admin->real_name;
        if ($data['real_name'] === '' || mb_strlen($data['real_name']) > 50) {
            return $this->fail('昵称不能为空且不能超过 50 个字符', 422);
        }
        if (isset($data['email']) && $data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->fail('邮箱格式不正确', 422);
        }
        if (isset($data['mobile']) && $data['mobile'] !== '' && !preg_match('/^1[3-9]\d{9}$/', $data['mobile'])) {
            return $this->fail('手机号格式不正确', 422);
        }
        if (isset($data['avatar']) && mb_strlen($data['avatar']) > 255) {
            return $this->fail('头像地址过长', 422);
        }

        $admin->save($data);
        $sessionAdmin = Session::get('admin', []);
        foreach ($data as $field => $value) {
            $sessionAdmin[$field] = $value;
        }
        Session::set('admin', $sessionAdmin);
        Cache::clear();
        return $this->ok($this->profileData($admin), '资料已更新');
    }

    public function password(): Response
    {
        $admin = $this->currentAdmin();
        if (!$admin) {
            return $this->fail('管理员不存在', 404);
        }
        $oldPassword = (string) $this->request->post('oldPassword', '');
        $newPassword = (string) $this->request->post('newPassword', '');
        if ($oldPassword === '' || mb_strlen($newPassword) < 8) {
            return $this->fail('原密码不能为空，新密码至少 8 位', 422);
        }
        if (!password_verify($oldPassword, (string) $admin->password)) {
            return $this->fail('原密码错误', 422);
        }
        if (password_verify($newPassword, (string) $admin->password)) {
            return $this->fail('新密码不能与原密码相同', 422);
        }

        $admin->save([
            'password' => SignHelper::password($newPassword),
            'token' => SignHelper::salt(20),
        ]);
        Cache::clear();
        AuthService::instance()->logout();
        return $this->ok(null, '密码已更新，请重新登录');
    }

    private function currentAdmin(): ?Admin
    {
        $adminId = (int) Session::get('admin.id', 0);
        return $adminId > 0 ? Admin::find($adminId) : null;
    }

    private function profileData(Admin $admin): array
    {
        return [
            'id' => (int) $admin->id,
            'username' => (string) $admin->username,
            'nickname' => (string) (($admin->real_name ?: $admin->username)),
            'avatar' => (string) $admin->avatar,
            'email' => (string) $admin->email,
            'mobile' => (string) $admin->mobile,
            'lastLoginIp' => (string) $admin->last_login_ip,
        ];
    }
}
