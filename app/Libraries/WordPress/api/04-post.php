<?php

use App\Libraries\WordPress\Bridge\PostMapper;
use App\Libraries\WordPress\QueryBuilder;
use App\Libraries\WordPress\Registry;
use App\Models\PostModel;

/**
 * Posts, post meta, and post-type registration.
 *
 * Reads go through QueryBuilder (so they honour the same argument
 * translation as WP_Query); writes go through PostModel, which keeps the
 * LightCMS automations — sitemap regeneration, slug-change redirects,
 * cache invalidation — working for content a plugin creates.
 */

if (! function_exists('get_post')) {
    function get_post($post = null, string $output = OBJECT, string $filter = 'raw')
    {
        if ($post === null) {
            $post = $GLOBALS['post'] ?? null;
        }

        if ($post instanceof WP_Post) {
            $result = $post;
        } elseif (is_object($post) && isset($post->ID)) {
            $result = new WP_Post((array) $post);
        } elseif (is_numeric($post) && (int) $post > 0) {
            $row    = (new PostModel())->find((int) $post);
            $result = $row ? PostMapper::fromRow($row) : null;
        } else {
            $result = null;
        }

        if ($result === null) {
            return null;
        }

        return match ($output) {
            ARRAY_A => $result->to_array(),
            ARRAY_N => array_values($result->to_array()),
            default => $result,
        };
    }
}

if (! function_exists('get_posts')) {
    function get_posts(array $args = []): array
    {
        $args = wp_parse_args($args, [
            'numberposts' => 5,
            'post_type'   => 'post',
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
            'suppress_filters' => true,
        ]);

        if (isset($args['numberposts']) && ! isset($args['posts_per_page'])) {
            $args['posts_per_page'] = $args['numberposts'];
        }

        $result = QueryBuilder::run($args);
        $posts  = PostMapper::fromRows($result['rows']);

        if (($args['fields'] ?? '') === 'ids') {
            return array_map(static fn (WP_Post $post) => $post->ID, $posts);
        }

        return $posts;
    }
}

if (! function_exists('get_post_field')) {
    function get_post_field(string $field, $post = null, string $context = 'display')
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post) {
            return '';
        }

        return $post->{$field} ?? '';
    }
}

if (! function_exists('get_the_ID')) {
    function get_the_ID()
    {
        $post = get_post();

        return $post instanceof WP_Post ? $post->ID : false;
    }
}

if (! function_exists('get_post_status')) {
    function get_post_status($post = null)
    {
        $post = get_post($post);

        return $post instanceof WP_Post ? $post->post_status : false;
    }
}

if (! function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        $post = get_post($post);

        return $post instanceof WP_Post ? $post->post_type : false;
    }
}

if (! function_exists('get_page_by_path')) {
    function get_page_by_path(string $page_path, string $output = OBJECT, $post_type = 'page')
    {
        $slug = basename(trim($page_path, '/'));
        $row  = (new PostModel())->where('slug', $slug)->whereIn('post_type', (array) $post_type)->first();

        return $row ? get_post(PostMapper::fromRow($row), $output) : null;
    }
}

if (! function_exists('get_page_by_title')) {
    function get_page_by_title(string $page_title, string $output = OBJECT, $post_type = 'page')
    {
        $row = (new PostModel())->where('title', $page_title)->whereIn('post_type', (array) $post_type)->first();

        return $row ? get_post(PostMapper::fromRow($row), $output) : null;
    }
}

if (! function_exists('get_pages')) {
    function get_pages(array $args = []): array
    {
        $args = wp_parse_args($args, [
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => (int) ($args['number'] ?? 0) ?: -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        return get_posts($args);
    }
}

if (! function_exists('get_adjacent_post')) {
    function get_adjacent_post(bool $in_same_term = false, $excluded_terms = '', bool $previous = true, string $taxonomy = 'category')
    {
        $post = get_post();

        if (! $post instanceof WP_Post) {
            return null;
        }

        $model = new PostModel();
        $row   = $model
            ->where('status', 'published')
            ->where('post_type', $post->post_type)
            ->where('published_at ' . ($previous ? '<' : '>'), $post->post_date)
            ->orderBy('published_at', $previous ? 'DESC' : 'ASC')
            ->first();

        return $row ? PostMapper::fromRow($row) : null;
    }
}

if (! function_exists('get_previous_post')) {
    function get_previous_post(bool $in_same_term = false, $excluded_terms = '', string $taxonomy = 'category')
    {
        return get_adjacent_post($in_same_term, $excluded_terms, true, $taxonomy);
    }
}

if (! function_exists('get_next_post')) {
    function get_next_post(bool $in_same_term = false, $excluded_terms = '', string $taxonomy = 'category')
    {
        return get_adjacent_post($in_same_term, $excluded_terms, false, $taxonomy);
    }
}

// -- The loop ------------------------------------------------------------------

if (! function_exists('setup_postdata')) {
    function setup_postdata($post): bool
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post) {
            return false;
        }

        $GLOBALS['post']     = $post;
        $GLOBALS['id']       = $post->ID;
        $GLOBALS['authordata'] = get_userdata($post->post_author);
        $GLOBALS['pages']    = [$post->post_content];
        $GLOBALS['page']     = 1;
        $GLOBALS['numpages'] = 1;
        $GLOBALS['multipage'] = 0;
        $GLOBALS['more']     = 1;

        do_action('the_post', $post, $GLOBALS['wp_query'] ?? null);

        return true;
    }
}

if (! function_exists('have_posts')) {
    function have_posts(): bool
    {
        return isset($GLOBALS['wp_query']) ? $GLOBALS['wp_query']->have_posts() : false;
    }
}

if (! function_exists('the_post')) {
    function the_post(): void
    {
        $GLOBALS['wp_query']?->the_post();
    }
}

if (! function_exists('rewind_posts')) {
    function rewind_posts(): void
    {
        $GLOBALS['wp_query']?->rewind_posts();
    }
}

if (! function_exists('in_the_loop')) {
    function in_the_loop(): bool
    {
        return (bool) ($GLOBALS['wp_query']->in_the_loop ?? false);
    }
}

if (! function_exists('wp_reset_postdata')) {
    function wp_reset_postdata(): void
    {
        if (isset($GLOBALS['wp_the_query'])) {
            $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
            $GLOBALS['wp_query']->reset_postdata();
        }
    }
}

if (! function_exists('wp_reset_query')) {
    function wp_reset_query(): void
    {
        wp_reset_postdata();
    }
}

if (! function_exists('query_posts')) {
    function query_posts($query): array
    {
        $GLOBALS['wp_query'] = new WP_Query($query);

        return $GLOBALS['wp_query']->posts;
    }
}

// -- Meta ------------------------------------------------------------------------

if (! function_exists('get_metadata')) {
    function get_metadata(string $meta_type, int $object_id, string $meta_key = '', bool $single = false)
    {
        return wp_meta_store()->get($meta_type, $object_id, $meta_key, $single);
    }
}

if (! function_exists('update_metadata')) {
    function update_metadata(string $meta_type, int $object_id, string $meta_key, $meta_value, $prev_value = '')
    {
        return wp_meta_store()->update($meta_type, $object_id, $meta_key, $meta_value, $prev_value);
    }
}

if (! function_exists('add_metadata')) {
    function add_metadata(string $meta_type, int $object_id, string $meta_key, $meta_value, bool $unique = false)
    {
        return wp_meta_store()->add($meta_type, $object_id, $meta_key, $meta_value, $unique);
    }
}

if (! function_exists('delete_metadata')) {
    function delete_metadata(string $meta_type, int $object_id, string $meta_key, $meta_value = '', bool $delete_all = false)
    {
        return wp_meta_store()->delete($meta_type, $object_id, $meta_key, $meta_value);
    }
}

if (! function_exists('metadata_exists')) {
    function metadata_exists(string $meta_type, int $object_id, string $meta_key): bool
    {
        return wp_meta_store()->get($meta_type, $object_id, $meta_key) !== [];
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $key = '', bool $single = false)
    {
        return apply_filters('get_post_metadata_result', get_metadata('post', $post_id, $key, $single), $post_id, $key, $single);
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta(int $post_id, string $meta_key, $meta_value, $prev_value = '')
    {
        return update_metadata('post', $post_id, $meta_key, $meta_value, $prev_value);
    }
}

if (! function_exists('add_post_meta')) {
    function add_post_meta(int $post_id, string $meta_key, $meta_value, bool $unique = false)
    {
        return add_metadata('post', $post_id, $meta_key, $meta_value, $unique);
    }
}

if (! function_exists('delete_post_meta')) {
    function delete_post_meta(int $post_id, string $meta_key, $meta_value = '')
    {
        return delete_metadata('post', $post_id, $meta_key, $meta_value);
    }
}

if (! function_exists('get_post_custom')) {
    function get_post_custom(int $post_id = 0): array
    {
        return wp_meta_store()->allFor('post', $post_id ?: (int) get_the_ID());
    }
}

if (! function_exists('get_post_custom_keys')) {
    function get_post_custom_keys(int $post_id = 0): array
    {
        return array_keys(get_post_custom($post_id));
    }
}

if (! function_exists('get_user_meta')) {
    function get_user_meta(int $user_id, string $key = '', bool $single = false)
    {
        return get_metadata('user', $user_id, $key, $single);
    }
}

if (! function_exists('update_user_meta')) {
    function update_user_meta(int $user_id, string $meta_key, $meta_value, $prev_value = '')
    {
        return update_metadata('user', $user_id, $meta_key, $meta_value, $prev_value);
    }
}

if (! function_exists('add_user_meta')) {
    function add_user_meta(int $user_id, string $meta_key, $meta_value, bool $unique = false)
    {
        return add_metadata('user', $user_id, $meta_key, $meta_value, $unique);
    }
}

if (! function_exists('delete_user_meta')) {
    function delete_user_meta(int $user_id, string $meta_key, $meta_value = '')
    {
        return delete_metadata('user', $user_id, $meta_key, $meta_value);
    }
}

if (! function_exists('get_term_meta')) {
    function get_term_meta(int $term_id, string $key = '', bool $single = false)
    {
        return get_metadata('term', $term_id, $key, $single);
    }
}

if (! function_exists('update_term_meta')) {
    function update_term_meta(int $term_id, string $meta_key, $meta_value, $prev_value = '')
    {
        return update_metadata('term', $term_id, $meta_key, $meta_value, $prev_value);
    }
}

if (! function_exists('add_term_meta')) {
    function add_term_meta(int $term_id, string $meta_key, $meta_value, bool $unique = false)
    {
        return add_metadata('term', $term_id, $meta_key, $meta_value, $unique);
    }
}

if (! function_exists('delete_term_meta')) {
    function delete_term_meta(int $term_id, string $meta_key, $meta_value = '')
    {
        return delete_metadata('term', $term_id, $meta_key, $meta_value);
    }
}

// -- Writes -----------------------------------------------------------------------

if (! function_exists('wp_insert_post')) {
    function wp_insert_post(array $postarr, bool $wp_error = false, bool $fire_after_hooks = true)
    {
        $row = PostMapper::toRow($postarr);

        if (($row['title'] ?? '') === '') {
            return $wp_error ? new WP_Error('empty_title', 'A post title is required.') : 0;
        }

        $row['content'] ??= PostMapper::contentToStorage('');
        $model = new PostModel();

        if (! empty($postarr['ID'])) {
            $id = (int) $postarr['ID'];
            $model->update($id, $row);
        } else {
            $row['slug'] = wp_unique_post_slug($row['slug'] ?? url_title($row['title'], '-', true));
            $id          = (int) $model->insert($row, true);
        }

        if ($id <= 0) {
            $errors = implode(' ', $model->errors() ?: ['Insert failed.']);

            return $wp_error ? new WP_Error('insert_failed', $errors) : 0;
        }

        foreach ($postarr['meta_input'] ?? [] as $key => $value) {
            update_post_meta($id, (string) $key, $value);
        }

        if ($fire_after_hooks) {
            $post = get_post($id);
            do_action('save_post', $id, $post, ! empty($postarr['ID']));
            do_action('wp_insert_post', $id, $post, ! empty($postarr['ID']));
        }

        return $id;
    }
}

if (! function_exists('wp_update_post')) {
    function wp_update_post($postarr = [], bool $wp_error = false, bool $fire_after_hooks = true)
    {
        $postarr = is_object($postarr) ? get_object_vars($postarr) : $postarr;

        if (empty($postarr['ID'])) {
            return $wp_error ? new WP_Error('invalid_post', 'Cannot update a post without an ID.') : 0;
        }

        return wp_insert_post($postarr, $wp_error, $fire_after_hooks);
    }
}

if (! function_exists('wp_unique_post_slug')) {
    function wp_unique_post_slug(string $slug, int $post_id = 0): string
    {
        $model  = new PostModel();
        $base   = $slug !== '' ? $slug : 'post';
        $unique = $base;
        $suffix = 1;

        while (true) {
            $builder = $model->builder()->where('slug', $unique);

            if ($post_id > 0) {
                $builder->where('id !=', $post_id);
            }

            if ($builder->countAllResults() === 0) {
                return $unique;
            }

            $unique = $base . '-' . ++$suffix;
        }
    }
}

if (! function_exists('wp_delete_post')) {
    function wp_delete_post(int $postid = 0, bool $force_delete = false)
    {
        $post = get_post($postid);

        if (! $post instanceof WP_Post) {
            return false;
        }

        $model = new PostModel();

        if ($force_delete) {
            do_action('before_delete_post', $postid, $post);
            $model->delete($postid);
            do_action('deleted_post', $postid, $post);
        } else {
            $model->update($postid, ['status' => 'trash']);
            do_action('wp_trash_post', $postid);
        }

        return $post;
    }
}

if (! function_exists('wp_trash_post')) {
    function wp_trash_post(int $post_id = 0)
    {
        return wp_delete_post($post_id, false);
    }
}

if (! function_exists('wp_publish_post')) {
    function wp_publish_post($post): void
    {
        $post = get_post($post);

        if ($post instanceof WP_Post) {
            (new PostModel())->update($post->ID, ['status' => 'published', 'published_at' => date('Y-m-d H:i:s')]);
            do_action('publish_post', $post->ID, $post);
        }
    }
}

// -- Post types --------------------------------------------------------------------

if (! function_exists('register_post_type')) {
    function register_post_type(string $post_type, $args = [])
    {
        $args = wp_parse_args($args, [
            'label'               => ucfirst($post_type),
            'labels'              => [],
            'public'              => true,
            'hierarchical'        => false,
            'has_archive'         => false,
            'rewrite'             => true,
            'supports'            => ['title', 'editor'],
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => false,
            'menu_icon'           => '',
            'menu_position'       => null,
            'taxonomies'          => [],
            'capability_type'     => 'post',
        ]);

        $object = (object) $args;
        $object->name = $post_type;
        $object->labels = (object) wp_parse_args($args['labels'], [
            'name'          => $args['label'],
            'singular_name' => $args['label'],
            'add_new_item'  => 'Add New ' . $args['label'],
            'edit_item'     => 'Edit ' . $args['label'],
            'all_items'     => 'All ' . $args['label'],
        ]);

        Registry::$postTypes[$post_type] = $object;

        do_action('registered_post_type', $post_type, $object);

        return $object;
    }
}

if (! function_exists('unregister_post_type')) {
    function unregister_post_type(string $post_type): bool
    {
        unset(Registry::$postTypes[$post_type]);

        return true;
    }
}

if (! function_exists('post_type_exists')) {
    function post_type_exists(string $post_type): bool
    {
        return in_array($post_type, ['post', 'page', 'attachment'], true) || isset(Registry::$postTypes[$post_type]);
    }
}

if (! function_exists('get_post_type_object')) {
    function get_post_type_object(string $post_type)
    {
        if (isset(Registry::$postTypes[$post_type])) {
            return Registry::$postTypes[$post_type];
        }

        if (in_array($post_type, ['post', 'page'], true)) {
            $label = ucfirst($post_type);

            return (object) [
                'name'   => $post_type,
                'label'  => $label . 's',
                'labels' => (object) ['name' => $label . 's', 'singular_name' => $label],
                'public' => true,
                'has_archive' => $post_type === 'post',
                'hierarchical' => $post_type === 'page',
            ];
        }

        return null;
    }
}

if (! function_exists('get_post_types')) {
    function get_post_types(array $args = [], string $output = 'names', string $operator = 'and'): array
    {
        $types = ['post' => get_post_type_object('post'), 'page' => get_post_type_object('page')] + Registry::$postTypes;

        if ($args !== []) {
            $types = wp_filter_object_list($types, $args, $operator);
        }

        return $output === 'objects' ? $types : array_keys($types);
    }
}

if (! function_exists('post_type_supports')) {
    function post_type_supports(string $post_type, string $feature): bool
    {
        $object = get_post_type_object($post_type);

        return in_array($feature, (array) ($object->supports ?? ['title', 'editor', 'thumbnail']), true);
    }
}

if (! function_exists('add_post_type_support')) {
    function add_post_type_support(string $post_type, $supports, ...$args): void
    {
        if (isset(Registry::$postTypes[$post_type])) {
            $object = Registry::$postTypes[$post_type];
            $object->supports = array_values(array_unique(array_merge((array) ($object->supports ?? []), (array) $supports)));
        }
    }
}

if (! function_exists('get_post_stati')) {
    function get_post_stati(array $args = [], string $output = 'names'): array
    {
        return ['publish', 'draft', 'future', 'trash', 'pending', 'private'];
    }
}

if (! function_exists('get_post_status_object')) {
    function get_post_status_object(string $post_status)
    {
        return (object) ['name' => $post_status, 'label' => ucfirst($post_status), 'public' => $post_status === 'publish'];
    }
}
