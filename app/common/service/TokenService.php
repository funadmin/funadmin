<?php

namespace app\common\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\App;

class TokenService extends AbstractService
{

    public function __construct(App $app)
    {
        // 如果 AbstractService 有构造函数参数或初始化逻辑，可以在需要时调用 parent::__construct();
        parent::__construct($app);
    }

    /**
     * 生成 JWT 令牌
     *
     * @param array $payload
     * @param int $ttl 令牌有效期（秒）
     * @param string $type 令牌类型 ('access' 或 'refresh')
     * @return string
     */
    public function build(array $payload, string $type = 'access'): string
    {
        $secretKey = $type === 'access' ? config('api.jwt_secret') : config('api.refresh_jwt_secret');
        $issuedAt = time();
        $ttl = $type === 'access' ? config('api.access_token_ttl') : config('api.refresh_token_ttl');
        $expirationTime = $issuedAt + $ttl;

        $tokenPayload = [
            'iat' => $issuedAt, // 签发时间
            'exp' => $expirationTime, // 过期时间
            'data' => $payload, // 用户数据
            'iss' => "funadmin.com", // 签发组织
            'aud' => "funadmin", // 签发作者
            'type' => $type, // 令牌类型
        ];

        return JWT::encode($tokenPayload, $secretKey, 'HS256');
    }

    /**
     * 验证 JWT 令牌
     *
     * @param string $token
     * @param string $type 令牌类型 ('access' 或 'refresh')
     * @return array|false
     */
    public function validateToken(string $token, string $type = 'access')
    {
        $secretKey = $type === 'access' ? config('api.jwt_secret') : config('api.refresh_jwt_secret');
        try {
            JWT::$leeway = 30;//当前时间减去60，把时间留点余地
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

            if ($decoded->type !== $type) {
                return false;
            }
        } catch (\Firebase\JWT\SignatureInvalidException $e) {  //签名不正确
            return false;
//            throw new \Exception ($e->getMessage());
        } catch (\Firebase\JWT\BeforeValidException $e) {  // 签名在某个时间点之后才能用
            return false;
//            throw new \Exception($e->getMessage());
        } catch (\Firebase\JWT\ExpiredException $e) {  // token过期
            return false;
//            throw new \Exception($e->getMessage());
        } catch (\Exception $e) {  //其他错误
            return false;
//            throw new \Exception($e->getMessage());
        }
        return (array)$decoded->data;

    }
}
