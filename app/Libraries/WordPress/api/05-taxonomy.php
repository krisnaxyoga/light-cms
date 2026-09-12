<?php

use App\Libraries\WordPress\Bridge\TermMapper;
use App\Libraries\WordPress\Registry;
use App\Models\CategoryModel;
use App\Models\TagModel;
use Config\Database;

/**
 * Taxonomies. LightCMS has two fixed taxonomies — `category` and
 * `post_tag` — backed by its own tables. register_taxonomy() records
 * custom taxonomies so themes/plugins that gate behaviour on
 * taxonomy_exists() behave, but terms for them are not stored; `wp:doctor`
 * flags a theme that depends on one.
 */

if (! function_exists('register_taxonomy')) {
    function register_taxonomy(string $taxonomy, $object_type, $args = [])
    {
        $args = wp_parse_args($args, [
            'label'        => ucfirst($taxonomy),
            'labels'       => [],
            'public'       => true,
            'hierarchical' => false,
            'show_ui'      => true,
            'rewrite'      => true,
            'show_in_rest' => false,
        ]);

        $object = (object) $args;
        $object->name = $taxonomy;
        $object->object_type = (array) $object_type;
        $object->labels = (object) wp_parse_args($args['labels'], ['name' => $args['label'], 'singular_name' => $args['label']]);

        Registry::$taxonomies[$taxonomy] = $object;

        do_action('registered_taxonomy', $taxonomy, $object_type, (array) $object);

        return $object;
    }
}

if (! function_exists('taxonomy_exists')) {
    function taxonomy_exists(string $taxonomy): bool
    {
        return in_array($taxonomy, ['category', 'post_tag'], true) || isset(Registry::$taxonomies[$taxonomy]);
    }
}

if (! function_exists('get_taxonomy')) {
    function get_taxonomy(string $taxonomy)
    {
        if (isset(Registry::$taxonomies[$taxonomy])) {
            return Registry::$taxonomies[$taxonomy];
        }

        return match ($taxonomy) {
            'category' => (object) ['name' => 'category', 'label' => 'Categories', 'labels' => (object) ['name' => 'Categories', 'singular_name' => 'Category'], 'hierarchical' => true, 'public' => true],
            'post_tag' => (object) ['name' => 'post_tag', 'label' => 'Tags', 'labels' => (object) ['name' => 'Tags', 'singular_name' => 'Tag'], 'hierarchical' => false, 'public' => true],
            default    => false,
        };
    }
}

if (! function_exists('get_taxonomies')) {
    function get_taxonomies(array $args = [], string $output = 'names', string $operator = 'and'): array
    {
        $all = ['category' => get_taxonomy('category'), 'post_tag' => get_taxonomy('post_tag')] + Registry::$taxonomies;

        if ($args !== []) {
            $all = wp_filter_object_list($all, $args, $operator);
        }

        return $output === 'objects' ? $all : array_keys($all);
    }
}

if (! function_exists('get_object_taxonomies')) {
    function get_object_taxonomies($object_type, string $output = 'names'): array
    {
        $taxonomies = ['category', 'post_tag'];

        foreach (Registry::$taxonomies as $name => $taxonomy) {
            if (array_intersect((array) $object_type, (array) ($taxonomy->object_type ?? []))) {
                $taxonomies[] = $name;
            }
        }

        return $output === 'objects' ? array_map('get_taxonomy', $taxonomies) : $taxonomies;
    }
}

if (! function_exists('get_term')) {
    function get_term($term, string $taxonomy = '', string $output = OBJECT, string $filter = 'raw')
    {
        if ($term instanceof WP_Term) {
            $found = $term;
        } else {
            $found = TermMapper::find((int) $term, $taxonomy);
        }

        if ($found === null) {
            return null;
        }

        return match ($output) {
            ARRAY_A => $found->to_array(),
            ARRAY_N => array_values($found->to_array()),
            default => $found,
        };
    }
}

if (! function_exists('get_term_by')) {
    function get_term_by(string $field, $value, string $taxonomy = '', string $output = OBJECT, string $filter = 'raw')
    {
        $taxonomy = $taxonomy !== '' ? $taxonomy : 'category';

        if ($field === 'name') {
            $model = $taxonomy === 'post_tag' ? new TagModel() : new CategoryModel();
            $row   = $model->where('name', $value)->first();
            $term  = $row === null ? null : ($taxonomy === 'post_tag' ? TermMapper::fromTag($row) : TermMapper::fromCategory($row));
        } else {
            $term = match ($field) {
                'slug' => TermMapper::findBySlug((string) $value, $taxonomy),
                'id', 'term_id', 'term_taxonomy_id' => TermMapper::find((int) $value, $taxonomy),
                default => null,
            };
        }

        if (! $term instanceof WP_Term) {
            return false;
        }

        return $output === ARRAY_A ? $term->to_array() : $term;
    }
}

if (! function_exists('get_terms')) {
    function get_terms($args = [], $deprecated = []): array
    {
        // Legacy signature: get_terms( 'category', [ ... ] )
        if (is_string($args)) {
            $args = wp_parse_args($deprecated, ['taxonomy' => $args]);
        }

        $args = wp_parse_args($args, [
            'taxonomy'   => 'category',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'number'     => 0,
            'fields'     => 'all',
        ]);

        $taxonomy = (array) $args['taxonomy'];
        $terms    = [];

        foreach ($taxonomy as $name) {
            if (! in_array($name, ['category', 'post_tag'], true)) {
                continue; // custom taxonomies have no term storage
            }

            $terms = array_merge($terms, TermMapper::all(
                $name,
                (bool) $args['hide_empty'],
                (int) $args['number'],
                (string) $args['orderby'],
                (string) $args['order']
            ));
        }

        if ($args['fields'] === 'ids') {
            return array_map(static fn (WP_Term $term) => $term->term_id, $terms);
        }

        if ($args['fields'] === 'names') {
            return array_map(static fn (WP_Term $term) => $term->name, $terms);
        }

        return $terms;
    }
}

if (! function_exists('get_categories')) {
    function get_categories(array $args = []): array
    {
        return get_terms(wp_parse_args($args, ['taxonomy' => 'category', 'hide_empty' => false]));
    }
}

if (! function_exists('get_tags')) {
    function get_tags(array $args = []): array
    {
        return get_terms(wp_parse_args($args, ['taxonomy' => 'post_tag', 'hide_empty' => false]));
    }
}

if (! function_exists('get_category')) {
    function get_category($category, string $output = OBJECT, string $filter = 'raw')
    {
        return get_term($category, 'category', $output, $filter);
    }
}

if (! function_exists('wp_get_object_terms')) {
    function wp_get_object_terms($object_ids, $taxonomies, array $args = []): array
    {
        $terms = [];

        foreach ((array) $object_ids as $objectId) {
            foreach ((array) $taxonomies as $taxonomy) {
                if (in_array($taxonomy, ['category', 'post_tag'], true)) {
                    $terms = array_merge($terms, TermMapper::forPost((int) $objectId, $taxonomy));
                }
            }
        }

        return $terms;
    }
}

if (! function_exists('wp_get_post_terms')) {
    function wp_get_post_terms(int $post_id = 0, $taxonomy = 'post_tag', array $args = []): array
    {
        $post_id = $post_id ?: (int) get_the_ID();

        return wp_get_object_terms($post_id, $taxonomy, $args);
    }
}

if (! function_exists('wp_get_post_categories')) {
    function wp_get_post_categories(int $post_id = 0, array $args = []): array
    {
        $terms = wp_get_post_terms($post_id ?: (int) get_the_ID(), 'category', $args);

        return ($args['fields'] ?? 'ids') === 'all' ? $terms : array_map(static fn (WP_Term $term) => $term->term_id, $terms);
    }
}

if (! function_exists('wp_get_post_tags')) {
    function wp_get_post_tags(int $post_id = 0, array $args = []): array
    {
        return wp_get_post_terms($post_id ?: (int) get_the_ID(), 'post_tag', $args);
    }
}

if (! function_exists('get_the_terms')) {
    function get_the_terms($post, string $taxonomy)
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post || ! in_array($taxonomy, ['category', 'post_tag'], true)) {
            return false;
        }

        $terms = TermMapper::forPost($post->ID, $taxonomy);

        return $terms === [] ? false : $terms;
    }
}

if (! function_exists('get_the_category')) {
    function get_the_category(?int $post_id = null): array
    {
        $post = get_post($post_id);

        return $post instanceof WP_Post ? TermMapper::forPost($post->ID, 'category') : [];
    }
}

if (! function_exists('get_the_tags')) {
    function get_the_tags(?int $post_id = null)
    {
        $post  = get_post($post_id);
        $terms = $post instanceof WP_Post ? TermMapper::forPost($post->ID, 'post_tag') : [];

        return $terms === [] ? false : $terms;
    }
}

if (! function_exists('has_term')) {
    function has_term($term = '', string $taxonomy = '', $post = null): bool
    {
        $taxonomy = $taxonomy !== '' ? $taxonomy : 'category';
        $terms    = get_the_terms($post, $taxonomy);

        if (! is_array($terms)) {
            return false;
        }

        if ($term === '' || $term === []) {
            return $terms !== [];
        }

        foreach ((array) $term as $needle) {
            foreach ($terms as $candidate) {
                if ((string) $needle === $candidate->slug
                    || (string) $needle === $candidate->name
                    || (is_numeric($needle) && (int) $needle === $candidate->term_id)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (! function_exists('in_category')) {
    function in_category($category, $post = null): bool
    {
        return has_term($category, 'category', $post);
    }
}

if (! function_exists('is_object_in_term')) {
    function is_object_in_term(int $object_id, string $taxonomy, $terms = null): bool
    {
        return has_term($terms ?? '', $taxonomy, $object_id);
    }
}

if (! function_exists('term_exists')) {
    function term_exists($term, string $taxonomy = '', ?int $parent_term_id = null)
    {
        $found = is_numeric($term)
            ? TermMapper::find((int) $term, $taxonomy)
            : TermMapper::findBySlug(sanitize_title((string) $term), $taxonomy !== '' ? $taxonomy : 'category');

        return $found === null ? null : ['term_id' => $found->term_id, 'term_taxonomy_id' => $found->term_taxonomy_id];
    }
}

// -- Writes ------------------------------------------------------------------------

if (! function_exists('wp_insert_term')) {
    function wp_insert_term(string $term, string $taxonomy = 'category', array $args = [])
    {
        $slug = $args['slug'] ?? sanitize_title($term);

        if ($taxonomy === 'post_tag') {
            $id = (new TagModel())->findOrCreate($term);

            return ['term_id' => TermMapper::tagTermId($id), 'term_taxonomy_id' => TermMapper::tagTermId($id)];
        }

        $model    = new CategoryModel();
        $existing = $model->findBySlug($slug);

        if ($existing) {
            return new WP_Error('term_exists', 'A term with that slug already exists.', (int) $existing['id']);
        }

        $id = (int) $model->insert([
            'name'        => $term,
            'slug'        => $slug,
            'description' => $args['description'] ?? null,
            'parent_id'   => (int) ($args['parent'] ?? 0),
        ], true);

        return ['term_id' => $id, 'term_taxonomy_id' => $id];
    }
}

if (! function_exists('wp_set_object_terms')) {
    function wp_set_object_terms(int $object_id, $terms, string $taxonomy, bool $append = false)
    {
        $db    = Database::connect();
        $table = $taxonomy === 'post_tag' ? 'post_tags' : 'post_categories';
        $column = $taxonomy === 'post_tag' ? 'tag_id' : 'category_id';
        $ids   = [];

        foreach ((array) $terms as $term) {
            if (is_numeric($term)) {
                $id = (int) $term;
                $ids[] = ($taxonomy === 'post_tag' && $id > TermMapper::TAG_OFFSET) ? $id - TermMapper::TAG_OFFSET : $id;
                continue;
            }

            $inserted = wp_insert_term((string) $term, $taxonomy);

            if (is_array($inserted)) {
                $id    = (int) $inserted['term_id'];
                $ids[] = ($taxonomy === 'post_tag' && $id > TermMapper::TAG_OFFSET) ? $id - TermMapper::TAG_OFFSET : $id;
            }
        }

        if (! $append) {
            $db->table($table)->where('post_id', $object_id)->delete();
        }

        foreach (array_unique($ids) as $id) {
            $exists = $db->table($table)->where('post_id', $object_id)->where($column, $id)->countAllResults() > 0;

            if (! $exists) {
                $db->table($table)->insert(['post_id' => $object_id, $column => $id]);
            }
        }

        return $ids;
    }
}

if (! function_exists('wp_set_post_terms')) {
    function wp_set_post_terms(int $post_id = 0, $terms = '', string $taxonomy = 'post_tag', bool $append = false)
    {
        return wp_set_object_terms($post_id ?: (int) get_the_ID(), wp_parse_list($terms), $taxonomy, $append);
    }
}

if (! function_exists('wp_set_post_categories')) {
    function wp_set_post_categories(int $post_id = 0, $post_categories = [], bool $append = false)
    {
        return wp_set_object_terms($post_id, (array) $post_categories, 'category', $append);
    }
}
