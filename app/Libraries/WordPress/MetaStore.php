<?php

namespace App\Libraries\WordPress;

use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;

/**
 * get_post_meta()/update_post_meta() and the user/term equivalents.
 *
 * LightCMS has no meta concept of its own, so this is the real storage
 * (wp_postmeta / wp_usermeta / wp_termmeta). Values are serialized the
 * way WordPress does it, which is what makes ACF-style "array in a meta
 * field" code work unchanged.
 */
class MetaStore
{
    protected const TABLES = [
        'post' => ['wp_postmeta', 'post_id', 'meta_id'],
        'user' => ['wp_usermeta', 'user_id', 'umeta_id'],
        'term' => ['wp_termmeta', 'term_id', 'meta_id'],
    ];

    /** [type][objectId] => [key => [values]] */
    protected array $cache = [];

    /**
     * @return mixed Single value when $single, otherwise a list. Matches
     *               WP: '' for a missing single, [] for a missing list.
     */
    public function get(string $type, int $objectId, string $key = '', bool $single = false): mixed
    {
        $all = $this->allFor($type, $objectId);

        if ($key === '') {
            return $all;
        }

        $values = $all[$key] ?? [];

        if ($single) {
            return $values === [] ? '' : $values[0];
        }

        return $values;
    }

    public function update(string $type, int $objectId, string $key, mixed $value, mixed $prevValue = ''): bool
    {
        [$table, $column] = $this->meta($type);
        $db               = Database::connect();
        $stored           = OptionStore::maybeSerialize($value);

        $builder = $db->table($table)->where($column, $objectId)->where('meta_key', $key);

        if ($prevValue !== '' && $prevValue !== null) {
            $builder->where('meta_value', OptionStore::maybeSerialize($prevValue));
        }

        $exists = (clone $builder)->countAllResults() > 0;

        if ($exists) {
            $builder->update(['meta_value' => $stored]);
        } else {
            $db->table($table)->insert([$column => $objectId, 'meta_key' => $key, 'meta_value' => $stored]);
        }

        unset($this->cache[$type][$objectId]);

        return true;
    }

    public function add(string $type, int $objectId, string $key, mixed $value, bool $unique = false): int|false
    {
        [$table, $column] = $this->meta($type);
        $db               = Database::connect();

        if ($unique && $db->table($table)->where($column, $objectId)->where('meta_key', $key)->countAllResults() > 0) {
            return false;
        }

        $db->table($table)->insert([
            $column      => $objectId,
            'meta_key'   => $key,
            'meta_value' => OptionStore::maybeSerialize($value),
        ]);

        unset($this->cache[$type][$objectId]);

        return (int) $db->insertID();
    }

    public function delete(string $type, int $objectId, string $key, mixed $value = ''): bool
    {
        [$table, $column] = $this->meta($type);
        $builder          = Database::connect()->table($table)->where($column, $objectId)->where('meta_key', $key);

        if ($value !== '' && $value !== null) {
            $builder->where('meta_value', OptionStore::maybeSerialize($value));
        }

        $deleted = (bool) $builder->delete();
        unset($this->cache[$type][$objectId]);

        return $deleted;
    }

    /** @return array<string, list<mixed>> */
    public function allFor(string $type, int $objectId): array
    {
        if (isset($this->cache[$type][$objectId])) {
            return $this->cache[$type][$objectId];
        }

        [$table, $column] = $this->meta($type);

        try {
            $rows = Database::connect()->table($table)
                ->where($column, $objectId)
                ->orderBy($this->meta($type)[2], 'ASC')
                ->get()->getResultArray();
        } catch (DatabaseException $e) {
            // The compat tables are created by migration 000021. Reading
            // before that has run should degrade to "no meta", not fatal.
            log_message('error', "WP compat: cannot read {$table} — run `php spark migrate`. ({$e->getMessage()})");

            return $this->cache[$type][$objectId] = [];
        }

        $map = [];

        foreach ($rows as $row) {
            $map[$row['meta_key']][] = OptionStore::maybeUnserialize($row['meta_value']);
        }

        return $this->cache[$type][$objectId] = $map;
    }

    /**
     * Warm the cache for a whole result set in one query — used by the
     * loop so a template calling get_post_meta() per post does not fire
     * one query per post.
     */
    public function primePosts(array $postIds): void
    {
        $postIds = array_values(array_filter(array_map('intval', $postIds)));

        if ($postIds === []) {
            return;
        }

        try {
            $rows = Database::connect()->table('wp_postmeta')
                ->whereIn('post_id', $postIds)
                ->orderBy('meta_id', 'ASC')
                ->get()->getResultArray();
        } catch (DatabaseException $e) {
            log_message('error', 'WP compat: cannot read wp_postmeta — run `php spark migrate`. (' . $e->getMessage() . ')');

            return;
        }

        foreach ($postIds as $id) {
            $this->cache['post'][$id] ??= [];
        }

        foreach ($rows as $row) {
            $this->cache['post'][(int) $row['post_id']][$row['meta_key']][] = OptionStore::maybeUnserialize($row['meta_value']);
        }
    }

    protected function meta(string $type): array
    {
        if (! isset(self::TABLES[$type])) {
            throw new \InvalidArgumentException("Unsupported meta type: {$type}");
        }

        return self::TABLES[$type];
    }
}
