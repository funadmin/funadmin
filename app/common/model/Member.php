<?php
/**
 * FunAadmin
 * ============================================================================
 * 版权所有 2017-2028 FunAadmin，并保留所有权利。
 * 网站地址: https://www.FunAadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/2
 */
namespace app\common\model;

use fun\helper\MailHelper;
use app\common\validate\MemberValidate;
use think\exception\ValidateException;

class  Member extends BaseModel{

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public function getProvince(){

        $this->hasOne('Region','province','id');
    }
    public function getCity(){

        $this->hasOne('Region','city','id');
    }
    public function getDistrict(){

        $this->hasOne('Region','district','id');
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
        $member->save = time();
        if (!$member->save())  throw new \Exception('login failed');
        session('member', $member);
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
        $code = mt_rand('100000', '999999');
        $time = 10 * 60;
        $content = '亲爱的Fun用户:' . $data['username'] . '<br>您正在激活邮箱，您的验证码为:' . $code . '，请在' . $time / 60 . '分钟内进行验证';
        $mail = MailHelper::sendEmail($data['email'], 'FunAdmin邮箱激活邮件', $content);
        if ($mail['code'] > 0) {
            cookie('code', $code, $time);
            cookie('email', $data['email'], $time);
            cookie('username', $data['username'], $time);
            return true;
        } else {
            throw new \Exception('发送失败');
        }
    }
}