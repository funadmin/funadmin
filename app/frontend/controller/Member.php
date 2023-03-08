<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: http://www.Funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/11/27
 */
namespace app\frontend\controller;

use app\common\controller\Frontend;
use fun\helper\StringHelper;
use think\App;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;
class Member extends Frontend {

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new \app\common\model\Member();
        View::assign(['member'=>\session('member'),'action'=>request()->action()]);
    }
    /**
     * @return \think\Response
     * 个人首页
     */
    public function index(){

        if(!session('member')) $this->redirect(__u('login/index'));
        return view();

    }
    /**
     * @return \think\Response
     * 其他个人主页
     */
    public function home(){
        $id = $this->request->param('id');
        $name = $this->request->param('name');
        if($id){
            $ouser = $this->modelClass->find($id);
        }elseif($name){
            $ouser = $this->modelClass->where('username',$name)->find();
            if(!$ouser)$this->error('error/err');
            $id = $ouser->id;
        }else{
            $id = session('member.id');
            $ouser = session('member');
        }
        if(!$ouser){
            $this->redirect(__u('login/index'));
        }
        View::assign('ouser',$ouser);
        return view();

    }
    /**
     * @return \think\Response
     * 设置
     */
    public function set(){
        $member = $this->isLogin();
        if(!$member) $this->redirect(__u('login/index'));
        if($this->request->isPost()){
            $data = $this->request->post();
            if(isset($data['avatar'])){
                $save = $member->save($data);
            }elseif($member->email==$data['email']){
                $save = $member->save($data);
            }else{
                $rule = [
                    'email'=>'require|unique:member'
                ];
                try {
                    $this->validate($data,$rule);
                }catch (ValidateException $e){
                    $this->error(lang($e->getMessage()));
                }
                $save = $member->save($data);
            }
            if(!$save) $this->error(lang('modified failed'));
            session('member',$member);
            $this->success(lang('modified Successfully'));

        }
        $province = $this->getProvinces(0);
        View::assign('province',$province);
        return view();
    }
    //修改密码
    public function repass(){
        if(!$this->isLogin()) $this->redirect(__u('login/index'));
        if($this->request->isPost()){
            $member = $this->isLogin();
            $data = $this->request->post();
            $validate = new \app\frontend\validate\MemberValidate();
            $res = $validate->scene('setPass')->check($data);
            if(!$res){
                $this->error($validate->getError());
            }
            $old =   strip_tags($data['oldpassword']);
            $pass = strip_tags($data['password']);
            $repass = strip_tags($data['repassword']);
            if(!password_verify($old,$member['password']))   $this->error(lang('Old password error'));
            if($pass!=$repass) $this->error(lang('Repeat password error'));
            $member->password =   password_hash($data['password'],PASSWORD_BCRYPT);
            if(!$member->save()) $this->error(lang('edit failed'));
            $this->success(lang('edit successful'));
        }
    }
    //获取地区
    public function getProvinces($pid=0){

        $pid = $this->request->param('pid')?$this->request->param('pid'):$pid;
        $list = Db::name('provinces')->where('pid',$pid)->cache(true)->select();
        if($this->request->isAjax()){
            $this->success('','',$list);
        }else{
            return $list;
        }
    }

    /**
     * 激活邮箱
     */
    public function activate(){
        $member = $this->isLogin();
        if (!$member) $this->redirect(__u('login/index'));
        if($this->request->isPost()){
            try {
                $this->modelClass->sendEmail($member);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success(lang("sendEmail successful"));
        }
        return view();
    }
    //链接邮箱激活
    public function emailactive(){
        $token = $this->request->param('token');
        if($token){
            $check_token = cookie('activeToken');
            if($check_token){
                if($check_token['time']>time()-3600*2 && $check_token==$token){
                    $member = $this->modelClass->find($check_token['member_id']);
                    $member->email_validated=1;
                    if($member->save()) {
                        $info = ['code'=>1,'msg'=>'邮箱激活成功，去登录FUN社区,带来的快乐吧'];
                    }else{
                        $info = ['code'=>0,'msg'=>'激活失败，请重新发送链接激活'];
                    }
                }else{
                    $info = ['code'=>0,'msg'=>'链接过期或链接无效，请重新发送链接'];
                }

            }else{
                $info = ['code'=>0,'msg'=>'激活链接过期，请重新发送链接'];
            }
        }else{
            $info = ['code'=>0,'msg'=>'激活链接不正确'];
        }
        View::assign(['info'=>$info]);
        return view();

    }
    //发送邮件
    public function sendEmail()
    {
        $member = $this->isLogin();
        $data = [$member->username, $member->email, $member->password];
        $token = cookie('activeToken');
        $validity = 2 * 3600;//有限期
        if(!$token || ($token && $token['time']<time()-$validity)) {
            $token = StringHelper::getToken($data);//验证码
            $tokenData = ['time' => $validity, 'token' => $token,'member_id'=>$member->id];
        }
        $link = __u('member/emailactive',['token' => $token]);
        $content = $this->_geteamilContent($validity/3600, $link);
        $param = ['to'=>$member->email,'subject'=>'FunAdmin 社区激活邮件','content'=>$content];
        $mail = hook('sendEmail',$param);
        $mail = json_decode($mail,true);
        if($mail['code']>0){
            cookie('activeToken', json_encode($tokenData));
        }else{
            throw new Exception($mail['msg']);
        }

        return json($mail);
    }
    //邮箱内容
    protected function _geteamilContent($validity,$link){
        $str = "<div>请点击以下的链接验证您的邮箱，验证成功后就可以使用"
            .syscfg('site','site_name').
            "提供的服务了。</div>
            <tr> <td colspan='2' style='font-size:12px; line-height: 20px; padding-top: 14px;padding-bottom: 25px; color: #909090;'><div>该链接的有效期为"
            .$validity .
            "小时,如链接超过有效期请重新发送邮件  <a href='"
            .$link.
            "' style='color: #03c5ff; text-decoration:underline;' rel='noopener' target='_blank'>点击链接去激活邮箱
            </a></div><div style=\"padding-top:4px;\">(如果不能打开页面，请复制该地址到浏览器打开)</div></td></tr>";

        return $str;

    }
    /**
     * @return \think\Response
     * 退出
     */
    public function logout(){
        logout();
        $this->success('退成成功！',__u('login/index'));

    }

}