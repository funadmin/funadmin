<?php

declare(strict_types=1);

namespace fun\plugins;

/** 对每个插件运行时边界单独隔离，单个失败不阻塞其他插件。 */
final class PluginRuntimeBooter
{
    public function __construct(private readonly mixed $report)
    {
    }

    /** 按插件逐个执行全部边界，失败插件及其 dependent 不再继续。 */
    public function boot(array $manifests, array $boundaries): void
    {
        $validator = new DependencyValidator('', PHP_VERSION);
        $failed = [];
        foreach ($validator->topologicalSort($manifests) as $code => $manifest) {
            $failedDependency = null;
            foreach (array_keys($manifest->dependencies()) as $dependency) {
                if (isset($failed[$dependency])) {
                    $failedDependency = $dependency;
                    break;
                }
            }
            if ($failedDependency !== null) {
                $exception = new \RuntimeException('依赖插件运行时加载失败：' . $failedDependency);
                $failed[$code] = $exception;
                $this->reportSafely($code, 'dependency', $exception);
                continue;
            }
            foreach ($boundaries as $boundary => $load) {
                try {
                    $load($manifest);
                } catch (\Throwable $exception) {
                    $failed[$code] = $exception;
                    $this->reportSafely($code, (string) $boundary, $exception);
                    break;
                }
            }
        }
    }

    private function reportSafely(string $code, string $boundary, \Throwable $exception): void
    {
        try {
            ($this->report)($code, $boundary, $exception);
        } catch (\Throwable $reportException) {
            error_log(sprintf('插件运行时失败上报异常 [%s:%s]：%s；原异常：%s', $code, $boundary, $reportException->getMessage(), $exception->getMessage()));
        }
    }
}
