<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table            = 'roles';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = ''; // roles has no updated_at column
    protected $allowedFields    = ['name', 'permissions'];

    protected array $casts = [
        'permissions' => '?json-array', // column is nullable at the DB level
    ];
}
