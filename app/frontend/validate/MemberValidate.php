<?php
namespace app\frontend\validate;

use think\Validate;

class MemberValidate extends Validate
{
    protected $rule = [
        'username|用户名' => 'require|min:2|max:18|unique:member',
        'email|邮箱' => 'require|email|unique:member',
        'password|密码' => 'require|min:6|max:20',
        'repassword|确认密码'=>'require|confirm:password',
        'nickname|昵称' => 'require|min:2|max:20',
        'vercode|校验码' => 'require|max:6',
        'sign|签名' => 'min:10|max:100',
        'sex|性别' => 'require',
        'oldpassword|旧密码' => 'require|min:6|max:20',
    ];

    protected $message  =   [
        'repassword.confirm:password' => '密码不一致',
        'username.max'     => '名称最多不能超过25个字符',
        'username.unique'     => '名称已经存在',
        'username.min'     => '名称最多不能少于2个字符',
        'age.number'   => '年龄必须是数字',
        'age.between'  => '年龄只能在1-120之间',
        'email'        => '邮箱格式错误',
    ];
    //邮件邮件码验证
    public function sceneCode()
    {
        return $this->only(['vercode']);
    }

    //username登陆验证场景
    public function sceneLoginUsername()
    {
        return $this->only(['username','password','vercode'])->remove('username', 'unique');
    }
    //emai登陆验证场景
    public function sceneLoginEmail()
    {
        return $this->only(['email','password','vercode'])->remove('email', 'unique');
    }

    //注册验证场景
    public function sceneReg()
    {
        return $this->only(['username','email','password','repassword','vercode']);
    }
    //注册验证场景
    public function sceneRegActive()
    {
        return $this->only(['username','email','vercode']);
    }
    //密码找回
    public function sceneForget()
    {
        return $this->only(['email','vercode'])->remove('email', 'unique');
    }
    //密码重设
    public function sceneRepass()
    {
        return $this->only(['password','repassword','vercode']);
    }
    //用户资料
    public function sceneSet()
    {return $this->only(['email','nickname','city','sex','sign'])->remove('email','unique');
    }
    //设置新密码
    public function sceneSetpass()
    {
        return $this->only(['oldpassword','password','repassword']);
    }
}