<?php
declare(strict_types=1);
namespace Tests\Cms;
require_once __DIR__.'/bootstrap.php';
use app\cms\service\FormCaptcha;
use app\cms\service\FormGuard;
use PHPUnit\Framework\TestCase;
use Webman\Captcha\PhraseBuilderInterface;
final class FormGuardTest extends TestCase
{
    private const SECRET='form-guard-test-secret';
    private int $now=1_800_000_000;
    private function guard(string $secret=self::SECRET): FormGuard
    {
        return new FormGuard($secret,fn(): int=>$this->now);
    }
    private static function rejection(callable $call): ?\InvalidArgumentException
    {
        try{$call();}catch(\InvalidArgumentException $e){return $e;}
        return null;
    }
    private static function assertRejected(string $key,int $code,callable $call): void
    {
        $e=self::rejection($call);
        self::assertNotNull($e,"expected {$key}");
        self::assertSame($key,$e->getMessage());
        self::assertSame($code,$e->getCode());
    }
    public function test_token_binds_site_and_form(): void
    {
        $guard=$this->guard();
        $token=$guard->issue('1','7');
        $this->now+=10;
        $guard->verify($token,'1','7');
        self::assertRejected('token_invalid',422,fn()=>$guard->verify($token,'2','7'));
        self::assertRejected('token_invalid',422,fn()=>$guard->verify($token,'1','8'));
        self::assertRejected('token_invalid',422,fn()=>$this->guard('another-secret')->verify($token,'1','7'));
        self::assertNotSame($token,$guard->issue('1','7'),'each token carries a fresh nonce');
    }
    public function test_token_rejects_under_3_seconds_and_over_7200_seconds(): void
    {
        $guard=$this->guard();
        $token=$guard->issue('1','7');
        self::assertRejected('too_fast',422,fn()=>$guard->verify($token,'1','7'));
        $this->now+=2;
        self::assertRejected('too_fast',422,fn()=>$guard->verify($token,'1','7'));
        $this->now+=1;
        $guard->verify($token,'1','7');
        $this->now+=7197;
        $guard->verify($token,'1','7');
        $this->now+=1;
        self::assertRejected('token_expired',422,fn()=>$guard->verify($token,'1','7'));
        $this->now-=7300;
        self::assertRejected('token_invalid',422,fn()=>$guard->verify($token,'1','7'));
    }
    public function test_tampered_token_is_rejected(): void
    {
        $guard=$this->guard();
        $token=$guard->issue('1','7');
        $this->now+=10;
        [$payload,$signature]=explode('.',$token);
        $decoded=base64_decode(strtr($payload,'-_','+/'));
        self::assertMatchesRegularExpression('/^1\|7\|1800000000\|[0-9a-f]+$/',$decoded);
        $encode=static fn(string $v): string=>rtrim(strtr(base64_encode($v),'+/','-_'),'=');
        $older=$encode(str_replace('|1800000000|','|1799999000|',$decoded));
        $moved=$encode(preg_replace('/^1\|7\|/','2|7|',$decoded));
        foreach([$older.'.'.$signature,$moved.'.'.$signature,$payload.'.'.$encode(str_repeat("\0",32)),$payload.'.'.strrev($signature),$payload,'',$payload.'.'.$signature.'.x','a.b','!!!.???'] as $bad){
            self::assertRejected('token_invalid',422,fn()=>$guard->verify($bad,'1','7'));
        }
        self::assertRejected('token_invalid',422,fn()=>$guard->verify($moved.'.'.$signature,'2','7'));
    }
    public function test_honeypot_is_triggered_only_by_non_empty_value(): void
    {
        $guard=$this->guard();
        self::assertFalse($guard->isHoneypot([]));
        self::assertFalse($guard->isHoneypot(['_hp'=>'']));
        self::assertFalse($guard->isHoneypot(['_hp'=>null]));
        self::assertTrue($guard->isHoneypot(['_hp'=>'http://spam']));
        self::assertTrue($guard->isHoneypot(['_hp'=>['x']]));
        self::assertTrue($guard->isHoneypot(['_hp'=>'0']));
    }
    public function test_throttle_blocks_sixth_attempt_in_window(): void
    {
        \Webman\Limiter\Limiter::init(null);
        $guard=$this->guard();
        $site='t'.bin2hex(random_bytes(6));
        for($i=0;$i<5;$i++) $guard->throttle($site,'7','203.0.113.9',5);
        self::assertRejected('too_many_requests',429,fn()=>$guard->throttle($site,'7','203.0.113.9',5));
        $guard->throttle($site,'7','203.0.113.10',5);
        $guard->throttle($site,'8','203.0.113.9',5);
        $other='t'.bin2hex(random_bytes(6));
        for($i=0;$i<60;$i++) $guard->throttle($other,'7','203.0.113.9',1000);
        self::assertRejected('too_many_requests',429,fn()=>$guard->throttle($other,'7','203.0.113.9',1000));
    }
    public function test_limiter_bucket_groups_ipv6_by_64_prefix(): void
    {
        self::assertSame(FormGuard::bucket('2001:db8:1:2::1'),FormGuard::bucket('2001:0db8:0001:0002:ffff:ffff:ffff:ffff'));
        self::assertNotSame(FormGuard::bucket('2001:db8:1:2::1'),FormGuard::bucket('2001:db8:1:3::1'));
        self::assertSame('203.0.113.9',FormGuard::bucket('203.0.113.9'),'IPv4 is kept per address');
        self::assertSame('203.0.113.9',FormGuard::bucket('::ffff:203.0.113.9'),'IPv4-mapped IPv6 is treated as IPv4');
        \Webman\Limiter\Limiter::init(null);
        $guard=$this->guard();
        $site='t'.bin2hex(random_bytes(6));
        for($i=1;$i<=5;$i++) $guard->throttle($site,'7',"2001:db8:9:9::$i",5);
        self::assertRejected('too_many_requests',429,fn()=>$guard->throttle($site,'7','2001:db8:9:9:abcd::1',5));
        $guard->throttle($site,'7','2001:db8:9:a::1',5);
    }
    public function test_captcha_is_single_use(): void
    {
        $phrases=new class implements PhraseBuilderInterface {
            public function build(){return 'aB3kz';}
            public function niceize($str){return $str;}
        };
        $captcha=new FormCaptcha(self::SECRET,$phrases);
        $issued=$captcha->issue();
        self::assertSame(['id','image'],array_keys($issued));
        self::assertMatchesRegularExpression('#^data:image/[a-z]+;base64,[A-Za-z0-9+/]+=*$#',$issued['image']);
        self::assertTrue($captcha->verify($issued['id'],'AB3KZ'),'case-insensitive');
        self::assertFalse($captcha->verify($issued['id'],'aB3kz'),'consumed after first verify');
        $wrong=$captcha->issue();
        self::assertFalse($captcha->verify($wrong['id'],'nope'));
        self::assertFalse($captcha->verify($wrong['id'],'aB3kz'),'a wrong guess consumes it');
        $forged=$captcha->issue();
        [$random]=explode('.',$forged['id']);
        self::assertFalse((new FormCaptcha('another-secret',$phrases))->verify($forged['id'],'aB3kz'));
        self::assertFalse($captcha->verify($random.'.'.str_repeat('0',64),'aB3kz'));
        self::assertFalse($captcha->verify($random,'aB3kz'));
        self::assertTrue($captcha->verify($forged['id'],'aB3kz'),'forged ids do not touch the stored answer');
    }
    public function test_secret_file_is_generated_with_0600(): void
    {
        $base=sys_get_temp_dir().'/cms-form-secret-'.bin2hex(random_bytes(6));
        $path=$base.'/cms/form.secret';
        $env=getenv('CMS_FORM_SECRET');
        putenv('CMS_FORM_SECRET');
        try{
            $first=new FormGuard(null,fn(): int=>$this->now,$path);
            self::assertFileExists($path);
            clearstatcache();
            self::assertSame(0600,fileperms($path)&0777);
            self::assertSame(0700,fileperms(dirname($path))&0777);
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/',(string)file_get_contents($path));
            $token=$first->issue('1','7');
            $this->now+=5;
            (new FormGuard(null,fn(): int=>$this->now,$path))->verify($token,'1','7');
            self::assertRejected('token_invalid',422,fn()=>$this->guard()->verify($token,'1','7'));
            putenv('CMS_FORM_SECRET=from-env');
            $fromEnv=new FormGuard(null,fn(): int=>$this->now,$path);
            self::assertRejected('token_invalid',422,fn()=>$fromEnv->verify($token,'1','7'));
            $this->now-=5;
            $envToken=$fromEnv->issue('1','7');
            $this->now+=5;
            $this->guard('from-env')->verify($envToken,'1','7');
        }finally{
            putenv($env===false?'CMS_FORM_SECRET':'CMS_FORM_SECRET='.$env);
            @unlink($path);@rmdir(dirname($path));@rmdir($base);
        }
    }
}
