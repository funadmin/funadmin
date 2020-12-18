<?php
/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/11/27
 */
namespace addons\bbs\frontend\controller;

use addons\bbs\common\model\Bbs;
use addons\bbs\common\model\BbsCollect;
use addons\bbs\common\model\BbsComment;
use addons\bbs\common\model\BbsMessage;
use FUN\helper\MailHelper;
use FUN\helper\SignHelper;
use FUN\helper\StringHelper;
use addons\bbs\common\model\User as MemberModel;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;
class Member extends Comm {

    public function initialize()
    {
        parent::initialize();

    }

    /**
     * @return \think\Response
     * 个人首页
     */
    public function index(){
        if(!$this->isLogin()) $this->redirect(url('login/login'));
        return view();

    }

    /**
     * @return \think\Response
     * 其他个人主页
     */
    public function home(){
        $id = input('id');
        $name = input('name');
        if($id){
            $ouser = MemberModel::find($id);
        }elseif($name){
            $ouser = MemberModel::where('username',$name)->find();
            if(!$ouser)$this->error('error/err');
            $id = $ouser->id;
        }else{
            $id = session('user.id');
            $ouser = session('user');
        }
        if(!$ouser){
            $this->redirect(url('login/login'));
        }
        $article = Bbs::where('status',1)->where('user_id',$id)
            ->withCount('comment')
            ->order('id desc')
            ->cache(3600)
            ->paginate($this->pageSize,false,['query'=>$this->request->param()]);
        $comment = BbsComment::where('user_id',$id)
            ->where('status',1)->paginate(10);
        View::assign('ouser',$ouser);
        View::assign('article',$article);
        View::assign('comment',$comment);
        return view();

    }
    /**
     * @return \think\Response
     * 设置
     */
    public function set(){
        $user = $this->isLogin();
        if(!$user) $this->redirect(url('login/login'));
        if($this->request->isPost()){
            $data = $this->request->post();
            if(!$user->save($data)) $this->error('修改失败！');
            session('user',$user);
            $this->success('修改成功！');

        }
        $province = $this->getRegion(0);
        View::assign('province',$province);
        return view();
    }
    //修改密码
    public function repass(){
        if(!$this->isLogin()) $this->redirect(url('login/login'));
        if($this->request->isPost()){

            $user = $this->isLogin();
            $data = input('post.','','trim');
            $validate = new \app\common\validate\User();
            $res = $validate->scene('setPass')->check($data);
            if(!$res){
                $this->error($validate->getError());
            }
            $old =   strip_tags($data['oldpassword']);
            $pass = strip_tags($data['password']);
            $repass = strip_tags($data['repassword']);
            if(!password_verify($old,$user['password']))   $this->error('旧密码错误');


            if($pass!=$repass) $this->error('重复密码错误');

            $user->password =   password_hash($data['password'],PASSWORD_BCRYPT, SignHelper::passwordSalt());
            if(!$user->save()) $this->error('修改失败！');
            $this->success('修改成功！');

        }

    }

    //消息
    public function message(){
        if(!$this->isLogin()) $this->redirect(url('login/login'));
        $message = BbsMessage::where('receive_id',session('user.id'))
            ->order('id desc')->paginate($this->pageSize, false, ['query' => $this->request->param()]);
        View::assign('message',$message);
        return view();
    }

    //帖子
    public function bbs(){
        if(!$this->isLogin()) $this->redirect(url('login/login'));
        $collect_ids = BbsCollect::where('user_id',session('user.id'))->column('bbs_id'    );
        if(!$collect_ids){
            $collect = [];
        }else {
            $collect = Bbs::where('status', 1)
                ->where('id', 'in', $collect_ids)
                ->order('id desc')->paginate($this->pageSize, false, ['query' => $this->request->param()]);
        }
        $bbs = Bbs::where('status',1)
            ->where('user_id',session('user.id'))
            ->withCount('comment')
            ->with([
                'user' => function($query){
                    $query->field('id,username,avatar,level_id');
                }])
            ->order('id desc')
            ->paginate($this->pageSize,false,['query'=>$this->request->param()]);
        View::assign('bbs',$bbs);
        View::assign('collect',$collect);
        return view();
    }

    //获取地区
    public function getRegion($pid=0){
        $info = Db::name('region')->where('pid',$pid)->select();
        return $info;
    }

    /**
     * 激活邮箱
     */
    public function activate(){
        $user = $this->isLogin();
        if(!$user) $this->redirect(url('login/login'));
        $email = $this->sendEmail()->getData();
        return view();

    }

    //链接邮箱激活
    public function emailactive(){
        $token = input('token');
        if($token){
            $check_token = cookie('activeToken');
            if($check_token){
                if($check_token['time']>time()-3600*2 && $check_token==$token){
                    $user = MemberModel::find($check_token['user_id']);
                    $user->email_validated=1;
                    if($user->save()) {
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
        $user = $this->isLogin();
        $data = [$user->username, $user->email, $user->password];
        $token = cookie('activeToken');
        $validity = 2 * 3600;//有限期
        if(!$token || ($token && $token['time']<time()-$validity)) {
            $token = StringHelper::getToken($data);//验证码
            $tokenData = ['time' => $validity, 'token' => $token,'user_id'=>$user->id];
        }
        $link = $this->BASE_URL . '/user/emailactive?token=' . $token;
        $content = $this->_geteamilContent($validity/3600, $link);
        $mail = MailHelper::sendEmail($user->email, 'FUNbbs 邮箱激活邮件', $content);
        if($mail['code']==1){
            cookie('activeToken', $tokenData);
        }
        return json($mail);
    }
    //邮箱内容
    protected function _geteamilContent($validity,$link){
        $str = "<div>请点击以下的链接验证您的邮箱，验证成功后就可以使用"
            .site_name().
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

        session('user',null);
        Session::clear();
        $this->success('退成成功！',url('index/index'));

    }

}