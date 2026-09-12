<?php

use App\Libraries\WordPress\Bridge\UserMapper;
use App\Models\UserModel;

/**
 * Users and capabilities, mapped onto the LightCMS `users`/`roles` tables.
 * Capability checks answer from Config\WordPress::$capabilityMap, so a
 * plugin gating an admin screen on 'manage_options' sees the same answer
 * LightCMS's own RBAC would give.
 */

if (! function_exists('wp_get_current_user')) {
    function wp_get_current_user(): WP_User
    {
        return $GLOBALS['current_user'] ??= UserMapper::current();
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return wp_get_current_user()->ID;
    }
}

if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return wp_get_current_user()->exists();
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability, ...$args): bool
    {
        $user = wp_get_current_user();

        if (! $user->exists()) {
            return false;
        }

        return (bool) apply_filters('user_has_cap', $user->has_cap($capability), $capability, $user);
    }
}

if (! function_exists('user_can')) {
    function user_can($user, string $capability, ...$args): bool
    {
        $user = is_numeric($user) ? UserMapper::find((int) $user) : $user;

        return $user instanceof WP_User && $user->has_cap($capability);
    }
}

if (! function_exists('get_userdata')) {
    function get_userdata(int $user_id)
    {
        return UserMapper::find($user_id) ?? false;
    }
}

if (! function_exists('get_user_by')) {
    function get_user_by(string $field, $value)
    {
        $model = new UserModel();

        $row = match ($field) {
            'id', 'ID'         => $model->find((int) $value),
            'slug', 'login'    => $model->where('username', $value)->first(),
            'email'            => $model->where('email', $value)->first(),
            default            => null,
        };

        return $row ? UserMapper::fromRow($row) : false;
    }
}

if (! function_exists('get_users')) {
    function get_users(array $args = []): array
    {
        $args  = wp_parse_args($args, ['number' => 20, 'orderby' => 'display_name', 'order' => 'ASC']);
        $model = new UserModel();

        $rows = $model
            ->orderBy(in_array($args['orderby'], ['display_name', 'username', 'email', 'id'], true) ? $args['orderby'] : 'display_name', $args['order'])
            ->findAll(max(1, (int) $args['number']));

        return array_map(UserMapper::fromRow(...), $rows);
    }
}

if (! function_exists('count_users')) {
    function count_users(string $strategy = 'time'): array
    {
        return ['total_users' => (new UserModel())->countAll(), 'avail_roles' => []];
    }
}

if (! function_exists('get_the_author')) {
    function get_the_author(): string
    {
        $author = $GLOBALS['authordata'] ?? null;

        if (! $author instanceof WP_User) {
            $post   = get_post();
            $author = $post instanceof WP_Post ? UserMapper::find($post->post_author) : null;
        }

        return apply_filters('the_author', $author?->display_name ?? '');
    }
}

if (! function_exists('the_author')) {
    function the_author(): void
    {
        echo get_the_author();
    }
}

if (! function_exists('get_the_author_meta')) {
    function get_the_author_meta(string $field = '', ?int $user_id = null)
    {
        if ($user_id === null) {
            $author = $GLOBALS['authordata'] ?? null;
            $user   = $author instanceof WP_User ? $author : UserMapper::find((int) (get_post()->post_author ?? 0));
        } else {
            $user = UserMapper::find($user_id);
        }

        if (! $user instanceof WP_User) {
            return '';
        }

        $value = match ($field) {
            'ID', 'id'         => $user->ID,
            'display_name'     => $user->display_name,
            'user_login', 'login' => $user->user_login,
            'user_email', 'email' => $user->user_email,
            'user_url', 'url'  => $user->user_url ?? '',
            'description'      => get_user_meta($user->ID, 'description', true),
            default            => $user->{$field} ?? '',
        };

        return apply_filters('get_the_author_' . $field, $value, $user->ID);
    }
}

if (! function_exists('the_author_meta')) {
    function the_author_meta(string $field = '', ?int $user_id = null): void
    {
        echo esc_html((string) get_the_author_meta($field, $user_id));
    }
}

if (! function_exists('get_avatar')) {
    function get_avatar($id_or_email, int $size = 96, string $default = '', string $alt = '', array $args = [])
    {
        $email = '';

        if (is_numeric($id_or_email)) {
            $user  = UserMapper::find((int) $id_or_email);
            $email = $user?->user_email ?? '';
        } elseif ($id_or_email instanceof WP_User) {
            $email = $id_or_email->user_email;
        } elseif (is_string($id_or_email)) {
            $email = $id_or_email;
        }

        $url = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?s=' . $size . '&d=mm';

        $html = sprintf(
            '<img alt="%s" src="%s" class="avatar avatar-%d photo" height="%d" width="%d" loading="lazy">',
            esc_attr($alt),
            esc_url($url),
            $size,
            $size,
            $size
        );

        return apply_filters('get_avatar', $html, $id_or_email, $size, $default, $alt, $args);
    }
}

if (! function_exists('get_avatar_url')) {
    function get_avatar_url($id_or_email, array $args = []): string
    {
        $size  = (int) ($args['size'] ?? 96);
        $email = is_string($id_or_email) ? $id_or_email : (UserMapper::find((int) $id_or_email)?->user_email ?? '');

        return 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?s=' . $size . '&d=mm';
    }
}

if (! function_exists('wp_get_current_commenter')) {
    function wp_get_current_commenter(): array
    {
        return ['comment_author' => '', 'comment_author_email' => '', 'comment_author_url' => ''];
    }
}

if (! function_exists('auth_redirect')) {
    function auth_redirect(): void
    {
        if (! is_user_logged_in()) {
            wp_die('You must be logged in.', 'Authentication required', ['response' => 401]);
        }
    }
}
