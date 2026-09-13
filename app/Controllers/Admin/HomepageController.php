<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Homepage\HomepageContent;
use App\Models\ActivityLogModel;

/**
 * Admin -> Homepage: every piece of wording, CTA link and image on the
 * Super Travel front page, the /blog listing header and the single-post
 * booking CTA, editable without touching theme files (PRD §3.3.B-style
 * "Customizer", but for a bespoke landing page rather than theme options).
 *
 * Storage: one JSON document in the `settings` table — see
 * App\Libraries\Homepage\HomepageContent for the schema, defaults and
 * merge rules.
 */
class HomepageController extends BaseController
{
    public function index()
    {
        return view('admin/homepage/index', [
            'content' => HomepageContent::get(),
            'schema'  => HomepageContent::schema(),
        ]);
    }

    public function update()
    {
        $content = HomepageContent::fromPost($this->request->getPost() ?? []);

        HomepageContent::save($content);

        (new ActivityLogModel())->record(session('userId'), 'update', 'homepage_content', null);

        session()->setFlashdata('success', 'Homepage content saved.');

        return redirect()->to('/admin/homepage');
    }
}
