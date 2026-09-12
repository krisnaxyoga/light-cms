<?php

namespace App\Libraries\Cache;

use CodeIgniter\Cache\CacheInterface;
use Config\Services;

/**
 * Thin wrapper around CI4's cache service adding simple tag invalidation
 * (PRD §3.6.A: page cache / object cache with invalidation on content
 * update). File/Redis cache handlers don't support wildcard deletes, so
 * tags are tracked as an explicit index of keys stored under the tag name.
 */
class CacheManager
{
    protected CacheInterface $cache;

    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache ?? Services::cache();
    }

    public function remember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        $value = $this->cache->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->cache->save($key, $value, $ttl);

        foreach ($tags as $tag) {
            $this->addKeyToTag($tag, $key);
        }

        return $value;
    }

    public function forget(string $key): bool
    {
        return $this->cache->delete($key);
    }

    public function flushTag(string $tag): void
    {
        $indexKey = $this->tagIndexKey($tag);
        $keys     = $this->cache->get($indexKey) ?? [];

        foreach ($keys as $key) {
            $this->cache->delete($key);
        }

        $this->cache->delete($indexKey);
    }

    public function flushAll(): bool
    {
        return $this->cache->clean();
    }

    protected function addKeyToTag(string $tag, string $key): void
    {
        $indexKey = $this->tagIndexKey($tag);
        $keys     = $this->cache->get($indexKey) ?? [];

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->cache->save($indexKey, $keys, 0); // no expiry — index itself is small
        }
    }

    protected function tagIndexKey(string $tag): string
    {
        return "tag_index.{$tag}";
    }
}
