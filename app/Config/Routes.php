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
    $routes->post('media/(:num)', 'MediaController::update/$1');
    $routes->post('media/(:num)/delete', 'MediaController::delete/$1');

    $routes->get('seo/redirects', 'SEOController::redirects');
    $routes->post('seo/redirects', 'SEOController::storeRedirect');
    $routes->post('seo/redirects/(:num)/delete', 'SEOController::deleteRedirect/$1');
    $routes->post('seo/sitemap', 'SEOController::regenerateSitemap');
    $routes->post('seo/robots', 'SEOController::regenerateRobots');

    $routes->get('languages', 'LanguageController::index');
    $routes->post('languages', 'LanguageController::store');
    $routes->post('languages/toggle', 'LanguageController::toggle');
    $routes->post('languages/(:num)', 'LanguageController::update/$1');
    $routes->post('languages/(:num)/default', 'LanguageController::makeDefault/$1');
    $routes->post('languages/(:num)/toggle', 'LanguageController::toggleActive/$1');
    $routes->post('languages/(:num)/delete', 'LanguageController::delete/$1');

    $routes->get('homepage', 'HomepageController::index');
    $routes->post('homepage', 'HomepageController::update');

    // Pull-to-deploy from GitLab — no `git`/SSH needed on the server, see
    // App\Libraries\Deploy\GitLabDeployer.
    $routes->get('deploy', 'DeployController::index');
    $routes->post('deploy/config', 'DeployController::saveConfig');
    $routes->post('deploy/pull', 'DeployController::pull');
    $routes->post('deploy/restore/(:segment)', 'DeployController::restore/$1');

    $routes->get('menus', 'MenuController::index');
    $routes->get('menus/search', 'MenuController::search');
    $routes->post('menus', 'MenuController::store');
    $routes->get('menus/(:num)', 'MenuController::index/$1');
    $routes->post('menus/(:num)', 'MenuController::update/$1');
    $routes->post('menus/(:num)/delete', 'MenuController::delete/$1');

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

// GitLab's own push-event webhook (Admin -> Deploy sets the URL/secret on
// the GitLab project's Webhooks page) — auto-pulls on every push. No admin
// session involved, so it lives outside the 'admin' group; authenticated
// instead by the X-Gitlab-Token header (see DeployWebhookController) and
// CSRF-exempted below (Config/Filters.php) the same way wp-admin/* is.
$routes->post('deploy/webhook', 'Frontend\DeployWebhookController::handle');

// --- Frontend --------------------------------------------------------------
$routes->get('/', 'Frontend\HomeController::index');
$routes->get('blog', 'Frontend\BlogController::index');
// A single post's canonical path is blog/{slug} (post_path(), PostController::show()
// 301-redirects here from the bare slug) — must be registered before the
// language-prefixed (:segment)/(:segment) route below, which would otherwise
// swallow "blog/{slug}" as "{prefix}/{slug}" and 404 (byPrefix('blog') is null).
$routes->get('blog/(:segment)', 'Frontend\PostController::show/$1');
$routes->get('search', 'Frontend\WordPressController::search');
$routes->get('category/(:segment)', 'Frontend\PostController::category/$1');
$routes->get('tag/(:segment)', 'Frontend\WordPressController::tag/$1');
$routes->get('author/(:segment)', 'Frontend\WordPressController::author/$1');

// Date archives. Bare-year URLs are deliberately not routed: they would
// shadow a numeric post slug, and few themes link to them.
$routes->get('([0-9]{4})/([0-9]{1,2})', 'Frontend\WordPressController::dateArchive/$1/$2');
$routes->get('([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})', 'Frontend\WordPressController::dateArchive/$1/$2/$3');

// --- Language-prefixed URLs (Admin -> Languages) ---------------------------
// With multi-language on, non-default languages live under a prefix
// (/id/...). The first segment is captured generically and the controllers
// reject unknown prefixes with a 404, so adding a language needs no route
// change. These sit below every literal route so /blog, /category/x and
// the date archives keep winning.
$routes->get('(:segment)/blog', 'Frontend\BlogController::index/$1');
$routes->get('(:segment)/blog/(:segment)', 'Frontend\PostController::show/$2/$1');
$routes->get('(:segment)/category/(:segment)', 'Frontend\PostController::category/$2/$1');
$routes->get('(:segment)/(:segment)', 'Frontend\PostController::show/$2/$1');

// Catch-all: any remaining path is treated as a post/page slug — or, with
// multi-language on, a bare language prefix (/id) meaning that language's
// home page. Must stay last so it never shadows the routes above.
$routes->get('(:segment)', 'Frontend\PostController::show/$1');
