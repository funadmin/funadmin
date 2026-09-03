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
use app\backend\model\AuthRule;
use app\common\model\Blacklist;
use app\common\service\AbstractService;
use app\common\traits\Jump;
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
     * @var array
     * config
     */
    protected $config = [];

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
        if ($auth = config('auth')) {
            $this->config = array_merge($this->config, $auth);
        }
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
     * 权限节点
     * @return array|int[]|mixed|string[]
     */
    public function nodeList()
    {
        $allAuthNode = [];
        if (session('admin')) {
            $cacheKey = 'auth-node-list-' . session('admin.id');
            $allAuthNode = Cache::get($cacheKey);
            if (empty($allAuthNode)) {
                $allAuthIds = $this->getRules($this->currentGroupIds());
                $allAuthNode = db_cache($cacheKey,function()use($allAuthIds,$cacheKey){
                    $authNode = AuthRule::where('status', 1)->whereIn('id', $allAuthIds)->cache($cacheKey)->column('href', 'href');
                    foreach ($authNode as $k => $v) {
                        $authNode[$k] = (parse_name($v, 1));
                    }
                    return array_flip( $authNode);
                });
                Cache::set($cacheKey, $allAuthNode,3600);
                
            }
        }
        return $allAuthNode;

    }

    /**
     * 菜单节点
     * @param $cate
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
     * Summary of auth
     * @param mixed $cate
     * @param mixed $rules
     * @param mixed $pid
     * @return array
     */
    public function auth($cate, $rules, $pid = 0)
    {
        $arr = array();
        $rulesArr = explode(',', $rules);
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                if (in_array($v['id'], $rulesArr)) {
                    $v['checked'] = true;
                }
                $v['open'] = true;
                $arr[] = $v;
                $arr = array_merge($arr, self::auth($cate, $v['id'], $rules));
            }
        }
        return $arr;
    }

    /**
     * 权限设置选中状态
     * @param array $cate
     * @param int $pid
     * @param string $rules
     * @param int $group_id
     * @return array
     */
    public function authChecked(array $cate, int $pid, string $rules, int $group_id)
    {
        $list = [];
        $rulesArr = explode(',', $rules);
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                $v['spread'] = true;
                if (!in_array($v['module'], ['backend'])) $v['href'] = $v['module'] . '/' . $v['href'];
                $v['title'] = lang($v['title']) .' '. $v['module'].'@' . $v['href'];
                if (self::authChecked($cate, $v['id'], $rules, $group_id)) {
                    $v['children'] = self::authChecked($cate, $v['id'], $rules, $group_id);
                } else {
                    if (in_array($v['id'], $rulesArr) || $group_id == 1) {
                        $v['checked'] = true;
                    }
                }
                $list[] = $v;
            }
        }
        return $list;
    }

    /**
     * 权限多维转化为二维
     * @param $cate
     * @return array
     */
    public function authNormal($cate)
    {
        $list = [];
        foreach ($cate as $v) {
            $list[]['id'] = $v['id'];
//        $list[]['title'] = $v['title'];
//        $list[]['pid'] = $v['pid'];
            if (!empty($v['children'])) {
                $listChild = self::authNormal($v['children']);
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
            $rules = AuthRule::where('status', 1)
                ->where('auth_verify', 0)
                ->field('module,href')
                ->select()
                ->toArray();
            foreach ($rules as $rule) {
                $item = PermissionResource::fromRoute((string) $rule['module'], (string) $rule['href']);
                if ($item) {
                    $result[$item['code']] = true;
                }
            }
            return $result;
        });
        return isset($codes[$resource['code']]);
    }

    private function aliasResource(string $requestUrl, array $resource): array
    {
        $aliases = array_change_key_case(config('funadmin.auth_route_aliases', []), CASE_LOWER);
        if (!isset($aliases[$requestUrl])) {
            return $resource;
        }
        return PermissionResource::fromRoute($this->app, (string) $aliases[$requestUrl]) ?: $resource;
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
        $list = $this->authMenuNode($cate);
        $theme = syscfg('site', 'site_theme');
        $cache_key = 'adminmenushtml-'.$theme.md5(json_encode($list));
        $html = db_cache($cache_key,function()use($list,$theme){
            if (empty($theme) || ($theme && in_array($theme,[1,2,5]))) {
                $html = '';
                foreach ($list as $key => $val) {
                    $html .= '<li class="layui-nav-item">';
                    $badge = '';
                    if (strtolower($val['title']) === 'addon') {
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
                    if (strtolower($val['title']) === 'addon') {
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
        $currentGroupIds = $this->adminGroupIds((int) $admin['id']);
        $sessionGroupIds = $this->normalizeIds($admin['group_ids'] ?? ($admin['group_id'] ?? []));
        if (!$me || (int) $me['status'] !== 1
            || !hash_equals((string) $me['token'], (string) ($admin['token'] ?? ''))
            || $currentGroupIds !== $sessionGroupIds) {
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
            $groupIds = $this->adminGroupIds((int) $admin['id']);
            if (!$groupIds || !$this->casbin()->activeGroupIds($groupIds)) {
                throw new \Exception(lang('You dont have permission'));
            }
            $ip = request()->ip();
            $admin->lastloginip = $ip;
            $admin->ip = $ip;
            $admin->token = SignHelper::authSign($admin);
            $admin->save();
            $admin = $admin->toArray();
            $admin['group_ids'] = $groupIds;
            // group_id 仅保留在会话中兼容现有模板展示，数据库不再存储该字段。
            $admin['group_id'] = implode(',', $groupIds);
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
     * 获取角色权限规则。
     *
     * @param mixed $groups 角色 ID 数组或逗号字符串
     * @return string|null
     */
    public function getRules($groups)
    {
        $groupIds = $this->normalizeIds($groups);
        if (in_array(1, $groupIds, true)) {
            $ruleIds = db_cache('super-admin-auth-group-rules', function () {
                return array_map('intval', AuthRule::where('status', 1)->column('id'));
            });
        } else {
            $key = 'auth-group-rules-' . implode(',', $groupIds);
            $ruleIds = db_cache($key, function () use ($groupIds) {
                return $this->casbin()->groupRuleIdsForGroups($groupIds);
            });
        }

        $noRuleIds = db_cache('no-rules', function () {
            return array_map('intval', AuthRule::where('auth_verify', 0)->column('id'));
        });
        $ruleIds = $this->normalizeIds(array_merge($ruleIds ?: [], $noRuleIds ?: []));
        return $ruleIds ? implode(',', $ruleIds) : null;
    }

    public function adminGroupIds(int $adminId): array
    {
        return $this->casbin()->adminGroupIds($adminId);
    }

    public function groupRuleIds(int $groupId): array
    {
        return $this->casbin()->groupRuleIds($groupId);
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
    public function currentGroupIds(): array
    {
        return $this->adminGroupIds((int) session('admin.id'));
    }

    /**
     * 当前管理员可管理的下级角色；可选包含自己的角色作为新角色父级。
     */
    public function manageableGroupIds(bool $includeOwn = false): array
    {
        if ($this->isSuperAdmin()) {
            return array_map('intval', AuthGroupModel::where('status', 1)->column('id'));
        }
        $ownIds = $this->currentGroupIds();
        $groups = AuthGroupModel::where('status', 1)->field('id,pid')->select()->toArray();
        $children = [];
        foreach ($groups as $group) {
            $children[(int) $group['pid']][] = (int) $group['id'];
        }
        $result = $includeOwn ? $ownIds : [];
        $queue = $ownIds;
        while ($queue) {
            $parentId = array_shift($queue);
            foreach ($children[$parentId] ?? [] as $childId) {
                if (!in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $result;
    }

    public function canManageGroup(int $groupId): bool
    {
        return $this->isSuperAdmin() || in_array($groupId, $this->manageableGroupIds(), true);
    }

    public function descendantGroupIds(int $groupId): array
    {
        $groups = AuthGroupModel::field('id,pid')->select()->toArray();
        $children = [];
        foreach ($groups as $group) {
            $children[(int) $group['pid']][] = (int) $group['id'];
        }
        $result = [];
        $queue = [$groupId];
        while ($queue) {
            $parentId = array_shift($queue);
            foreach ($children[$parentId] ?? [] as $childId) {
                if ($childId !== $groupId && !in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $result;
    }

    public function canUseParentGroup(int $groupId): bool
    {
        if ($groupId <= 0 || !AuthGroupModel::where('id', $groupId)->where('status', 1)->find()) {
            return false;
        }
        return $this->isSuperAdmin() || in_array($groupId, $this->manageableGroupIds(true), true);
    }

    public function canAssignGroups($groupIds): bool
    {
        $groupIds = $this->normalizeIds($groupIds);
        if (!$groupIds || AuthGroupModel::where('status', 1)->whereIn('id', $groupIds)->count() !== count($groupIds)) {
            return false;
        }
        return $this->isSuperAdmin() || !array_diff($groupIds, $this->manageableGroupIds());
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
        $groupIds = $this->adminGroupIds((int) $admin['id']);
        return $groupIds && !array_diff($groupIds, $this->manageableGroupIds());
    }

    /**
     * 下级角色只能获得当前管理员自身已有的权限。
     */
    public function canAssignRules($ruleIds): bool
    {
        $ruleIds = $this->normalizeIds($ruleIds);
        if (!$ruleIds || AuthRule::where('status', 1)->whereIn('id', $ruleIds)->count() !== count($ruleIds)) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        $ownRuleIds = $this->normalizeIds($this->getRules($this->currentGroupIds()));
        return !array_diff($ruleIds, $ownRuleIds);
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
    protected function authMenuNode($menu, $pid = 0, $rules = [])
    {
        $authorizedIds = $this->normalizeIds($this->getRules($this->currentGroupIds()));
        $list = [];
        foreach ($menu as $item) {
            if ((int) $item['pid'] !== (int) $pid) {
                continue;
            }

            $children = $this->authMenuNode($menu, (int) $item['id'], $authorizedIds);
            $allowed = $this->isSuperAdmin()
                || in_array((int) $item['id'], $authorizedIds, true)
                || $children !== [];
            if (!$allowed) {
                continue;
            }

            $href = (string) $item['href'];
            $url = parse_url($href);
            if (empty($url['host'])) {
                $path = '/' . trim((string) $item['module'] . '/' . trim($href, '/'), '/');
                $query = trim((string) ($url['query'] ?? '') . '&' . (string) $item['query'], '&');
                if ((int) $item['menu_status'] === 1 && !Str::endsWith($path, '/index')) {
                    $path .= '/index';
                }
                $item['href'] = $path . ($query !== '' ? '?' . $query : '');
            }
            $item['child'] = $children;
            $list[] = $item;
        }
        return $list;
    }

    /** 
     * 获取所有子id
     * @param mixed $pid
     * @return string
     */
    public function getAllIdsBypid($pid)
    {
        $res = db_cache('get-rule-ids-by-pid-'.$pid,function()use($pid){
            $res = AuthRule::where('pid', $pid)->where('status', 1)->select();
            $ids = [];
            if (!empty($res)) {
                foreach ($res as $v) {
                    $ids[] = $v['id'];
                    $ids = array_merge($ids, explode(',', $this->getAllIdsBypid($v['id'])));
                }
            }
            return implode(',', array_filter($ids));
        });
        return $res;
    }


}
