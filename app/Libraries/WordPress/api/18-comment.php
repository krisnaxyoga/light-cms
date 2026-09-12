<?php

use App\Models\CommentModel;

/**
 * Comments, mapped onto the LightCMS `comments` table. Display and
 * counting work; comment *submission* is not wired up in LightCMS itself,
 * so comment_form() renders a form that posts to the LightCMS endpoint
 * only when that endpoint exists (it is filterable either way).
 */

if (! function_exists('wp_comment_row')) {
    /** @internal LightCMS comment row -> WP_Comment */
    function wp_comment_object(array $row): WP_Comment
    {
        return new WP_Comment([
            'comment_ID'           => (int) $row['id'],
            'comment_post_ID'      => (int) $row['post_id'],
            'comment_author'       => (string) ($row['author_name'] ?? ''),
            'comment_author_email' => (string) ($row['author_email'] ?? ''),
            'comment_author_IP'    => (string) ($row['author_ip'] ?? ''),
            'comment_date'         => (string) ($row['created_at'] ?? ''),
            'comment_date_gmt'     => (string) ($row['created_at'] ?? ''),
            'comment_content'      => (string) ($row['content'] ?? ''),
            'comment_approved'     => ($row['status'] ?? '') === 'approved' ? '1' : '0',
            'comment_parent'       => (int) ($row['parent_id'] ?? 0),
        ]);
    }
}

if (! function_exists('get_comments')) {
    function get_comments(array $args = []): array
    {
        $args  = wp_parse_args($args, ['post_id' => 0, 'status' => 'approve', 'number' => 50, 'order' => 'ASC']);
        $model = new CommentModel();

        $builder = $model->orderBy('created_at', strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC');

        if ((int) $args['post_id'] > 0) {
            $builder = $builder->where('post_id', (int) $args['post_id']);
        }

        if ($args['status'] === 'approve') {
            $builder = $builder->where('status', 'approved');
        }

        return array_map('wp_comment_object', $builder->findAll(max(1, (int) $args['number'])));
    }
}

if (! function_exists('get_comment')) {
    function get_comment($comment = null, string $output = OBJECT)
    {
        if ($comment instanceof WP_Comment) {
            return $comment;
        }

        $row = (new CommentModel())->find((int) $comment);

        return $row ? wp_comment_object($row) : null;
    }
}

if (! function_exists('get_comments_number')) {
    function get_comments_number($post = 0): int
    {
        $post = get_post($post ?: null);

        if (! $post instanceof WP_Post) {
            return 0;
        }

        return (new CommentModel())->where('post_id', $post->ID)->where('status', 'approved')->countAllResults();
    }
}

if (! function_exists('comments_number')) {
    function comments_number(?string $zero = null, ?string $one = null, ?string $more = null): void
    {
        $count = get_comments_number();

        echo match (true) {
            $count === 0 => $zero ?? 'No Comments',
            $count === 1 => $one ?? '1 Comment',
            default      => str_replace('%', (string) $count, $more ?? '% Comments'),
        };
    }
}

if (! function_exists('comments_open')) {
    function comments_open($post = null): bool
    {
        $post = get_post($post);

        return $post instanceof WP_Post && $post->comment_status === 'open';
    }
}

if (! function_exists('pings_open')) {
    function pings_open($post = null): bool
    {
        return false;
    }
}

if (! function_exists('have_comments')) {
    function have_comments(): bool
    {
        return get_comments_number() > 0;
    }
}

if (! function_exists('comments_template')) {
    function comments_template(string $file = '/comments.php', bool $separate_comments = false): void
    {
        $post = get_post();

        if (! $post instanceof WP_Post) {
            return;
        }

        $GLOBALS['comments'] = get_comments(['post_id' => $post->ID]);
        $GLOBALS['comment_count'] = count($GLOBALS['comments']);

        if (isset($GLOBALS['wp_query'])) {
            $GLOBALS['wp_query']->comments      = $GLOBALS['comments'];
            $GLOBALS['wp_query']->comment_count = $GLOBALS['comment_count'];
            $GLOBALS['wp_query']->current_comment = -1;
        }

        $located = locate_template([ltrim($file, '/')]);

        if ($located !== '') {
            wp_include_file($located);
        }
    }
}

if (! function_exists('wp_list_comments')) {
    function wp_list_comments($args = [], $comments = null)
    {
        $args     = wp_parse_args($args, ['walker' => null, 'max_depth' => 5, 'style' => 'ol', 'echo' => true]);
        $comments = $comments ?? ($GLOBALS['comments'] ?? []);

        $walker = $args['walker'] instanceof Walker ? $args['walker'] : new Walker_Comment();
        $output = $walker->walk($comments, (int) $args['max_depth'], $args);

        if ($args['echo']) {
            echo $output;

            return null;
        }

        return $output;
    }
}

if (! function_exists('comment_form')) {
    function comment_form(array $args = [], $post = null): void
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post || ! comments_open($post)) {
            return;
        }

        // LightCMS ships no public comment endpoint yet; a site that adds
        // one can point the form at it by filtering this URL.
        $action = apply_filters('comment_form_action', '');

        if ($action === '') {
            echo '<p class="comment-form-disabled">' . esc_html__('Comments are displayed but not accepted on this site.') . '</p>';

            return;
        }

        echo '<form action="' . esc_url($action) . '" method="post" class="comment-form">'
            . '<p><label for="author">' . esc_html__('Name') . '</label><input id="author" name="author" type="text" required></p>'
            . '<p><label for="email">' . esc_html__('Email') . '</label><input id="email" name="email" type="email" required></p>'
            . '<p><label for="comment">' . esc_html__('Comment') . '</label><textarea id="comment" name="comment" required></textarea></p>'
            . '<input type="hidden" name="comment_post_ID" value="' . (int) $post->ID . '">'
            . wp_nonce_field('comment_' . $post->ID, '_wpnonce', true, false)
            . '<p><button type="submit">' . esc_html__('Post Comment') . '</button></p></form>';
    }
}

if (! function_exists('comment_text')) {
    function comment_text($comment = null, array $args = []): void
    {
        $comment = get_comment($comment ?? ($GLOBALS['comment'] ?? null));

        if ($comment instanceof WP_Comment) {
            echo apply_filters('comment_text', wpautop(esc_html($comment->comment_content)), $comment);
        }
    }
}

if (! function_exists('get_comment_text')) {
    function get_comment_text($comment = null, array $args = []): string
    {
        $comment = get_comment($comment ?? ($GLOBALS['comment'] ?? null));

        return $comment instanceof WP_Comment ? $comment->comment_content : '';
    }
}

if (! function_exists('get_comment_author')) {
    function get_comment_author($comment = null): string
    {
        $comment = get_comment($comment ?? ($GLOBALS['comment'] ?? null));

        return $comment instanceof WP_Comment ? ($comment->comment_author ?: 'Anonymous') : '';
    }
}

if (! function_exists('comment_author')) {
    function comment_author($comment = null): void
    {
        echo esc_html(get_comment_author($comment));
    }
}

if (! function_exists('get_comment_date')) {
    function get_comment_date(string $format = '', $comment = null)
    {
        $comment = get_comment($comment ?? ($GLOBALS['comment'] ?? null));

        if (! $comment instanceof WP_Comment) {
            return '';
        }

        return mysql2date($format !== '' ? $format : (string) get_option('date_format', 'F j, Y'), $comment->comment_date);
    }
}

if (! function_exists('comment_date')) {
    function comment_date(string $format = '', $comment = null): void
    {
        echo esc_html((string) get_comment_date($format, $comment));
    }
}

if (! function_exists('get_comment_link')) {
    function get_comment_link($comment = null, array $args = []): string
    {
        $comment = get_comment($comment ?? ($GLOBALS['comment'] ?? null));

        if (! $comment instanceof WP_Comment) {
            return '';
        }

        return (string) get_permalink($comment->comment_post_ID) . '#comment-' . $comment->comment_ID;
    }
}

if (! function_exists('comments_link')) {
    function comments_link($post = null): void
    {
        echo esc_url((string) get_permalink($post) . '#comments');
    }
}

if (! function_exists('comment_reply_link')) {
    function comment_reply_link($args = [], $comment = null, $post = null)
    {
        return '';
    }
}

if (! function_exists('paginate_comments_links')) {
    function paginate_comments_links(array $args = [])
    {
        return '';
    }
}
