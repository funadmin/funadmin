<?php

declare(strict_types=1);

namespace app\admin\plugin\service\concern;

use app\admin\plugin\PluginPreconditionException;
use app\common\plugin\model\Plugin;
use app\common\plugin\sdk\{ComposerConflictDetector, ComposerConflictException, DependencyValidator, HostPackageRepository, Manifest};

/** Composer acceptance is request-scoped; only successful operations persist it. */
trait PluginComposerDependencies
{
    private bool $acceptComposerConflicts = false;
    private array $pendingComposerAcknowledgements = [];
    private string $composerConflictActor = '';

    public function withComposerConflictAcceptance(bool $accept, callable $operation, string $actor = ''): mixed
    {
        $previous = [$this->acceptComposerConflicts, $this->pendingComposerAcknowledgements, $this->composerConflictActor];
        $this->acceptComposerConflicts = $accept;
        $this->pendingComposerAcknowledgements = [];
        $this->composerConflictActor = $actor;
        try { return $operation(); }
        finally { [$this->acceptComposerConflicts, $this->pendingComposerAcknowledgements, $this->composerConflictActor] = $previous; }
    }

    /** Validate before state mutation or loading plugin PHP, including staged packages. */
    public function assertComposerDependencies(Manifest $manifest): array
    {
        $root = $this->projectRoot ?? base_path();
        $host = new HostPackageRepository($root . '/vendor/composer/installed.php');
        try { (new DependencyValidator((string) config('funadmin.version'), PHP_VERSION, $host))->assertComposerSatisfied($manifest); }
        catch (\RuntimeException $e) { throw new PluginPreconditionException($e->getMessage(), 0, $e); }
        $others = [];
        foreach (Plugin::where('lifecycle_state', 'enabled')->where('status', 1)->where('needs_reinstall', 0)->whereNull('operation_token')->get() as $record) {
            $code = (string) $record->code;
            if ($code !== $manifest->code()) { $others[$code] = $root . '/plugins/' . $code; }
        }
        $conflicts = (new ComposerConflictDetector($host))->detect($manifest->directory(), $others);
        $record = $this->isInstall($manifest->code());
        $fingerprint = $conflicts === [] ? '' : ComposerConflictDetector::fingerprint($conflicts);
        $saved = (string) ($record?->composer_conflict_ack ?? '');
        if ($fingerprint !== '' && !hash_equals($saved, $fingerprint)) {
            if (!$this->acceptComposerConflicts) { throw new ComposerConflictException($conflicts); }
            if (!in_array('composer_conflict_ack', $this->infrastructure()->pluginColumns(), true)) {
                throw new PluginPreconditionException('请先在 admin/ 执行 php bin/console.php migrate，补齐 composer_conflict_ack 字段');
            }
            $this->pendingComposerAcknowledgements[$manifest->code()] = ['fingerprint'=>$fingerprint, 'conflicts'=>$conflicts];
        } elseif ($fingerprint === '' && $saved !== '') {
            $this->pendingComposerAcknowledgements[$manifest->code()] = ['fingerprint'=>'', 'conflicts'=>[]];
        }
        return $conflicts;
    }

    private function persistComposerAcknowledgement(string $code, string $token): void
    {
        $pending = $this->pendingComposerAcknowledgements[$code] ?? null;
        if ($pending === null) { return; }
        Plugin::withTrashed()->where('code', $code)->update(['composer_conflict_ack'=>$pending['fingerprint']]);
        if ($pending['conflicts'] !== []) {
            $this->audit()->composerWarning($code, $token, $pending['conflicts'], $this->composerConflictActor);
        }
        unset($this->pendingComposerAcknowledgements[$code]);
    }
}
