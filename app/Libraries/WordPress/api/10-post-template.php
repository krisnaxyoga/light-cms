<?php

use App\Libraries\WordPress\Bridge\TermMapper;

/**
 * The template tags used inside the loop: the_title(), the_content(),
 * the_excerpt(), post_class(), the_category(), and friends.
 */

if (! function_exists('get_the_title')) {
    function get_the_title($post = 0): string
    {
        $post = get_post($post ?: null);

        if (! $post instanceof WP_Post) {
            return '';
        }

        return (string) apply_filters('the_title', $post->post_title, $post->ID);
    }
}

if (! function_exists('the_title')) {
    function the_title(string $before = '', string $after = '', bool $display = true)
    {
        $title = get_the_title();

        if ($title === '') {
            return $display ? null : '';
        }

        if ($display) {
            echo $before . $title . $after;

            return null;
        }

        return $before . $title . $after;
    }
}

if (! function_exists('the_title_attribute')) {
    function the_title_attribute($args = '')
    {
        $args  = wp_parse_args($args, ['before' => '', 'after' => '', 'echo' => true, 'post' => null]);
        $title = esc_attr(wp_strip_all_tags(get_the_title($args['post'] ?? 0)));
        $title = $args['before'] . $title . $args['after'];

        if ($args['echo']) {
            echo $title;

            return null;
        }

        return $title;
    }
}

if (! function_exists('get_the_content')) {
    function get_the_content(?string $more_link_text = null, bool $strip_teaser = false, $post = null): string
    {
        $post = get_post($post);

        return $post instanceof WP_Post ? $post->post_content : '';
    }
}

if (! function_exists('the_content')) {
    function the_content(?string $more_link_text = null, bool $strip_teaser = false): void
    {
        $content = apply_filters('the_content', get_the_content($more_link_text, $strip_teaser));

        echo str_replace(']]>', ']]&gt;', $content);
    }
}

if (! function_exists('get_the_excerpt')) {
    function get_the_excerpt($post = null): string
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post) {
            return '';
        }

        $excerpt = $post->post_excerpt;

        if (trim($excerpt) === '') {
            $excerpt = wp_trim_words($post->post_content, (int) apply_filters('excerpt_length', 55), (string) apply_filters('excerpt_more', ' &hellip;'));
        }

        return (string) apply_filters('get_the_excerpt', $excerpt, $post);
    }
}

if (! function_exists('the_excerpt')) {
    function the_excerpt(): void
    {
        echo apply_filters('the_excerpt', get_the_excerpt());
    }
}

if (! function_exists('the_ID')) {
    function the_ID(): void
    {
        echo (string) get_the_ID();
    }
}

if (! function_exists('get_the_date')) {
    function get_the_date(string $format = '', $post = null)
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post) {
            return false;
        }

        $format = $format !== '' ? $format : (string) get_option('date_format', 'F j, Y');

        return apply_filters('get_the_date', mysql2date($format, $post->post_date), $format, $post);
    }
}

if (! function_exists('the_date')) {
    function the_date(string $format = '', string $before = '', string $after = '', bool $display = true)
    {
        $date = (string) get_the_date($format);

        if ($display) {
            echo $before . $date . $after;

            return null;
        }

        return $before . $date . $after;
    }
}

if (! function_exists('get_the_time')) {
    function get_the_time(string $format = '', $post = null)
    {
        $post   = get_post($post);
        $format = $format !== '' ? $format : (string) get_option('time_format', 'g:i a');

        return $post instanceof WP_Post ? mysql2date($format, $post->post_date) : false;
    }
}

if (! function_exists('the_time')) {
    function the_time(string $format = ''): void
    {
        echo (string) get_the_time($format);
    }
}

if (! function_exists('get_the_modified_date')) {
    function get_the_modified_date(string $format = '', $post = null)
    {
        $post   = get_post($post);
        $format = $format !== '' ? $format : (string) get_option('date_format', 'F j, Y');

        return $post instanceof WP_Post ? mysql2date($format, $post->post_modified) : false;
    }
}

if (! function_exists('the_modified_date')) {
    function the_modified_date(string $format = ''): void
    {
        echo (string) get_the_modified_date($format);
    }
}

if (! function_exists('get_post_time')) {
    function get_post_time(string $format = 'U', bool $gmt = false, $post = null, bool $translate = false)
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post) {
            return false;
        }

        return $format === 'U' ? strtotime($post->post_date) : mysql2date($format, $post->post_date);
    }
}

if (! function_exists('get_the_category_list')) {
    function get_the_category_list(string $separator = '', string $parents = '', int $post_id = 0): string
    {
        $terms = get_the_category($post_id ?: null);

        if ($terms === []) {
            return '';
        }

        $links = array_map(
            static fn (WP_Term $term) => '<a href="' . esc_url((string) get_category_link($term->term_id)) . '" rel="category tag">' . esc_html($term->name) . '</a>',
            $terms
        );

        return $separator === ''
            ? '<ul class="post-categories"><li>' . implode('</li><li>', $links) . '</li></ul>'
            : implode($separator, $links);
    }
}

if (! function_exists('the_category')) {
    function the_category(string $separator = '', string $parents = '', int $post_id = 0): void
    {
        echo get_the_category_list($separator, $parents, $post_id);
    }
}

if (! function_exists('get_the_tag_list')) {
    function get_the_tag_list(string $before = '', string $sep = '', string $after = '', int $post_id = 0): string
    {
        $terms = get_the_tags($post_id ?: null);

        if (! is_array($terms) || $terms === []) {
            return '';
        }

        $links = array_map(
            static fn (WP_Term $term) => '<a href="' . esc_url((string) get_tag_link($term->term_id)) . '" rel="tag">' . esc_html($term->name) . '</a>',
            $terms
        );

        return $before . implode($sep !== '' ? $sep : ', ', $links) . $after;
    }
}

if (! function_exists('the_tags')) {
    function the_tags(string $before = 'Tags: ', string $sep = ', ', string $after = ''): void
    {
        echo get_the_tag_list($before, $sep, $after);
    }
}

if (! function_exists('get_the_term_list')) {
    function get_the_term_list($post_id, string $taxonomy, string $before = '', string $sep = '', string $after = '')
    {
        $terms = get_the_terms($post_id, $taxonomy);

        if (! is_array($terms) || $terms === []) {
            return false;
        }

        $links = array_map(
            static fn (WP_Term $term) => '<a href="' . esc_url((string) get_term_link($term)) . '">' . esc_html($term->name) . '</a>',
            $terms
        );

        return $before . implode($sep !== '' ? $sep : ', ', $links) . $after;
    }
}

if (! function_exists('the_terms')) {
    function the_terms(int $post_id, string $taxonomy, string $before = '', string $sep = ', ', string $after = ''): void
    {
        $list = get_the_term_list($post_id, $taxonomy, $before, $sep, $after);

        if ($list !== false) {
            echo $list;
        }
    }
}

// -- Classes --------------------------------------------------------------------

if (! function_exists('get_post_class')) {
    function get_post_class($css_class = '', $post = null): array
    {
        $post    = get_post($post);
        $classes = [];

        if ($post instanceof WP_Post) {
            $classes[] = 'post-' . $post->ID;
            $classes[] = $post->post_type;
            $classes[] = 'type-' . $post->post_type;
            $classes[] = 'status-' . $post->post_status;

            if ($post->lightcms_featured_image ?? null) {
                $classes[] = 'has-post-thumbnail';
            }

            foreach (TermMapper::forPost($post->ID, 'category') as $term) {
                $classes[] = 'category-' . $term->slug;
            }

            foreach (TermMapper::forPost($post->ID, 'post_tag') as $term) {
                $classes[] = 'tag-' . $term->slug;
            }
        }

        if ($css_class !== '' && $css_class !== []) {
            $classes = array_merge($classes, is_array($css_class) ? $css_class : (preg_split('/\s+/', (string) $css_class) ?: []));
        }

        $classes = array_map('sanitize_html_class', array_filter($classes));

        return array_values(array_unique(apply_filters('post_class', $classes, $css_class, $post?->ID ?? 0)));
    }
}

if (! function_exists('post_class')) {
    function post_class($css_class = '', $post = null): void
    {
        echo 'class="' . esc_attr(implode(' ', get_post_class($css_class, $post))) . '"';
    }
}

if (! function_exists('get_body_class')) {
    function get_body_class($css_class = ''): array
    {
        $classes = [];

        if (is_front_page()) {
            $classes[] = 'home';
        }

        if (is_home()) {
            $classes[] = 'blog';
        }

        if (is_archive()) {
            $classes[] = 'archive';
        }

        if (is_category()) {
            $classes[] = 'category';
        }

        if (is_tag()) {
            $classes[] = 'tag';
        }

        if (is_search()) {
            $classes[] = 'search';
        }

        if (is_404()) {
            $classes[] = 'error404';
        }

        if (is_singular()) {
            $post      = get_post();
            $classes[] = 'singular';
            $classes[] = 'single-' . ($post?->post_type ?? 'post');
            $classes[] = 'postid-' . ($post?->ID ?? 0);

            if (is_page()) {
                $classes[] = 'page';
                $classes[] = 'page-' . ($post?->post_name ?? '');
            }
        }

        if (is_user_logged_in()) {
            $classes[] = 'logged-in';
        }

        if ($css_class !== '' && $css_class !== []) {
            $classes = array_merge($classes, is_array($css_class) ? $css_class : (preg_split('/\s+/', (string) $css_class) ?: []));
        }

        $classes = array_map('sanitize_html_class', array_filter($classes));

        return array_values(array_unique(apply_filters('body_class', $classes, $css_class)));
    }
}

if (! function_exists('body_class')) {
    function body_class($css_class = ''): void
    {
        echo 'class="' . esc_attr(implode(' ', get_body_class($css_class))) . '"';
    }
}

if (! function_exists('post_password_required')) {
    function post_password_required($post = null): bool
    {
        return false;
    }
}

if (! function_exists('wp_link_pages')) {
    function wp_link_pages($args = '')
    {
        return '';
    }
}

if (! function_exists('edit_post_link')) {
    function edit_post_link(?string $text = null, string $before = '', string $after = '', int $post_id = 0, string $css_class = 'post-edit-link'): void
    {
        if (! current_user_can('edit_posts')) {
            return;
        }

        $id = $post_id ?: (int) get_the_ID();

        echo $before . '<a class="' . esc_attr($css_class) . '" href="' . esc_url(admin_url('posts/' . $id . '/edit')) . '">'
            . esc_html($text ?? 'Edit') . '</a>' . $after;
    }
}

if (! function_exists('get_edit_post_link')) {
    function get_edit_post_link(int $post_id = 0, string $context = 'display')
    {
        return current_user_can('edit_posts') ? admin_url('posts/' . ($post_id ?: (int) get_the_ID()) . '/edit') : null;
    }
}
