<?php

namespace App\Libraries\WordPress;

use App\Libraries\WordPress\Bridge\TermMapper;
use Config\Database;

/**
 * Translates a WP_Query argument array into SQL against the LightCMS
 * tables. Supports the arguments classic themes actually use; anything
 * unrecognised is ignored rather than fatal, and `wp:doctor` reports
 * which unsupported args a theme passes.
 */
class QueryBuilder
{
    public const SUPPORTED_ARGS = [
        'post_type', 'post_status', 'posts_per_page', 'numberposts', 'paged', 'offset', 'nopaging',
        'orderby', 'order', 'name', 'pagename', 'p', 'page_id', 'post__in', 'post__not_in',
        'category_name', 'cat', 'category__in', 'tag', 'tag_id', 'tag__in', 's', 'author', 'author_name',
        'meta_key', 'meta_value', 'meta_compare', 'meta_query', 'tax_query', 'year', 'monthnum', 'day',
        'fields', 'ignore_sticky_posts', 'suppress_filters', 'no_found_rows', 'post_parent', 'exclude', 'include',
    ];

    /**
     * @return array{rows: list<array>, found: int}
     */
    public static function run(array $args): array
    {
        $db      = Database::connect();
        $builder = $db->table('posts p')->select('p.*');
        $args    = static::normalise($args);

        // --- post type / status ------------------------------------------
        $types = (array) $args['post_type'];

        if (! in_array('any', $types, true)) {
            $builder->whereIn('p.post_type', $types);
        }

        $statuses = array_values(array_filter(array_map(
            static fn ($status) => Bridge\PostMapper::statusFromWp((string) $status),
            (array) $args['post_status']
        )));

        if (! in_array('any', (array) $args['post_status'], true) && $statuses !== []) {
            $builder->whereIn('p.status', array_unique($statuses));

            if (in_array('published', $statuses, true) && count($statuses) === 1) {
                $builder->where('(p.published_at IS NULL OR p.published_at <= NOW())', null, false);
            }
        }

        // --- single post selectors ---------------------------------------
        if ($args['p']) {
            $builder->where('p.id', (int) $args['p']);
        }

        if ($args['name'] !== '') {
            $builder->where('p.slug', $args['name']);
        }

        if ($args['post__in'] !== []) {
            $builder->whereIn('p.id', array_map('intval', $args['post__in']));
        }

        if ($args['post__not_in'] !== []) {
            $builder->whereNotIn('p.id', array_map('intval', $args['post__not_in']));
        }

        // --- author -------------------------------------------------------
        if ($args['author']) {
            $builder->where('p.author_id', (int) $args['author']);
        }

        if ($args['author_name'] !== '') {
            $builder->join('users u', 'u.id = p.author_id')->where('u.username', $args['author_name']);
        }

        // --- taxonomy ------------------------------------------------------
        foreach (static::taxonomyFilters($args) as $filter) {
            [$taxonomy, $termIds] = $filter;

            if ($termIds === []) {
                $builder->where('1 = 0', null, false); // term does not exist: match nothing
                continue;
            }

            if ($taxonomy === 'post_tag') {
                $builder->where(static::postIdsForTerms('post_tags', 'tag_id', $termIds), null, false);
            } else {
                $builder->where(static::postIdsForTerms('post_categories', 'category_id', $termIds), null, false);
            }
        }

        // --- search ---------------------------------------------------------
        if ($args['s'] !== '') {
            $builder->groupStart()
                ->like('p.title', $args['s'])
                ->orLike('p.excerpt', $args['s'])
                ->orLike('p.content', $args['s'])
                ->groupEnd();
        }

        // --- date -----------------------------------------------------------
        foreach (['year' => '%Y', 'monthnum' => '%m', 'day' => '%d'] as $key => $format) {
            if ($args[$key]) {
                $builder->where("DATE_FORMAT(COALESCE(p.published_at, p.created_at), '{$format}') = " . $db->escape(str_pad((string) $args[$key], $key === 'year' ? 4 : 2, '0', STR_PAD_LEFT)), null, false);
            }
        }

        // --- meta -------------------------------------------------------------
        static::applyMeta($builder, $args, $db);

        // --- order --------------------------------------------------------------
        static::applyOrder($builder, $args);

        $countBuilder = clone $builder;
        $found        = $args['no_found_rows'] ? 0 : $countBuilder->countAllResults(false);

        if (! $args['nopaging'] && $args['posts_per_page'] > 0) {
            $offset = $args['offset'] !== null
                ? (int) $args['offset']
                : max(0, ($args['paged'] - 1) * $args['posts_per_page']);

            $rows = $builder->get($args['posts_per_page'], $offset)->getResultArray();
        } else {
            $rows = $builder->get()->getResultArray();
        }

        return ['rows' => $rows, 'found' => $args['no_found_rows'] ? count($rows) : $found];
    }

    /** Arguments a theme passed that this layer does not act on. */
    public static function unsupported(array $args): array
    {
        return array_values(array_diff(array_keys($args), self::SUPPORTED_ARGS));
    }

    protected static function normalise(array $args): array
    {
        $perPage = $args['posts_per_page'] ?? $args['numberposts'] ?? (int) get_option('posts_per_page', 10);

        return [
            'post_type'      => $args['post_type'] ?? 'post',
            'post_status'    => $args['post_status'] ?? 'publish',
            'posts_per_page' => (int) $perPage,
            'paged'          => max(1, (int) ($args['paged'] ?? 1)),
            'offset'         => $args['offset'] ?? null,
            'nopaging'       => (bool) ($args['nopaging'] ?? false) || (int) $perPage === -1,
            'orderby'        => $args['orderby'] ?? 'date',
            'order'          => strtoupper((string) ($args['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
            'name'           => (string) ($args['name'] ?? $args['pagename'] ?? ''),
            'p'              => (int) ($args['p'] ?? $args['page_id'] ?? 0),
            'post__in'       => (array) ($args['post__in'] ?? $args['include'] ?? []),
            'post__not_in'   => (array) ($args['post__not_in'] ?? $args['exclude'] ?? []),
            'category_name'  => (string) ($args['category_name'] ?? ''),
            'cat'            => $args['cat'] ?? ($args['category__in'] ?? 0),
            'tag'            => (string) ($args['tag'] ?? ''),
            'tag_id'         => $args['tag_id'] ?? ($args['tag__in'] ?? 0),
            'tax_query'      => (array) ($args['tax_query'] ?? []),
            's'              => (string) ($args['s'] ?? ''),
            'author'         => (int) ($args['author'] ?? 0),
            'author_name'    => (string) ($args['author_name'] ?? ''),
            'meta_key'       => (string) ($args['meta_key'] ?? ''),
            'meta_value'     => $args['meta_value'] ?? null,
            'meta_compare'   => (string) ($args['meta_compare'] ?? '='),
            'meta_query'     => (array) ($args['meta_query'] ?? []),
            'year'           => (int) ($args['year'] ?? 0),
            'monthnum'       => (int) ($args['monthnum'] ?? 0),
            'day'            => (int) ($args['day'] ?? 0),
            'no_found_rows'  => (bool) ($args['no_found_rows'] ?? false),
            'fields'         => (string) ($args['fields'] ?? ''),
        ];
    }

    /** @return list<array{0: string, 1: list<int>}> */
    protected static function taxonomyFilters(array $args): array
    {
        $filters = [];

        if ($args['category_name'] !== '') {
            $ids = [];

            foreach (explode(',', $args['category_name']) as $slug) {
                $term = TermMapper::findBySlug(trim($slug), 'category');

                if ($term !== null) {
                    $ids[] = $term->term_id;
                }
            }

            $filters[] = ['category', $ids];
        }

        if (! empty($args['cat'])) {
            $filters[] = ['category', array_map('intval', (array) $args['cat'])];
        }

        if ($args['tag'] !== '') {
            $ids = [];

            foreach (explode(',', $args['tag']) as $slug) {
                $term = TermMapper::findBySlug(trim($slug), 'post_tag');

                if ($term !== null) {
                    $ids[] = $term->term_id - TermMapper::TAG_OFFSET;
                }
            }

            $filters[] = ['post_tag', $ids];
        }

        if (! empty($args['tag_id'])) {
            $filters[] = ['post_tag', array_map(
                static fn ($id) => (int) $id > TermMapper::TAG_OFFSET ? (int) $id - TermMapper::TAG_OFFSET : (int) $id,
                (array) $args['tag_id']
            )];
        }

        foreach ($args['tax_query'] as $clause) {
            if (! is_array($clause) || ! isset($clause['taxonomy'])) {
                continue;
            }

            $taxonomy = $clause['taxonomy'] === 'post_tag' ? 'post_tag' : 'category';
            $field    = $clause['field'] ?? 'term_id';
            $terms    = (array) ($clause['terms'] ?? []);
            $ids      = [];

            foreach ($terms as $term) {
                if ($field === 'slug' || $field === 'name') {
                    $found = TermMapper::findBySlug((string) $term, $taxonomy);

                    if ($found !== null) {
                        $ids[] = $taxonomy === 'post_tag' ? $found->term_id - TermMapper::TAG_OFFSET : $found->term_id;
                    }
                } else {
                    $id    = (int) $term;
                    $ids[] = ($taxonomy === 'post_tag' && $id > TermMapper::TAG_OFFSET) ? $id - TermMapper::TAG_OFFSET : $id;
                }
            }

            $filters[] = [$taxonomy, $ids];
        }

        // Category ids arriving as WP term ids need the tag offset stripped.
        return array_map(static function (array $filter) {
            [$taxonomy, $ids] = $filter;

            if ($taxonomy === 'category') {
                $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0 && $id < TermMapper::TAG_OFFSET));
            } else {
                $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0));
            }

            return [$taxonomy, $ids];
        }, $filters);
    }

    /**
     * Term filters are expressed as an IN (SELECT ...) predicate. Ids are
     * cast to int on the way in, so there is nothing to escape here.
     */
    protected static function postIdsForTerms(string $table, string $column, array $termIds): string
    {
        $ids = implode(',', array_map('intval', $termIds));

        return "p.id IN (SELECT post_id FROM {$table} WHERE {$column} IN ({$ids}))";
    }

    protected static function applyMeta($builder, array $args, $db): void
    {
        $clauses = $args['meta_query'];

        if ($args['meta_key'] !== '') {
            $clauses[] = [
                'key'     => $args['meta_key'],
                'value'   => $args['meta_value'],
                'compare' => $args['meta_compare'],
            ];
        }

        $relation = strtoupper((string) ($clauses['relation'] ?? 'AND'));
        unset($clauses['relation']);

        if ($clauses === []) {
            return;
        }

        $sub = [];

        foreach ($clauses as $clause) {
            if (! is_array($clause) || ! isset($clause['key'])) {
                continue;
            }

            $key     = $db->escape((string) $clause['key']);
            $compare = strtoupper((string) ($clause['compare'] ?? '='));
            $value   = $clause['value'] ?? null;

            if ($value === null || $compare === 'EXISTS') {
                $sub[] = "SELECT post_id FROM wp_postmeta WHERE meta_key = {$key}";
                continue;
            }

            $operator = match ($compare) {
                '!=', 'NOT LIKE', 'NOT IN' => $compare,
                '>', '>=', '<', '<=', 'LIKE', 'IN' => $compare,
                default => '=',
            };

            if ($operator === 'IN' || $operator === 'NOT IN') {
                $list  = implode(',', array_map(static fn ($v) => $db->escape((string) $v), (array) $value));
                $sub[] = "SELECT post_id FROM wp_postmeta WHERE meta_key = {$key} AND meta_value {$operator} ({$list})";
                continue;
            }

            $escaped = $db->escape($operator === 'LIKE' || $operator === 'NOT LIKE' ? '%' . $value . '%' : (string) $value);
            $sub[]   = "SELECT post_id FROM wp_postmeta WHERE meta_key = {$key} AND meta_value {$operator} {$escaped}";
        }

        if ($sub === []) {
            return;
        }

        if ($relation === 'OR') {
            $builder->where('p.id IN (' . implode(' UNION ', $sub) . ')', null, false);

            return;
        }

        foreach ($sub as $select) {
            $builder->where("p.id IN ({$select})", null, false);
        }
    }

    protected static function applyOrder($builder, array $args): void
    {
        $order = $args['order'];

        foreach (preg_split('/\s+/', trim((string) $args['orderby'])) ?: ['date'] as $orderby) {
            match ($orderby) {
                'title'          => $builder->orderBy('p.title', $order),
                'name', 'slug'   => $builder->orderBy('p.slug', $order),
                'ID', 'id'       => $builder->orderBy('p.id', $order),
                'rand'           => $builder->orderBy('', 'RANDOM'),
                'modified'       => $builder->orderBy('p.updated_at', $order),
                'comment_count'  => $builder->orderBy('p.view_count', $order),
                'menu_order'     => $builder->orderBy('p.id', $order),
                'post__in'       => $args['post__in'] !== []
                    ? $builder->orderBy('FIELD(p.id, ' . implode(',', array_map('intval', $args['post__in'])) . ')', '', false)
                    : $builder->orderBy('p.published_at', $order),
                'meta_value', 'meta_value_num' => $builder->orderBy(
                    '(SELECT meta_value FROM wp_postmeta m WHERE m.post_id = p.id AND m.meta_key = ' . Database::connect()->escape($args['meta_key']) . ' LIMIT 1)',
                    $order,
                    false
                ),
                default          => $builder->orderBy('p.published_at', $order),
            };
        }
    }
}
