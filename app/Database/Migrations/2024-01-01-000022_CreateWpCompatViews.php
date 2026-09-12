<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * WordPress-shaped SQL views over the LightCMS tables, so plugin/theme
 * code that reaches for raw $wpdb SQL ("SELECT ... FROM {$wpdb->posts}")
 * reads real data instead of erroring out.
 *
 * These are READ-only by design (the term views are UNIONs, which MySQL
 * cannot make updatable). Writes must go through the API — wp_insert_post(),
 * update_post_meta() and friends — which the compat layer routes to the
 * LightCMS models. wp_options/wp_postmeta/wp_usermeta/wp_termmeta are real
 * tables, so those are read *and* write.
 *
 * Term ids: categories keep their LightCMS id; tags are offset by
 * TermMapper::TAG_OFFSET so the two never collide in one term space.
 */
class CreateWpCompatViews extends Migration
{
    private const TAG_OFFSET = 1000000;

    public function up(): void
    {
        if (! $this->isMySQL()) {
            return; // Views are MySQL-specific; other drivers just skip them.
        }

        $offset = self::TAG_OFFSET;

        // Source tables carry the connection prefix (usually empty, but
        // test runs and shared-hosting installs set one).
        $posts      = $this->db->prefixTable('posts');
        $users      = $this->db->prefixTable('users');
        $categories = $this->db->prefixTable('categories');
        $tags       = $this->db->prefixTable('tags');
        $comments   = $this->db->prefixTable('comments');
        $postCats   = $this->db->prefixTable('post_categories');
        $postTags   = $this->db->prefixTable('post_tags');

        $this->view('wp_posts', <<<SQL
            SELECT
                p.id                                            AS ID,
                COALESCE(p.author_id, 0)                        AS post_author,
                COALESCE(p.published_at, p.created_at)          AS post_date,
                COALESCE(p.published_at, p.created_at)          AS post_date_gmt,
                COALESCE(p.content, '')                         AS post_content,
                p.title                                         AS post_title,
                COALESCE(p.excerpt, '')                         AS post_excerpt,
                CASE p.status
                    WHEN 'published' THEN 'publish'
                    WHEN 'scheduled' THEN 'future'
                    WHEN 'trash'     THEN 'trash'
                    ELSE 'draft'
                END                                             AS post_status,
                p.comment_status                                AS comment_status,
                'closed'                                        AS ping_status,
                ''                                              AS post_password,
                p.slug                                          AS post_name,
                COALESCE(p.updated_at, p.created_at)            AS post_modified,
                COALESCE(p.updated_at, p.created_at)            AS post_modified_gmt,
                0                                               AS post_parent,
                ''                                              AS guid,
                0                                               AS menu_order,
                p.post_type                                     AS post_type,
                ''                                              AS post_mime_type,
                (SELECT COUNT(*) FROM {$comments} c WHERE c.post_id = p.id AND c.status = 'approved') AS comment_count
            FROM {$posts} p
        SQL);

        $this->view('wp_users', <<<SQL
            SELECT
                u.id                                 AS ID,
                u.username                           AS user_login,
                u.password                           AS user_pass,
                u.username                           AS user_nicename,
                u.email                              AS user_email,
                ''                                   AS user_url,
                COALESCE(u.created_at, NOW())        AS user_registered,
                ''                                   AS user_activation_key,
                CASE WHEN u.status = 'active' THEN 0 ELSE 1 END AS user_status,
                COALESCE(u.display_name, u.username) AS display_name
            FROM {$users} u
        SQL);

        $this->view('wp_terms', <<<SQL
            SELECT c.id AS term_id, c.name AS name, c.slug AS slug, 0 AS term_group FROM {$categories} c
            UNION ALL
            SELECT t.id + {$offset} AS term_id, t.name AS name, t.slug AS slug, 0 AS term_group FROM {$tags} t
        SQL);

        $this->view('wp_term_taxonomy', <<<SQL
            SELECT
                c.id AS term_taxonomy_id, c.id AS term_id, 'category' AS taxonomy,
                COALESCE(c.description, '') AS description, COALESCE(c.parent_id, 0) AS parent,
                (SELECT COUNT(*) FROM {$postCats} pc WHERE pc.category_id = c.id) AS count
            FROM {$categories} c
            UNION ALL
            SELECT
                t.id + {$offset} AS term_taxonomy_id, t.id + {$offset} AS term_id, 'post_tag' AS taxonomy,
                '' AS description, 0 AS parent,
                (SELECT COUNT(*) FROM {$postTags} pt WHERE pt.tag_id = t.id) AS count
            FROM {$tags} t
        SQL);

        $this->view('wp_term_relationships', <<<SQL
            SELECT pc.post_id AS object_id, pc.category_id AS term_taxonomy_id, 0 AS term_order FROM {$postCats} pc
            UNION ALL
            SELECT pt.post_id AS object_id, pt.tag_id + {$offset} AS term_taxonomy_id, 0 AS term_order FROM {$postTags} pt
        SQL);

        $this->view('wp_comments', <<<SQL
            SELECT
                c.id                            AS comment_ID,
                c.post_id                       AS comment_post_ID,
                COALESCE(c.author_name, '')     AS comment_author,
                COALESCE(c.author_email, '')    AS comment_author_email,
                ''                              AS comment_author_url,
                COALESCE(c.author_ip, '')       AS comment_author_IP,
                COALESCE(c.created_at, NOW())   AS comment_date,
                COALESCE(c.created_at, NOW())   AS comment_date_gmt,
                COALESCE(c.content, '')         AS comment_content,
                0                               AS comment_karma,
                CASE c.status WHEN 'approved' THEN '1' WHEN 'spam' THEN 'spam' WHEN 'trash' THEN 'trash' ELSE '0' END AS comment_approved,
                ''                              AS comment_agent,
                'comment'                       AS comment_type,
                COALESCE(c.parent_id, 0)        AS comment_parent,
                0                               AS user_id
            FROM {$comments} c
        SQL);
    }

    public function down(): void
    {
        if (! $this->isMySQL()) {
            return;
        }

        foreach (['wp_posts', 'wp_users', 'wp_terms', 'wp_term_taxonomy', 'wp_term_relationships', 'wp_comments'] as $view) {
            $this->db->query('DROP VIEW IF EXISTS ' . $this->db->prefixTable($view));
        }
    }

    private function view(string $name, string $select): void
    {
        $name = $this->db->prefixTable($name);

        // A previous run may have left a *table* of that name behind (e.g.
        // someone imported a real WP dump) — never silently replace one.
        $existing = $this->db->query(
            'SELECT table_type FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$name]
        )->getRowArray();

        if ($existing !== null && strtoupper($existing['table_type']) === 'BASE TABLE') {
            log_message('warning', "WP compat: {$name} exists as a real table; skipping view creation.");

            return;
        }

        $this->db->query("CREATE OR REPLACE VIEW {$name} AS {$select}");
    }

    private function isMySQL(): bool
    {
        return in_array(strtolower($this->db->DBDriver), ['mysqli', 'mysql', 'pdo'], true)
            && str_contains(strtolower($this->db->getPlatform()), 'mysql');
    }
}
