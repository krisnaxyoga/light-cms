<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuItemModel extends Model
{
    protected $table         = 'menu_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowedFields = [
        'menu_id', 'parent_id', 'title', 'url', 'target', 'css_class', 'position',
    ];

    /**
     * Ordered, nested tree of items for a menu — one query, built into a
     * tree in PHP (cheap for the small item counts a site menu has).
     */
    public function treeForMenu(int $menuId): array
    {
        $items = $this->where('menu_id', $menuId)->orderBy('position', 'ASC')->findAll();

        $byParent = [];
        foreach ($items as $item) {
            $byParent[$item['parent_id']][] = $item;
        }

        $build = static function (int $parentId) use (&$build, $byParent): array {
            $branch = [];
            foreach ($byParent[$parentId] ?? [] as $item) {
                $item['children'] = $build((int) $item['id']);
                $branch[]         = $item;
            }

            return $branch;
        };

        return $build(0);
    }

    /**
     * Replace every item in a menu with the given tree (the admin builder's
     * "save whole structure" action — same delete-then-insert pattern
     * PostModel::attachCategories()/attachTags() use for pivots). Each node:
     * ['title' => ..., 'url' => ..., 'target' => bool, 'css_class' => ...,
     *  'children' => [...]].
     */
    public function replaceTree(int $menuId, array $tree): void
    {
        $this->where('menu_id', $menuId)->delete();
        $this->insertBranch($menuId, 0, $tree);
    }

    protected function insertBranch(int $menuId, int $parentId, array $nodes): void
    {
        $position = 0;

        foreach ($nodes as $node) {
            $title = trim((string) ($node['title'] ?? ''));
            $url   = trim((string) ($node['url'] ?? ''));

            if ($title === '' && $url === '') {
                continue; // a blank custom-link row the admin never filled in
            }

            $id = $this->insert([
                'menu_id'   => $menuId,
                'parent_id' => $parentId,
                'title'     => $title,
                'url'       => $url,
                'target'    => ! empty($node['target']) ? '_blank' : null,
                'css_class' => trim((string) ($node['css_class'] ?? '')) ?: null,
                'position'  => $position++,
            ], true);

            if (! empty($node['children']) && is_array($node['children'])) {
                $this->insertBranch($menuId, (int) $id, $node['children']);
            }
        }
    }
}
