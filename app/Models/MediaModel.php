<?php

namespace App\Models;

use CodeIgniter\Model;

class MediaModel extends Model
{
    protected $table         = 'media';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowedFields = [
        'filename', 'title', 'filepath', 'filetype', 'filesize', 'width', 'height',
        'variants', 'alt_text', 'caption', 'uploaded_by',
    ];

    protected $validationRules = [
        // Only enforced when 'filename' is actually present in the data
        // being validated (i.e. a rename) — MediaController::update() only
        // includes it then, so a plain title/alt-text save never touches
        // this rule at all. Deliberately NOT is_unique: `filename` alone
        // (e.g. "photo.jpg") is only meaningful scoped to its own
        // uploads/YYYY/MM/ folder, and two different months can legitimately
        // share a bare name with no real collision — MediaController checks
        // the actual filesystem path for that instead.
        'filename' => 'permit_empty|max_length[255]|regex_match[/^[A-Za-z0-9._-]+$/]',
    ];

    protected array $casts = [
        // WebP + thumbnail paths from the upload pipeline (PRD ADDENDUM
        // §4); nullable because non-image uploads (PDF, audio…) have none.
        'variants' => '?json-array',
    ];

    public function search(?string $term = null, ?string $filetype = null, int $limit = 20, int $offset = 0): array
    {
        $builder = $this->orderBy('created_at', 'DESC');

        if ($term) {
            $builder = $builder->like('filename', $term);
        }

        if ($filetype) {
            $builder = $builder->where('filetype', $filetype);
        }

        return $builder->findAll($limit, $offset);
    }

    /**
     * Media rows with no matching entry anywhere in posts.content or
     * featured_image — a cheap approximation of the PRD's "unused media
     * detector" (§3.2.4). Good enough for a warn-the-user admin list;
     * not a hard guarantee (block content can reference by URL too).
     */
    public function findUnused(int $limit = 50): array
    {
        return $this->db->table('media m')
            ->select('m.*')
            ->join('posts p', 'p.featured_image = m.filepath OR p.content LIKE CONCAT(\'%\', m.filepath, \'%\')', 'left')
            ->where('p.id IS NULL')
            ->limit($limit)
            ->get()->getResultArray();
    }
}
