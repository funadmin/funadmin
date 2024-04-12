<?php
declare(strict_types=1);

namespace think\middleware\throttle;

use Psr\SimpleCache\CacheInterface;

/**
 * 漏桶算法
 * Class LeakyBucket
 * @package think\middleware\throttle
 */
class LeakyBucket extends ThrottleAbstract
{

    public function allowRequest(string $key, float $micronow, int $max_requests, int $duration, CacheInterface $cache): bool
    {
        if ($max_requests <= 0) return false;

        $last_time = (float) $cache->get($key, 0);      // 最近一次请求
        $rate = (float) $duration / $max_requests;       // 平均 n 秒一个请求
        if ($micronow - $last_time < $rate) {
            $this->cur_requests = 1;
            $this->wait_seconds = ceil($rate - ($micronow - $last_time));
            return false;
        }

        $cache->set($key, $micronow, $duration);
        return true;
    }
}