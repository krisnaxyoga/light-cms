<?php

namespace App\Models;

use CodeIgniter\Model;

class SEOModel extends Model
{
    protected $table         = 'seo_meta';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'post_id', 'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
        'robots_index', 'robots_follow', 'og_title', 'og_description', 'og_image',
        'twitter_card', 'schema_data', 'focus_keyword', 'seo_score',
    ];

    protected array $casts = [
        // '?' marks it nullable — the column has no default and most rows
        // never set it, so a plain 'json-array' cast throws on every read.
        'schema_data' => '?json-array',
    ];

    public function findByPostId(int $postId): ?array
    {
        return $this->where('post_id', $postId)->first();
    }

    /**
     * Insert or update the seo_meta row for a post (post_id is UNIQUE).
     */
    public function saveForPost(int $postId, array $data): bool
    {
        $existing = $this->findByPostId($postId);
        $data['post_id'] = $postId;

        if ($existing) {
            return (bool) $this->update($existing['id'], $data);
        }

        return (bool) $this->insert($data);
    }
}
