<?php
/**
 * Plugin Name: LightCMS Hello
 * Plugin URI: https://example.com/lightcms-hello
 * Description: Demonstration plugin for the LightCMS WordPress compatibility layer — a settings page, a shortcode, a content filter, an AJAX action and a REST route. Written exactly as it would be for WordPress.
 * Version: 1.0.0
 * Author: LightCMS
 * Text Domain: lightcms-hello
 * License: GPLv2 or later
 */

if (! defined('ABSPATH')) {
    exit;
}

define('LIGHTCMS_HELLO_FILE', __FILE__);

/** Seed the option on activation. */
register_activation_hook(__FILE__, static function (): void {
    add_option('lightcms_hello_greeting', 'Hello from a WordPress plugin.');
});

/** Settings screen: admin/wp/lightcms-hello in LightCMS. */
add_action('admin_menu', static function (): void {
    add_menu_page(
        __('LightCMS Hello', 'lightcms-hello'),
        __('Hello', 'lightcms-hello'),
        'manage_options',
        'lightcms-hello',
        'lightcms_hello_render_settings',
        'dashicons-megaphone',
        80
    );
});

add_action('admin_init', static function (): void {
    register_setting('lightcms_hello', 'lightcms_hello_greeting', ['sanitize_callback' => 'sanitize_text_field']);

    add_settings_section('lightcms_hello_main', __('Greeting', 'lightcms-hello'), static function (): void {
        echo '<p>' . esc_html__('Shown by the [hello] shortcode.', 'lightcms-hello') . '</p>';
    }, 'lightcms-hello');

    add_settings_field('lightcms_hello_greeting', __('Message', 'lightcms-hello'), static function (): void {
        printf(
            '<input type="text" name="lightcms_hello_greeting" value="%s" class="regular-text">',
            esc_attr((string) get_option('lightcms_hello_greeting', ''))
        );
    }, 'lightcms-hello', 'lightcms_hello_main', ['label_for' => 'lightcms_hello_greeting']);
});

function lightcms_hello_render_settings(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }
    ?>
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="<?php echo esc_url(admin_url('wp/options')); ?>" method="post">
        <?php settings_fields('lightcms_hello'); ?>
        <?php do_settings_sections('lightcms-hello'); ?>
        <?php submit_button(); ?>
    </form>
    <?php
}

/** [hello name="World"] */
add_shortcode('hello', static function ($atts): string {
    $atts = shortcode_atts(['name' => ''], $atts, 'hello');
    $text = (string) get_option('lightcms_hello_greeting', 'Hello.');

    return '<span class="lightcms-hello">' . esc_html($atts['name'] !== '' ? $text . ' ' . $atts['name'] : $text) . '</span>';
});

/** Append a line to every single post, the classic filter way. */
add_filter('the_content', static function (string $content): string {
    if (! is_single()) {
        return $content;
    }

    return $content . '<p class="lightcms-hello-footer">' . esc_html__('Served through the LightCMS WordPress compatibility layer.', 'lightcms-hello') . '</p>';
}, 20);

/** AJAX: /wp-admin/admin-ajax.php?action=lightcms_hello */
add_action('wp_ajax_lightcms_hello', 'lightcms_hello_ajax');
add_action('wp_ajax_nopriv_lightcms_hello', 'lightcms_hello_ajax');

function lightcms_hello_ajax(): void
{
    wp_send_json_success([
        'greeting' => (string) get_option('lightcms_hello_greeting', ''),
        'posts'    => count(get_posts(['numberposts' => 3])),
    ]);
}

/** REST: /wp-json/lightcms-hello/v1/greeting */
add_action('rest_api_init', static function (): void {
    register_rest_route('lightcms-hello/v1', '/greeting', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => static function ($request) {
            return rest_ensure_response([
                'greeting' => (string) get_option('lightcms_hello_greeting', ''),
                'name'     => (string) $request->get_param('name'),
            ]);
        },
    ]);
});
