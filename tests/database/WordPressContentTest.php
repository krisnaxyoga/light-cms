<?php

namespace Tests\Database;

use App\Libraries\WordPress\Bridge\PostMapper;
use App\Libraries\WordPress\Runtime;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use WP_Post;
use WP_Query;

/**
 * The database-backed half of the WordPress compatibility layer: options,
 * post meta and WP_Query reading real LightCMS rows.
 *
 * Migrations run against the test connection, so the wp_options/wp_*meta
 * tables exist here; the WP-shaped SQL views are MySQL-only, which the
 * $wpdb test accounts for.
 */
final class WordPressContentTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    /** null = run migrations from every namespace, App included. */
    protected $namespace = null;

    protected function setUp(): void
    {
        // Checked *before* parent::setUp(), because that is what runs the
        // migrations — and the LightCMS schema is MySQL-specific (ENUM
        // columns, ENGINE=InnoDB), so it cannot build on the default
        // in-memory SQLite test connection. Point the tests group at MySQL
        // to run these, e.g.:
        //   env 'database.tests.DBDriver=MySQLi' \
        //       'database.tests.database=lightcms_test' ... vendor/bin/phpunit
        $driver = (string) (config('Database')->tests['DBDriver'] ?? '');

        if (! str_contains(strtolower($driver), 'mysql')) {
            $this->markTestSkipped('Needs a MySQL "tests" connection; the LightCMS migrations are MySQL-only.');
        }

        parent::setUp();

        Runtime::reset();
        Runtime::instance()->bootCore();
    }

    private function seedPost(array $overrides = []): int
    {
        $row = array_merge([
            'title'        => 'Compat Post',
            'slug'         => 'compat-post',
            'content'      => json_encode([['type' => 'paragraph', 'attrs' => [], 'content' => 'Body text.']]),
            'excerpt'      => 'Short excerpt.',
            'author_id'    => 1,
            'post_type'    => 'post',
            'status'       => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ], $overrides);

        $this->db->table('posts')->insert($row);

        return (int) $this->db->insertID();
    }

    public function testOptionsRoundTripIncludingArrays(): void
    {
        $this->assertTrue(update_option('lc_scalar', 'value'));
        $this->assertSame('value', get_option('lc_scalar'));

        update_option('lc_array', ['a' => 1, 'b' => [2, 3]]);
        $this->assertSame(['a' => 1, 'b' => [2, 3]], get_option('lc_array'));

        $this->assertFalse(get_option('lc_missing'));
        $this->assertSame('fallback', get_option('lc_missing', 'fallback'));

        $this->assertTrue(delete_option('lc_scalar'));
        $this->assertFalse(get_option('lc_scalar'));
    }

    public function testCoreOptionsReadThroughToLightCmsSettings(): void
    {
        $this->db->table('settings')->insert(['setting_key' => 'site_title', 'setting_value' => 'My Site', 'autoload' => 1]);

        $this->assertSame('My Site', get_option('blogname'));
        $this->assertSame(rtrim(base_url(), '/'), get_option('home'));
    }

    public function testPostMetaRoundTrip(): void
    {
        $postId = $this->seedPost();

        add_post_meta($postId, 'subtitle', 'From a plugin');
        update_post_meta($postId, 'counter', 5);

        $this->assertSame('From a plugin', get_post_meta($postId, 'subtitle', true));
        $this->assertSame('5', get_post_meta($postId, 'counter', true));
        $this->assertSame([], get_post_meta($postId, 'nope'));
        $this->assertSame('', get_post_meta($postId, 'nope', true));

        update_post_meta($postId, 'subtitle', 'Changed');
        $this->assertSame('Changed', get_post_meta($postId, 'subtitle', true));

        delete_post_meta($postId, 'subtitle');
        $this->assertSame('', get_post_meta($postId, 'subtitle', true));
    }

    public function testWpQueryReadsPublishedPostsAndRendersBlockContentAsHtml(): void
    {
        $this->seedPost(['slug' => 'first', 'title' => 'First']);
        $this->seedPost(['slug' => 'second', 'title' => 'Second', 'status' => 'draft']);

        $query = new WP_Query(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 10]);

        $this->assertSame(1, $query->post_count);
        $this->assertSame('First', $query->posts[0]->post_title);
        $this->assertSame('publish', $query->posts[0]->post_status);
        $this->assertStringContainsString('<p>Body text.</p>', $query->posts[0]->post_content);
    }

    public function testTheLoopSetsUpPostData(): void
    {
        $this->seedPost(['slug' => 'loopable', 'title' => 'Loopable']);

        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(['post_type' => 'post', 'posts_per_page' => 5]);

        $titles = [];

        while (have_posts()) {
            the_post();
            $titles[] = get_the_title();
        }

        $this->assertSame(['Loopable'], $titles);

        // WordPress rewinds the query when a loop finishes, so asking again
        // starts it over — templates that run two loops rely on this.
        $this->assertTrue(have_posts());
        $this->assertSame(-1, $GLOBALS['wp_query']->current_post);
    }

    public function testInsertingAPostThroughTheWordPressApiCreatesALightCmsRow(): void
    {
        $id = wp_insert_post([
            'post_title'   => 'Made by a plugin',
            'post_content' => '<p>Written as HTML.</p>',
            'post_status'  => 'publish',
            'post_type'    => 'post',
            'meta_input'   => ['source' => 'plugin'],
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = $this->db->table('posts')->where('id', $id)->get()->getRowArray();

        $this->assertSame('Made by a plugin', $row['title']);
        $this->assertSame('published', $row['status'], 'WP post_status must map onto the LightCMS status enum.');
        $this->assertSame('made-by-a-plugin', $row['slug']);
        $this->assertSame('plugin', get_post_meta($id, 'source', true));

        $post = get_post($id);
        $this->assertInstanceOf(WP_Post::class, $post);
        $this->assertStringContainsString('Written as HTML.', $post->post_content);
    }

    public function testStatusMappingIsSymmetric(): void
    {
        foreach (['published' => 'publish', 'draft' => 'draft', 'scheduled' => 'future', 'trash' => 'trash'] as $local => $wp) {
            $this->assertSame($wp, PostMapper::statusToWp($local));
            $this->assertSame($local, PostMapper::statusFromWp($wp));
        }
    }

    public function testWpdbReadsThroughTheCompatViews(): void
    {
        if (! str_contains(strtolower($this->db->getPlatform()), 'mysql')) {
            $this->markTestSkipped('The WP-shaped views are MySQL-only (migration 000022 skips other drivers).');
        }

        $this->seedPost(['slug' => 'via-wpdb', 'title' => 'Via wpdb']);

        /** @var \wpdb $wpdb */
        $wpdb = $GLOBALS['wpdb'];

        $row = $wpdb->get_row($wpdb->prepare("SELECT post_title, post_status FROM {$wpdb->posts} WHERE post_name = %s", 'via-wpdb'));

        $this->assertNotNull($row);
        $this->assertSame('Via wpdb', $row->post_title);
        $this->assertSame('publish', $row->post_status);

        // The views are read-only on purpose: writes must go through the API.
        $this->assertFalse($wpdb->insert($wpdb->posts, ['post_title' => 'nope']));
        $this->assertStringContainsString('read-only', $wpdb->last_error);
    }
}
