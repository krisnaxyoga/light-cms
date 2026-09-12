<?php

use Config\Database as CIDatabase;

defined('OBJECT')  || define('OBJECT', 'OBJECT');
defined('OBJECT_K') || define('OBJECT_K', 'OBJECT_K');
defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
defined('ARRAY_N') || define('ARRAY_N', 'ARRAY_N');

/**
 * The $wpdb shim.
 *
 * $wpdb->posts and friends point at the WP-shaped SQL views created by
 * migration 000022, so plugin code that runs raw SELECTs against
 * {$wpdb->posts} reads real LightCMS content. Those views are read-only:
 * an INSERT/UPDATE against them fails, by design — writes belong in the
 * API (wp_insert_post(), update_post_meta(), ...) which routes to the
 * LightCMS models and keeps sitemaps/caches/redirects consistent.
 *
 * wp_options and the three *meta tables are real tables, so they are
 * fully readable and writable.
 */
if (! class_exists('wpdb')) {
    #[AllowDynamicProperties]
    class wpdb
    {
        public string $prefix = 'wp_';
        public string $base_prefix = 'wp_';
        public string $posts = 'wp_posts';
        public string $postmeta = 'wp_postmeta';
        public string $options = 'wp_options';
        public string $users = 'wp_users';
        public string $usermeta = 'wp_usermeta';
        public string $terms = 'wp_terms';
        public string $termmeta = 'wp_termmeta';
        public string $term_taxonomy = 'wp_term_taxonomy';
        public string $term_relationships = 'wp_term_relationships';
        public string $comments = 'wp_comments';
        public string $commentmeta = 'wp_commentmeta';
        public string $last_error = '';
        public string $last_query = '';
        public int $insert_id = 0;
        public int $num_rows = 0;
        public int $rows_affected = 0;
        public bool $show_errors = false;
        public bool $suppress_errors = false;

        /** Views that cannot be written to; see the class docblock. */
        protected const READ_ONLY_SUFFIXES = ['posts', 'users', 'terms', 'term_taxonomy', 'term_relationships', 'comments'];

        protected $db;

        public function __construct($connection = null)
        {
            $this->db = $connection ?? CIDatabase::connect();

            // The connection prefix (Config\Database::DBPrefix) comes first:
            // the compat tables and views are created through the same
            // connection, so they carry it too.
            $prefix = $this->db->getPrefix() . config(\Config\WordPress::class)->tablePrefix;
            $this->prefix = $this->base_prefix = $prefix;

            foreach (['posts', 'postmeta', 'options', 'users', 'usermeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'comments', 'commentmeta'] as $table) {
                $this->{$table} = $prefix . $table;
            }
        }

        public function prepare(string $query, ...$args)
        {
            if ($args === []) {
                return $query;
            }

            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }

            // %s/%d/%f, optionally quoted by the caller ('%s') — WP strips
            // those quotes because it adds its own.
            $query = str_replace(["'%s'", '"%s"'], '%s', $query);
            $query = preg_replace('/%(?![sdfF%])/', '%%', $query) ?? $query;

            $index = 0;

            return preg_replace_callback('/%[sdfF]/', function (array $match) use (&$index, $args) {
                $value = $args[$index] ?? '';
                $index++;

                return match ($match[0]) {
                    '%d'       => (string) (int) $value,
                    '%f', '%F' => (string) (float) $value,
                    default    => $this->db->escape((string) $value),
                };
            }, $query);
        }

        public function query(string $query)
        {
            $this->last_query = $query;
            $this->last_error = '';

            if ($this->isWriteToView($query)) {
                $this->last_error = 'LightCMS: ' . $this->targetTable($query) . ' is a read-only compatibility view; use the WordPress API (wp_insert_post/update_post_meta/...) to write.';
                log_message('warning', 'WP compat: blocked write to view — ' . $this->last_query);

                return false;
            }

            try {
                $result = $this->db->query($query);
            } catch (\Throwable $e) {
                $this->last_error = $e->getMessage();
                log_message('error', 'WP compat $wpdb error: ' . $e->getMessage() . ' -- ' . $query);

                return false;
            }

            if (is_bool($result)) {
                $this->rows_affected = $this->db->affectedRows();
                $this->insert_id     = (int) $this->db->insertID();

                return $this->rows_affected;
            }

            $this->num_rows = $result->getNumRows();

            return $result;
        }

        public function get_results(string $query, string $output = OBJECT)
        {
            $result = $this->query($query);

            if ($result === false || is_int($result)) {
                return [];
            }

            return match ($output) {
                ARRAY_A => $result->getResultArray(),
                ARRAY_N => array_map('array_values', $result->getResultArray()),
                default => $result->getResult(),
            };
        }

        public function get_row(string $query, string $output = OBJECT, int $y = 0)
        {
            $rows = $this->get_results($query, $output);

            return $rows[$y] ?? null;
        }

        public function get_var(string $query, int $x = 0, int $y = 0)
        {
            $rows = $this->get_results($query, ARRAY_N);

            return $rows[$y][$x] ?? null;
        }

        public function get_col(string $query, int $x = 0)
        {
            $rows = $this->get_results($query, ARRAY_N);

            return array_map(static fn (array $row) => $row[$x] ?? null, $rows);
        }

        public function insert(string $table, array $data, $format = null)
        {
            if ($this->isReadOnly($table)) {
                $this->last_error = "LightCMS: {$table} is a read-only compatibility view.";

                return false;
            }

            $done = $this->db->table($table)->insert($data);
            $this->insert_id = (int) $this->db->insertID();

            return $done ? 1 : false;
        }

        public function replace(string $table, array $data, $format = null)
        {
            return $this->insert($table, $data, $format);
        }

        public function update(string $table, array $data, array $where, $format = null, $whereFormat = null)
        {
            if ($this->isReadOnly($table)) {
                $this->last_error = "LightCMS: {$table} is a read-only compatibility view.";

                return false;
            }

            $builder = $this->db->table($table);

            foreach ($where as $column => $value) {
                $builder->where($column, $value);
            }

            $done = $builder->update($data);
            $this->rows_affected = $this->db->affectedRows();

            return $done ? $this->rows_affected : false;
        }

        public function delete(string $table, array $where, $whereFormat = null)
        {
            if ($this->isReadOnly($table)) {
                $this->last_error = "LightCMS: {$table} is a read-only compatibility view.";

                return false;
            }

            $builder = $this->db->table($table);

            foreach ($where as $column => $value) {
                $builder->where($column, $value);
            }

            $done = $builder->delete();
            $this->rows_affected = $this->db->affectedRows();

            return $done ? $this->rows_affected : false;
        }

        public function esc_like(string $text): string
        {
            return addcslashes($text, '_%\\');
        }

        public function get_charset_collate(): string
        {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        public function get_blog_prefix($blogId = null): string
        {
            return $this->prefix;
        }

        public function has_cap(string $capability): bool
        {
            return in_array(strtolower($capability), ['collation', 'set_charset', 'utf8mb4', 'utf8mb4_520'], true);
        }

        public function flush(): void
        {
            $this->last_error = '';
            $this->num_rows   = 0;
        }

        public function hide_errors(): void
        {
            $this->show_errors = false;
        }

        public function show_errors(): void
        {
            $this->show_errors = true;
        }

        public function suppress_errors($suppress = true)
        {
            $previous = $this->suppress_errors;
            $this->suppress_errors = (bool) $suppress;

            return $previous;
        }

        public function print_error(): void
        {
            if ($this->last_error !== '' && $this->show_errors) {
                echo '<div class="error"><p>' . esc_html($this->last_error) . '</p></div>';
            }
        }

        protected function isWriteToView(string $query): bool
        {
            if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE)\b/i', $query) !== 1) {
                return false;
            }

            return $this->isReadOnly($this->targetTable($query));
        }

        /** True for the WP-shaped views, which cannot be written to. */
        protected function isReadOnly(string $table): bool
        {
            foreach (self::READ_ONLY_SUFFIXES as $suffix) {
                if ($table === $this->prefix . $suffix) {
                    return true;
                }
            }

            return false;
        }

        protected function targetTable(string $query): string
        {
            if (preg_match('/(?:INTO|UPDATE|FROM|TABLE)\s+`?([A-Za-z0-9_]+)`?/i', $query, $match) === 1) {
                return $match[1];
            }

            return '';
        }
    }
}
