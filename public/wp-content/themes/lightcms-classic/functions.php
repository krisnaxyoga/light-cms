<?php
/**
 * LightCMS Classic — a normal classic-theme functions.php. Nothing here is
 * LightCMS-specific: it is the same code you would write for WordPress.
 */

if (! defined('ABSPATH')) {
    exit;
}

function lightcms_classic_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', ['search-form', 'gallery', 'caption']);

    register_nav_menus([
        'primary' => __('Primary Menu', 'lightcms-classic'),
        'footer'  => __('Footer Menu', 'lightcms-classic'),
    ]);

    load_theme_textdomain('lightcms-classic', get_template_directory() . '/languages');
}
add_action('after_setup_theme', 'lightcms_classic_setup');

function lightcms_classic_widgets_init(): void
{
    register_sidebar([
        'name'          => __('Sidebar', 'lightcms-classic'),
        'id'            => 'sidebar-1',
        'description'   => __('Widgets shown beside posts.', 'lightcms-classic'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ]);
}
add_action('widgets_init', 'lightcms_classic_widgets_init');

function lightcms_classic_assets(): void
{
    wp_enqueue_style('lightcms-classic', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
    wp_enqueue_script('lightcms-classic', get_template_directory_uri() . '/assets/theme.js', [], '1.0.0', true);
    wp_localize_script('lightcms-classic', 'lightcmsClassic', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'home'    => home_url(),
    ]);
}
add_action('wp_enqueue_scripts', 'lightcms_classic_assets');

/** A shortcode, to prove do_shortcode() runs on post content. */
function lightcms_classic_year_shortcode($atts): string
{
    $atts = shortcode_atts(['prefix' => ''], $atts, 'current_year');

    return esc_html($atts['prefix'] . date('Y'));
}
add_shortcode('current_year', 'lightcms_classic_year_shortcode');

/** A filter, to prove the filter chain runs. */
function lightcms_classic_excerpt_length(int $length): int
{
    return 30;
}
add_filter('excerpt_length', 'lightcms_classic_excerpt_length');

/** Customizer options, editable at admin/wp/customize in LightCMS. */
function lightcms_classic_customize_register($wp_customize): void
{
    $wp_customize->add_section('lightcms_classic_options', [
        'title'    => __('Theme Options', 'lightcms-classic'),
        'priority' => 30,
    ]);

    $wp_customize->add_setting('footer_text', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    $wp_customize->add_control('footer_text', [
        'label'       => __('Footer text', 'lightcms-classic'),
        'section'     => 'lightcms_classic_options',
        'type'        => 'text',
        'description' => __('Shown in the footer, after the copyright line.', 'lightcms-classic'),
    ]);
}
add_action('customize_register', 'lightcms_classic_customize_register');
