<?php
declare(strict_types=1);
namespace Tests;

use app\admin\plugin\service\{PluginMarketplaceService,PluginService};
use app\common\plugin\marketplace\{CloudAccountSession,MarketplaceException,NativeMarketplaceAdapter,NativeMarketplaceHttpTransport,SessionStore};
use app\common\plugin\marketplace\dto\{MarketplaceSearchRequestDto,UpdateCheckRequestDto};
use GuzzleHttp\{Client,HandlerStack,Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class MarketplaceConfigurationTest extends TestCase
{
    public function test_unconfigured_marketplace_skips_automatic_update_check(): void
    {
        $previous=getenv('PLUGIN_MARKETPLACE_URL');putenv('PLUGIN_MARKETPLACE_URL=');
        \Webman\Config::load(config_path(),['route']);
        try {
            self::assertEmpty(config('plugins.marketplace.url'));
            self::assertEmpty(config('funadmin.api_domain'));
            $service=PluginMarketplaceService::create(new PluginService());
            $error=null;$updates=null;
            try { $updates=$service->checkUpdates(new UpdateCheckRequestDto([['code'=>'cms','code_version'=>'1.0.0','db_version'=>'001_cms','modified'=>false]])); }
            catch(\Throwable $exception) { $error=$exception->getMessage(); }
            self::assertNull($error,'Unconfigured update checks must not access a cloud session or transport');
            self::assertSame([],$updates);
        } finally {
            putenv($previous===false?'PLUGIN_MARKETPLACE_URL':'PLUGIN_MARKETPLACE_URL='.$previous);
            \Webman\Config::load(config_path(),['route']);
        }
    }

    public function test_invalid_marketplace_addresses_fail_before_any_network_request(): void
    {
        foreach(['','market.example.test','/market','ftp://market.example.test','https:///market'] as $domain) {
            $history=[];$stack=HandlerStack::create(new MockHandler([new Response(200,[],'{"code":200,"data":{}}')]));$stack->push(Middleware::history($history));
            $transport=new NativeMarketplaceHttpTransport(new Client(['handler'=>$stack]),$domain);
            try { $transport('/api/v3/plugins',[],''); self::fail('Invalid marketplace address accepted: '.$domain); }
            catch(MarketplaceException $error) {
                self::assertSame(422,$error->status);
                self::assertStringContainsString('PLUGIN_MARKETPLACE_URL',$error->getMessage());
            }
            self::assertSame([],$history);
        }
    }

    public function test_explicit_market_search_preserves_the_actionable_configuration_error(): void
    {
        $store=new class implements SessionStore {
            public function get(string $key): mixed { return null; }
            public function set(string $key,mixed $value): void {}
            public function delete(string $key): void {}
        };
        $adapter=new NativeMarketplaceAdapter(new NativeMarketplaceHttpTransport(new Client(['handler'=>new MockHandler([])]),''),new CloudAccountSession($store),'1.0.0',PHP_VERSION);
        try { $adapter->search(new MarketplaceSearchRequestDto());self::fail('Expected a missing-marketplace configuration message'); }
        catch(MarketplaceException $error) { self::assertSame(422,$error->status);self::assertStringContainsString('PLUGIN_MARKETPLACE_URL',$error->getMessage());self::assertStringNotContainsString('网络请求失败',$error->getMessage()); }
    }

    public function test_configured_marketplace_keeps_absolute_url_base_path_token_and_timeouts(): void
    {
        $history=[];$stack=HandlerStack::create(new MockHandler([new Response(200,[],'{"code":200,"data":{"items":[]}}')]));$stack->push(Middleware::history($history));
        $transport=new NativeMarketplaceHttpTransport(new Client(['handler'=>$stack]),' https://market.example.test/market/ ',15,4);
        self::assertSame(200,$transport('/api/v3/plugins/check-updates',['installed'=>[]],'test-token')['code']);
        self::assertSame('https://market.example.test/market/api/v3/plugins/check-updates',(string)$history[0]['request']->getUri());
        self::assertSame('Bearer test-token',$history[0]['request']->getHeaderLine('Authorization'));
        self::assertSame(15,$history[0]['options']['timeout']);self::assertSame(4,$history[0]['options']['connect_timeout']);
    }

    public function test_configured_service_continues_checking_nonempty_installed_lists(): void
    {
        $calls=[];
        $store=new class implements SessionStore {
            public function get(string $key): mixed { return null; }
            public function set(string $key,mixed $value): void {}
            public function delete(string $key): void {}
        };
        $gateway=new NativeMarketplaceAdapter(static function(string $endpoint,array $params,string $token)use(&$calls):array {
            $calls[]=[$endpoint,$params];return ['code'=>200,'data'=>['items'=>[]]];
        },new CloudAccountSession($store),'1.0.0',PHP_VERSION);
        $pipeline=new \app\admin\plugin\service\PluginPackagePipeline(new \stdClass(),static fn()=>true,static fn()=>true);
        $downloader=new \app\common\plugin\package\PluginPackageDownloader(sys_get_temp_dir().'/unused-market-download',static function():void{},null);
        $service=new PluginMarketplaceService($gateway,$pipeline,$downloader);
        $installed=[['code'=>'cms','code_version'=>'1.0.0','db_version'=>'001_cms','modified'=>false]];
        self::assertSame([],$service->checkUpdates(new UpdateCheckRequestDto($installed)));
        self::assertSame('/api/v3/plugins/check-updates',$calls[0][0]);
        self::assertSame($installed,$calls[0][1]['installed']);
        self::assertSame([],$service->checkUpdates(new UpdateCheckRequestDto([])));
        self::assertCount(1,$calls);
    }
}
