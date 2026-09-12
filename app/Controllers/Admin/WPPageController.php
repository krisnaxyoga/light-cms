<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\WordPress\Exceptions\WordPressDieException;
use App\Libraries\WordPress\Exceptions\WordPressJsonException;
use App\Libraries\WordPress\Exceptions\WordPressRedirectException;
use App\Libraries\WordPress\Registry;
use Throwable;

/**
 * Renders the admin screens plugins and themes register with
 * add_menu_page()/add_submenu_page(), plus the Settings API save handler
 * (WordPress's options.php) and the Customizer stand-in.
 *
 * The plugin's own markup is echoed inside the LightCMS admin chrome, so
 * a settings screen written for wp-admin works with no changes.
 */
class WPPageController extends BaseController
{
    public function show(string $slug)
    {
        $runtime = lcms_wp_boot(lcms_wp_active() ? 'theme' : 'plugins');
        $runtime->bootAdmin();

        $page = $this->findPage($slug);

        if ($page === null) {
            session()->setFlashdata('error', "No WordPress admin page is registered for '{$slug}'.");

            return redirect()->to('/admin/plugins');
        }

        if (! current_user_can($page['capability'])) {
            session()->setFlashdata('error', 'You do not have permission to open that screen.');

            return redirect()->to('/admin');
        }

        $_GET['page']    = $slug;
        $_REQUEST['page'] = $slug;
        $GLOBALS['title'] = $page['page_title'];
        set_current_screen('toplevel_page_' . $slug);

        try {
            ob_start();

            if (is_callable($page['callback'])) {
                ($page['callback'])();
            } else {
                echo '<p>This page has no render callback.</p>';
            }

            $content = (string) ob_get_clean();
        } catch (WordPressRedirectException $e) {
            return $this->response->redirect($e->location(), 'auto', $e->statusCode());
        } catch (WordPressJsonException $e) {
            return $this->response->setStatusCode($e->statusCode())->setJSON($e->data());
        } catch (WordPressDieException $e) {
            ob_end_clean();
            session()->setFlashdata('error', $e->getMessage());

            return redirect()->to('/admin/plugins');
        } catch (Throwable $e) {
            ob_end_clean();
            log_message('error', 'WP compat admin page failed: ' . $e->getMessage());
            session()->setFlashdata('error', 'That screen raised an error: ' . $e->getMessage());

            return redirect()->to('/admin/plugins');
        }

        return view('admin/wp/page', [
            'title'   => $page['page_title'],
            'content' => $content,
            'notices' => Registry::$adminNotices,
            'menus'   => $this->menuTree(),
            'slug'    => $slug,
        ]);
    }

    /**
     * The Settings API save endpoint (WordPress posts these to
     * options.php). Only options registered with register_setting() for
     * the submitted group are written — anything else in the POST body is
     * ignored, exactly like WordPress.
     */
    public function saveOptions()
    {
        $runtime = lcms_wp_boot(lcms_wp_active() ? 'theme' : 'plugins');
        $runtime->bootAdmin();

        $group = (string) $this->request->getPost('option_page');
        $known = Registry::$settings[$group] ?? [];

        // Plugin settings forms are pure WordPress markup, so they carry a
        // WP nonce rather than CodeIgniter's CSRF token (this route is
        // exempted from the csrf filter for exactly that reason).
        $_REQUEST['_wpnonce'] = (string) $this->request->getPost('_wpnonce');

        if (! wp_verify_nonce($_REQUEST['_wpnonce'], $group . '-options')) {
            session()->setFlashdata('error', 'That settings form has expired. Reload the page and try again.');

            return redirect()->back();
        }

        if ($known === []) {
            session()->setFlashdata('error', "No settings are registered for the group '{$group}'.");

            return redirect()->back();
        }

        if (! current_user_can('manage_options')) {
            session()->setFlashdata('error', 'You do not have permission to change these settings.');

            return redirect()->back();
        }

        foreach ($known as $option => $args) {
            $value = $this->request->getPost($option);

            if ($value === null) {
                // An unchecked checkbox posts nothing; store the empty value
                // so the setting can actually be turned off.
                $value = '';
            }

            if (is_callable($args['sanitize_callback'] ?? null)) {
                $value = ($args['sanitize_callback'])($value);
            }

            update_option($option, $value);
        }

        session()->setFlashdata('success', 'Settings saved.');

        $referer = (string) ($this->request->getPost('_wp_http_referer') ?? '');

        return $referer !== '' ? redirect()->to($referer) : redirect()->back();
    }

    /**
     * The Customizer stand-in: every setting a theme registered through
     * `customize_register`, rendered as a plain form. No live preview —
     * saving writes theme mods and the site picks them up on reload.
     */
    public function customize()
    {
        $runtime = lcms_wp_boot('theme');
        $runtime->bootAdmin();

        if (! lcms_wp_active()) {
            session()->setFlashdata('error', 'The Customizer applies to WordPress themes; the active theme is a native LightCMS theme.');

            return redirect()->to('/admin/themes');
        }

        do_action('customize_register', $this->customizeManager());

        return view('admin/wp/customize', [
            'title'    => 'Customize: ' . (string) get_option('stylesheet'),
            'sections' => Registry::$customize['sections'],
            'controls' => Registry::$customize['controls'],
            'settings' => Registry::$customize['settings'],
            'menus'    => $this->menuTree(),
        ]);
    }

    public function saveCustomize()
    {
        $runtime = lcms_wp_boot('theme');
        $runtime->bootAdmin();

        do_action('customize_register', $this->customizeManager());

        if (! current_user_can('edit_theme_options')) {
            session()->setFlashdata('error', 'You do not have permission to customize this theme.');

            return redirect()->to('/admin/themes');
        }

        foreach (Registry::$customize['settings'] as $id => $setting) {
            $value = $this->request->getPost(str_replace(['[', ']'], ['_', ''], $id));

            if ($value === null) {
                $value = '';
            }

            if (is_callable($setting->sanitize_callback ?? null)) {
                $value = ($setting->sanitize_callback)($value, $setting);
            }

            if (($setting->type ?? 'theme_mod') === 'option') {
                update_option($id, $value);
            } else {
                set_theme_mod($id, $value);
            }
        }

        session()->setFlashdata('success', 'Theme options saved.');

        return redirect()->to('/admin/wp/customize');
    }

    protected function customizeManager(): \WP_Customize_Manager
    {
        return $GLOBALS['wp_customize'] ??= new \WP_Customize_Manager();
    }

    /** @return array<string, mixed>|null */
    protected function findPage(string $slug): ?array
    {
        foreach (Registry::$adminMenus as $menu) {
            if ($menu['menu_slug'] === $slug) {
                return $menu;
            }
        }

        foreach (Registry::$adminSubmenus as $items) {
            foreach ($items as $item) {
                if ($item['menu_slug'] === $slug) {
                    return $item + ['icon_url' => ''];
                }
            }
        }

        return null;
    }

    /** Top-level pages with their submenus, for the sidebar in the WP admin views. */
    protected function menuTree(): array
    {
        $tree = [];

        foreach (Registry::$adminMenus as $menu) {
            $tree[] = [
                'title'    => $menu['menu_title'],
                'slug'     => $menu['menu_slug'],
                'children' => array_map(
                    static fn (array $child) => ['title' => $child['menu_title'], 'slug' => $child['menu_slug']],
                    Registry::$adminSubmenus[$menu['menu_slug']] ?? []
                ),
            ];
        }

        foreach (Registry::$adminSubmenus as $parent => $items) {
            if (in_array($parent, array_column(Registry::$adminMenus, 'menu_slug'), true)) {
                continue;
            }

            foreach ($items as $item) {
                $tree[] = ['title' => $item['menu_title'], 'slug' => $item['menu_slug'], 'children' => []];
            }
        }

        return $tree;
    }
}
