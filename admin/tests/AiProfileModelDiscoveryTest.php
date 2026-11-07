<?php
declare(strict_types=1);
namespace Tests;

use app\common\ai\provider\OpenAiCompatibleGateway;
use PHPUnit\Framework\TestCase;

final class AiProfileModelDiscoveryTest extends TestCase
{
    public function test_private_ipv4_http_base_url_is_allowed_for_model_discovery(): void
    {
        $gateway=new OpenAiCompatibleGateway(new class implements \GuzzleHttp\ClientInterface {
            public function request($method,$uri,array $options=[]): \Psr\Http\Message\ResponseInterface { return new \GuzzleHttp\Psr7\Response(200,[],json_encode(['data'=>[['id'=>'local-model']]])); }
            public function send(\Psr\Http\Message\RequestInterface $request,array $options=[]): \Psr\Http\Message\ResponseInterface { return $this->request($request->getMethod(),(string)$request->getUri(),$options); }
            public function sendAsync(\Psr\Http\Message\RequestInterface $request,array $options=[]): \GuzzleHttp\Promise\PromiseInterface { return \GuzzleHttp\Promise\Create::promiseFor($this->send($request,$options)); }
            public function requestAsync($method,$uri,array $options=[]): \GuzzleHttp\Promise\PromiseInterface { return \GuzzleHttp\Promise\Create::promiseFor($this->request($method,$uri,$options)); }
            public function getConfig($option=null) { return $option===null?[]:null; }
        },['base_url'=>'http://192.168.200.58:3000/v1','protocol'=>'openai-chat','model'=>'local-model','api_key'=>'key']);
        self::assertSame([['id'=>'local-model']],$gateway->models());
    }
}
