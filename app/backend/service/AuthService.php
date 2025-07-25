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
use fun\helper\SignHelper;
use think\facade\Cache;
use think\facade\Config;
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
     * @var $hrefId ;
     */
    protected $hrefId;
    /**
     * 当前管理员权限规则id
     * @var array
     */
    protected $adminRules = [];

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
        if ($auth = Config::get('auth')) {
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
                $allAuthIds = $this->getRules(session('admin.group_id'));
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
     * 验证权限
     * @return true|void
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
        if($this->request->isPost() && $cfg['isDemo'] == 1){
            $this->error(lang('Demo is not allow to change data'));
        }
        $adminId = session('admin.id');
        if ($adminId != $cfg['superAdminId']) {
            if ($this->request->isPost() && $cfg['isDemo'] == 1) $this->error(lang('Demo is not allow to change data'));
            $map= [
                ['href','=', $this->requesturl],
                ['module','=', $this->app]
            ];
            $cache_key = 'get-rule-id-by-href-'.md5(json_encode($map));
            $this->hrefId = db_cache($cache_key,function()use($map){
                return AuthRule::where($map)->where('status', 1)->value('id');
            });
            $hrefTemp = trim($this->requesturl, '/');
            $menuid = 0;
            if (Str::endsWith($hrefTemp, '/index')) {
                $where =[
                    ['href', '=', substr($hrefTemp, 0, strlen($hrefTemp) - 6)],
                    ['module', '=', $this->app]
                ];
                $cache_key = 'get-rule-id-by-href-'.md5(json_encode($where));
                $menuid = db_cache($cache_key,function()use($where){
                    return AuthRule::where($where)->where('status', 1)->value('id');
                });
            }
            if ($menuid) $this->hrefId = $menuid;
            //当前管理员权限
            $rules = $this->getRules(session('admin.group_id'));
            //用户权限规则id
            $this->adminRules = array_unique(array_filter(explode(',', $rules)));
            if ($this->hrefId) {
                if (!in_array($this->hrefId,  $this->adminRules)) $this->error(lang('Permission Denied'));
            }
        }
    }

    /**
     * 前台权限节点
     * @param $url
     * @return bool
     */
    public function nodeAccess($url)
    {
        // 判断权限验证开关
        $cfg = Config::get('funadmin');
        if (isset($cfg['auth_on']) && $cfg['auth_on'] == false) {
            return true;
        }
        $parse =  parse_url($url);
        $url = $parse['path'];
        if(Str::endsWith($url,'.' . config('view.view_suffix'))){
            $this->requesturl = (string)$url;
        }else{
            $this->requesturl = (string) __u($url);
        }
        if (Str::endsWith($this->requesturl, '.' . config('view.view_suffix'))) {

            $this->requesturl = Str::substr($this->requesturl, 0, strlen($this->requesturl) - strlen(config('view.view_suffix')) - 1);
        }
        if(!empty($parse['host']) && !empty($parse['scheme'])){
            $this->requesturl = $this->app.$this->requesturl;
        }
        $this->requesturl = trim($this->requesturl, '/');
        $requesturlArr = explode('/', $this->requesturl);
        $app = array_shift($requesturlArr);
        $this->requesturl = implode('/', $requesturlArr);
        if ($this->requesturl === '/')  return false;
        if (!$this->isLogin()) return false;
        $adminId = session('admin.id');
        if ($adminId != $cfg['superAdminId']) {
            $map = [
                ['href', '=', $this->requesturl],
                ['module', '=', $app]
            ];
            $cache_key = 'get-rule-id-by-module-href'.md5(json_encode($map));
            $this->hrefId = db_cache($cache_key,function()use($map){
                return AuthRule::where($map)->where('status', 1)->value('id');
            });
            $menuid = 0;
            if (Str::endsWith($this->requesturl, '/index')) {
                $where[] = [
                    ['href', '=', substr($this->requesturl, 0, strlen($this->requesturl) - 6)],
                    ['module', '=', $app]
                ];
                $cache_key = 'get-rule-id-by-module-href'.md5(json_encode($where));
                $menuid = db_cache($cache_key,function()use($where){
                    return AuthRule::where($where)->where('status', 1)->value('id');
                });
            }
            if ($menuid) $this->hrefId = $menuid;
            //当前管理员权限
            $rules = $this->getRules(session('admin.group_id'));
            //用户权限规则id
            $this->adminRules = array_unique(array_filter(explode(',', $rules)));
            if ($this->hrefId && in_array($this->hrefId, $this->adminRules)) return true;
        } else {//超管
            return true;
        }
        return false;
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
        //判断是否同一时间同一账号只能在一个地方登录// 要是备份还原的话，这里会有点问题
        $me = db_cache('admin-user',function()use($admin){
            return  AdminModel::find($admin['id']);
        });
        // if (!$me || $me['token'] != $admin['token']) {
        if (!$me) {
            $this->logout();
            return false;
        }
        //}
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
            if ($admin['status'] == 0) {
                throw new \Exception(lang('Account is disabled'));
            }
            if (!password_verify($password, $admin['password'])) {
                throw new \Exception(lang('Please check username or password'));
            }
            if (!$admin['group_id']) {
                throw new \Exception(lang('You dont have permission'));
            }
            $ip = request()->ip();
            $admin->lastloginip = $ip;
            $admin->ip = $ip;
            $admin->token = SignHelper::authSign($admin);
            $admin->save();
            $admin = $admin->toArray();
            $rules = $this->getRules($admin['group_id']);
            $admin['rules'] = $rules;
            if ($rememberMe) {
                $admin['expiretime'] = 30 * 24 * 3600 + time();
            } else {
                $admin['expiretime'] = config('session.expire') + time();
            }
            unset($admin['password']);
            Session::set('admin', $admin);
        } catch (\Exception $e) {
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
        Session::clear();
        Cookie::delete("rememberMe");
        return true;
    }


    /**
     * 获取用户组规则
     * @param mixed $groups
     * @return string|null
     */
    protected function getRules($groups)
    {
        if ($groups && in_array(1, explode(",", $groups))) {
            $rules = db_cache('super-admin-auth-group-rules',function(){
                $rules = AuthRule::where('status', 1)->cache('superAdmin', 24 * 3600)->column('id');
                $rules = implode(',', $rules);
                return $rules;
            });
        } else {
            //这一句有长度限制
//            $rules  = AuthGroupModel::where('id', 'in', $groups)->where('status', 1)->field('group_concat(rules)')->value('group_concat(rules)');
            $groups = is_string($groups)?explode(',', $groups):$groups;
            $key = 'auth-group-rules-'.implode(',',$groups);
            $rules = db_cache($key,function() use ($groups){
                $rules = AuthGroupModel::where('id', 'in', $groups)->where('status', 1)->column('rules');// 获取所有组的权限规则ID
                $rules = implode(',', $rules);// 获取用户组权限规则ID
                $rules = array_unique(explode(',', $rules));// 转换成数组并去重
                sort($rules);// 重新排序
                $rules = implode(',', $rules);//重新组合成字符串
                return $rules;
            });
            
        }
        $norules = db_cache('no-rules',function(){
            $norules = AuthRule::where('auth_verify', 0)->column('id');
            $norules = $norules ? implode(',', $norules) : '';
            return $norules;
        });
        $result = trim($rules . ',' . $norules, ',');
        return $result !== '' ? $result : null;
    }

    //获取左侧主菜单树形结构
    protected function authMenuNode($menu, $pid = 0, $rules = [])
    {
        $authrules = array_unique(explode(',', $this->getRules(session('admin.group_id'))));
        $list = array();
        foreach ($menu as $v) {
            $href = $v['href'];
            $url = parse_url($href);
            if (empty($url['host'])){
                $v['href'] = "/" . $v['module']. '/' . trim($v['href'], '/');
                $url = parse_url($v['href']);
                $path = $url['path'];
                $query = $url['query']??'';
                $query = trim($query.'&'.$v['query'],'&');
                $query = $query?'?'.$query :'';
                if ($v['menu_status'] == 1) {
                    $v['href'] = '/'.trim($path, '/').$query;
                    if (!Str::endsWith($path, '/index')) {
                        $v['href'] = '/'.trim($path, '/') . '/index'.$query;
                    }
                }
            }
            if ($v['pid'] == $pid) {
                if (session('admin.id') != 1) {
                    if (in_array($v['id'], $authrules)) {
                        $child = AuthRule::field('href,id')
                            ->where('status', 1)
                            ->where('menu_status', 1)
                            ->where('pid', $v['id'])->find();
                        //删除下级没有list的菜单权限
                        if (!$child) {
                            $v['child'] = [];
                            $list[] = $v;
                        } else {
                            $v['child'] = self::authMenuNode($menu, $v['id']);
                            $list[] = $v;
                        }
                    }
                } else {
                    $v['child'] = self::authMenuNode($menu, $v['id']);
                    $list[] = $v;
                }
            }
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
