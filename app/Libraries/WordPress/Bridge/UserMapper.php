<?php

namespace App\Libraries\WordPress\Bridge;

use App\Models\UserModel;
use Config\Database;
use Config\WordPress as WordPressConfig;
use WP_User;

/**
 * LightCMS `users` row -> WP_User, including a capability set derived
 * from the LightCMS role so current_user_can() answers sensibly for
 * plugin code that gates admin UI on 'manage_options' and friends.
 */
class UserMapper
{
    /** @var array<int, WP_User> */
    protected static array $cache = [];

    public static function fromRow(array $row): WP_User
    {
        $id = (int) ($row['id'] ?? 0);

        if ($id > 0 && isset(static::$cache[$id])) {
            return static::$cache[$id];
        }

        $role = static::roleSlug($row);
        $caps = static::capabilities($role);

        $user = new WP_User([
            'ID'              => $id,
            'user_login'      => (string) ($row['username'] ?? ''),
            'user_nicename'   => (string) ($row['username'] ?? ''),
            'user_email'      => (string) ($row['email'] ?? ''),
            'user_url'        => '',
            'user_registered' => (string) ($row['created_at'] ?? ''),
            'display_name'    => (string) ($row['display_name'] ?? $row['username'] ?? ''),
            'roles'           => $role === '' ? [] : [$role],
            'allcaps'         => $caps,
            'caps'            => $role === '' ? [] : [$role => true],
        ]);

        $user->data->nickname = $user->data->display_name;
        $user->data->avatar   = $row['avatar'] ?? '';

        if ($id > 0) {
            static::$cache[$id] = $user;
        }

        return $user;
    }

    public static function find(int $id): ?WP_User
    {
        if ($id <= 0) {
            return null;
        }

        $row = (new UserModel())->find($id);

        return $row ? static::fromRow($row) : null;
    }

    /** The logged-in LightCMS admin, or an empty WP_User for a visitor. */
    public static function current(): WP_User
    {
        $session = session();
        $id      = (int) ($session?->get('userId') ?? 0);

        if ($id > 0 && ($user = static::find($id)) !== null) {
            return $user;
        }

        return new WP_User(['ID' => 0, 'user_login' => '', 'display_name' => '', 'roles' => [], 'allcaps' => []]);
    }

    protected static function roleSlug(array $row): string
    {
        if (isset($row['role_slug'])) {
            return (string) $row['role_slug'];
        }

        if (empty($row['role_id'])) {
            return 'subscriber';
        }

        $role = Database::connect()->table('roles')->where('id', $row['role_id'])->get()->getRowArray();

        return (string) ($role['slug'] ?? $role['name'] ?? 'subscriber');
    }

    /** @return array<string, bool> */
    public static function capabilities(string $role): array
    {
        $map  = config(WordPressConfig::class)->capabilityMap;
        $caps = $map[$role] ?? $map['subscriber'];

        $out = [];

        foreach ($caps as $cap) {
            $out[$cap] = true;
        }

        if (isset($out['*'])) {
            // An administrator: also answer true for the named core caps a
            // plugin might check with has_cap() directly.
            foreach (['manage_options', 'edit_posts', 'edit_pages', 'publish_posts', 'upload_files', 'edit_theme_options', 'activate_plugins', 'install_plugins', 'switch_themes', 'manage_categories', 'moderate_comments', 'edit_others_posts', 'delete_posts', 'read', 'unfiltered_html', 'edit_users', 'list_users'] as $cap) {
                $out[$cap] = true;
            }
        }

        return $out;
    }

    public static function flush(): void
    {
        static::$cache = [];
    }
}
