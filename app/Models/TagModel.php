<?php

namespace App\Models;

use CodeIgniter\Model;

class TagModel extends Model
{
    protected $table         = 'tags';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowedFields = ['name', 'slug', 'description'];

    public function findBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->first();
    }

    /**
     * Find-or-create a tag by name, returning its id. Used by the editor
     * when a user types a free-form tag on a post.
     */
    public function findOrCreate(string $name): int
    {
        $slug = url_title($name, '-', true);
        $tag  = $this->findBySlug($slug);

        if ($tag) {
            return (int) $tag['id'];
        }

        return (int) $this->insert(['name' => $name, 'slug' => $slug], true);
    }
}
