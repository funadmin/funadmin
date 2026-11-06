<?php

declare(strict_types=1);

use app\admin\plugin\service\PluginPackagePipeline;
use app\common\plugin\sdk\ComposerConflictException;
use PHPUnit\Framework\TestCase;

final class PluginComposerPipelineTest extends TestCase
{
    public function testRejectedPreflightDiscardsBeforeCoordinatorAndDoesNotWriteFailureHistory(): void
    {
        $archive = tempnam(sys_get_temp_dir(), 'composer-pipeline-'); file_put_contents($archive, 'fixture');
        $packages = new class {
            public array $events = [];
            public function inspect(...$args): array { return ['code'=>'alpha','version'=>'1.0.0']; }
            public function stage(...$args): array { $this->events[] = 'stage'; return ['code'=>'alpha','version'=>'1.0.0','plugin_directory'=>'/unused/alpha']; }
            public function discard(array $staged): void { $this->events[] = 'discard'; }
            public function deploy(...$args): mixed { throw new LogicException('Must not deploy'); }
        };
        $history = []; $coordinated = false;
        $pipeline = new PluginPackagePipeline($packages, fn()=>true, fn()=>true,
            function ($row) use (&$history) { $history[] = $row; }, null,
            function () use (&$coordinated) { $coordinated = true; }, null, null,
            static function (array $staged): void { throw new ComposerConflictException([['package'=>'test/lib']]); });
        try { $pipeline->installLocal($archive); self::fail('Must request confirmation'); }
        catch (ComposerConflictException $e) { self::assertSame('test/lib', $e->conflicts[0]['package']); }
        finally { unlink($archive); }
        self::assertFalse($coordinated); self::assertSame([], $history); self::assertSame(['stage','discard'], $packages->events);
    }
    public function testSuccessfulPreflightStagesOnlyOnceAndCleanupWarningDoesNotBecomeFailure(): void
    {
        $archive = tempnam(sys_get_temp_dir(), 'composer-pipeline-'); file_put_contents($archive, 'fixture');
        $packages = new class {
            public int $stages = 0;
            public function inspect(...$args): array { return ['code'=>'alpha','version'=>'1.0.0']; }
            public function stage(...$args): array { $this->stages++; return ['code'=>'alpha','version'=>'1.0.0','plugin_directory'=>'/unused/alpha']; }
            public function deploy(...$args): mixed { return null; }
            public function finish(...$args): void { throw new RuntimeException('cleanup warning'); }
            public function discard(...$args): void { throw new LogicException('Cleanup must not run twice'); }
        };
        try {
            $pipeline = new PluginPackagePipeline($packages, fn()=>true, fn()=>true, null, fn()=>null, null, null, null, fn()=>null);
            $result = $pipeline->installLocal($archive);
            self::assertSame(['cleanup warning'], $result['warnings']); self::assertSame(1, $packages->stages);
        } finally { unlink($archive); }
    }

}
