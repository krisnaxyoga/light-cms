<?php

namespace App\Libraries\Cache;

use Config\LightCMS as LightCMSConfig;

/**
 * Model-level query caching helper (PRD §3.6.A.1). Wraps CacheManager
 * with the app's default query TTL and a naming convention so cache
 * keys don't collide between models.
 */
class QueryCache
{
    protected CacheManager $cache;
    protected int $defaultTtl;

    public function __construct(?CacheManager $cache = null)
    {
        $this->cache      = $cache ?? new CacheManager();
        $this->defaultTtl = config(LightCMSConfig::class)->queryCacheTTL;
    }

    public function remember(string $model, string $key, callable $callback, ?int $ttl = null, array $tags = [])
    {
        return $this->cache->remember(
            "query.{$model}.{$key}",
            $ttl ?? $this->defaultTtl,
            $callback,
            array_merge([$model], $tags)
        );
    }

    /**
     * Call after any write to $model so every cached query for it is
     * dropped rather than served stale.
     */
    public function invalidate(string $model): void
    {
        $this->cache->flushTag($model);
    }
}
