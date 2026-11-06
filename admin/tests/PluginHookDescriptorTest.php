<?php
namespace Tests;
use app\common\plugin\sdk\{PluginActivationReader,PluginActivationSnapshot,Manifest};
use app\common\plugin\service\FrontendHookService;
use PHPUnit\Framework\TestCase;
final class PluginHookDescriptorTest extends TestCase
{

    public function test_core_descriptor_routes_are_not_owned_by_a_plugin_named_plugins(): void
    {
        $reader = new class('unused') extends PluginActivationReader {
            public function readBundle(): array
            {
                return [
                    'activation' => PluginActivationSnapshot::trusted([
                        'plugins' => [
                            'code' => 'plugins', 'enabled' => false, 'state' => 'disabled',
                            'needs_reinstall' => false, 'operation_token' => null, 'dependencies' => [],
                            'applications' => ['app' => false, 'admin' => false],
                        ],
                    ]),
                    'ownership' => \app\common\plugin\sdk\PluginOwnershipSnapshot::trusted([]),
                ];
            }
        };
        $guard = new \app\common\middleware\PluginApplicationGuardMiddleware($reader, '/unused');
        foreach (['/plugins/hooks/frontend', '/admin/plugins/hooks/frontend'] as $path) {
            $request = new \support\Request("GET $path HTTP/1.1\r\nHost: localhost\r\n\r\n");
            $request->controller = \app\common\plugin\controller\FrontendHooks::class;
            self::assertSame(200, $guard->process($request, static fn () => response('core descriptor'))->getStatusCode());
        }
    }

    public function test_exact_audience_permissions_and_no_application_required(): void {
        $reader=new class('unused') extends PluginActivationReader {
            public function read(): PluginActivationSnapshot { return PluginActivationSnapshot::trusted(['demo'=>['code'=>'demo','enabled'=>true,'state'=>'enabled','needs_reinstall'=>false,'operation_token'=>null,'dependencies'=>[],'version'=>'1.0.0','applications'=>['app'=>false,'admin'=>false]]]); }
            public function activeGeneration(): ?string {return str_repeat('a',32);}
        };
        $manifest=Manifest::fromCompiled('/unused/demo',['code'=>'demo','version'=>'1.0.0','adminWeb'=>['components'=>['Card'=>'Card.vue']], 'clientHooks'=>[
            ['id'=>'public','point'=>'demo.test','type'=>'action','file'=>'hooks.ts','export'=>'run','audiences'=>['public']],
            ['id'=>'private','point'=>'demo.test','type'=>'component','component'=>'Card','permission'=>'demo:card:read'],
        ]]);
        $service=new FrontendHookService($reader,fn()=>['demo'=>$manifest]);
        $public=$service->descriptors('public');
        self::assertSame(['public'],array_column($public['modules'][0]['clientHooks'],'id'));
        self::assertEquals((object)[], $public['modules'][0]['components']);
        self::assertSame([], $service->descriptors('admin',[])['modules']);
        self::assertSame(['private'],array_column($service->descriptors('admin',['demo:card:read'])['modules'][0]['clientHooks'],'id'));
    }
    public function test_untrusted_snapshot_returns_no_descriptors(): void {
        $reader=new class('unused') extends PluginActivationReader {public function read(): PluginActivationSnapshot{return PluginActivationSnapshot::untrusted();}};
        self::assertSame([], (new FrontendHookService($reader,fn()=>throw new \Exception('must not discover')))->descriptors('public')['modules']);
    }
    public function test_http_audience_validation_and_anonymous_authentication(): void {
        $controller=new \app\common\plugin\controller\FrontendHooks();
        foreach (['member'=>401,'admin'=>401,'invalid'=>400] as $audience=>$status) {
            $request=new \support\Request("GET /plugins/hooks/frontend?audience=".$audience." HTTP/1.1\r\nHost: localhost\r\n\r\n");
            self::assertSame($status,$controller->index($request)->getStatusCode());
        }
        $request=new \support\Request("GET /plugins/hooks/frontend?audience=public HTTP/1.1\r\nHost: localhost\r\n\r\n");
        self::assertSame(200,$controller->index($request)->getStatusCode());
    }
}
