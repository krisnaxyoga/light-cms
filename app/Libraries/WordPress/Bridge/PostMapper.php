<?php

namespace App\Libraries\WordPress\Bridge;

use App\Libraries\Editor\BlockRenderer;
use WP_Post;

/**
 * LightCMS `posts` row <-> WP_Post.
 *
 * The one interesting part is content: LightCMS stores a block array as
 * JSON, while every WP theme expects HTML in $post->post_content. So the
 * blocks are rendered once here and the raw JSON is kept in
 * post_content_filtered, which is where a LightCMS-aware plugin can still
 * reach the structured version.
 */
class PostMapper
{
    protected static ?BlockRenderer $renderer = null;

    /** @var array<int, WP_Post> */
    protected static array $cache = [];

    public static function fromRow(array $row): WP_Post
    {
        $id = (int) ($row['id'] ?? 0);

        if ($id > 0 && isset(static::$cache[$id])) {
            return static::$cache[$id];
        }

        $date     = $row['published_at'] ?? $row['created_at'] ?? null;
        $modified = $row['updated_at'] ?? $date;
        $raw      = (string) ($row['content'] ?? '');

        $post = new WP_Post([
            'ID'                    => $id,
            'post_author'           => (int) ($row['author_id'] ?? 0),
            'post_date'             => (string) ($date ?? '0000-00-00 00:00:00'),
            'post_date_gmt'         => (string) ($date ?? '0000-00-00 00:00:00'),
            'post_content'          => static::renderContent($raw),
            'post_content_filtered' => $raw,
            'post_title'            => (string) ($row['title'] ?? ''),
            'post_excerpt'          => (string) ($row['excerpt'] ?? ''),
            'post_status'           => static::statusToWp((string) ($row['status'] ?? 'draft')),
            'comment_status'        => (string) ($row['comment_status'] ?? 'open'),
            'post_name'             => (string) ($row['slug'] ?? ''),
            'post_modified'         => (string) ($modified ?? '0000-00-00 00:00:00'),
            'post_modified_gmt'     => (string) ($modified ?? '0000-00-00 00:00:00'),
            'post_type'             => (string) ($row['post_type'] ?? 'post'),
            'guid'                  => rtrim(base_url((string) ($row['slug'] ?? '')), '/'),
            'comment_count'         => (int) ($row['comment_count'] ?? 0),
            'filter'                => 'raw',
        ]);

        // Featured image lives on the row in LightCMS; expose it the WP way
        // too so has_post_thumbnail()/the_post_thumbnail() work.
        $post->lightcms_featured_image = $row['featured_image'] ?? null;
        $post->lightcms_view_count     = (int) ($row['view_count'] ?? 0);

        if ($id > 0) {
            static::$cache[$id] = $post;
        }

        return $post;
    }

    /** @param list<array> $rows */
    public static function fromRows(array $rows): array
    {
        return array_map(static::fromRow(...), $rows);
    }

    /**
     * WP_Post/array of WP fields -> LightCMS `posts` columns, for
     * wp_insert_post()/wp_update_post().
     */
    public static function toRow(array $data): array
    {
        $row = [];
        $map = [
            'post_title'     => 'title',
            'post_name'      => 'slug',
            'post_excerpt'   => 'excerpt',
            'post_author'    => 'author_id',
            'post_type'      => 'post_type',
            'comment_status' => 'comment_status',
        ];

        foreach ($map as $wp => $local) {
            if (array_key_exists($wp, $data)) {
                $row[$local] = $data[$wp];
            }
        }

        if (array_key_exists('post_content', $data)) {
            $row['content'] = static::contentToStorage((string) $data['post_content']);
        }

        if (array_key_exists('post_status', $data)) {
            $row['status'] = static::statusFromWp((string) $data['post_status']);
        }

        if (! empty($data['post_date'])) {
            $row['published_at'] = $data['post_date'];
        } elseif (($row['status'] ?? null) === 'published' && ! array_key_exists('published_at', $row)) {
            $row['published_at'] = date('Y-m-d H:i:s');
        }

        if (isset($row['title']) && empty($row['slug'])) {
            $row['slug'] = url_title($row['title'], '-', true);
        }

        return $row;
    }

    public static function statusToWp(string $status): string
    {
        return match ($status) {
            'published' => 'publish',
            'scheduled' => 'future',
            'trash'     => 'trash',
            default     => 'draft',
        };
    }

    public static function statusFromWp(string $status): string
    {
        return match ($status) {
            'publish' => 'published',
            'future'  => 'scheduled',
            'trash'   => 'trash',
            'private' => 'published',
            default   => 'draft',
        };
    }

    /**
     * Block JSON -> HTML. Anything that is not a block array (a WP theme
     * writing plain HTML through wp_insert_post, say) is passed through.
     */
    public static function renderContent(string $content): string
    {
        $trimmed = trim($content);

        if ($trimmed === '' || $trimmed[0] !== '[') {
            return $content;
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            return $content;
        }

        static::$renderer ??= new BlockRenderer();

        try {
            return static::$renderer->render($trimmed);
        } catch (\Throwable $e) {
            log_message('error', 'WP compat: block render failed: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * HTML coming from WP code is stored as a single html block so the
     * LightCMS editor can still open the post without losing anything.
     */
    public static function contentToStorage(string $html): string
    {
        $trimmed = trim($html);

        if ($trimmed !== '' && $trimmed[0] === '[' && is_array(json_decode($trimmed, true))) {
            return $trimmed; // already block JSON
        }

        return json_encode([[ 'type' => 'html', 'content' => $html, 'attrs' => new \stdClass() ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function flush(): void
    {
        static::$cache = [];
    }
}
