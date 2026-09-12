<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table         = 'categories';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = ''; // no updated_at column

    protected $allowedFields = [
        'name', 'slug', 'description', 'parent_id', 'meta_title', 'meta_description',
    ];

    public function findBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->first();
    }

    public function tree(): array
    {
        $all = $this->orderBy('name', 'ASC')->findAll();
        $byParent = [];

        foreach ($all as $category) {
            $byParent[$category['parent_id']][] = $category;
        }

        return $byParent;
    }
}
