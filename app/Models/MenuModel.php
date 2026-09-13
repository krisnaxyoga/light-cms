<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuModel extends Model
{
    protected $table         = 'menus';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowedFields = ['name', 'slug', 'location'];

    protected $validationRules = [
        'name' => 'required|max_length[100]',
        'slug' => 'required|max_length[100]|is_unique[menus.slug,id,{id}]',
        // Only present so the is_unique[...,id,{id}] placeholder can
        // resolve against *this* row on update — same trick PostModel uses.
        'id'   => 'permit_empty|is_natural_no_zero',
    ];

    public function findByLocation(string $location): ?array
    {
        return $this->where('location', $location)->first();
    }

    /**
     * A theme location (e.g. 'primary') can only be assigned to one menu
     * at a time — reassigning it here is what "frees" it from whichever
     * menu had it before.
     */
    public function clearLocation(string $location, ?int $exceptMenuId = null): void
    {
        $query = $this->where('location', $location);

        if ($exceptMenuId !== null) {
            $query->where('id !=', $exceptMenuId);
        }

        $query->set('location', null)->update();
    }
}
