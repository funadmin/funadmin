<?php

declare (strict_types=1);
use PHPUnit\Framework\TestCase;
use app\common\plugin\sdk\{PluginActivationCompiler, PluginActivationReader};
use app\common\plugin\hook\HookRuntime;
final class PluginHookRuntimeTest extends TestCase
{
    private string $dir;
    private array $records = [];
    private array $manifests = [];
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hooks-' . bin2hex(random_bytes(8));
        mkdir($this->dir . '/plugins', 0755, true);
    }
    protected function tearDown(): void
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }
    private function plugin(string $code, string $source, array $hooks, array $deps = []): void
    {
        mkdir($this->dir . '/plugins/' . $code . '/hooks', 0755, true);
        file_put_contents($this->dir . '/plugins/' . $code . '/hooks/main.php', '<?php ' . $source);
        $this->records[$code] = ['code' => $code, 'status' => 1, 'lifecycle_state' => 'enabled'];
        $this->manifests[$code] = ['backendHooks' => $hooks, 'requires' => ['plugins' => $deps]];
    }
    private function hook(string $id, string $type = 'filter', int $priority = 100, string $point = 'test.run'): array
    {
        return compact('id', 'type', 'priority', 'point') + ['file' => 'hooks/main.php', 'handler' => $id];
    }
    private function runtime(?callable $reporter = null): HookRuntime
    {
        (new PluginActivationCompiler($this->dir . '/runtime', $this->dir . '/plugins'))->compile($this->records, $this->manifests);
        return new HookRuntime(new PluginActivationReader($this->dir . '/runtime'), $this->dir . '/plugins', $reporter ?? static function () {
        });
    }
    public function testCrossPluginOrderAndFalsyFilterValuesWithoutApplications(): void
    {
        $this->plugin('beta', "return ['second'=>fn(\$v)=>\$v === false ? null : 'wrong'];", [$this->hook('second', priority: 20)]);
        $this->plugin('alpha', "return ['first'=>fn(\$v)=>false];", [$this->hook('first', priority: 10)]);
        self::assertNull($this->runtime()->filter('test.run', 'input'));
    }
    public function testActionErrorsContinueAndContextCannotBeSpoofed(): void
    {
        $errors = [];
        $this->plugin('alpha', "return ['bad'=>function(){throw new \\RuntimeException('bad');},'good'=>function(\$p,\$c){\$GLOBALS['hook_seen']=\$c;}];", [$this->hook('bad', 'action', 1), $this->hook('good', 'action', 2)]);
        $this->runtime(function ($e) use (&$errors) {
            $errors[] = $e;
        })->action('test.run', null, ['pluginCode' => 'spoof']);
        self::assertCount(1, $errors);
        self::assertSame('alpha', $GLOBALS['hook_seen']['pluginCode']);
    }
    public function testDisableAcrossReadersAndDependencyGateBeforeInclude(): void
    {
        $this->plugin('alpha', "\$GLOBALS['hook_loaded']=true; return ['first'=>fn(\$v)=>'changed'];", [$this->hook('first')], ['missing' => '*']);
        $runtime = $this->runtime();
        unset($GLOBALS['hook_loaded']);
        self::assertSame('value', $runtime->filter('test.run', 'value'));
        self::assertArrayNotHasKey('hook_loaded', $GLOBALS);
        $this->manifests['alpha']['requires']['plugins'] = [];
        $this->runtime();
        self::assertSame('changed', $runtime->filter('test.run', 'value'));
        $this->records['alpha']['status'] = 0;
        $this->runtime();
        self::assertSame('value', $runtime->filter('test.run', 'value'));
    }
    public function testIntegrityFailureNeverIncludesAndFilterThrows(): void
    {
        $this->plugin('alpha', "return ['first'=>fn(\$v)=>'changed'];", [$this->hook('first')]);
        $runtime = $this->runtime();
        file_put_contents($this->dir . '/plugins/alpha/hooks/main.php', '<?php $GLOBALS["tampered"]=true; return [];');
        $this->expectException(RuntimeException::class);
        $runtime->filter('test.run', 'value');
    }
    public function testUntrustedActionReportsAndFilterThrows(): void
    {
        $errors = [];
        $runtime = $this->runtime(function ($e) use (&$errors) {
            $errors[] = $e;
        });
        file_put_contents($this->dir . '/runtime/active', 'broken');
        $runtime->action('test.run');
        self::assertCount(1, $errors);
        $this->expectException(RuntimeException::class);
        $runtime->filter('test.run', false);
    }
    public function testBoundNestedDispatchHasDepthLimitAndIndependentRoots(): void
    {
        $this->plugin('alpha', "return ['first'=>fn(\$v,\$c)=>\$c['hooks']->filter('test.run',\$v+1)];", [$this->hook('first')]);
        $runtime = $this->runtime();
        for ($i = 0; $i < 2; $i++) {
            try {
                $runtime->filter('test.run', 0);
                self::fail('missing limit');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('32', $e->getMessage());
            }
        }
    }
    public function testEmptyTrustedSnapshotPreservesValues(): void
    {
        $runtime = $this->runtime();
        $runtime->action('unknown.point');
        self::assertFalse($runtime->filter('unknown.point', false));
    }
    public function testNamedFunctionsClassMethodsAndInvokableObjects(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $name = 'HookCallable' . $suffix;
        $source = "function hookfn{$suffix}(\$v){return \$v.'f';} class {$name} {public static function apply(\$v){return \$v.'c';} public function __invoke(\$v){return \$v.'o';}} return ['fun'=>'hookfn{$suffix}','class'=>['{$name}','apply'],'object'=>new {$name}];";
        $this->plugin('alpha', $source, [$this->hook('fun', priority: 1), $this->hook('class', priority: 2), $this->hook('object', priority: 3)]);
        self::assertSame('fco', $this->runtime()->filter('test.run', ''));
    }
    public function testFacadeUsesContainerWithoutHttpRequest(): void
    {
        $runtime = $this->runtime();
        \support\Container::instance()->addDefinitions([HookRuntime::class => static fn() => $runtime]);
        try {
            self::assertSame('original', \app\common\plugin\hook\Hook::filter('missing.point', 'original'));
        } finally {
            \support\Container::instance()->addDefinitions([HookRuntime::class => static fn() => new HookRuntime(new PluginActivationReader(base_path('runtime/plugins/activation'), true), base_path('plugins'))]);
        }
    }
    public function testDefaultReaderAllowsOnlyAbsentActivationDirectory(): void
    {
        $reader = new PluginActivationReader($this->dir . '/runtime', true);
        self::assertTrue($reader->read()->isTrusted());
        self::assertFalse((new PluginActivationReader($this->dir . '/runtime'))->read()->isTrusted());
        mkdir($this->dir . '/runtime');
        self::assertFalse($reader->read()->isTrusted());
    }
    public function testOldSnapshotWithoutHooksRemainsTrusted(): void
    {
        $this->records = ['alpha' => ['code' => 'alpha', 'status' => 1, 'lifecycle_state' => 'enabled']];
        $runtime = $this->runtime();
        self::assertTrue((new PluginActivationReader($this->dir . '/runtime'))->read()->isTrusted());
        self::assertSame(0, $runtime->filter('test.run', 0));
    }
    public function testCachedCallableMapIsIncludedOnlyOncePerProcess(): void
    {
        $this->plugin('alpha', "\$GLOBALS['include_count']=(\$GLOBALS['include_count']??0)+1; return ['first'=>fn(\$v)=>\$v];", [$this->hook('first')]);
        $runtime = $this->runtime();
        unset($GLOBALS['include_count']);
        $runtime->filter('test.run', 1);
        $runtime->filter('test.run', 2);
        self::assertSame(1, $GLOBALS['include_count']);
    }
    public function testMalformedHashMetadataFailsClosedEvenWithMatchingEnvelope(): void
    {
        $this->plugin('alpha', "return ['first'=>fn(\$v)=>\$v];", [$this->hook('first')]);
        $this->runtime();
        $reader = new PluginActivationReader($this->dir . '/runtime');
        $file = $this->dir . '/runtime/generations/' . $reader->activeGeneration() . '/snapshot.json';
        $payload = json_decode(file_get_contents($file), true);
        $payload['plugins']['alpha']['backendHookFiles']['hooks/main.php'] = [];
        $payload['activation_hash'] = PluginActivationCompiler::integrityHash(array_intersect_key($payload, array_flip(['schema_version', 'generation', 'plugins'])));
        file_put_contents($file, json_encode($payload));
        self::assertFalse($reader->read()->isTrusted());
    }
    public function testInvalidCallableMapIsNotIncludedAgain(): void
    {
        $this->plugin('alpha', "\$GLOBALS['bad_include_count']=(\$GLOBALS['bad_include_count']??0)+1; return false;", [$this->hook('first', 'action')]);
        $runtime = $this->runtime();
        unset($GLOBALS['bad_include_count']);
        $runtime->action('test.run');
        $runtime->action('test.run');
        self::assertSame(1, $GLOBALS['bad_include_count']);
    }
    public function testEqualPrioritySortsByPluginThenId(): void
    {
        $this->plugin('beta', "return ['a'=>fn(\$v)=>\$v.'B'];", [$this->hook('a')]);
        $this->plugin('alpha', "return ['z'=>fn(\$v)=>\$v.'Z','a'=>fn(\$v)=>\$v.'A'];", [$this->hook('z'), $this->hook('a')]);
        self::assertSame('AZB', $this->runtime()->filter('test.run', ''));
    }
    public function testSymlinkReplacementIsRejectedBeforeInclude(): void
    {
        $this->plugin('alpha', "return ['first'=>fn(\$v)=>'changed'];", [$this->hook('first')]);
        $runtime = $this->runtime();
        $file = $this->dir.'/plugins/alpha/hooks/main.php';
        rename($file, $this->dir.'/outside.php');
        symlink($this->dir.'/outside.php', $file);
        $this->expectException(RuntimeException::class);
        $runtime->filter('test.run', 'original');
    }
    public function testFilterFailureStopsSubsequentHandlers(): void
    {
        $this->plugin('alpha', "return ['bad'=>function(){throw new \\RuntimeException('filter failed');},'late'=>function(){\$GLOBALS['late_hook']=true;}];", [$this->hook('bad', priority:1), $this->hook('late', priority:2)]);
        unset($GLOBALS['late_hook']);
        try { $this->runtime()->filter('test.run', 1); self::fail('filter must throw'); }
        catch (RuntimeException $error) { self::assertSame('filter failed', $error->getMessage()); }
        self::assertArrayNotHasKey('late_hook', $GLOBALS);
    }

    public function testNullHookSectionsAreRejected(): void
    {
        $this->records=['alpha'=>['code'=>'alpha','status'=>1,'lifecycle_state'=>'enabled']];
        $this->runtime();
        $reader=new PluginActivationReader($this->dir.'/runtime');
        $file=$this->dir.'/runtime/generations/'.$reader->activeGeneration().'/snapshot.json';
        $payload=json_decode(file_get_contents($file),true);
        $payload['plugins']['alpha']['backendHooks']=null;
        $payload['activation_hash']=PluginActivationCompiler::integrityHash(array_intersect_key($payload,array_flip(['schema_version','generation','plugins'])));
        file_put_contents($file,json_encode($payload));
        self::assertFalse($reader->read()->isTrusted());
    }

}
