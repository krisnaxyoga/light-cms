<?php

namespace App\Models;

use CodeIgniter\Model;

class RedirectModel extends Model
{
    protected $table         = 'redirects';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowedFields = [
        'source_url', 'target_url', 'redirect_type', 'hits', 'is_regex', 'status',
    ];

    /**
     * Resolve a request path against active redirects. Exact matches are
     * checked first (cheap), then regex rules (PRD §3.1.B.4).
     */
    public function match(string $path): ?array
    {
        $exact = $this->where('source_url', $path)
            ->where('is_regex', 0)
            ->where('status', 1)
            ->first();

        if ($exact) {
            return $exact;
        }

        $regexRules = $this->where('is_regex', 1)->where('status', 1)->findAll();

        foreach ($regexRules as $rule) {
            if (@preg_match($rule['source_url'], $path)) {
                return $rule;
            }
        }

        return null;
    }

    public function recordHit(int $id): bool
    {
        return $this->set('hits', 'hits + 1', false)->where('id', $id)->update();
    }
}
