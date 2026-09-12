<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// --- Admin ---------------------------------------------------------------
// Auth gate for everything here is the 'adminAuth' filter, wired in
// Config/Filters.php against the 'admin/*' pattern (login itself is
// exempted inside the filter, not here).
$routes->group('admin', ['namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::attempt');
    $routes->get('logout', 'AuthController::logout');

    $routes->get('/', 'Dashboard::index');
    $routes->post('notifications/read-all', 'Dashboard::markNotificationsRead');

    $routes->get('profile', 'ProfileController::index');
    $routes->post('profile', 'ProfileController::update');
    $routes->post('profile/password', 'ProfileController::updatePassword');

    $routes->get('posts', 'PostController::index/post');
    $routes->get('pages', 'PostController::index/page');
    $routes->get('posts/create', 'PostController::create/post');
    $routes->get('pages/create', 'PostController::create/page');
    $routes->post('posts', 'PostController::store');
    $routes->post('posts/analyze', 'PostController::analyze');
    $routes->get('posts/(:num)/edit', 'PostController::edit/$1');
    $routes->post('posts/(:num)', 'PostController::update/$1');
    $routes->post('posts/(:num)/trash', 'PostController::trash/$1');
    $routes->post('posts/(:num)/restore', 'PostController::restore/$1');
    $routes->post('posts/(:num)/delete', 'PostController::delete/$1');

    $routes->get('media', 'MediaController::index');
    $routes->get('media/unused', 'MediaController::unused');
    $routes->get('media/list', 'MediaController::listJson');
    $routes->post('media/upload', 'MediaController::upload');
    $routes->post('media/(:num)/alt', 'MediaController::updateAlt/$1');
    $routes->post('media/(:num)/delete', 'MediaController::delete/$1');

    $routes->get('seo/redirects', 'SEOController::redirects');
    $routes->post('seo/redirects', 'SEOController::storeRedirect');
    $routes->post('seo/redirects/(:num)/delete', 'SEOController::deleteRedirect/$1');
    $routes->post('seo/sitemap', 'SEOController::regenerateSitemap');
    $routes->post('seo/robots', 'SEOController::regenerateRobots');

    $routes->get('themes', 'ThemeController::index');
    $routes->post('themes/(:segment)/activate', 'ThemeController::activate/$1');

    // --- WordPress compatibility (see app/Config/WordPress.php) ---------
    $routes->post('wp-themes/(:segment)/activate', 'ThemeController::activateWordPress/$1');

    $routes->get('plugins', 'PluginController::index');
    $routes->post('plugins/(:segment)/activate', 'PluginController::activate/$1');
    $routes->post('plugins/(:segment)/deactivate', 'PluginController::deactivate/$1');

    // 'wp/customize' and 'wp/options' must stay above the catch-all
    // 'wp/(:segment)' that renders plugin-registered screens.
    $routes->get('wp/customize', 'WPPageController::customize');
    $routes->post('wp/customize', 'WPPageController::saveCustomize');
    $routes->post('wp/options', 'WPPageController::saveOptions');
    $routes->get('wp/(:segment)', 'WPPageController::show/$1');

    $routes->get('settings', 'SettingsController::index');
    $routes->post('settings', 'SettingsController::update');
});

// --- WordPress compatibility endpoints ---------------------------------------
// These have no LightCMS equivalent, so they are served directly by the
// compat layer. The home page, single posts and category archives stay on
// the native controllers, which delegate when a WP theme is active.
$routes->match(['get', 'post'], 'wp-admin/admin-ajax.php', 'Frontend\WordPressController::ajax');
$routes->match(['get', 'post'], 'wp-admin/admin-post.php', 'Frontend\WordPressController::adminPost');
$routes->match(['get', 'post', 'put', 'patch', 'delete'], 'wp-json/(:any)', 'Frontend\WordPressRestController::dispatch/$1');

// --- Frontend --------------------------------------------------------------
$routes->get('/', 'Frontend\HomeController::index');
$routes->get('search', 'Frontend\WordPressController::search');
$routes->get('category/(:segment)', 'Frontend\PostController::category/$1');
$routes->get('tag/(:segment)', 'Frontend\WordPressController::tag/$1');
$routes->get('author/(:segment)', 'Frontend\WordPressController::author/$1');

// Date archives. Bare-year URLs are deliberately not routed: they would
// shadow a numeric post slug, and few themes link to them.
$routes->get('([0-9]{4})/([0-9]{1,2})', 'Frontend\WordPressController::dateArchive/$1/$2');
$routes->get('([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})', 'Frontend\WordPressController::dateArchive/$1/$2/$3');

// Catch-all: any remaining path is treated as a post/page slug — must stay
// last so it never shadows the routes above.
$routes->get('(:segment)', 'Frontend\PostController::show/$1');
