<?php

namespace App\Models;

use CodeIgniter\Model;

class ThemeModel extends Model
{
    protected $table         = 'themes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = ['name', 'slug', 'engine', 'parent_slug', 'version', 'author', 'is_active', 'settings'];

    protected array $casts = [
        'settings' => '?json-array', // column is nullable at the DB level
    ];

    public function getActive(): ?array
    {
        return $this->where('is_active', 1)->first();
    }

    /**
     * Flip the active flag atomically: everything off, then the chosen
     * theme on. Simple and correct for the low write-volume this action has.
     */
    public function activate(string $slug): bool
    {
        $theme = $this->where('slug', $slug)->first();

        if (! $theme) {
            return false;
        }

        $this->db->transStart();
        $this->builder()->set('is_active', 0)->where('id !=', 0)->update();
        $this->update($theme['id'], ['is_active' => 1]);
        $this->db->transComplete();

        return $this->db->transStatus();
    }
}
