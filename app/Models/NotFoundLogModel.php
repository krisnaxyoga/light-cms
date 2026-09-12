<?php

namespace App\Models;

use CodeIgniter\Model;

class NotFoundLogModel extends Model
{
    protected $table         = 'not_found_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowedFields = ['url', 'referer', 'ip_address'];

    /**
     * Distinct unknown URLs hit within the last $minutes — the basis for
     * the 404-spike notification (PRD ADDENDUM §8/§13).
     */
    public function distinctHitsSince(int $minutes): int
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));

        return $this->select('url')->distinct()->where('created_at >=', $since)->countAllResults();
    }

    /**
     * Existing post/page slugs that look similar to a missed URL —
     * a cheap stand-in for "auto-find similar URLs" (PRD ADDENDUM §8.1).
     */
    public function suggestSimilarSlugs(string $missedPath, int $limit = 5): array
    {
        $needle = trim($missedPath, '/');

        if ($needle === '') {
            return [];
        }

        return (new PostModel())
            ->select('slug')
            ->where('status', 'published')
            ->like('slug', $needle)
            ->limit($limit)
            ->findColumn('slug') ?? [];
    }
}
