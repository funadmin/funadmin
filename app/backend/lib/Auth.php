<?php

/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */

namespace app\backend\lib;

use app\backend\model\Admin;
use app\backend\model\AuthGroup as AuthGroupModel;
use app\backend\model\AuthRule;
use app\common\traits\Jump;
use speed\helper\SignHelper;
use speed\helper\StringHelper;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Request;
use think\facade\Session;
use function GuzzleHttp\Psr7\str;

class Auth
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

    protected $controller;

    protected $action;

    protected $requesturl;

    protected $config;

    public function __construct()
    {
        if ($auth = Config::get('auth')) {
            $this->config = array_merge($this->config, $auth);
        }

        // 初始化request
        $this->request = Request::instance();
        $this->controller = parse_name($this->request->controller());
        $this->action = parse_name($this->request->action());
        $this->controller= $this->controller  ? $this->controller : 'index';
        $this->action = $this->action ?  $this->action : 'index';
        $url =  $this->controller . '/' .  $this->action;
        $this->requesturl = $url;
    }



    //获取左侧主菜单
    public function authMenu($arr, $pid = 0, $rules = [])
    {
        $authrules = explode(',', session('admin.rules'));
        $authopen = AuthRule::where('auth_open', 1)->column('id');
        if ($authopen) {
            $authrules = array_unique(array_merge($authrules, $authopen));
        }
        $list = array();
        foreach ($arr as $k => $v) {
            $v['href'] = url(trim($v['href'], ' '));
            if (session('admin.id') != 1) {
                if ($v['pid'] == $pid) {
                    if (in_array($v['id'], $authrules)) {
                        $v['child'] = self::authMenu($arr, $v['id']);
                        $list[] = $v;
                    }
                }
            } else {
                if ($v['pid'] == $pid) {
                    $v['child'] = self::authMenu($arr, $v['id']);
                    $list[] = $v;
                }
            }

        }
        return $list;

    }

    /**
     * 权限节点
     */
    public function nodeList()
    {
        $allAuthNode = [];
        if (session('admin')) {
            $allAuthNode = Cookie::get('allAuthNode_' . session('admin.id'));
            if (empty($allAuthNode)) {
                $allAuthIds = session('admin.rules');
                $allAuthNode = AuthRule::whereIn('id', ($allAuthIds))->column('href');
                $allAuthNode  =json_encode($allAuthNode);
                Cookie::set('allAuthNode_' . session('admin.id'), $allAuthNode);
            }
            $allAuthNode = json_decode($allAuthNode);
        }
        return $allAuthNode;

    }

    /*
    * 自定义菜单排列
    */
    public function menu($cate, $lefthtml = '|— ', $pid = 0, $lvl = 0, $leftpin = 0)
    {
        $arr = array();
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                $v['lvl'] = $lvl + 1;
                $v['leftpin'] = $leftpin + 0;
                $v['lefthtml'] = str_repeat($lefthtml, $lvl);
                $v['ltitle'] = $v['lefthtml'] . $v['title'];
                $arr[] = $v;
                $arr = array_merge($arr, self::menu($cate, $lefthtml, $v['id'], $lvl + 1, $leftpin + 20));
            }
        }

        return $arr;
    }

    public function cate($cate, $lefthtml = '|— ', $pid = 0, $lvl = 0, $leftpin = 0)
    {
        $arr = array();
        foreach ($cate as $v) {
            if ($v['pid'] == $pid) {
                $v['lvl'] = $lvl + 1;
                $v['leftpin'] = $leftpin + 0;
                $v['lefthtml'] = str_repeat($lefthtml, $lvl);
                $arr[] = $v;
                $arr = array_merge($arr, self::menu($cate, $lefthtml, $v['id'], $lvl + 1, $leftpin + 20));
            }
        }

        return $arr;
    }

    /*
     * 权限
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
     * 验证权限
     */
    public function checkNode()
    {
        $cfg = config('backend');
        if($this->requesturl=='/'){
            $this->error(lang('Login again'),  url('login/index'));
        }
        $adminId = session('admin.id');

        if (
            !in_array($this->controller, $cfg['noLoginController'])
            && !in_array($this->requesturl, $cfg['noLoginNode'])

        ) {

            empty($adminId) && $this->error('请先登录后台', url('login/index'));

            if(!$this->isLogin()){
                $this->error(lang('Please Login Again'), url('login/index'));
            }
            if ($adminId  && $adminId != $cfg['superAdminId']) {
                $this->hrefId = Db::name('auth_rule')->where('href', $this->requesturl)->value('id');
                //当前管理员权限
                $map['a.id'] = Session::get('admin.id');
                $rules = Db::name('admin')->alias('a')
                    ->join('auth_group ag', 'a.group_id = ag.id', 'left')
                    ->where($map)
                    ->value('ag.rules');
                //用户权限规则id
                $adminRules = explode(',', $rules);
                // 不需要权限的规则id;
                $noruls = AuthRule::where('auth_open', 1)->column('id');
                $this->adminRules = array_merge($adminRules, $noruls);
                if ($this->hrefId) {
                    // 不在权限里面，并且请求为post
                    if (!in_array($this->hrefId, $this->adminRules)) {
                        $this->error(lang('Permission denied'));
                    }
                } else {
                    if (!in_array($this->requesturl, $cfg['noRightNode'])) {
                        $this->error(lang('Permission denied'));
                    }
                }
//            未登录且不在范围内
            }
        }elseif(
            in_array($this->controller, $cfg['noLoginController'])
            && in_array($this->requesturl, $cfg['noLoginNode'])

        ){
          if($this->isLogin()){
              $this->redirect(url('index/index'));
          }
        }

//        return $this->adminRules;

    }

    /**
     * 检测是否登录
     *
     * @return boolean
     */
    public function isLogin()
    {
        $admin = session('admin');
        if (!$admin) {
            return false;
        }
        //判断是否同一时间同一账号只能在一个地方登录
        $me = Admin::getOne($admin['id']);


        if (!$me || $me['token'] != $admin['token']) {
            echo 1;
//            $this->logout();
            return false;
        }
        if(!session('admin.expiretime') || session('admin.expiretime')<time()){
//            $this->logout();
            echo 2;
            return false;
        }
        //判断管理员IP是否变动
        if (!isset($admin['lastloginip']) || $admin['lastloginip'] != request()->ip()) {
            echo 3;
//            $this->logout();
            return false;
        }
        return true;
    }



    /**
     * 根据用户名密码，验证用户是否能成功登陆
     * @param string $user
     * @param string $pwd
     * @return mixed
     * @throws \Exception
     */
    public function checkLogin($user, $password, $rememberMe)
    {

        try {
            $where['username'] = strip_tags(trim($user));
            $password = strip_tags(trim($password));
            $admin = Admin::where($where)->find();
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
                $admin['group_id'] = 1;
            }
            $ip = request()->ip();
            $admin->lastloginip = $ip;
            $admin->ip = request()->ip();
            $admin->token = SignHelper::authSign($admin);
            $admin->save();
            $admin = $admin->toArray();
            $rules = AuthGroupModel::where('id', $admin['group_id'])
                ->value('rules');
            $admin['rules'] = $rules;
            if ($rememberMe) {
                $admin['expiretime'] = 30*24*3600 +time();
            }else{
                $admin['expiretime'] = 7*24*3600 +time();
            }
            unset($admin['password']);
            Session::set('admin', $admin);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return true;
    }


    /**
     * 注销登录
     */
    public function logout()
    {
        $admin = Admin::find(intval(\session('admin.id')));
        if ($admin) {
            $admin->token = '';
            $admin->save();
        }
        Session::clear();
        Cookie::delete("rememberMe");
        return true;
    }


}
