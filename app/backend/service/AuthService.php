<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\backend\service;

use app\backend\model\Admin as AdminModel;
use app\backend\model\AuthGroup as AuthGroupModel;
use app\backend\model\Permission;
use app\common\model\Blacklist;
use app\common\service\AbstractService;
use app\common\traits\Jump;
use fun\helper\SignHelper;
use think\facade\Cache;
use think\facade\Cookie;
use think\facade\Request;
use think\facade\Session;
use think\helper\Str;

class AuthService extends AbstractService
{
    use Jump;

    /**
     * @var object 对象实例
     */

    /**
     * 当前请求实例
     * @var Request
     */
    protected $request;

    protected $app;

    protected $controller;

    protected $action;

    protected $requesturl;
    /**
     * 获取用户信息
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return Session::get('admin.' . $name);
    }

    public function __construct()
    {
        parent::__construct();
        // 初始化request
        $this->request = Request::instance();
        $this->app = app('http')->getName();
        $this->controller = Str::camel($this->request->controller());
        $this->action = $this->request->action();
        $this->action = $this->action ?: 'index';
        $this->requesturl = $this->request->pathinfo();
        if (Str::endsWith($this->requesturl, '.' . config('view.view_suffix'))) {
            $this->requesturl = rtrim($this->requesturl, '.' .config('view.view_suffix'));
        }
        if (Str::contains($this->requesturl, '.' . config('view.view_suffix').'?')) {
            $this->requesturl = str_replace( '.' .config('view.view_suffix').'?','.' .config('view.view_suffix'),$this->requesturl);
        }
        $this->requesturl = trim($this->requesturl, '/');
    }

    /**
     * 菜单节点
     * @param array $cate
     * @param $lefthtml
     * @param $pid
     * @param $lvl
     * @param $leftpin
     * @return array
     */
    public function treemenu($cate, $lefthtml = '├─', $pid = 0, $lvl = 0, $leftpin = 0)
    {
        $arr = array();
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                $v['lvl'] = $lvl + 1;
                $v['leftpin'] = $leftpin + 0;
                $v['lefthtml'] = str_repeat($lefthtml, $lvl);
                $v['ltitle'] = $v['lefthtml'] . $v['title'];
                $arr[] = $v;
                $arr = array_merge($arr, self::treemenu($cate, $lefthtml, $v['id'], $lvl + 1, $leftpin + 20));
            }
        }

        return $arr;
    }

    /**
     * 权限设置选中状态
     * @param array $permissions
     * @param int $pid
     * @param array $permissionIds
     * @param bool $allPermissions
     * @return array
     */
    public function buildPermissionTree(array $permissions, int $pid, array $permissionIds, bool $allPermissions = false)
    {
        $list = [];
        $permissionIds = $this->normalizeIds($permissionIds);
        foreach ($permissions as $v) {
            if ($v['pid'] == $pid) {
                $v['spread'] = true;
                $v['title'] = lang($v['title']) . (!empty($v['code']) ? ' ' . $v['code'] : '');
                $children = $this->buildPermissionTree(
                    $permissions,
                    (int) $v['id'],
                    $permissionIds,
                    $allPermissions
                );
                if ($children) {
                    $v['children'] = $children;
                } elseif (in_array((int) $v['id'], $permissionIds, true) || $allPermissions) {
                    $v['checked'] = true;
                }
                $list[] = $v;
            }
        }
        return $list;
    }

    /**
     * 权限多维转化为二维
     * @param array $permissions
     * @return array
     */
    public function flattenPermissionTree(array $permissions)
    {
        $list = [];
        foreach ($permissions as $v) {
            $list[]['id'] = $v['id'];
//        $list[]['title'] = $v['title'];
//        $list[]['pid'] = $v['pid'];
            if (!empty($v['children'])) {
                $listChild = $this->flattenPermissionTree($v['children']);
                $list = array_merge($list, $listChild);
            }
        }
        return $list;
    }

    /**
     * 验证当前后台请求权限。
     */
    public function roleAccess()
    {
        $cfg = config('funadmin');
        if ($this->requesturl === '/' || !$this->isLogin()) {
            $this->error(lang('Please Login First'));
        }
        if (isset($cfg['auth_on']) && $cfg['auth_on'] == false) {
            return true;
        }
        if ($this->request->isPost() && $cfg['isDemo'] == 1) {
            $this->error(lang('Demo is not allow to change data'));
        }

        $requestUrl = strtolower(trim($this->requesturl, '/'));
        if (in_array($requestUrl, config('funadmin.auth_login_only_routes', []), true)) {
            return true;
        }
        if (in_array($requestUrl, config('funadmin.auth_super_only_routes', []), true)) {
            if (!$this->isSuperAdmin()) {
                $this->error(lang('Permission Denied'));
            }
            return true;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }

        $resource = PermissionResource::fromRequest($this->request, $this->app);
        $resource = $this->aliasResource($requestUrl, $resource);
        if ($this->isLoginOnlyResource($resource)) {
            return true;
        }
        if (!$this->casbin()->enforceAdmin((int) session('admin.id'), $resource['obj'], $resource['act'])) {
            $this->error(lang('Permission Denied'));
        }
        return true;
    }

    /**
     * 判断页面菜单或按钮 URL 是否有权限。
     */
    public function nodeAccess($url)
    {
        $cfg = config('funadmin');
        if (isset($cfg['auth_on']) && $cfg['auth_on'] == false) {
            return true;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }

        $resource = PermissionResource::fromRoute($this->app, (string) $url);
        if (!$resource) {
            return false;
        }
        if ($this->isLoginOnlyResource($resource)) {
            return true;
        }
        return $this->casbin()->enforceAdmin(
            (int) session('admin.id'),
            $resource['obj'],
            $resource['act']
        );
    }

    private function isLoginOnlyResource(array $resource): bool
    {
        $codes = db_cache('auth-login-only-resource-codes', function () {
            $result = [];
            $permissions = Permission::where('status', 1)
                ->where('is_public', 1)
                ->where('code', '<>', '')
                ->column('code');
            foreach ($permissions as $code) {
                $result[(string) $code] = true;
            }
            return $result;
        });
        return isset($codes[$resource['code']]);
    }

    private function aliasResource(string $requestUrl, array $resource): array
    {
        $aliases = array_change_key_case(config('funadmin.auth_route_aliases', []), CASE_LOWER);
        $target = $aliases[$requestUrl] ?? $aliases[strtolower((string) ($resource['code'] ?? ''))] ?? null;
        if ($target === null) {
            return $resource;
        }
        return PermissionResource::fromRoute($this->app, (string) $target) ?: $resource;
    }

    /**
     * @param $cate
     * @return string
     * 帅刷新菜单；
     */
    public function menuhtml($cate, $force = true)
    {
        if ($force) {
            Cache::delete('adminmenushtml' . session('admin.id'));
        }
        $list = $this->filterAuthorizedMenus($cate);
        $theme = syscfg('site', 'site_theme');
        $cache_key = 'adminmenushtml-'.$theme.md5(json_encode($list));
        $html = db_cache($cache_key,function()use($list,$theme){
            if (empty($theme) || ($theme && in_array($theme,[1,2,5]))) {
                $html = '';
                foreach ($list as $key => $val) {
                    $html .= '<li class="layui-nav-item">';
                    $badge = '';
                    if (strtolower($val['title']) === 'plugin') {
                        $badge = '<span class="layui-badge" style="text-align: right;float: right;position: absolute;right: 10%;">new</span>';
                    }
                    if ($val['child'] and count($val['child']) > 0) {
                        $html .= '<a href="javascript:;" lay-id="' . $val['id'] . '" data-id="' . $val['id'] . '" title="' . lang($val['title']) . '" data-tips="' . lang($val['title']) . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite>' . $badge . '</a>';
                        $html = $this->childmenuhtml($html, $val['child']);
                    } else {
                        $target = $val['target'] ? $val['target'] : '_self';
                        $html .= '<a href="javascript:;" lay-id="' . $val['id'] . '"  data-id="' . $val['id'] . '" title="' . lang($val['title']) . '" data-tips="' . lang($val['title']) . '" data-url="' . $val['href'] . '" target="' . $target . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite>' . $badge . '</a>';
                    }
                    $html .= '</li>';
                }
            } elseif ($theme == 3  ||  $theme ==4) {
                $html = [];
                $hide = '';
                $html['nav'] = '';
                $html['menu'] = '';
                $html['navm'] = '<li class="layui-nav-item"  menu-id="' . $list[0]['id'] . '">
                        <a href="javascript:;"><i class="fa fa-list-ul"></i> 请选择<span class="layui-nav-more"></span></a>
                        <dl class="layui-nav-child">';
                foreach ($list as $key => $val) {
                    $laythis = $key == 0 ? 'layui-this' : '';
                    $html['nav'] .= '<li class="layui-nav-item ' . $laythis . '"  menu-id="' . $val['id'] . '">';
                    $html['navm'] .= '<dd><a href="javascript:;" menu-id="' . $val['id'] . '" lay-id="' . $val['id'] . '"  data-id="' . $val['id'] . '" title="' . lang($val['title']) . '"  data-tips="' . lang($val['title']) . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite></a></dd>';
                    $badge = '';
                    if (strtolower($val['title']) === 'plugin') {
                        $badge = '<span class="layui-badge">new</span>';
                    }
                    $hide = '';
                    if($theme==3){
                        $hide = $theme<=3 && $key > 0 ? 'layui-hide' : '';
                    }
                    if($theme==4){
                        $hide = 'layui-hide';
                    }
                    $html['menu'] .= '<ul style="display:block"  lay-accordion class="layui-nav layui-nav-tree ' . $hide . '" menu-id="' . $val['id'] . '" lay-filter="menulist"  lay-shrink="all" id="layui-side-left-menu-ul">';
                    if ($val['child'] and count($val['child']) > 0) {
                        $html['nav'] .= '<a href="javascript:;" menu-id="' . $val['id'] . '" lay-id="' . $val['id'] . '" data-id="' . $val['id'] . '" title="' . lang($val['title']) . '" data-tips="' . lang($val['title']) . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite>' . $badge . '</a>';
                        foreach ($val['child'] as $k => $v) {
                            if ($v['child'] and count($v['child']) > 0) {
                                $html['menu'] .= '<li class="layui-nav-item"  menu-id="' . $v['id'] . '"><a href="javascript:;"  lay-id="' . $v['id'] . '" data-id="' . $v['id'] . '" title="' . lang($v['title']) . '" data-tips="' . lang($v['title']) . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite>' . $badge . '</a>';
                                $html['menu'] .= $this->childmenuhtml('', $v['child']);
                                $html['menu'] .= '</li>';
                            } else {
                                $target = $val['target'] ? $val['target'] : '_self';
                                $html['menu'] .= '<li class="layui-nav-item"  lay-id="' . $v['id'] . '"><a href="javascript:;" lay-id="' . $v['id'] . '"  data-id="' . $v['id'] . '" title="' . lang($v['title']) . '" data-tips="' . lang($v['title']) . '" data-url="' . $v['href'] . '" target="' . $target . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite>' . $badge . '</a></li>';
                            }
                        }
                        $html['menu'] .= '</ul>';
                    } else {
                        $target = $val['target'] ? $val['target'] : '_self';
                        $html['nav'] .= '<a href="javascript:;" lay-event="tab" lay-id="' . $val['id'] . '"  data-id="' . $val['id'] . '" title="' . lang($val['title']) . '" data-tips="' . lang($val['title']) . '" data-url="' . $val['href'] . '" target="' . $target . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite>' . $badge . '</a>';
                        $html['menu'] .= '<li class="layui-nav-item"  menu-id="' . $val['id'] . '"  lay-id="' . $val['id'] . '"><a href="javascript:;" lay-id="' . $val['id'] . '"  data-id="' . $val['id'] . '" title="' . lang($val['title']) . '" data-tips="' . lang($val['title']) . '" data-url="' . $val['href'] . '" target="' . $target . '"><i class="' . $val['icon'] . '"></i><cite> ' . lang($val['title']) . '</cite>' . $badge . '</a></li>';
                    }
                    $html['menu'] .= '</ul>';
                    $html['nav'] .= '</li>';
                }
                $html['navm'] .= '</dl><li>';
            }
            return $html;
        });
        return $html;
        
    }

    /**
     * 获取子菜单html
     * @param $html
     * @param $child
     * @return string
     */
    public function childmenuhtml($html, $child, $type = 1)
    {
        if ($type < 3) {
            $html .= '<dl class="layui-nav-child">';
            foreach ($child as $k => $v) {
                $html .= '<dd >';
                if ($v['child'] and count($v['child']) > 0) {
                    $html .= '<a href="javascript:;" lay-id="' . $v['id'] . '"  data-id="' . $v['id'] . '" title="' . lang($v['title']) . '"  data-tips="' . lang($v['title']) . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite></a>';
                    $html = self::childmenuhtml($html, $v['child'], $type);
                } else {
                    $v['target'] = $v['target'] ? $v['target'] : '_self';
                    $html .= '<a href="javascript:;" lay-id="' . $v['id'] . '"   data-id="' . $v['id'] . '" title="' . lang($v['title']) . '" data-tips="' . lang($v['title']) . '" data-url="' . $v['href'] . '" target="' . $v['target'] . '">
                    <i class="' . $v['icon'] . '"></i>
                    <cite> ' . lang($v['title']) . '</cite></a>';
                }
                $html .= '</dd>';
            };
            $html .= '</dl>';
        } else {
            $html .= '<dl class="layui-nav-child">';
            foreach ($child as $k => $v) {
                $html .= '<dd >';
                if ($v['child'] and count($v['child']) > 0) {
                    $html .= '<a href="javascript:;" lay-id="' . $v['id'] . '"  data-id="' . $v['id'] . '" title="' . lang($v['title']) . '"  data-tips="' . lang($v['title']) . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite></a>';
                    $html = self::childmenuhtml($html, $v['child'], $type);
                } else {
                    $v['target'] = $v['target'] ? $v['target'] : '_self';
                    $html .= '<a href="javascript:;" lay-id="' . $v['id'] . '"   data-id="' . $v['id'] . '" title="' . lang($v['title']) . '" data-tips="' . lang($v['title']) . '" data-url="' . $v['href'] . '" target="' . $v['target'] . '"><i class="' . $v['icon'] . '"></i><cite> ' . lang($v['title']) . '</cite></a>';
                }
                $html .= '</dd>';
            };
            $html .= '</dl>';;
        }
        return $html;
    }

    /**
     * 获取用户信息
     * @return mixed
     */
    public function getAdmin(){
        return Session::get('admin');
    }
    /**
     * 检测是否登录
     * @return boolean
     */
    public function isLogin()
    {
        $admin = session('admin');
        if (!$admin) {
            return false;
        }
        // 每次请求复核账号状态和登录令牌，确保停用、改密和异地登录立即生效。
        $me = AdminModel::find((int) $admin['id']);
        $currentRoleIds = $this->adminRoleIds((int) $admin['id']);
        $sessionRoleIds = $this->normalizeIds($admin['role_ids'] ?? []);
        if (!$me || (int) $me['status'] !== 1
            || !hash_equals((string) $me['token'], (string) ($admin['token'] ?? ''))
            || $currentRoleIds !== $sessionRoleIds) {
            // 仅销毁当前旧会话，不清理数据库中的新登录令牌。
            Session::destroy();
            Cookie:[REDACTED]("rememberMe");
            return false;
        }
        //过期
        if (!session('admin.expiretime') || session('admin.expiretime') < time()) {
            $this->logout();
            return false;
        }
        //判断管理员IP是否变动
        if (config('funadmin.ip_check') && (!isset($admin['lastloginip']) || $admin['lastloginip'] != request()->ip())) {
            $this->logout();
            return false;
        }
        return true;
    }

    /**
     * 根据用户名密码，验证用户是否能成功登陆
     * @param $username
     * @param $password
     * @param $rememberMe
     * @return true
     * @throws \Exception
     */
    public function checkLogin($username, $password, $rememberMe)
    {
        $loginKey = 'admin-login-attempt-' . hash('sha256', request()->ip() . '|' . strtolower(trim((string) $username)));
        $attempts = (int) Cache::get($loginKey, 0);
        if ($attempts >= 5) {
            throw new \Exception(lang('Login attempts too frequent'));
        }
        try {
            $ip = request()->ip();
            if(Blacklist::where('ip',$ip)->where('status',1)->find()){
                throw new \Exception(lang('You dont have permission'));
            }
            $where['username|email'] = strip_tags(trim($username));
            $password = strip_tags(trim($password));
            $admin = AdminModel::where($where)->find();
            if (!$admin) {
                throw new \Exception(lang('Please check username or password'));
            }
            if ((int) $admin['status'] !== 1) {
                throw new \Exception(lang('Account is disabled'));
            }
            if (!password_verify($password, $admin['password'])) {
                throw new \Exception(lang('Please check username or password'));
            }
            $roleIds = $this->adminRoleIds((int) $admin['id']);
            if (!$roleIds || !$this->casbin()->activeRoleIds($roleIds)) {
                throw new \Exception(lang('You dont have permission'));
            }
            $ip = request()->ip();
            $admin->lastloginip = $ip;
            $admin->ip = $ip;
            $admin->token = SignHelper::authSign($admin);
            $admin->save();
            $admin = $admin->toArray();
            $admin['role_ids'] = $roleIds;
                                    if ($rememberMe) {
                $admin['expiretime'] = 30 * 24 * 3600 + time();
            } else {
                $admin['expiretime'] = config('session.expire') + time();
            }
            unset($admin['password']);
            Session::regenerate(true);
            Session::set('admin', $admin);
            Cache::delete($loginKey);
        } catch (\Exception $e) {
            Cache::set($loginKey, $attempts + 1, 600);
            throw new \Exception($e->getMessage());
        }
        return true;
    }

    /**
     * 退出登录
     * @return boolean
     */
    public function logout()
    {
        $admin = AdminModel::find(intval(\session('admin.id')));
        if ($admin) {
            $admin->token = '';
            $admin->save();
        }
        Session::destroy();
        Cookie::delete("rememberMe");
        return true;
    }


    /**
     * 获取角色可用的权限 ID。
     *
     * @param mixed $roles 角色 ID
     * @return array<int>
     */
    public function permissionIdsForRoles($roles)
    {
        $roleIds = $this->normalizeIds($roles);
        if (in_array((int) config('funadmin.superRoleId'), $roleIds, true)) {
            $permissionIds = db_cache('super-admin-permission-ids', function () {
                return array_map('intval', Permission::where('status', 1)->column('id'));
            });
        } else {
            $key = 'role-permissions-' . implode(',', $roleIds);
            $permissionIds = db_cache($key, function () use ($roleIds) {
                return $this->casbin()->permissionIdsForRoles($roleIds);
            });
        }

        $publicPermissionIds = db_cache('public-permission-ids', function () {
            return array_map('intval', Permission::where('status', 1)->where('is_public', 1)->column('id'));
        });
        $permissionIds = $this->permissionIdsWithAncestors(array_merge($permissionIds ?: [], $publicPermissionIds ?: []));
        return $permissionIds;
    }

    public function adminRoleIds(int $adminId): array
    {
        return $this->casbin()->adminRoleIds($adminId);
    }

    public function rolePermissionIds(int $roleId): array
    {
        return $this->casbin()->rolePermissionIds($roleId);
    }

    /**
     * 当前账号是否为配置中的超级管理员。
     */
    public function isSuperAdmin(): bool
    {
        return (int) session('admin.id') === (int) config('funadmin.superAdminId');
    }

    /**
     * 当前管理员所属角色 ID。
     */
    public function currentRoleIds(): array
    {
        return $this->adminRoleIds((int) session('admin.id'));
    }

    /**
     * 当前管理员可管理的下级角色；可选包含自己的角色作为新角色父级。
     */
    public function manageableRoleIds(bool $includeOwn = false): array
    {
        if ($this->isSuperAdmin()) {
            return array_map('intval', AuthGroupModel::where('status', 1)->column('id'));
        }
        $ownIds = $this->currentRoleIds();
        $result = $includeOwn ? $ownIds : [];
        $guard = new RoleGuardService();
        foreach ($ownIds as $roleId) {
            $result = array_merge($result, $guard->descendantRoleIds((int) $roleId));
        }
        return $this->normalizeIds($result);
    }

    public function canManageRole(int $roleId): bool
    {
        return $this->isSuperAdmin() || in_array($roleId, $this->manageableRoleIds(), true);
    }

    public function descendantRoleIds(int $roleId): array
    {
        return (new RoleGuardService())->descendantRoleIds($roleId);
    }

    public function canUseParentRole(int $roleId): bool
    {
        if ($roleId <= 0 || !AuthGroupModel::where('id', $roleId)->where('status', 1)->find()) {
            return false;
        }
        return $this->isSuperAdmin() || in_array($roleId, $this->manageableRoleIds(true), true);
    }

    public function canAssignRoles($roleIds): bool
    {
        $roleIds = $this->normalizeIds($roleIds);
        if (!$roleIds || AuthGroupModel::where('status', 1)->whereIn('id', $roleIds)->count() !== count($roleIds)) {
            return false;
        }
        return $this->isSuperAdmin() || !array_diff($roleIds, $this->manageableRoleIds());
    }

    public function canManageAdmin($admin, bool $allowSelf = false): bool
    {
        if (!$admin) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($allowSelf && (int) $admin['id'] === (int) session('admin.id')) {
            return true;
        }
        $roleIds = $this->adminRoleIds((int) $admin['id']);
        return $roleIds && !array_diff($roleIds, $this->manageableRoleIds());
    }

    /**
     * 下级角色只能获得当前管理员自身已有的权限。
     */
    public function canAssignPermissions($permissionIds): bool
    {
        $permissionIds = $this->normalizeIds($permissionIds);
        if (!$permissionIds) {
            return true;
        }
        if (Permission::where('status', 1)->whereIn('id', $permissionIds)->count() !== count($permissionIds)) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        $ownPermissionIds = $this->normalizeIds($this->permissionIdsForRoles($this->currentRoleIds()));
        return !array_diff($permissionIds, $ownPermissionIds);
    }

    protected function casbin(): CasbinService
    {
        return CasbinService::instance();
    }

    protected function normalizeIds($ids): array
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        $ids = array_map('intval', $ids);
        $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
        return $ids;
    }

    // 获取左侧菜单树：叶子菜单按 Casbin 权限过滤，目录由可见子菜单反向保留。
    private function permissionIdsWithAncestors(array $permissionIds): array
    {
        $ids = $this->normalizeIds($permissionIds);
        $parents = Permission::where('status', 1)->column('pid', 'id');
        foreach ($ids as $id) {
            $parentId = (int) ($parents[$id] ?? 0);
            while ($parentId > 0 && !in_array($parentId, $ids, true)) {
                $ids[] = $parentId;
                $parentId = (int) ($parents[$parentId] ?? 0);
            }
        }
        return $ids;
    }

    protected function filterAuthorizedMenus(array $menus, int $pid = 0, ?array $permissionIds = null): array
    {
        $permissionIds ??= $this->permissionIdsForRoles($this->currentRoleIds());
        $list = [];
        foreach ($menus as $item) {
            if ((int) $item['pid'] !== $pid) {
                continue;
            }

            $children = $this->filterAuthorizedMenus($menus, (int) $item['id'], $permissionIds);
            $allowed = $this->isSuperAdmin()
                || in_array((int) ($item['permission_id'] ?? 0), $permissionIds, true)
                || $children !== [];
            if (!$allowed) {
                continue;
            }

            $href = (string) $item['href'];
            $url = parse_url($href);
            if ($href !== '' && empty($url['host'])) {
                $path = '/' . trim((string) $item['module'] . '/' . trim($href, '/'), '/');
                $query = trim((string) ($url['query'] ?? '') . '&' . (string) $item['query'], '&');
                if (!Str::endsWith($path, '/index')) {
                    $path .= '/index';
                }
                $item['href'] = $path . ($query !== '' ? '?' . $query : '');
            }
            $item['child'] = $children;
            $list[] = $item;
        }
        return $list;
    }


}
