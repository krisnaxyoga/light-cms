<?php

use App\Models\MediaModel;
use App\Models\PostModel;

/**
 * Featured images and attachments.
 *
 * LightCMS stores a post's featured image as a path on the post row and
 * keeps upload metadata (dimensions, alt text, WebP/thumbnail variants)
 * in `media`. Both are bridged here, so the_post_thumbnail() renders even
 * for an image that was never registered as an attachment, and the
 * generated srcset uses the variants the LightCMS image pipeline made.
 */

if (! function_exists('wp_media_row')) {
    /** @internal media row for an attachment id or a stored file path */
    function wp_media_row($idOrPath): ?array
    {
        static $cache = [];
        $key = (string) $idOrPath;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $model = new MediaModel();
        $row   = is_numeric($idOrPath)
            ? $model->find((int) $idOrPath)
            : $model->where('filepath', ltrim((string) $idOrPath, '/'))->first();

        return $cache[$key] = $row ?: null;
    }
}

if (! function_exists('wp_media_url')) {
    function wp_media_url(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        return preg_match('#^https?://#i', $path) === 1 ? $path : base_url(ltrim($path, '/'));
    }
}

if (! function_exists('wp_upload_dir')) {
    function wp_upload_dir(?string $time = null, bool $create_dir = true, bool $refresh_cache = false): array
    {
        $subdir  = '/' . date('Y/m', $time !== null ? (strtotime($time) ?: time()) : time());
        $baseDir = rtrim(FCPATH . config(Config\WordPress::class)->uploadsPath, '/');
        $baseUrl = rtrim(base_url(config(Config\WordPress::class)->uploadsPath), '/');

        return [
            'path'    => $baseDir . $subdir,
            'url'     => $baseUrl . $subdir,
            'subdir'  => $subdir,
            'basedir' => $baseDir,
            'baseurl' => $baseUrl,
            'error'   => false,
        ];
    }
}

if (! function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post = null)
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post) {
            return false;
        }

        $metaId = get_post_meta($post->ID, '_thumbnail_id', true);

        if ($metaId !== '' && (int) $metaId > 0) {
            return (int) $metaId;
        }

        $path = $post->lightcms_featured_image ?? null;
        $row  = $path ? wp_media_row($path) : null;

        return $row ? (int) $row['id'] : 0;
    }
}

if (! function_exists('has_post_thumbnail')) {
    function has_post_thumbnail($post = null): bool
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post) {
            return false;
        }

        return ! empty($post->lightcms_featured_image) || (int) get_post_thumbnail_id($post) > 0;
    }
}

if (! function_exists('wp_attachment_source')) {
    /**
     * @internal Resolve [url, width, height, alt] for an attachment id or
     *           featured-image path at the requested size.
     *
     * @return array{0: string, 1: int, 2: int, 3: string}
     */
    function wp_attachment_source($idOrPath, $size = 'full'): array
    {
        $row = wp_media_row($idOrPath);

        if ($row === null) {
            return [wp_media_url(is_string($idOrPath) ? $idOrPath : ''), 0, 0, ''];
        }

        $path     = $row['filepath'];
        $variants = $row['variants'] ?? [];
        $sizeName = is_array($size) ? 'full' : (string) $size;

        if ($sizeName !== 'full' && is_array($variants) && isset($variants[$sizeName])) {
            $path = is_array($variants[$sizeName]) ? ($variants[$sizeName]['path'] ?? $path) : $variants[$sizeName];
        }

        return [
            wp_media_url($path),
            (int) ($row['width'] ?? 0),
            (int) ($row['height'] ?? 0),
            (string) ($row['alt_text'] ?? ''),
        ];
    }
}

if (! function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url(int $attachment_id = 0)
    {
        $row = wp_media_row($attachment_id);

        return $row === null ? false : wp_media_url($row['filepath']);
    }
}

if (! function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url(int $attachment_id, $size = 'thumbnail', bool $icon = false)
    {
        [$url] = wp_attachment_source($attachment_id, $size);

        return $url !== '' ? $url : false;
    }
}

if (! function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src(int $attachment_id, $size = 'thumbnail', bool $icon = false)
    {
        [$url, $width, $height] = wp_attachment_source($attachment_id, $size);

        return $url === '' ? false : [$url, $width, $height, $size !== 'full'];
    }
}

if (! function_exists('wp_get_attachment_image')) {
    function wp_get_attachment_image(int $attachment_id, $size = 'thumbnail', bool $icon = false, $attr = ''): string
    {
        [$url, $width, $height, $alt] = wp_attachment_source($attachment_id, $size);

        if ($url === '') {
            return '';
        }

        $attr = wp_parse_args($attr, ['alt' => $alt, 'loading' => 'lazy', 'decoding' => 'async']);
        $attr = apply_filters('wp_get_attachment_image_attributes', $attr, get_post($attachment_id), $size);

        $html = '<img src="' . esc_url($url) . '"';

        if ($width > 0) {
            $html .= ' width="' . $width . '"';
        }

        if ($height > 0) {
            $html .= ' height="' . $height . '"';
        }

        foreach ($attr as $name => $value) {
            $html .= ' ' . $name . '="' . esc_attr((string) $value) . '"';
        }

        return $html . '>';
    }
}

if (! function_exists('wp_get_attachment_metadata')) {
    function wp_get_attachment_metadata(int $attachment_id = 0, bool $unfiltered = false)
    {
        $row = wp_media_row($attachment_id);

        if ($row === null) {
            return false;
        }

        return [
            'width'  => (int) ($row['width'] ?? 0),
            'height' => (int) ($row['height'] ?? 0),
            'file'   => $row['filepath'],
            'sizes'  => $row['variants'] ?? [],
        ];
    }
}

if (! function_exists('wp_get_attachment_caption')) {
    function wp_get_attachment_caption(int $post_id = 0)
    {
        $row = wp_media_row($post_id);

        return $row === null ? false : (string) ($row['caption'] ?? '');
    }
}

if (! function_exists('get_attached_file')) {
    function get_attached_file(int $attachment_id, bool $unfiltered = false)
    {
        $row = wp_media_row($attachment_id);

        return $row === null ? false : FCPATH . ltrim($row['filepath'], '/');
    }
}

if (! function_exists('get_the_post_thumbnail')) {
    function get_the_post_thumbnail($post = null, $size = 'post-thumbnail', $attr = ''): string
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post || ! has_post_thumbnail($post)) {
            return '';
        }

        $id = (int) get_post_thumbnail_id($post);

        if ($id > 0) {
            $html = wp_get_attachment_image($id, $size, false, $attr);

            if ($html !== '') {
                return apply_filters('post_thumbnail_html', $html, $post->ID, $id, $size, $attr);
            }
        }

        // No media row: render straight from the stored path so the theme
        // still gets its image.
        $attr = wp_parse_args($attr, ['alt' => $post->post_title, 'loading' => 'lazy']);
        $html = '<img src="' . esc_url(wp_media_url((string) $post->lightcms_featured_image)) . '"';

        foreach ($attr as $name => $value) {
            $html .= ' ' . $name . '="' . esc_attr((string) $value) . '"';
        }

        return apply_filters('post_thumbnail_html', $html . '>', $post->ID, $id, $size, $attr);
    }
}

if (! function_exists('the_post_thumbnail')) {
    function the_post_thumbnail($size = 'post-thumbnail', $attr = ''): void
    {
        echo get_the_post_thumbnail(null, $size, $attr);
    }
}

if (! function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url($post = null, $size = 'post-thumbnail')
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post) {
            return false;
        }

        $id = (int) get_post_thumbnail_id($post);

        if ($id > 0) {
            [$url] = wp_attachment_source($id, $size);

            if ($url !== '') {
                return $url;
            }
        }

        $path = (string) ($post->lightcms_featured_image ?? '');

        return $path === '' ? false : wp_media_url($path);
    }
}

if (! function_exists('the_post_thumbnail_url')) {
    function the_post_thumbnail_url($size = 'post-thumbnail'): void
    {
        echo esc_url((string) get_the_post_thumbnail_url(null, $size));
    }
}

if (! function_exists('set_post_thumbnail')) {
    function set_post_thumbnail($post, int $thumbnail_id)
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post) {
            return false;
        }

        $row = wp_media_row($thumbnail_id);

        if ($row !== null) {
            (new PostModel())->update($post->ID, ['featured_image' => $row['filepath']]);
        }

        return update_post_meta($post->ID, '_thumbnail_id', $thumbnail_id);
    }
}

if (! function_exists('delete_post_thumbnail')) {
    function delete_post_thumbnail($post)
    {
        $post = get_post($post);

        if (! $post instanceof WP_Post) {
            return false;
        }

        (new PostModel())->update($post->ID, ['featured_image' => null]);

        return delete_post_meta($post->ID, '_thumbnail_id');
    }
}

if (! function_exists('wp_get_attachment_thumb_url')) {
    function wp_get_attachment_thumb_url(int $post_id = 0)
    {
        return wp_get_attachment_image_url($post_id, 'thumbnail');
    }
}

if (! function_exists('wp_attachment_is_image')) {
    function wp_attachment_is_image($post = null): bool
    {
        $row = wp_media_row(is_object($post) ? ($post->ID ?? 0) : (int) $post);

        return $row !== null && str_starts_with((string) ($row['filetype'] ?? ''), 'image');
    }
}

if (! function_exists('wp_calculate_image_srcset')) {
    function wp_calculate_image_srcset(array $size_array, string $image_src, array $image_meta, int $attachment_id = 0)
    {
        $sizes = $image_meta['sizes'] ?? [];

        if (! is_array($sizes) || $sizes === []) {
            return false;
        }

        $parts = [];

        foreach ($sizes as $variant) {
            $path  = is_array($variant) ? ($variant['path'] ?? null) : $variant;
            $width = is_array($variant) ? (int) ($variant['width'] ?? 0) : 0;

            if ($path !== null && $width > 0) {
                $parts[] = wp_media_url($path) . ' ' . $width . 'w';
            }
        }

        return $parts === [] ? false : implode(', ', $parts);
    }
}

if (! function_exists('wp_get_attachment_link')) {
    function wp_get_attachment_link($post = 0, $size = 'thumbnail', bool $permalink = false, bool $icon = false, $text = false): string
    {
        $id  = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
        $url = wp_get_attachment_url($id);

        if ($url === false) {
            return '';
        }

        return '<a href="' . esc_url($url) . '">' . ($text !== false ? esc_html((string) $text) : wp_get_attachment_image($id, $size)) . '</a>';
    }
}
