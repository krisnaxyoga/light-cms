<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Theme\Theme;
use App\Models\CategoryModel;
use App\Models\MenuItemModel;
use App\Models\MenuModel;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Services;

/**
 * Admin -> Menus: a nested (silo-style) navigation menu builder. Existing
 * items are grouped under a parent to form the sub-topics of a silo; the
 * frontend theme renders whatever depth is built here (theme_menu()).
 *
 * The schema (menus/menu_items) already existed for the WordPress-import
 * path — this is the first native admin UI for it.
 */
class MenuController extends BaseController
{
    public function index(?int $id = null)
    {
        $menuModel = new MenuModel();
        $menus     = $menuModel->orderBy('name', 'ASC')->findAll();

        $menu = $id ? $menuModel->find($id) : ($menus[0] ?? null);

        if ($id && $menu === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        // Ensures the active theme's functions.php has run so Theme::menus()
        // reflects its real registered locations (primary/footer/etc.).
        theme_engine();

        $assignedLocations = array_column($menus, 'location');
        $currentLocation    = $menu['location'] ?? null;

        return view('admin/menus/index', [
            'menus'     => $menus,
            'menu'      => $menu,
            'items'     => $menu ? (new MenuItemModel())->treeForMenu((int) $menu['id']) : [],
            'locations' => Theme::menus(),
            // A location already used by another menu can't be picked again
            // (one menu per slot) — except the slot this menu already holds.
            'takenLocations' => array_diff($assignedLocations, [$currentLocation]),
            'multilang'       => Services::locale()->enabled(),
        ]);
    }

    public function store()
    {
        $name  = trim((string) $this->request->getPost('name'));
        $model = new MenuModel();

        $data = [
            // Present only so the is_unique[...,id,{id}] rule has a field to
            // resolve its placeholder against (same trick as PostModel) —
            // doProtectFields() strips it before the INSERT since it's not
            // in $allowedFields.
            'id'   => null,
            'name' => $name !== '' ? $name : 'New Menu',
            'slug' => $this->uniqueSlug($model, url_title($name, '-', true) ?: 'menu'),
        ];

        if (! $model->insert($data, false)) {
            session()->setFlashdata('errors', $model->errors());

            return redirect()->to('/admin/menus');
        }

        session()->setFlashdata('success', 'Menu created — start adding items to it.');

        return redirect()->to('/admin/menus/' . $model->getInsertID());
    }

    public function update(int $id)
    {
        $menuModel = new MenuModel();
        $menu      = $menuModel->find($id);

        if ($menu === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $name     = trim((string) $this->request->getPost('name')) ?: $menu['name'];
        $location = (string) $this->request->getPost('location') ?: null;

        // 'slug' is stable once created (it's not exposed in this form),
        // but the validation rule requires it, so carry the current value
        // through — same {id} placeholder trick as PostModel's is_unique.
        $data = ['id' => $id, 'name' => $name, 'slug' => $menu['slug']];

        if ($location !== $menu['location']) {
            if ($location !== null) {
                // A theme location is a single slot — reassigning it here
                // vacates whichever other menu had it.
                $menuModel->clearLocation($location, $id);
            }

            $data['location'] = $location;
        }

        if (! $menuModel->update($id, $data)) {
            session()->setFlashdata('errors', $menuModel->errors());

            return redirect()->to('/admin/menus/' . $id);
        }

        $tree = json_decode((string) $this->request->getPost('items_json'), true);
        (new MenuItemModel())->replaceTree($id, is_array($tree) ? $tree : []);

        Services::cacheManager()->flushTag('posts'); // full-page cache keys the nav is baked into

        session()->setFlashdata('success', 'Menu saved.');

        return redirect()->to('/admin/menus/' . $id);
    }

    public function delete(int $id)
    {
        $menuModel = new MenuModel();

        if ($menuModel->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        (new MenuItemModel())->where('menu_id', $id)->delete();
        $menuModel->delete($id);

        Services::cacheManager()->flushTag('posts');
        session()->setFlashdata('success', 'Menu deleted.');

        return redirect()->to('/admin/menus');
    }

    /**
     * AJAX (GET, no CSRF needed) for the "Add menu items" panel.
     * ?type=page|post|category&q=search+text
     */
    public function search()
    {
        $type = (string) $this->request->getGet('type');
        $q    = trim((string) $this->request->getGet('q'));
        $locale = Services::locale();

        $rows = match ($type) {
            'page', 'post' => (new PostModel())
                ->select('id, title, slug, locale')
                ->where('post_type', $type)
                ->where('status', 'published')
                ->like('title', $q)
                ->orderBy('title', 'ASC')
                ->findAll(20),
            'category' => (new CategoryModel())
                ->select('id, name, slug')
                ->like('name', $q)
                ->orderBy('name', 'ASC')
                ->findAll(20),
            default => [],
        };

        $results = array_map(static function (array $row) use ($type, $locale) {
            $title = $row['title'] ?? $row['name'];
            $url   = $type === 'category'
                ? $locale->url('category/' . $row['slug'])
                // $type is already 'page' or 'post' here (the query above
                // filtered on it), so it stands in for the row's own
                // post_type — a real blog post's menu link gets /blog/{slug}.
                : post_url(['slug' => $row['slug'], 'post_type' => $type], $row['locale'] ?? null);

            return [
                'title'  => $title,
                'url'    => $url,
                'locale' => $row['locale'] ?? null,
            ];
        }, $rows);

        return $this->response->setJSON($results);
    }

    protected function uniqueSlug(MenuModel $model, string $base): string
    {
        $slug = $base;
        $i    = 2;

        while ($model->where('slug', $slug)->first() !== null) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
