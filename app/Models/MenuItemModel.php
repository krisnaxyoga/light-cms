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
}
