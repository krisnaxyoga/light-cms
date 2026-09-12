<?php

namespace App\Libraries\WordPress;

use WP_Post;
use WP_Query;
use WP_Term;

/**
 * The WordPress template hierarchy, in the order WP itself tries files.
 * Child themes win over parents because ThemeRepository::locate() looks
 * in the stylesheet directory first.
 */
class TemplateHierarchy
{
    public function __construct(protected ThemeRepository $themes)
    {
    }

    /**
     * Candidate template filenames for a query, most specific first.
     *
     * @return list<string>
     */
    public function candidates(WP_Query $query): array
    {
        $candidates = [];

        if ($query->is_404) {
            $candidates[] = '404.php';
        } elseif ($query->is_search) {
            $candidates[] = 'search.php';
        } elseif ($query->is_front_page && ! $query->is_home) {
            $candidates[] = 'front-page.php';
        } elseif ($query->is_singular) {
            $candidates = array_merge($candidates, $this->singular($query));
        } elseif ($query->is_category || $query->is_tag || $query->is_tax) {
            $candidates = array_merge($candidates, $this->taxonomy($query));
        } elseif ($query->is_author) {
            $candidates[] = 'author.php';
            $candidates[] = 'archive.php';
        } elseif ($query->is_date) {
            $candidates[] = 'date.php';
            $candidates[] = 'archive.php';
        } elseif ($query->is_post_type_archive) {
            $postType = (string) ($query->query_vars['post_type'] ?? 'post');
            $candidates[] = "archive-{$postType}.php";
            $candidates[] = 'archive.php';
        } elseif ($query->is_home) {
            $candidates[] = 'home.php';

            if ($query->is_front_page) {
                array_unshift($candidates, 'front-page.php');
            }
        }

        $candidates[] = 'index.php';

        return array_values(array_unique(array_filter($candidates)));
    }

    /** Absolute path of the template that should render this query. */
    public function locate(WP_Query $query): ?string
    {
        foreach ($this->candidates($query) as $candidate) {
            $path = $this->themes->locate($candidate);

            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    /** @return list<string> */
    protected function singular(WP_Query $query): array
    {
        $post = $query->post;

        if (! $post instanceof WP_Post) {
            return ['singular.php'];
        }

        $candidates = [];

        if ($post->post_type === 'page') {
            $template = (string) get_post_meta($post->ID, '_wp_page_template', true);

            if ($template !== '' && $template !== 'default') {
                $candidates[] = $template;
            }

            $candidates[] = "page-{$post->post_name}.php";
            $candidates[] = "page-{$post->ID}.php";
            $candidates[] = 'page.php';
        } else {
            $candidates[] = "single-{$post->post_type}-{$post->post_name}.php";
            $candidates[] = "single-{$post->post_type}.php";
            $candidates[] = 'single.php';
        }

        $candidates[] = 'singular.php';

        return $candidates;
    }

    /** @return list<string> */
    protected function taxonomy(WP_Query $query): array
    {
        $term       = $query->get_queried_object();
        $candidates = [];

        if ($term instanceof WP_Term) {
            if ($query->is_category) {
                $candidates[] = "category-{$term->slug}.php";
                $candidates[] = "category-{$term->term_id}.php";
                $candidates[] = 'category.php';
            } elseif ($query->is_tag) {
                $candidates[] = "tag-{$term->slug}.php";
                $candidates[] = "tag-{$term->term_id}.php";
                $candidates[] = 'tag.php';
            } else {
                $candidates[] = "taxonomy-{$term->taxonomy}-{$term->slug}.php";
                $candidates[] = "taxonomy-{$term->taxonomy}.php";
                $candidates[] = 'taxonomy.php';
            }
        }

        $candidates[] = 'archive.php';

        return $candidates;
    }
}
