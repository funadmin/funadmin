<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\service;
use Defuse\Crypto\{Crypto,Key};
use RuntimeException;
final class ProfileSecret
{
    public function __construct(private readonly string $masterKey){}
    private function key():Key{try{return Key::loadFromAsciiSafeString($this->masterKey);}catch(\Throwable){throw new RuntimeException('请先配置有效的 OAUTH_ENCRYPTION_KEY 加密主密钥',503);}}
    public function seal(string $id,array $credentials):string{return Crypto::encrypt(json_encode(['purpose'=>'plugin:oss:credentials','profile'=>$id,'credentials'=>$credentials],JSON_THROW_ON_ERROR),$this->key());}
    public function open(string $id,string $cipher):array{
        $key=$this->key();try{$data=json_decode(Crypto::decrypt($cipher,$key),true,512,JSON_THROW_ON_ERROR);if(($data['purpose']??'')!=='plugin:oss:credentials'||($data['profile']??'')!==$id||!is_array($data['credentials']??null))throw new RuntimeException();return $data['credentials'];}catch(\Throwable){throw new RuntimeException('云存储凭据不可用，请检查原加密主密钥或重新填写凭据',503);}
    }
}
