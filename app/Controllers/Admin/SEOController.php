<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\NotFoundLogModel;
use App\Models\RedirectModel;
use Config\Services;

class SEOController extends BaseController
{
    public function redirects()
    {
        $redirectModel = new RedirectModel();

        return view('admin/seo/redirects', [
            'redirects'  => $redirectModel->orderBy('created_at', 'DESC')->paginate(20),
            'pager'      => $redirectModel->pager,
            'notFounds'  => (new NotFoundLogModel())->orderBy('created_at', 'DESC')->findAll(10),
        ]);
    }

    public function storeRedirect()
    {
        (new RedirectModel())->insert([
            'source_url'    => (string) $this->request->getPost('source_url'),
            'target_url'    => (string) $this->request->getPost('target_url'),
            'redirect_type' => (string) $this->request->getPost('redirect_type') ?: '301',
            'is_regex'      => $this->request->getPost('is_regex') ? 1 : 0,
            'status'        => 1,
        ]);

        session()->setFlashdata('success', 'Redirect created.');

        return redirect()->to('/admin/seo/redirects');
    }

    public function deleteRedirect(int $id)
    {
        (new RedirectModel())->delete($id);

        return redirect()->back();
    }

    /**
     * Rebuild sitemap.xml on demand (PRD §3.1.B.1). PostModel already
     * regenerates it automatically on publish/unpublish/delete (PRD
     * ADDENDUM §3.1) — this is the manual "just in case" button.
     */
    public function regenerateSitemap()
    {
        $files = Services::sitemapGenerator()->writeFiles();

        session()->setFlashdata('success', 'Sitemap regenerated: ' . implode(', ', $files));

        return redirect()->back();
    }

    /**
     * Manual robots.txt rebuild. Settings already trigger this
     * automatically on save (PRD ADDENDUM §7.1) — same "just in case" role.
     */
    public function regenerateRobots()
    {
        Services::robotsTxtGenerator()->write();

        session()->setFlashdata('success', 'robots.txt regenerated.');

        return redirect()->back();
    }
}
