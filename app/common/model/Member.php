<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/2
 */
namespace app\common\model;

use app\common\validate\MemberValidate;
use fun\helper\StringHelper;
use think\exception\ValidateException;
use think\facade\Db;
use think\model\concern\SoftDelete;

class  Member extends BaseModel{
    /**
     * @var bool
     */
    use SoftDelete;


    
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public function getProvince(){

        $this->hasOne('Provinces','province','id');
    }
    public function getCity(){

        $this->hasOne('Provinces','city','id');
    }
    public function getDistrict(){

        $this->hasOne('Provinces','district','id');
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 登陆
     */
    public function login(){
        $data = request()->post();
        if(!request()->checkToken('__token__', request()->param())){
            throw new ValidateException('invalid token');
        }
        $member = $this->whereOr([
            [['email','=', $data['username']]],
            [['username','=', $data['username']]],
            [['mobile','=', $data['username']]]
        ])->find();
        if (!$member) throw new \Exception('email not registered yet');
        if ($member && $member->status == 0) throw new \Exception('The account is disabled, please contact the management');
        if (strlen($data['password']) < 6)  throw new \Exception('password length cannot be less than 6 characters');
        if (!captcha_check($data['vercode']))  throw new \Exception('verification_code_error');
        if (!password_verify($data['password'], $member->password))  throw new \Exception('wrong_password');
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        $member->login_num = $member->login_num + 1;
        $member->last_ip = $_SERVER['REMOTE_ADDR'];
        $member->last_login = time();
        $member->token = token();
        $member->login_time = time();
        if (!$member->save())  throw new \Exception('login failed');
        session('member', $member);
        $_COOKIE['mid']= $member->id;
    }

    /**
     * @return bool
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 注册
     */
    public function reg(){
        Db::startTrans();
        try {
            $data = request()->post();
            $member = $this->where('email', $data['email'])->find();
            if ($member) throw new \Exception('email already exists');
            if ($member && $member->status == 0) throw new \Exception('The account is disabled, please contact the management');
            if ($data['password'] != $data['repassword']) throw new \Exception('inconsistent passwords');
            try {
                validate(MemberValidate::class)
                    ->scene('Reg')
                    ->check($data);
            } catch (ValidateException $e) {
                throw new \Exception($e->getError());
            }
            if (!captcha_check($data['vercode'])) throw new \Exception('验证码错误');
            $num = rand(0, 13);
            $data['avatar'] = '/static/frontend/images/avatar/' . $num . '.jpg';
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            session('regData', $data);
            self::create($data);
            Db::commit();
            return true;
        }catch (\Exception $e){
            Db::rollBack();
            throw new \Exception($e->getMessage());
        }
//        发送邮件
//        $code = mt_rand('100000', '999999');
//        $time = 10 * 60;
//        $content = '亲爱的Fun用户:' . $data['username'] . '<br>您正在激活邮箱，您的验证码为:' . $code . '，请在' . $time / 60 . '分钟内进行验证';
//        try {
//            $param = ['to'=>$data['email'],'subject'=>'FunAdmin邮箱激活邮件','content'=>$content];
////            hook('sendEmail',$param);
//            cookie('code', $code, $time);
//            cookie('email', $data['email'], $time);
//            cookie('username', $data['username'], $time);
//            return true;
//        }catch (\Exception $e){
//            throw new \Exception($e->getMessage());
//        }
    }


    //发送邮件
    public function sendEmail($member)
    {
        $data = [$member->username, $member->email, $member->password];
        $token = json_decode(cookie('activeToken'));
        $validity = 2 * 3600;//有限期
        if(!$token || ($token && $token['time']<time()-$validity)) {
            $token = StringHelper::getToken($data);//验证码
            $tokenData = ['time' => $validity, 'token' => $token,'member_id'=>$member->id];
        }
        $link = __u('member/emailactive',['token' => $token]);
        $content = $this->_geteamilContent($validity/3600, $link);
        $param = ['to'=>$member->email,'subject'=>'FunAdmin 社区激活邮件','content'=>$content];
        $mail = json_decode( hook('sendEmail',$param),true);
        if($mail['code']>0){
            cookie('activeToken', json_encode($tokenData));
        }else{
            throw new Exception($mail['msg']);
        }
        return true;
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
}