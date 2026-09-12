<?php

/**
 * General template tags: the header/footer/part loaders, wp_head/wp_footer,
 * bloginfo, the conditional tags, and archive titles.
 */

if (! function_exists('get_header')) {
    function get_header(?string $name = null, array $args = []): void
    {
        do_action('get_header', $name, $args);

        $templates = [];

        if ($name !== null && $name !== '') {
            $templates[] = "header-{$name}.php";
        }

        $templates[] = 'header.php';

        locate_template($templates, true, false, $args);
    }
}

if (! function_exists('get_footer')) {
    function get_footer(?string $name = null, array $args = []): void
    {
        do_action('get_footer', $name, $args);

        $templates = [];

        if ($name !== null && $name !== '') {
            $templates[] = "footer-{$name}.php";
        }

        $templates[] = 'footer.php';

        locate_template($templates, true, false, $args);
    }
}

if (! function_exists('get_sidebar')) {
    function get_sidebar(?string $name = null, array $args = []): void
    {
        do_action('get_sidebar', $name, $args);

        $templates = [];

        if ($name !== null && $name !== '') {
            $templates[] = "sidebar-{$name}.php";
        }

        $templates[] = 'sidebar.php';

        locate_template($templates, true, false, $args);
    }
}

if (! function_exists('get_template_part')) {
    function get_template_part(string $slug, ?string $name = null, array $args = [])
    {
        do_action('get_template_part_' . $slug, $slug, $name, $args);

        $templates = [];

        if ($name !== null && $name !== '') {
            $templates[] = "{$slug}-{$name}.php";
        }

        $templates[] = "{$slug}.php";

        $located = locate_template($templates, true, false, $args);

        return $located === '' ? false : null;
    }
}

if (! function_exists('get_search_form')) {
    function get_search_form($args = [])
    {
        $args = is_array($args) ? $args : ['echo' => (bool) $args];
        $echo = $args['echo'] ?? true;

        $located = locate_template(['searchform.php']);

        if ($located !== '') {
            ob_start();
            wp_include_file($located);
            $form = (string) ob_get_clean();
        } else {
            $form = '<form role="search" method="get" class="search-form" action="' . esc_url(home_url('search')) . '">'
                . '<label><span class="screen-reader-text">' . esc_html__('Search for:') . '</span>'
                . '<input type="search" class="search-field" placeholder="' . esc_attr__('Search &hellip;') . '" value="' . esc_attr((string) get_search_query()) . '" name="s"></label>'
                . '<button type="submit" class="search-submit">' . esc_html__('Search') . '</button></form>';
        }

        $form = apply_filters('get_search_form', $form);

        if ($echo) {
            echo $form;

            return null;
        }

        return $form;
    }
}

if (! function_exists('get_search_query')) {
    function get_search_query(bool $escaped = true)
    {
        $query = (string) ($GLOBALS['wp_query']->query_vars['s'] ?? '');

        return $escaped ? esc_attr($query) : $query;
    }
}

if (! function_exists('the_search_query')) {
    function the_search_query(): void
    {
        echo esc_attr((string) get_search_query(false));
    }
}

// -- Head / footer ---------------------------------------------------------------

if (! function_exists('wp_head')) {
    function wp_head(): void
    {
        static $titlePrinted = false;

        // WP fires wp_enqueue_scripts from inside wp_head, so a theme that
        // enqueues on that hook still gets its handles printed here.
        do_action('wp_enqueue_scripts');

        if (current_theme_supports('title-tag') && ! $titlePrinted) {
            $titlePrinted = true;
            echo '<title>' . esc_html(wp_get_document_title()) . "</title>\n";
        }

        do_action('wp_head');

        echo wp_assets()->printStyles() . "\n";
        echo wp_assets()->printScripts(false) . "\n";
    }
}

if (! function_exists('wp_footer')) {
    function wp_footer(): void
    {
        do_action('wp_footer');
        echo wp_assets()->printScripts(true) . "\n";
    }
}

if (! function_exists('wp_body_open')) {
    function wp_body_open(): void
    {
        do_action('wp_body_open');
    }
}

if (! function_exists('wp_get_document_title')) {
    function wp_get_document_title(): string
    {
        $parts = [];

        if (is_singular()) {
            $parts[] = get_the_title();
        } elseif (is_category() || is_tag() || is_tax()) {
            $parts[] = single_term_title('', false);
        } elseif (is_search()) {
            $parts[] = 'Search results for "' . get_search_query(false) . '"';
        } elseif (is_404()) {
            $parts[] = 'Page not found';
        } elseif (is_author()) {
            $parts[] = get_the_author();
        }

        $parts[] = get_bloginfo('name');

        return apply_filters('document_title', implode(' - ', array_filter($parts)));
    }
}

if (! function_exists('wp_title')) {
    function wp_title(string $sep = '&raquo;', bool $display = true, string $seplocation = '')
    {
        $title = wp_get_document_title();

        if ($display) {
            echo $title;

            return null;
        }

        return $title;
    }
}

if (! function_exists('language_attributes')) {
    function language_attributes(string $doctype = 'html'): void
    {
        echo 'lang="' . esc_attr(str_replace('_', '-', get_locale())) . '"';
    }
}

if (! function_exists('get_language_attributes')) {
    function get_language_attributes(string $doctype = 'html'): string
    {
        return 'lang="' . esc_attr(str_replace('_', '-', get_locale())) . '"';
    }
}

if (! function_exists('bloginfo')) {
    function bloginfo(string $show = ''): void
    {
        echo get_bloginfo($show, 'display');
    }
}

if (! function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = '', string $filter = 'raw')
    {
        $value = match ($show) {
            '', 'name'          => (string) get_option('blogname', 'LightCMS'),
            'description'       => (string) get_option('blogdescription', ''),
            'url', 'home'       => home_url(),
            'wpurl', 'siteurl'  => site_url(),
            'admin_email'       => (string) get_option('admin_email', ''),
            'charset'           => 'UTF-8',
            'version'           => $GLOBALS['wp_version'] ?? '6.5',
            'language'          => str_replace('_', '-', get_locale()),
            'html_type'         => 'text/html',
            'text_direction'    => 'ltr',
            'stylesheet_url'    => get_stylesheet_uri(),
            'stylesheet_directory' => get_stylesheet_directory_uri(),
            'template_url', 'template_directory' => get_template_directory_uri(),
            'atom_url'          => home_url('feed/atom'),
            'rss_url', 'rss2_url' => home_url('feed'),
            'pingback_url'      => home_url('xmlrpc.php'),
            default             => '',
        };

        $value = apply_filters('bloginfo', $value, $show);

        return $filter === 'display' ? esc_html($value) : $value;
    }
}

if (! function_exists('bloginfo_rss')) {
    function bloginfo_rss(string $show = ''): void
    {
        echo esc_html((string) get_bloginfo($show));
    }
}

// -- Conditional tags ----------------------------------------------------------------

if (! function_exists('wp_query_flag')) {
    /** @internal shared reader for the main query's is_* flags */
    function wp_query_flag(string $flag): bool
    {
        $query = $GLOBALS['wp_query'] ?? null;

        return $query instanceof WP_Query ? (bool) ($query->{$flag} ?? false) : false;
    }
}

if (! function_exists('is_home')) {
    function is_home(): bool
    {
        return wp_query_flag('is_home');
    }
}

if (! function_exists('is_front_page')) {
    function is_front_page(): bool
    {
        return wp_query_flag('is_front_page');
    }
}

if (! function_exists('is_single')) {
    function is_single($post = ''): bool
    {
        if (! wp_query_flag('is_single')) {
            return false;
        }

        if ($post === '' || $post === []) {
            return true;
        }

        $current = get_post();

        foreach ((array) $post as $needle) {
            if ((string) $needle === (string) $current?->ID
                || (string) $needle === ($current?->post_name ?? '')
                || (string) $needle === ($current?->post_title ?? '')) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('is_page')) {
    function is_page($page = ''): bool
    {
        if (! wp_query_flag('is_page')) {
            return false;
        }

        if ($page === '' || $page === []) {
            return true;
        }

        $current = get_post();

        foreach ((array) $page as $needle) {
            if ((string) $needle === (string) $current?->ID
                || (string) $needle === ($current?->post_name ?? '')
                || (string) $needle === ($current?->post_title ?? '')) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('is_singular')) {
    function is_singular($post_types = ''): bool
    {
        if (! wp_query_flag('is_singular')) {
            return false;
        }

        if ($post_types === '' || $post_types === []) {
            return true;
        }

        return in_array((string) get_post_type(), (array) $post_types, true);
    }
}

if (! function_exists('is_archive')) {
    function is_archive(): bool
    {
        return wp_query_flag('is_archive');
    }
}

if (! function_exists('is_category')) {
    function is_category($category = ''): bool
    {
        if (! wp_query_flag('is_category')) {
            return false;
        }

        if ($category === '' || $category === []) {
            return true;
        }

        $queried = $GLOBALS['wp_query']->get_queried_object();

        foreach ((array) $category as $needle) {
            if ((string) $needle === ($queried->slug ?? '') || (string) $needle === (string) ($queried->term_id ?? '') || (string) $needle === ($queried->name ?? '')) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('is_tag')) {
    function is_tag($tag = ''): bool
    {
        if (! wp_query_flag('is_tag')) {
            return false;
        }

        if ($tag === '' || $tag === []) {
            return true;
        }

        $queried = $GLOBALS['wp_query']->get_queried_object();

        return in_array((string) ($queried->slug ?? ''), array_map('strval', (array) $tag), true);
    }
}

if (! function_exists('is_tax')) {
    function is_tax($taxonomy = '', $term = ''): bool
    {
        return wp_query_flag('is_tax');
    }
}

if (! function_exists('is_search')) {
    function is_search(): bool
    {
        return wp_query_flag('is_search');
    }
}

if (! function_exists('is_404')) {
    function is_404(): bool
    {
        return wp_query_flag('is_404');
    }
}

if (! function_exists('is_author')) {
    function is_author($author = ''): bool
    {
        return wp_query_flag('is_author');
    }
}

if (! function_exists('is_date')) {
    function is_date(): bool
    {
        return wp_query_flag('is_date');
    }
}

if (! function_exists('is_year')) {
    function is_year(): bool
    {
        return wp_query_flag('is_year');
    }
}

if (! function_exists('is_month')) {
    function is_month(): bool
    {
        return wp_query_flag('is_month');
    }
}

if (! function_exists('is_day')) {
    function is_day(): bool
    {
        return wp_query_flag('is_day');
    }
}

if (! function_exists('is_paged')) {
    function is_paged(): bool
    {
        return wp_query_flag('is_paged');
    }
}

if (! function_exists('is_attachment')) {
    function is_attachment($attachment = ''): bool
    {
        return false;
    }
}

if (! function_exists('is_post_type_archive')) {
    function is_post_type_archive($post_types = ''): bool
    {
        return wp_query_flag('is_post_type_archive');
    }
}

if (! function_exists('is_feed')) {
    function is_feed($feeds = ''): bool
    {
        return wp_query_flag('is_feed');
    }
}

if (! function_exists('is_preview')) {
    function is_preview(): bool
    {
        return false;
    }
}

if (! function_exists('is_admin')) {
    function is_admin(): bool
    {
        return (bool) ($GLOBALS['lightcms_is_admin'] ?? false);
    }
}

if (! function_exists('is_customize_preview')) {
    function is_customize_preview(): bool
    {
        return false;
    }
}

if (! function_exists('is_ssl')) {
    function is_ssl(): bool
    {
        return str_starts_with(strtolower(base_url()), 'https://');
    }
}

if (! function_exists('is_multisite')) {
    function is_multisite(): bool
    {
        return false;
    }
}

if (! function_exists('is_main_query')) {
    function is_main_query(): bool
    {
        return ($GLOBALS['wp_query'] ?? null) === ($GLOBALS['wp_the_query'] ?? null);
    }
}

if (! function_exists('is_rtl')) {
    function is_rtl(): bool
    {
        return false;
    }
}

// -- Archive titles ---------------------------------------------------------------------

if (! function_exists('single_term_title')) {
    function single_term_title(string $prefix = '', bool $display = true)
    {
        $term  = $GLOBALS['wp_query']?->get_queried_object();
        $title = $term instanceof WP_Term ? $term->name : '';

        if ($title === '') {
            return $display ? null : '';
        }

        if ($display) {
            echo $prefix . esc_html($title);

            return null;
        }

        return $prefix . $title;
    }
}

if (! function_exists('single_cat_title')) {
    function single_cat_title(string $prefix = '', bool $display = true)
    {
        return single_term_title($prefix, $display);
    }
}

if (! function_exists('single_tag_title')) {
    function single_tag_title(string $prefix = '', bool $display = true)
    {
        return single_term_title($prefix, $display);
    }
}

if (! function_exists('single_post_title')) {
    function single_post_title(string $prefix = '', bool $display = true)
    {
        $title = $prefix . (string) get_the_title();

        if ($display) {
            echo $title;

            return null;
        }

        return $title;
    }
}

if (! function_exists('get_the_archive_title')) {
    function get_the_archive_title(): string
    {
        $title = match (true) {
            is_category() => 'Category: ' . (string) single_term_title('', false),
            is_tag()      => 'Tag: ' . (string) single_term_title('', false),
            is_author()   => 'Author: ' . get_the_author(),
            is_year()     => 'Year: ' . date('Y'),
            is_search()   => 'Search results for: ' . get_search_query(false),
            default       => 'Archives',
        };

        return apply_filters('get_the_archive_title', $title);
    }
}

if (! function_exists('the_archive_title')) {
    function the_archive_title(string $before = '', string $after = ''): void
    {
        $title = get_the_archive_title();

        if ($title !== '') {
            echo $before . $title . $after;
        }
    }
}

if (! function_exists('get_the_archive_description')) {
    function get_the_archive_description(): string
    {
        $term = $GLOBALS['wp_query']?->get_queried_object();

        return apply_filters('get_the_archive_description', $term instanceof WP_Term ? $term->description : '');
    }
}

if (! function_exists('the_archive_description')) {
    function the_archive_description(string $before = '', string $after = ''): void
    {
        $description = get_the_archive_description();

        if ($description !== '') {
            echo $before . $description . $after;
        }
    }
}

if (! function_exists('the_posts_pagination')) {
    function the_posts_pagination(array $args = []): void
    {
        $links = paginate_links($args);

        if ($links !== '' && $links !== []) {
            echo '<nav class="navigation pagination"><div class="nav-links">' . $links . '</div></nav>';
        }
    }
}

if (! function_exists('the_posts_navigation')) {
    function the_posts_navigation(array $args = []): void
    {
        the_posts_pagination($args);
    }
}

if (! function_exists('posts_nav_link')) {
    function posts_nav_link(string $sep = '', string $prelabel = '', string $nxtlabel = ''): void
    {
        the_posts_pagination();
    }
}

if (! function_exists('the_post_navigation')) {
    function the_post_navigation(array $args = []): void
    {
        $previous = get_previous_post();
        $next     = get_next_post();

        if ($previous === null && $next === null) {
            return;
        }

        echo '<nav class="navigation post-navigation"><div class="nav-links">';

        if ($previous instanceof WP_Post) {
            echo '<div class="nav-previous"><a href="' . esc_url((string) get_permalink($previous)) . '">' . esc_html($previous->post_title) . '</a></div>';
        }

        if ($next instanceof WP_Post) {
            echo '<div class="nav-next"><a href="' . esc_url((string) get_permalink($next)) . '">' . esc_html($next->post_title) . '</a></div>';
        }

        echo '</div></nav>';
    }
}

if (! function_exists('previous_post_link')) {
    function previous_post_link(string $format = '&laquo; %link', string $link = '%title', bool $in_same_term = false): void
    {
        $post = get_previous_post($in_same_term);

        if ($post instanceof WP_Post) {
            $anchor = '<a href="' . esc_url((string) get_permalink($post)) . '" rel="prev">' . str_replace('%title', esc_html($post->post_title), $link) . '</a>';
            echo str_replace('%link', $anchor, $format);
        }
    }
}

if (! function_exists('next_post_link')) {
    function next_post_link(string $format = '%link &raquo;', string $link = '%title', bool $in_same_term = false): void
    {
        $post = get_next_post($in_same_term);

        if ($post instanceof WP_Post) {
            $anchor = '<a href="' . esc_url((string) get_permalink($post)) . '" rel="next">' . str_replace('%title', esc_html($post->post_title), $link) . '</a>';
            echo str_replace('%link', $anchor, $format);
        }
    }
}
