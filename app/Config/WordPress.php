<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * WordPress compatibility layer (see README "WordPress compatibility").
 *
 * LightCMS can run *classic* WordPress themes and plugins by emulating the
 * WordPress runtime API on top of its own tables. Nothing from WordPress
 * core is bundled — these are re-implementations of the documented API
 * surface, so the LightCMS codebase stays GPL-free. The themes/plugins you
 * drop into wp-content/ keep their own licence.
 */
class WordPress extends BaseConfig
{
    /**
     * Master switch. When false the compat layer never boots, no global
     * functions are defined, and WP themes cannot be activated.
     */
    public bool $enabled = true;

    /** Paths relative to FCPATH (public/). */
    public string $contentPath = 'wp-content/';
    public string $themesPath  = 'wp-content/themes/';
    public string $pluginsPath = 'wp-content/plugins/';
    public string $uploadsPath = 'uploads/';

    /**
     * Prefix used by the $wpdb shim and by the WP-shaped SQL views that
     * migration 000022 creates over the LightCMS tables.
     */
    public string $tablePrefix = 'wp_';

    /**
     * Fired-on-request pseudo-cron, the WP_CRON equivalent. Off by default:
     * LightCMS already ships a real cron entry (`php spark lightcms:cleanup`)
     * and that command runs due WP cron events too.
     */
    public bool $spawnCronOnRequest = false;

    /**
     * Let plugin/theme code reach the network through wp_remote_*().
     * Turn off to hard-block outbound HTTP from third-party code.
     */
    public bool $allowRemoteRequests = true;

    /** Timeout (seconds) applied to every wp_remote_* call. */
    public int $remoteTimeout = 10;

    /**
     * Value reported by get_bloginfo('version') / $wp_version. Some themes
     * gate features on it; claim a modern classic-era release.
     */
    public string $emulatedVersion = '6.5';

    /**
     * Extra image sizes are collected from add_image_size(); these are the
     * built-ins that map onto LightCMS's own thumbnail pipeline.
     */
    public array $imageSizeMap = [
        'thumbnail' => 'thumbnail',
        'medium'    => 'medium',
        'large'     => 'large',
        'full'      => '',
    ];

    /**
     * WP capabilities granted per LightCMS role slug. Used by
     * current_user_can() so plugin/theme permission checks behave.
     */
    public array $capabilityMap = [
        'super_admin'   => ['*'],
        'administrator' => ['*'],
        'editor'        => ['read', 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_others_posts', 'manage_categories', 'moderate_comments', 'upload_files', 'edit_pages', 'publish_pages'],
        'author'        => ['read', 'edit_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'upload_files'],
        'contributor'   => ['read', 'edit_posts', 'delete_posts'],
        'subscriber'    => ['read'],
    ];
}
