<?php

use App\Libraries\WordPress\Bridge\PostMapper;
use App\Libraries\WordPress\Hooks;
use App\Libraries\WordPress\QueryBuilder;

/**
 * WP_Query on top of the LightCMS `posts` table.
 *
 * The public surface is the one themes use: the loop (have_posts/the_post),
 * the conditional flags, found_posts/max_num_pages, and get()/set() on
 * query vars. Argument translation lives in QueryBuilder.
 */
if (! class_exists('WP_Query')) {
    #[AllowDynamicProperties]
    class WP_Query
    {
        public array $query = [];
        public array $query_vars = [];

        /** @var list<WP_Post> */
        public array $posts = [];

        public int $post_count = 0;
        public int $current_post = -1;
        public bool $in_the_loop = false;
        public ?WP_Post $post = null;
        public int $found_posts = 0;
        public int $max_num_pages = 0;

        /** @var list<WP_Comment> */
        public array $comments = [];
        public int $comment_count = 0;
        public int $current_comment = -1;

        public bool $is_single = false;
        public bool $is_preview = false;
        public bool $is_page = false;
        public bool $is_archive = false;
        public bool $is_date = false;
        public bool $is_year = false;
        public bool $is_month = false;
        public bool $is_day = false;
        public bool $is_time = false;
        public bool $is_author = false;
        public bool $is_category = false;
        public bool $is_tag = false;
        public bool $is_tax = false;
        public bool $is_search = false;
        public bool $is_feed = false;
        public bool $is_comment_feed = false;
        public bool $is_trackback = false;
        public bool $is_home = false;
        public bool $is_privacy_policy = false;
        public bool $is_404 = false;
        public bool $is_embed = false;
        public bool $is_paged = false;
        public bool $is_admin = false;
        public bool $is_attachment = false;
        public bool $is_singular = false;
        public bool $is_robots = false;
        public bool $is_favicon = false;
        public bool $is_posts_page = false;
        public bool $is_post_type_archive = false;
        public bool $is_front_page = false;

        public mixed $queried_object = null;
        public int $queried_object_id = 0;

        public function __construct(array|string $query = '')
        {
            if ($query !== '' && $query !== []) {
                $this->query($query);
            }
        }

        public function query(array|string $query): array
        {
            $this->init_query_flags();

            if (is_string($query)) {
                parse_str($query, $parsed);
                $query = $parsed;
            }

            $this->query      = $query;
            $this->query_vars = $query;

            return $this->get_posts();
        }

        public function get_posts(): array
        {
            $args = Hooks::applyFilters('lightcms_wp_query_args', $this->query_vars, [$this]);

            $result = QueryBuilder::run($args);
            $rows   = $result['rows'];

            $this->posts = PostMapper::fromRows($rows);
            $this->posts = (array) Hooks::applyFilters('the_posts', $this->posts, [$this]);

            $this->post_count    = count($this->posts);
            $this->found_posts   = (int) $result['found'];
            $perPage             = (int) ($args['posts_per_page'] ?? get_option('posts_per_page', 10));
            $this->max_num_pages = $perPage > 0 ? (int) ceil($this->found_posts / $perPage) : 1;
            $this->post          = $this->posts[0] ?? null;
            $this->current_post  = -1;

            if ($this->posts !== []) {
                // One query for every post's meta instead of one per post.
                wp_meta_store()->primePosts(array_map(static fn (WP_Post $post) => $post->ID, $this->posts));
            }

            if (($args['fields'] ?? '') === 'ids') {
                return array_map(static fn (WP_Post $post) => $post->ID, $this->posts);
            }

            return $this->posts;
        }

        public function have_posts(): bool
        {
            if ($this->current_post + 1 < $this->post_count) {
                return true;
            }

            if ($this->post_count > 0 && $this->current_post + 1 === $this->post_count) {
                Hooks::doAction('loop_end', [$this]);
                $this->rewind_posts();
            }

            $this->in_the_loop = false;

            return false;
        }

        public function the_post(): void
        {
            if (! $this->in_the_loop) {
                Hooks::doAction('loop_start', [$this]);
            }

            $this->in_the_loop = true;
            $this->current_post++;

            $this->post = $this->posts[$this->current_post] ?? null;

            if ($this->post !== null) {
                setup_postdata($this->post);
            }
        }

        public function rewind_posts(): void
        {
            $this->current_post = -1;
            $this->post         = $this->posts[0] ?? null;
        }

        public function reset_postdata(): void
        {
            if ($this->post !== null) {
                $GLOBALS['post'] = $this->post;
                setup_postdata($this->post);
            }
        }

        public function get(string $var, mixed $default = ''): mixed
        {
            return $this->query_vars[$var] ?? $default;
        }

        public function set(string $var, mixed $value): void
        {
            $this->query_vars[$var] = $value;
        }

        public function is_main_query(): bool
        {
            return isset($GLOBALS['wp_the_query']) && $GLOBALS['wp_the_query'] === $this;
        }

        public function get_queried_object(): mixed
        {
            return $this->queried_object;
        }

        public function get_queried_object_id(): int
        {
            return $this->queried_object_id;
        }

        public function have_comments(): bool
        {
            return $this->current_comment + 1 < $this->comment_count;
        }

        public function the_comment(): void
        {
            $this->current_comment++;
            $GLOBALS['comment'] = $this->comments[$this->current_comment] ?? null;
        }

        public function init_query_flags(): void
        {
            foreach (get_object_vars($this) as $name => $value) {
                if (str_starts_with($name, 'is_') && is_bool($value)) {
                    $this->{$name} = false;
                }
            }
        }
    }
}
