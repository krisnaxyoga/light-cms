<?php

namespace Tests\Unit;

use App\Libraries\WordPress\Headers;
use App\Libraries\WordPress\Hooks;
use App\Libraries\WordPress\Registry;
use App\Libraries\WordPress\Runtime;
use App\Libraries\WordPress\TemplateHierarchy;
use App\Libraries\WordPress\ThemeRepository;
use CodeIgniter\Test\CIUnitTestCase;
use WP_Query;

/**
 * The parts of the WordPress compatibility layer that need no database:
 * the hook engine, shortcodes, the escaping/formatting helpers, file
 * headers and the template hierarchy.
 */
final class WordPressCompatTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Runtime::instance()->bootCore();
        Hooks::reset();
        Registry::reset();
    }

    public function testActionsRunInPriorityOrder(): void
    {
        $order = [];

        add_action('lc_test', static function () use (&$order) { $order[] = 'late'; }, 20);
        add_action('lc_test', static function () use (&$order) { $order[] = 'early'; }, 1);
        add_action('lc_test', static function () use (&$order) { $order[] = 'default'; });

        do_action('lc_test');

        $this->assertSame(['early', 'default', 'late'], $order);
        $this->assertSame(1, did_action('lc_test'));
    }

    public function testFiltersChainAndPassExtraArguments(): void
    {
        add_filter('lc_filter', static fn (string $value, string $suffix): string => $value . $suffix, 10, 2);
        add_filter('lc_filter', static fn (string $value): string => strtoupper($value), 20, 1);

        $this->assertSame('AB', apply_filters('lc_filter', 'a', 'b'));
    }

    public function testRemoveActionUnhooksTheSameCallback(): void
    {
        $callback = static fn () => null;

        add_action('lc_remove', $callback, 5);
        $this->assertSame(5, has_action('lc_remove', $callback));

        $this->assertTrue(remove_action('lc_remove', $callback, 5));
        $this->assertFalse(has_action('lc_remove', $callback));
    }

    public function testACallbackAddedDuringAFilterStillRuns(): void
    {
        add_filter('lc_reentrant', static function (string $value): string {
            add_filter('lc_reentrant', static fn (string $inner): string => $inner . '-second', 20);

            return $value . '-first';
        }, 10);

        $this->assertSame('start-first-second', apply_filters('lc_reentrant', 'start'));
    }

    public function testShortcodesExpandWithAttributesAndEnclosedContent(): void
    {
        add_shortcode('lc_greet', static function ($atts, $content = null) {
            $atts = shortcode_atts(['name' => 'world'], $atts, 'lc_greet');

            return 'hi ' . $atts['name'] . ($content !== null ? ':' . $content : '');
        });

        $this->assertSame('hi budi', do_shortcode('[lc_greet name="budi"]'));
        $this->assertSame('hi world:inner', do_shortcode('[lc_greet]inner[/lc_greet]'));
        $this->assertTrue(has_shortcode('a [lc_greet] b', 'lc_greet'));
        $this->assertSame('', trim(strip_shortcodes('[lc_greet]')));
    }

    public function testEscapersRejectDangerousUrls(): void
    {
        $this->assertSame('', esc_url('javascript:alert(1)'));
        $this->assertSame('', esc_url('data:text/html;base64,PHN2Zz4='));
        $this->assertStringContainsString('https://example.com/a%20b', esc_url('https://example.com/a b'));
        $this->assertSame('&lt;b&gt;', esc_html('<b>'));
        $this->assertSame('a&amp;b', esc_attr('a&b'));
    }

    public function testKsesStripsScriptsAndEventHandlers(): void
    {
        $dirty = '<p onclick="steal()">ok</p><script>alert(1)</script><a href="javascript:x">link</a>';
        $clean = wp_kses_post($dirty);

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('ok', $clean);
    }

    public function testWpautopWrapsTextButLeavesBlockMarkupAlone(): void
    {
        $this->assertSame("<p>one</p>\n<p>two</p>\n", wpautop("one\n\ntwo"));
        $this->assertStringNotContainsString('<p><div', wpautop('<div>block</div>'));
    }

    public function testTrimWordsAndSanitizeTitle(): void
    {
        $this->assertSame('one two three&hellip;', wp_trim_words('one two three four five', 3, '&hellip;'));
        $this->assertSame('hello-world', sanitize_title('Hello World!'));
        $this->assertSame('a-b', sanitize_title('  a   b  '));
    }

    public function testThemeAndPluginHeadersAreParsed(): void
    {
        $theme = Headers::parse(FCPATH . 'wp-content/themes/lightcms-classic/style.css', Headers::THEME);

        $this->assertSame('LightCMS Classic', $theme['Name']);
        $this->assertSame('lightcms-classic', $theme['TextDomain']);
        $this->assertNotSame('', $theme['Version']);

        $plugin = Headers::parse(FCPATH . 'wp-content/plugins/lightcms-hello/lightcms-hello.php', Headers::PLUGIN);

        $this->assertSame('LightCMS Hello', $plugin['Name']);
        $this->assertSame('1.0.0', $plugin['Version']);
    }

    public function testTemplateHierarchyOrdersCandidatesLikeWordPress(): void
    {
        $hierarchy = new TemplateHierarchy(new ThemeRepository());

        $query          = new WP_Query();
        $query->is_404  = true;
        $this->assertSame(['404.php', 'index.php'], $hierarchy->candidates($query));

        $query             = new WP_Query();
        $query->is_singular = true;
        $query->is_single   = true;
        $query->post        = new \WP_Post(['ID' => 7, 'post_type' => 'post', 'post_name' => 'hello']);

        $this->assertSame(
            ['single-post-hello.php', 'single-post.php', 'single.php', 'singular.php', 'index.php'],
            $hierarchy->candidates($query)
        );

        $query              = new WP_Query();
        $query->is_singular = true;
        $query->is_page     = true;
        $query->post        = new \WP_Post(['ID' => 9, 'post_type' => 'page', 'post_name' => 'about']);

        $this->assertSame(
            ['page-about.php', 'page-9.php', 'page.php', 'singular.php', 'index.php'],
            $hierarchy->candidates($query)
        );
    }

    public function testEnqueuedAssetsPrintInDependencyOrder(): void
    {
        $assets = Runtime::instance()->assets();

        $assets->registerScript('lc-base', 'https://cdn.example.com/base.js', [], '1.0');
        $assets->enqueueScript('lc-app', 'https://cdn.example.com/app.js', ['lc-base'], '2.0', true);

        $html = $assets->printScripts(true);

        $this->assertLessThan(
            strpos($html, 'app.js'),
            strpos($html, 'base.js'),
            'A dependency must be printed before the script that needs it.'
        );
        $this->assertStringContainsString('ver=2.0', $html);
    }

    public function testNoncesVerifyAndRejectTampering(): void
    {
        $nonce = wp_create_nonce('lc-action');

        $this->assertNotFalse(wp_verify_nonce($nonce, 'lc-action'));
        $this->assertFalse(wp_verify_nonce($nonce, 'other-action'));
        $this->assertFalse(wp_verify_nonce('deadbeef', 'lc-action'));
    }

    public function testThemeSupportsAndMenuRegistrationAreRecorded(): void
    {
        add_theme_support('post-thumbnails');
        add_theme_support('html5', ['search-form', 'gallery']);
        register_nav_menus(['primary' => 'Primary']);

        $this->assertTrue(current_theme_supports('post-thumbnails'));
        $this->assertTrue(current_theme_supports('html5', 'gallery'));
        $this->assertFalse(current_theme_supports('html5', 'comment-list'));
        $this->assertFalse(current_theme_supports('custom-logo'));
        $this->assertArrayHasKey('primary', get_registered_nav_menus());
    }
}
