<?php

namespace App\Libraries\WordPress\Bridge;

use App\Models\CategoryModel;
use App\Models\TagModel;
use Config\Database;
use WP_Term;

/**
 * LightCMS categories/tags <-> WP_Term.
 *
 * WordPress keeps every taxonomy in one term space, LightCMS has two
 * separate tables with their own auto-increment ids. Tags are therefore
 * offset by TAG_OFFSET so a term id is unambiguous everywhere — the same
 * offset the wp_terms SQL view uses, so $wpdb queries agree with the API.
 */
class TermMapper
{
    public const TAG_OFFSET = 1000000;

    public static function categoryTermId(int $categoryId): int
    {
        return $categoryId;
    }

    public static function tagTermId(int $tagId): int
    {
        return $tagId + self::TAG_OFFSET;
    }

    /** @return array{0: string, 1: int} [taxonomy, local id] */
    public static function split(int $termId): array
    {
        return $termId > self::TAG_OFFSET
            ? ['post_tag', $termId - self::TAG_OFFSET]
            : ['category', $termId];
    }

    public static function fromCategory(array $row): WP_Term
    {
        $id = (int) $row['id'];

        return new WP_Term([
            'term_id'          => $id,
            'term_taxonomy_id' => $id,
            'name'             => (string) $row['name'],
            'slug'             => (string) $row['slug'],
            'taxonomy'         => 'category',
            'description'      => (string) ($row['description'] ?? ''),
            'parent'           => (int) ($row['parent_id'] ?? 0),
            'count'            => (int) ($row['count'] ?? 0),
        ]);
    }

    public static function fromTag(array $row): WP_Term
    {
        $id = static::tagTermId((int) $row['id']);

        return new WP_Term([
            'term_id'          => $id,
            'term_taxonomy_id' => $id,
            'name'             => (string) $row['name'],
            'slug'             => (string) $row['slug'],
            'taxonomy'         => 'post_tag',
            'description'      => (string) ($row['description'] ?? ''),
            'parent'           => 0,
            'count'            => (int) ($row['count'] ?? 0),
        ]);
    }

    public static function find(int $termId, string $taxonomy = ''): ?WP_Term
    {
        [$detected, $localId] = static::split($termId);
        $taxonomy = $taxonomy !== '' ? $taxonomy : $detected;

        if ($taxonomy === 'post_tag') {
            $row = (new TagModel())->find($localId);

            return $row ? static::fromTag($row) : null;
        }

        $row = (new CategoryModel())->find($localId);

        return $row ? static::fromCategory($row) : null;
    }

    public static function findBySlug(string $slug, string $taxonomy = 'category'): ?WP_Term
    {
        if ($taxonomy === 'post_tag') {
            $row = (new TagModel())->findBySlug($slug);

            return $row ? static::fromTag($row) : null;
        }

        $row = (new CategoryModel())->findBySlug($slug);

        return $row ? static::fromCategory($row) : null;
    }

    /** @return list<WP_Term> */
    public static function forPost(int $postId, string $taxonomy = 'category'): array
    {
        $db = Database::connect();

        if ($taxonomy === 'post_tag') {
            $rows = $db->table('tags t')
                ->select('t.id, t.name, t.slug, t.description')
                ->join('post_tags pt', 'pt.tag_id = t.id')
                ->where('pt.post_id', $postId)
                ->orderBy('t.name', 'ASC')
                ->get()->getResultArray();

            return array_map(static::fromTag(...), $rows);
        }

        $rows = $db->table('categories c')
            ->select('c.id, c.name, c.slug, c.description, c.parent_id')
            ->join('post_categories pc', 'pc.category_id = c.id')
            ->where('pc.post_id', $postId)
            ->orderBy('c.name', 'ASC')
            ->get()->getResultArray();

        return array_map(static::fromCategory(...), $rows);
    }

    /**
     * get_terms()-style listing with the counts filled in.
     *
     * @return list<WP_Term>
     */
    public static function all(string $taxonomy = 'category', bool $hideEmpty = false, int $limit = 0, string $orderBy = 'name', string $order = 'ASC'): array
    {
        $db = Database::connect();

        if ($taxonomy === 'post_tag') {
            $builder = $db->table('tags t')
                ->select('t.id, t.name, t.slug, t.description, COUNT(pt.post_id) AS count')
                ->join('post_tags pt', 'pt.tag_id = t.id', 'left')
                ->groupBy('t.id');
            $column = 't.' . (in_array($orderBy, ['name', 'slug', 'id'], true) ? $orderBy : 'name');
        } else {
            $builder = $db->table('categories c')
                ->select('c.id, c.name, c.slug, c.description, c.parent_id, COUNT(pc.post_id) AS count')
                ->join('post_categories pc', 'pc.category_id = c.id', 'left')
                ->groupBy('c.id');
            $column = 'c.' . (in_array($orderBy, ['name', 'slug', 'id'], true) ? $orderBy : 'name');
        }

        $builder->orderBy($column, strtoupper($order) === 'DESC' ? 'DESC' : 'ASC');

        if ($hideEmpty) {
            $builder->having('count >', 0);
        }

        $rows = $limit > 0 ? $builder->get($limit)->getResultArray() : $builder->get()->getResultArray();
        $make = $taxonomy === 'post_tag' ? static::fromTag(...) : static::fromCategory(...);

        return array_map($make, $rows);
    }
}
