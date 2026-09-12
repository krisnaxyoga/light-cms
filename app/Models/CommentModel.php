<?php

namespace App\Models;

use CodeIgniter\Model;

class CommentModel extends Model
{
    protected $table         = 'comments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowedFields = [
        'post_id', 'parent_id', 'author_name', 'author_email', 'author_ip',
        'content', 'status',
    ];

    public function approvedForPost(int $postId): array
    {
        return $this->where('post_id', $postId)
            ->where('status', 'approved')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    public function pendingCount(): int
    {
        return $this->where('status', 'pending')->countAllResults();
    }
}
