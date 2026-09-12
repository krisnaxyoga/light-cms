<?php

use App\Libraries\WordPress\Bridge\TermMapper;

/**
 * URL builders. Permalinks follow the LightCMS routing shape
 * (/<slug>, /category/<slug>) rather than a configurable permalink
 * structure — the CMS has one canonical URL per post and the redirect
 * table keeps old ones alive.
 */

if (! function_exists('home_url')) {
    function home_url(string $path = '', ?string $scheme = null): string
    {
        return get_home_url(null, $path, $scheme);
    }
}

if (! function_exists('get_home_url')) {
    function get_home_url($blog_id = null, string $path = '', ?string $scheme = null): string
    {
        $url = rtrim(base_url(), '/');

        if ($scheme === 'https' || $scheme === 'http') {
            $url = preg_replace('#^https?://#i', $scheme . '://', $url) ?? $url;
        }

        $url = $path === '' ? $url : $url . '/' . ltrim($path, '/');

        return apply_filters('home_url', $url, $path, $scheme, $blog_id);
    }
}

if (! function_exists('site_url')) {
    function site_url(string $path = '', ?string $scheme = null): string
    {
        return home_url($path, $scheme);
    }
}

if (! function_exists('get_site_url')) {
    function get_site_url($blog_id = null, string $path = '', ?string $scheme = null): string
    {
        return get_home_url($blog_id, $path, $scheme);
    }
}

if (! function_exists('network_home_url')) {
    function network_home_url(string $path = '', ?string $scheme = null): string
    {
        return home_url($path, $scheme);
    }
}

if (! function_exists('network_site_url')) {
    function network_site_url(string $path = '', ?string $scheme = null): string
    {
        return home_url($path, $scheme);
    }
}

if (! function_exists('admin_url')) {
    /**
     * The LightCMS admin lives at /admin, so that is where admin URLs
     * point. The two wp-admin *endpoints* themes actually request —
     * admin-ajax.php and admin-post.php — keep their canonical
     * /wp-admin/ paths, because that is what the routes serve and what
     * `admin_url('admin-ajax.php')` is expected to return.
     */
    function admin_url(string $path = '', string $scheme = 'admin'): string
    {
        $path = ltrim($path, '/');

        if (preg_match('#^(admin-ajax\.php|admin-post\.php)(\?.*)?$#', $path) === 1) {
            $url = home_url('wp-admin/' . $path);
        } else {
            $url = $path === '' ? home_url('admin/') : home_url('admin/' . $path);
        }

        return apply_filters('admin_url', $url, $path, null, $scheme);
    }
}

if (! function_exists('get_admin_url')) {
    function get_admin_url($blog_id = null, string $path = '', string $scheme = 'admin'): string
    {
        return admin_url($path, $scheme);
    }
}

if (! function_exists('self_admin_url')) {
    function self_admin_url(string $path = '', string $scheme = 'admin'): string
    {
        return admin_url($path, $scheme);
    }
}

if (! function_exists('includes_url')) {
    function includes_url(string $path = ''): string
    {
        return home_url('wp-includes/' . ltrim($path, '/'));
    }
}

if (! function_exists('content_url')) {
    function content_url(string $path = ''): string
    {
        return rtrim(WP_CONTENT_URL, '/') . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}

if (! function_exists('wp_login_url')) {
    function wp_login_url(string $redirect = '', bool $force_reauth = false): string
    {
        $url = admin_url('login');

        return $redirect === '' ? $url : add_query_arg('redirect_to', urlencode($redirect), $url);
    }
}

if (! function_exists('wp_logout_url')) {
    function wp_logout_url(string $redirect = ''): string
    {
        return admin_url('logout');
    }
}

if (! function_exists('wp_registration_url')) {
    function wp_registration_url(): string
    {
        return admin_url('login');
    }
}

if (! function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return home_url('wp-json/' . ltrim($path, '/'));
    }
}

if (! function_exists('admin_ajax_url')) {
    function admin_ajax_url(): string
    {
        return home_url('wp-admin/admin-ajax.php');
    }
}

// -- Permalinks --------------------------------------------------------------

if (! function_exists('get_permalink')) {
    function get_permalink($post = 0, bool $leavename = false)
    {
        $post = get_post($post ?: null);

        if (! $post instanceof WP_Post) {
            return false;
        }

        return apply_filters('post_link', home_url($post->post_name), $post, $leavename);
    }
}

if (! function_exists('get_the_permalink')) {
    function get_the_permalink($post = 0, bool $leavename = false)
    {
        return get_permalink($post, $leavename);
    }
}

if (! function_exists('the_permalink')) {
    function the_permalink($post = 0): void
    {
        echo esc_url((string) get_permalink($post));
    }
}

if (! function_exists('get_post_permalink')) {
    function get_post_permalink($post = 0, bool $leavename = false, bool $sample = false)
    {
        return get_permalink($post, $leavename);
    }
}

if (! function_exists('get_page_link')) {
    function get_page_link($post = false, bool $leavename = false, bool $sample = false)
    {
        return get_permalink($post ?: 0, $leavename);
    }
}

if (! function_exists('get_term_link')) {
    function get_term_link($term, string $taxonomy = '')
    {
        if (is_numeric($term)) {
            $term = TermMapper::find((int) $term, $taxonomy);
        } elseif (is_string($term)) {
            $term = TermMapper::findBySlug($term, $taxonomy !== '' ? $taxonomy : 'category');
        }

        if (! $term instanceof WP_Term) {
            return new WP_Error('invalid_term', 'Term not found.');
        }

        $base = $term->taxonomy === 'post_tag' ? 'tag/' : 'category/';

        return apply_filters('term_link', home_url($base . $term->slug), $term, $term->taxonomy);
    }
}

if (! function_exists('get_category_link')) {
    function get_category_link($category)
    {
        $link = get_term_link($category, 'category');

        return is_wp_error($link) ? '' : $link;
    }
}

if (! function_exists('get_tag_link')) {
    function get_tag_link($tag)
    {
        $link = get_term_link($tag, 'post_tag');

        return is_wp_error($link) ? '' : $link;
    }
}

if (! function_exists('get_author_posts_url')) {
    function get_author_posts_url(int $author_id, string $author_nicename = ''): string
    {
        $user = $author_nicename !== '' ? null : App\Libraries\WordPress\Bridge\UserMapper::find($author_id);
        $slug = $author_nicename !== '' ? $author_nicename : ($user?->user_login ?? (string) $author_id);

        return apply_filters('author_link', home_url('author/' . $slug), $author_id, $author_nicename);
    }
}

if (! function_exists('get_search_link')) {
    function get_search_link(string $query = ''): string
    {
        $query = $query !== '' ? $query : (string) get_search_query();

        return home_url('search?s=' . urlencode($query));
    }
}

if (! function_exists('get_year_link')) {
    function get_year_link($year): string
    {
        return home_url((string) ($year ?: date('Y')));
    }
}

if (! function_exists('get_month_link')) {
    function get_month_link($year, $month): string
    {
        return home_url(($year ?: date('Y')) . '/' . zeroise($month ?: date('m'), 2));
    }
}

if (! function_exists('get_day_link')) {
    function get_day_link($year, $month, $day): string
    {
        return home_url(($year ?: date('Y')) . '/' . zeroise($month ?: date('m'), 2) . '/' . zeroise($day ?: date('d'), 2));
    }
}

if (! function_exists('get_post_type_archive_link')) {
    function get_post_type_archive_link(string $post_type)
    {
        $object = get_post_type_object($post_type);

        if ($object === null || empty($object->has_archive)) {
            return false;
        }

        $slug = is_string($object->has_archive) ? $object->has_archive : $post_type;

        return home_url($slug);
    }
}

// -- Query strings and redirects ---------------------------------------------------

if (! function_exists('add_query_arg')) {
    function add_query_arg(...$args): string
    {
        if (is_array($args[0])) {
            $params = $args[0];
            $url    = $args[1] ?? wp_current_url();
        } else {
            $params = [$args[0] => $args[1] ?? ''];
            $url    = $args[2] ?? wp_current_url();
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        foreach ($params as $key => $value) {
            if ($value === false || $value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        $base = ($parts['scheme'] ?? '') !== ''
            ? $parts['scheme'] . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '') . ($parts['path'] ?? '')
            : ($parts['path'] ?? '');

        $queryString = http_build_query($query);

        return $base . ($queryString !== '' ? '?' . $queryString : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}

if (! function_exists('remove_query_arg')) {
    function remove_query_arg($key, $query = false): string
    {
        $url = $query ?: wp_current_url();

        foreach ((array) $key as $name) {
            $url = add_query_arg([$name => false], $url);
        }

        return $url;
    }
}

if (! function_exists('wp_current_url')) {
    function wp_current_url(): string
    {
        return current_url(true)->__toString();
    }
}

if (! function_exists('wp_redirect')) {
    function wp_redirect(string $location, int $status = 302, $x_redirect_by = 'WordPress'): bool
    {
        $location = apply_filters('wp_redirect', $location, $status);

        if ($location === '') {
            return false;
        }

        // Throwing keeps the redirect inside the CI response cycle — the
        // controller catches it and returns a real RedirectResponse.
        throw new App\Libraries\WordPress\Exceptions\WordPressRedirectException($location, $status);
    }
}

if (! function_exists('wp_safe_redirect')) {
    function wp_safe_redirect(string $location, int $status = 302, $x_redirect_by = 'WordPress'): bool
    {
        $host = parse_url($location, PHP_URL_HOST);

        if ($host !== null && $host !== parse_url(base_url(), PHP_URL_HOST)) {
            $location = home_url();
        }

        return wp_redirect($location, $status, $x_redirect_by);
    }
}

if (! function_exists('wp_validate_redirect')) {
    function wp_validate_redirect(string $location, string $fallback_url = ''): string
    {
        $host = parse_url($location, PHP_URL_HOST);

        return ($host === null || $host === parse_url(base_url(), PHP_URL_HOST)) ? $location : $fallback_url;
    }
}

if (! function_exists('get_pagenum_link')) {
    function get_pagenum_link(int $pagenum = 1, bool $escape = true): string
    {
        $url = $pagenum <= 1 ? remove_query_arg('page') : add_query_arg('page', $pagenum);

        return $escape ? esc_url($url) : $url;
    }
}

if (! function_exists('paginate_links')) {
    function paginate_links(array $args = [])
    {
        $query = $GLOBALS['wp_query'] ?? null;

        $args = wp_parse_args($args, [
            'total'     => (int) ($query->max_num_pages ?? 1),
            'current'   => max(1, (int) ($query->query_vars['paged'] ?? 1)),
            'mid_size'  => 2,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
            'type'      => 'plain',
            'add_args'  => [],
        ]);

        $total   = max(1, (int) $args['total']);
        $current = min(max(1, (int) $args['current']), $total);

        if ($total <= 1) {
            return $args['type'] === 'array' ? [] : '';
        }

        $links = [];

        if ($current > 1) {
            $links[] = '<a class="prev page-numbers" href="' . get_pagenum_link($current - 1) . '">' . $args['prev_text'] . '</a>';
        }

        for ($page = 1; $page <= $total; $page++) {
            $inWindow = abs($page - $current) <= (int) $args['mid_size'] || $page === 1 || $page === $total;

            if (! $inWindow) {
                if (! str_ends_with(end($links) ?: '', 'dots">&hellip;</span>')) {
                    $links[] = '<span class="page-numbers dots">&hellip;</span>';
                }

                continue;
            }

            $links[] = $page === $current
                ? '<span aria-current="page" class="page-numbers current">' . $page . '</span>'
                : '<a class="page-numbers" href="' . get_pagenum_link($page) . '">' . $page . '</a>';
        }

        if ($current < $total) {
            $links[] = '<a class="next page-numbers" href="' . get_pagenum_link($current + 1) . '">' . $args['next_text'] . '</a>';
        }

        return $args['type'] === 'array' ? $links : implode("\n", $links);
    }
}
