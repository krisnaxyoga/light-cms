<?php

namespace App\Libraries\WordPress;

use App\Libraries\WordPress\Bridge\PostMapper;
use App\Libraries\WordPress\Exceptions\WordPressDieException;
use Throwable;
use WP_Query;
use WP_Term;
use WP_User;

/**
 * Turns a LightCMS route into a rendered WordPress page: builds the main
 * query, sets the globals a template expects, fires template_redirect and
 * includes the template the hierarchy picked.
 *
 * Every render is buffered, so a template that dies halfway through
 * cannot leak a half-page — the controller gets either HTML or an
 * exception it can turn into an error response.
 */
class Renderer
{
    protected TemplateHierarchy $hierarchy;

    public function __construct(protected Runtime $runtime)
    {
        $this->hierarchy = new TemplateHierarchy($runtime->themes());
    }

    public function home(int $paged = 1): string
    {
        $query = $this->query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => (int) get_option('posts_per_page', 10),
            'paged'          => $paged,
        ]);

        $query->is_home       = true;
        $query->is_front_page = true;
        $query->is_paged      = $paged > 1;

        return $this->render($query);
    }

    public function singular(array $postRow): string
    {
        $post  = PostMapper::fromRow($postRow);
        $query = $this->query([
            'p'              => $post->ID,
            'post_type'      => $post->post_type,
            'post_status'    => 'any',
            'posts_per_page' => 1,
        ]);

        $query->is_singular = true;
        $query->is_single   = $post->post_type !== 'page';
        $query->is_page     = $post->post_type === 'page';
        $query->queried_object    = $post;
        $query->queried_object_id = $post->ID;

        return $this->render($query);
    }

    public function term(WP_Term $term, int $paged = 1): string
    {
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => (int) get_option('posts_per_page', 10),
            'paged'          => $paged,
        ];

        if ($term->taxonomy === 'post_tag') {
            $args['tag_id'] = $term->term_id;
        } else {
            $args['cat'] = $term->term_id;
        }

        $query = $this->query($args);

        $query->is_archive  = true;
        $query->is_category = $term->taxonomy === 'category';
        $query->is_tag      = $term->taxonomy === 'post_tag';
        $query->is_tax      = ! $query->is_category && ! $query->is_tag;
        $query->is_paged    = $paged > 1;
        $query->queried_object    = $term;
        $query->queried_object_id = $term->term_id;

        return $this->render($query);
    }

    public function author(WP_User $user, int $paged = 1): string
    {
        $query = $this->query([
            'author'         => $user->ID,
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => (int) get_option('posts_per_page', 10),
            'paged'          => $paged,
        ]);

        $query->is_archive = true;
        $query->is_author  = true;
        $query->is_paged   = $paged > 1;
        $query->queried_object    = $user;
        $query->queried_object_id = $user->ID;

        return $this->render($query);
    }

    public function search(string $term, int $paged = 1): string
    {
        $query = $this->query([
            's'              => $term,
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => (int) get_option('posts_per_page', 10),
            'paged'          => $paged,
        ]);

        $query->is_search = true;
        $query->is_paged  = $paged > 1;

        return $this->render($query);
    }

    public function dateArchive(int $year, ?int $month = null, ?int $day = null, int $paged = 1): string
    {
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => (int) get_option('posts_per_page', 10),
            'paged'          => $paged,
            'year'           => $year,
        ];

        if ($month !== null) {
            $args['monthnum'] = $month;
        }

        if ($day !== null) {
            $args['day'] = $day;
        }

        $query = $this->query($args);

        $query->is_archive = true;
        $query->is_date    = true;
        $query->is_year    = $month === null;
        $query->is_month   = $month !== null && $day === null;
        $query->is_day     = $day !== null;

        return $this->render($query);
    }

    public function postTypeArchive(string $postType, int $paged = 1): string
    {
        $query = $this->query([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => (int) get_option('posts_per_page', 10),
            'paged'          => $paged,
        ]);

        $query->is_archive           = true;
        $query->is_post_type_archive = true;
        $query->is_paged             = $paged > 1;

        return $this->render($query);
    }

    public function notFound(): string
    {
        $query = new WP_Query();
        $query->init_query_flags();
        $query->is_404 = true;
        $query->query_vars = [];

        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = $query;

        return $this->render($query);
    }

    /** Build the main query and publish it as the globals templates read. */
    protected function query(array $args): WP_Query
    {
        $query = new WP_Query();
        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = $query;

        $query->query($args);

        return $query;
    }

    protected function render(WP_Query $query): string
    {
        $template = $this->hierarchy->locate($query);

        if ($template === null) {
            throw new WordPressDieException(
                'The active theme has no template for this request (looked for: '
                . implode(', ', $this->hierarchy->candidates($query)) . ').',
                500,
                'Template not found'
            );
        }

        if ($query->post !== null) {
            $GLOBALS['post'] = $query->post;
            setup_postdata($query->post);
        }

        do_action('template_redirect');
        do_action('wp', $query);
        do_action('template_include', $template);

        $level = ob_get_level();

        try {
            ob_start();
            wp_include_file($template);
            $html = (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }

        do_action('shutdown');

        return $html;
    }
}
