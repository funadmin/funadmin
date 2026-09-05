<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\validate\MemberValidate;
use fun\helper\IpHelper;
use think\exception\ValidateException;

function fieldGovernanceExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function emailWithLength(int $length): string
{
    $suffix = '@example.com';
    return str_repeat('a', $length - strlen($suffix)) . $suffix;
}

function registrationEmailAccepted(string $email): bool
{
    try {
        (new MemberValidate())
            ->only(['email'])
            ->remove('email', 'unique')
            ->check(['email' => $email]);
        return true;
    } catch (ValidateException) {
        return false;
    }
}

fieldGovernanceExpect(registrationEmailAccepted(emailWithLength(60)), '注册邮箱应接受 60 字符边界值');
fieldGovernanceExpect(!registrationEmailAccepted(emailWithLength(61)), '注册邮箱应拒绝超过 60 字符的值');

fieldGovernanceExpect((bool) IpHelper::is_ip('203.0.113.9'), 'IP helper 应接受合法 IPv4');
fieldGovernanceExpect((bool) IpHelper::is_ip('2001:db8::1'), 'IP helper 应接受合法 IPv6');
fieldGovernanceExpect(!(bool) IpHelper::is_ip('999.1.1.1'), 'IP helper 应拒绝非法 IPv4');
fieldGovernanceExpect(!(bool) IpHelper::is_ip('2001:db8:::1'), 'IP helper 应拒绝非法 IPv6');

$memberSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/model/Member.php');
$loginStart = strpos($memberSource, 'public function login()');
$registrationStart = strpos($memberSource, 'public function reg()');
$loginSource = $loginStart !== false && $registrationStart !== false
    ? substr($memberSource, $loginStart, $registrationStart - $loginStart)
    : '';

fieldGovernanceExpect(str_contains($loginSource, 'request()->ip()'), 'Member 登录必须从 request()->ip() 获取客户端 IP');
fieldGovernanceExpect(!str_contains($loginSource, '$_SERVER'), 'Member 登录不得直接读取 $_SERVER');

echo "field governance behavior test passed\n";
