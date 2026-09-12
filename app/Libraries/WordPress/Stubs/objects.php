<?php

/**
 * Core WordPress value objects, re-implemented for the LightCMS compat
 * layer. These live in the global namespace because that is where theme
 * and plugin code expects them (`$post instanceof WP_Post`).
 *
 * Every class is guarded with class_exists() so the layer can also run
 * inside an environment where the real classes already exist.
 */

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        protected array $errors = [];
        public array $error_data = [];

        public function __construct(string|int $code = '', string $message = '', mixed $data = '')
        {
            if ($code !== '' && $code !== 0) {
                $this->errors[$code][] = $message;

                if ($data !== '') {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_codes(): array
        {
            return array_keys($this->errors);
        }

        public function get_error_code(): string|int
        {
            return $this->get_error_codes()[0] ?? '';
        }

        public function get_error_messages(string|int $code = ''): array
        {
            if ($code === '') {
                return array_merge(...array_values($this->errors) ?: [[]]);
            }

            return $this->errors[$code] ?? [];
        }

        public function get_error_message(string|int $code = ''): string
        {
            $code ??= $this->get_error_code();
            $code = $code === '' ? $this->get_error_code() : $code;

            return $this->errors[$code][0] ?? '';
        }

        public function get_error_data(string|int $code = ''): mixed
        {
            $code = $code === '' ? $this->get_error_code() : $code;

            return $this->error_data[$code] ?? null;
        }

        public function has_errors(): bool
        {
            return $this->errors !== [];
        }

        public function add(string|int $code, string $message, mixed $data = ''): void
        {
            $this->errors[$code][] = $message;

            if ($data !== '') {
                $this->error_data[$code] = $data;
            }
        }

        public function add_data(mixed $data, string|int $code = ''): void
        {
            $this->error_data[$code === '' ? $this->get_error_code() : $code] = $data;
        }

        public function remove(string|int $code): void
        {
            unset($this->errors[$code], $this->error_data[$code]);
        }
    }
}

if (! class_exists('WP_Post')) {
    #[AllowDynamicProperties]
    class WP_Post implements ArrayAccess
    {
        public int $ID = 0;
        public int $post_author = 0;
        public string $post_date = '0000-00-00 00:00:00';
        public string $post_date_gmt = '0000-00-00 00:00:00';
        public string $post_content = '';
        public string $post_title = '';
        public string $post_excerpt = '';
        public string $post_status = 'publish';
        public string $comment_status = 'open';
        public string $ping_status = 'closed';
        public string $post_password = '';
        public string $post_name = '';
        public string $to_ping = '';
        public string $pinged = '';
        public string $post_modified = '0000-00-00 00:00:00';
        public string $post_modified_gmt = '0000-00-00 00:00:00';
        public string $post_content_filtered = '';
        public int $post_parent = 0;
        public string $guid = '';
        public int $menu_order = 0;
        public string $post_type = 'post';
        public string $post_mime_type = '';
        public int $comment_count = 0;
        public string $filter = 'raw';

        public function __construct(array|object $data = [])
        {
            foreach ((array) $data as $key => $value) {
                if (property_exists($this, $key)) {
                    settype($value, gettype($this->{$key}));
                }

                $this->{$key} = $value;
            }
        }

        public static function get_instance(int $postId): ?WP_Post
        {
            return get_post($postId);
        }

        /** Unknown properties fall through to post meta, exactly like WP. */
        public function __get(string $name): mixed
        {
            if ($name === 'ancestors') {
                return [];
            }

            if ($name === 'page_template') {
                return get_post_meta($this->ID, '_wp_page_template', true);
            }

            $meta = get_post_meta($this->ID, $name, true);

            return $meta === '' ? null : $meta;
        }

        public function __isset(string $name): bool
        {
            return metadata_exists('post', $this->ID, $name);
        }

        public function to_array(): array
        {
            return get_object_vars($this);
        }

        public function offsetExists(mixed $offset): bool
        {
            return property_exists($this, (string) $offset) || $this->__isset((string) $offset);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->{$offset} ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->{$offset} = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->{$offset});
        }
    }
}

if (! class_exists('WP_Term')) {
    #[AllowDynamicProperties]
    class WP_Term implements ArrayAccess
    {
        public int $term_id = 0;
        public string $name = '';
        public string $slug = '';
        public int $term_group = 0;
        public int $term_taxonomy_id = 0;
        public string $taxonomy = 'category';
        public string $description = '';
        public int $parent = 0;
        public int $count = 0;
        public string $filter = 'raw';

        public function __construct(array|object $data = [])
        {
            foreach ((array) $data as $key => $value) {
                if (property_exists($this, $key)) {
                    settype($value, gettype($this->{$key}));
                }

                $this->{$key} = $value;
            }
        }

        public function to_array(): array
        {
            return get_object_vars($this);
        }

        public function offsetExists(mixed $offset): bool
        {
            return property_exists($this, (string) $offset);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->{$offset} ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->{$offset} = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->{$offset});
        }
    }
}

if (! class_exists('WP_User')) {
    #[AllowDynamicProperties]
    class WP_User
    {
        public int $ID = 0;
        public object $data;
        public array $roles = [];
        public array $allcaps = [];
        public array $caps = [];
        public string $first_name = '';
        public string $last_name = '';

        public function __construct(array|object $data = [])
        {
            $this->data = (object) (array) $data;
            $this->ID   = (int) ($this->data->ID ?? 0);

            foreach (['roles', 'allcaps', 'caps'] as $key) {
                if (isset($this->data->{$key}) && is_array($this->data->{$key})) {
                    $this->{$key} = $this->data->{$key};
                    unset($this->data->{$key});
                }
            }
        }

        public function exists(): bool
        {
            return $this->ID > 0;
        }

        public function __get(string $name): mixed
        {
            if (isset($this->data->{$name})) {
                return $this->data->{$name};
            }

            return get_user_meta($this->ID, $name, true);
        }

        public function __isset(string $name): bool
        {
            return isset($this->data->{$name});
        }

        public function has_cap(string $capability, ...$args): bool
        {
            if (in_array('*', $this->allcaps, true) || ! empty($this->allcaps['*'])) {
                return true;
            }

            return ! empty($this->allcaps[$capability]);
        }

        public function has_prop(string $name): bool
        {
            return $this->__isset($name);
        }

        public function to_array(): array
        {
            return (array) $this->data;
        }
    }
}

if (! class_exists('WP_Comment')) {
    #[AllowDynamicProperties]
    class WP_Comment
    {
        public int $comment_ID = 0;
        public int $comment_post_ID = 0;
        public string $comment_author = '';
        public string $comment_author_email = '';
        public string $comment_author_url = '';
        public string $comment_author_IP = '';
        public string $comment_date = '0000-00-00 00:00:00';
        public string $comment_date_gmt = '0000-00-00 00:00:00';
        public string $comment_content = '';
        public int $comment_karma = 0;
        public string $comment_approved = '1';
        public string $comment_agent = '';
        public string $comment_type = 'comment';
        public int $comment_parent = 0;
        public int $user_id = 0;
        public array $children = [];

        public function __construct(array|object $data = [])
        {
            foreach ((array) $data as $key => $value) {
                if (property_exists($this, $key) && ! is_array($this->{$key})) {
                    settype($value, gettype($this->{$key}));
                }

                $this->{$key} = $value;
            }
        }

        public function to_array(): array
        {
            return get_object_vars($this);
        }

        public function get_children(): array
        {
            return $this->children;
        }
    }
}

if (! class_exists('WP_Theme')) {
    #[AllowDynamicProperties]
    class WP_Theme implements ArrayAccess
    {
        public function __construct(protected array $headers = [])
        {
        }

        public function get(string $header): string|false
        {
            return $this->headers[$header] ?? false;
        }

        public function get_stylesheet(): string
        {
            return (string) ($this->headers['slug'] ?? '');
        }

        public function get_template(): string
        {
            return (string) ($this->headers['Template'] ?: ($this->headers['slug'] ?? ''));
        }

        public function get_stylesheet_directory(): string
        {
            return (string) ($this->headers['path'] ?? '');
        }

        public function get_stylesheet_directory_uri(): string
        {
            return (string) ($this->headers['uri'] ?? '');
        }

        public function get_template_directory(): string
        {
            return get_template_directory();
        }

        public function get_template_directory_uri(): string
        {
            return get_template_directory_uri();
        }

        public function get_screenshot(): string|false
        {
            return ($this->headers['screenshot'] ?? '') ?: false;
        }

        public function exists(): bool
        {
            return ($this->headers['Name'] ?? '') !== '';
        }

        public function parent(): WP_Theme|false
        {
            $parent = $this->headers['Template'] ?? '';

            return $parent === '' ? false : wp_get_theme($parent);
        }

        public function display(string $header): string
        {
            return (string) $this->get($header);
        }

        public function __toString(): string
        {
            return (string) ($this->headers['Name'] ?? '');
        }

        public function offsetExists(mixed $offset): bool
        {
            return isset($this->headers[$offset]);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->headers[$offset] ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->headers[$offset] = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->headers[$offset]);
        }
    }
}

/**
 * wp_styles()/wp_scripts() return these. Themes mostly use them to read
 * `registered`/`queue` or to call add_data()/add_inline_style(), so those
 * are wired to the Assets queue; the rest of WP_Dependencies is not.
 */
if (! class_exists('WP_Dependencies')) {
    #[AllowDynamicProperties]
    class WP_Dependencies
    {
        public array $registered = [];
        public array $queue = [];
        public array $done = [];

        public function add($handle, $src, $deps = [], $ver = false, $args = null): bool
        {
            $this->registered[$handle] = (object) compact('handle', 'src', 'deps', 'ver', 'args');

            return true;
        }

        public function enqueue($handles): void
        {
            foreach ((array) $handles as $handle) {
                $this->queue[] = $handle;
            }
        }

        public function dequeue($handles): void
        {
            $this->queue = array_values(array_diff($this->queue, (array) $handles));
        }

        public function query($handle, $status = 'registered')
        {
            return $this->registered[$handle] ?? false;
        }

        public function add_data($handle, $key, $value): bool
        {
            return true;
        }

        public function get_data($handle, $key)
        {
            return false;
        }
    }
}

if (! class_exists('WP_Styles')) {
    class WP_Styles extends WP_Dependencies
    {
        public function add_inline_style($handle, $data): bool
        {
            return wp_add_inline_style($handle, $data);
        }
    }
}

if (! class_exists('WP_Scripts')) {
    class WP_Scripts extends WP_Dependencies
    {
        public function add_inline_script($handle, $data, $position = 'after'): bool
        {
            return wp_add_inline_script($handle, $data, $position);
        }

        public function localize($handle, $object_name, $l10n): bool
        {
            return wp_localize_script($handle, $object_name, (array) $l10n);
        }
    }
}
