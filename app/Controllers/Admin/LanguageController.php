<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LanguageModel;
use App\Models\SettingModel;
use Config\Services;

/**
 * Admin -> Languages: the multi-language master switch plus the list of
 * languages the site serves. The default language keeps the bare URL
 * (/slug); every other active language is reachable under its URL
 * prefix (/id/slug). See App\Libraries\I18n\LocaleManager.
 */
class LanguageController extends BaseController
{
    public function index()
    {
        $counts = [];

        foreach (\Config\Database::connect()->table('posts')->select('locale, COUNT(*) AS total')->groupBy('locale')->get()->getResultArray() as $row) {
            $counts[$row['locale']] = (int) $row['total'];
        }

        return view('admin/languages/index', [
            'languages'  => (new LanguageModel())->allOrdered(),
            'enabled'    => Services::locale()->enabled(),
            'postCounts' => $counts,
            'reserved'   => LanguageModel::RESERVED_PREFIXES,
        ]);
    }

    /** Master on/off switch — off means the site runs single-language as before. */
    public function toggle()
    {
        $on = $this->request->getPost('multilang_enabled') ? '1' : '0';

        (new SettingModel())->setValue('multilang_enabled', $on);
        $this->afterChange();

        session()->setFlashdata('success', $on === '1'
            ? 'Multi-language enabled. Add the languages you want below.'
            : 'Multi-language disabled — the site is back to a single language.');

        return redirect()->to('/admin/languages');
    }

    public function store()
    {
        $model = new LanguageModel();
        $data  = $this->collectInput();

        $data['is_active']  = 1;
        $data['is_default'] = $model->countAllResults() === 0 ? 1 : 0;
        $data['sort_order'] = $model->countAllResults();

        if ($error = $this->reservedPrefixError($data['url_prefix'])) {
            session()->setFlashdata('error', $error);

            return redirect()->back()->withInput();
        }

        if (! $model->insert($data, false)) {
            session()->setFlashdata('errors', $model->errors());

            return redirect()->back()->withInput();
        }

        $this->afterChange();
        session()->setFlashdata('success', "Language \"{$data['name']}\" added.");

        return redirect()->to('/admin/languages');
    }

    public function update(int $id)
    {
        $model    = new LanguageModel();
        $existing = $model->find($id);

        if ($existing === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $data       = $this->collectInput();
        $data['id'] = $id; // for the is_unique[...,id,{id}] placeholders only

        if ($error = $this->reservedPrefixError($data['url_prefix'])) {
            session()->setFlashdata('error', $error);

            return redirect()->back();
        }

        if (! $model->update($id, $data)) {
            session()->setFlashdata('errors', $model->errors());

            return redirect()->back();
        }

        // Posts are tagged by code, so a renamed code carries its rows along.
        if ($existing['code'] !== $data['code']) {
            \Config\Database::connect()->table('posts')
                ->where('locale', $existing['code'])
                ->update(['locale' => $data['code']]);
        }

        $this->afterChange();
        session()->setFlashdata('success', "Language \"{$data['name']}\" updated.");

        return redirect()->to('/admin/languages');
    }

    public function makeDefault(int $id)
    {
        $model = new LanguageModel();

        if ($model->find($id) === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $model->setDefault($id);
        $this->afterChange();

        session()->setFlashdata('success', 'Default language changed. Its URLs now have no prefix; the previous default is served under its prefix.');

        return redirect()->to('/admin/languages');
    }

    public function toggleActive(int $id)
    {
        $model = new LanguageModel();
        $lang  = $model->find($id);

        if ($lang === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if ((int) $lang['is_default'] === 1) {
            session()->setFlashdata('error', 'The default language is always active. Make another language the default first.');

            return redirect()->to('/admin/languages');
        }

        $model->update($id, ['is_active' => (int) $lang['is_active'] === 1 ? 0 : 1]);
        $this->afterChange();

        return redirect()->to('/admin/languages');
    }

    public function delete(int $id)
    {
        $model = new LanguageModel();
        $lang  = $model->find($id);

        if ($lang === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if ((int) $lang['is_default'] === 1) {
            session()->setFlashdata('error', 'The default language cannot be deleted.');

            return redirect()->to('/admin/languages');
        }

        $posts = \Config\Database::connect()->table('posts')->where('locale', $lang['code'])->countAllResults();

        if ($posts > 0) {
            session()->setFlashdata('error', "\"{$lang['name']}\" still has {$posts} post(s)/page(s). Deactivate it instead, or delete that content first.");

            return redirect()->to('/admin/languages');
        }

        $model->delete($id);
        $this->afterChange();

        session()->setFlashdata('success', "Language \"{$lang['name']}\" deleted.");

        return redirect()->to('/admin/languages');
    }

    protected function collectInput(): array
    {
        $code   = strtolower(trim((string) $this->request->getPost('code')));
        $prefix = strtolower(trim((string) $this->request->getPost('url_prefix'), " \t/")) ?: $code;

        return [
            'code'        => $code,
            'url_prefix'  => $prefix,
            'name'        => trim((string) $this->request->getPost('name')),
            'native_name' => trim((string) $this->request->getPost('native_name')) ?: null,
        ];
    }

    protected function reservedPrefixError(string $prefix): ?string
    {
        if (LanguageModel::isReservedPrefix($prefix)) {
            return "\"/{$prefix}\" is already used by the site itself and cannot be a language prefix.";
        }

        return null;
    }

    /**
     * Language changes affect URLs everywhere: drop the language cache,
     * cached guest pages, and rebuild the sitemap so hreflang entries match.
     */
    protected function afterChange(): void
    {
        Services::locale()->flushCache();
        Services::cacheManager()->flushTag('posts');
        Services::sitemapGenerator()->writeFiles();
    }
}
