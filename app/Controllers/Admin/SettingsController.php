<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use Config\Services;

class SettingsController extends BaseController
{
    protected array $textKeys = [
        'site_title', 'site_description', 'site_logo', 'site_favicon',
        'posts_per_page', 'excerpt_length',
        'timezone', 'seo_separator', 'default_og_image', 'robots_crawl_delay',
        // Single source of truth for every WhatsApp CTA on the site — see
        // app/Helpers/whatsapp_helper.php. Previously duplicated as a
        // literal string in theme.json and in HomepageContent's defaults.
        'whatsapp_number',
        // Google Analytics (gtag.js) measurement ID, e.g. G-XXXXXXXXXX.
        // Rendered in every theme's <head> — see layouts/header.php.
        'google_analytics_id',
    ];

    /** Rendered as checkboxes — absent in $_POST means "unchecked", not "leave alone". */
    protected array $checkboxKeys = ['robots_index', 'robots_follow'];

    public function index()
    {
        $settingModel = new SettingModel();
        $settings     = [];

        foreach ([...$this->textKeys, ...$this->checkboxKeys] as $key) {
            $settings[$key] = $settingModel->get($key);
        }

        return view('admin/settings/index', ['settings' => $settings]);
    }

    public function update()
    {
        $settingModel = new SettingModel();

        foreach ($this->textKeys as $key) {
            if ($this->request->getPost($key) !== null) {
                $settingModel->setValue($key, (string) $this->request->getPost($key));
            }
        }

        // Checkboxes: an absent field means "unchecked", so always write
        // 0/1 rather than skipping when getPost() comes back null.
        foreach ($this->checkboxKeys as $key) {
            $settingModel->setValue($key, $this->request->getPost($key) ? '1' : '0');
        }

        // robots.txt reflects robots_index/crawl-delay — regenerate now
        // rather than waiting for the next unrelated trigger (PRD ADDENDUM
        // §7.1 autoRegenerate on settings change).
        Services::robotsTxtGenerator()->write();

        session()->setFlashdata('success', 'Settings saved.');

        return redirect()->to('/admin/settings');
    }
}
