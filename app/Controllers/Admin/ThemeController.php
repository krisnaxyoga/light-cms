<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;
use App\Models\ThemeModel;
use Config\LightCMS as LightCMSConfig;
use Config\Services;

class ThemeController extends BaseController
{
    public function index()
    {
        $themeModel = new ThemeModel();
        $themesPath = FCPATH . config(LightCMSConfig::class)->themesPath;

        // Union of themes registered in the DB and theme folders on disk —
        // a folder dropped in without running an "install" step still shows.
        $installed = array_map('basename', glob($themesPath . '*', GLOB_ONLYDIR) ?: []);
        $known     = $themeModel->findAll();
        $knownSlugs = array_column($known, 'slug');

        foreach (array_diff($installed, $knownSlugs) as $slug) {
            $meta = json_decode((string) @file_get_contents($themesPath . $slug . '/theme.json'), true) ?: [];
            $known[] = [
                'id'        => null,
                'name'      => $meta['name'] ?? $slug,
                'slug'      => $slug,
                'engine'    => 'native',
                'version'   => $meta['version'] ?? '1.0.0',
                'author'    => $meta['author'] ?? '',
                'is_active' => 0,
            ];
        }

        // Native themes only in the first list; WordPress themes live in
        // public/wp-content/themes and are discovered by their style.css
        // header rather than a theme.json.
        $native = array_values(array_filter($known, static fn (array $theme) => ($theme['engine'] ?? 'native') !== 'wordpress'));
        $active = $themeModel->getActive();

        $wordpress = [];

        if (lcms_wp_enabled()) {
            foreach (lcms_wp_boot('core')->themes()->available() as $slug => $header) {
                $wordpress[] = [
                    'name'       => $header['Name'],
                    'slug'       => $slug,
                    'version'    => $header['Version'],
                    'author'     => strip_tags($header['Author']),
                    'screenshot' => $header['screenshot'],
                    'parent'     => $header['Template'],
                    'is_child'   => $header['is_child'],
                    'is_active'  => ($active['slug'] ?? null) === $slug && ($active['engine'] ?? '') === 'wordpress' ? 1 : 0,
                ];
            }
        }

        return view('admin/themes/index', [
            'themes'    => $native,
            'wpThemes'  => $wordpress,
            'wpEnabled' => lcms_wp_enabled(),
        ]);
    }

    public function activate(string $slug)
    {
        $themeModel = new ThemeModel();

        if (! $themeModel->where('slug', $slug)->first()) {
            $themesPath = FCPATH . config(LightCMSConfig::class)->themesPath . $slug;
            $meta       = json_decode((string) @file_get_contents($themesPath . '/theme.json'), true) ?: [];

            $themeModel->insert([
                'name'      => $meta['name'] ?? $slug,
                'slug'      => $slug,
                'version'   => $meta['version'] ?? '1.0.0',
                'author'    => $meta['author'] ?? '',
                'is_active' => 0,
                'settings'  => $meta['settings'] ?? [],
            ]);
        }

        $themeModel->update($themeModel->where('slug', $slug)->first()['id'], ['engine' => 'native', 'parent_slug' => null]);
        $themeModel->activate($slug);
        Services::cacheManager()->flushAll();
        (new ActivityLogModel())->record(session('userId'), 'activate_theme', 'theme', null);

        session()->setFlashdata('success', "Theme '{$slug}' activated.");

        return redirect()->to('/admin/themes');
    }

    /**
     * Activate a WordPress theme from public/wp-content/themes. The theme
     * is loaded once straight away so a broken functions.php is reported
     * here instead of taking the front end down.
     */
    public function activateWordPress(string $slug)
    {
        if (! lcms_wp_enabled()) {
            session()->setFlashdata('error', 'The WordPress compatibility layer is disabled in app/Config/WordPress.php.');

            return redirect()->to('/admin/themes');
        }

        $runtime = lcms_wp_boot('core');

        if (! $runtime->themes()->activate($slug)) {
            session()->setFlashdata('error', "No WordPress theme called '{$slug}' was found in public/wp-content/themes.");

            return redirect()->to('/admin/themes');
        }

        Services::cacheManager()->flushAll();
        \App\Libraries\WordPress\Runtime::reset();

        try {
            lcms_wp_boot('theme');
            $failures = \App\Libraries\WordPress\Registry::$adminNotices;
        } catch (\Throwable $e) {
            $failures = [['type' => 'error', 'message' => $e->getMessage()]];
        }

        (new ActivityLogModel())->record(session('userId'), 'activate_theme', 'theme', null);

        if ($failures !== []) {
            session()->setFlashdata('error', "Theme '{$slug}' activated, but it reported: " . $failures[0]['message']);
        } else {
            session()->setFlashdata('success', "WordPress theme '{$slug}' activated.");
        }

        return redirect()->to('/admin/themes');
    }
}
