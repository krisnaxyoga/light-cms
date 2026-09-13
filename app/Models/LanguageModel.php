<?php

namespace App\Models;

use CodeIgniter\Model;

class LanguageModel extends Model
{
    public const CACHE_KEY = 'languages_all';

    /**
     * First URL segments that already mean something to the router, so a
     * language can never claim them as its prefix.
     */
    public const RESERVED_PREFIXES = [
        'admin', 'api', 'blog', 'search', 'category', 'tag', 'author',
        'wp-admin', 'wp-json', 'wp-content', 'assets', 'themes', 'uploads',
    ];

    protected $table         = 'languages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'code', 'url_prefix', 'name', 'native_name', 'is_default', 'is_active', 'sort_order',
    ];

    protected $validationRules = [
        'code'       => 'required|max_length[10]|regex_match[/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/]|is_unique[languages.code,id,{id}]',
        'url_prefix' => 'required|max_length[10]|regex_match[/^[a-z][a-z0-9-]{0,9}$/]|is_unique[languages.url_prefix,id,{id}]',
        'name'       => 'required|max_length[100]',
        'id'         => 'permit_empty|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'code' => [
            'regex_match' => 'Language code must be an ISO code like "en", "id" or "pt-br".',
            'is_unique'   => 'That language code already exists.',
        ],
        'url_prefix' => [
            'regex_match' => 'URL prefix may only contain lowercase letters, digits and dashes.',
            'is_unique'   => 'That URL prefix is already used by another language.',
        ],
    ];

    protected $afterInsert = ['flushCache'];
    protected $afterUpdate = ['flushCache'];
    protected $afterDelete = ['flushCache'];

    public function allOrdered(): array
    {
        return $this->orderBy('is_default', 'DESC')->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
    }

    public function findByCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }

    /**
     * Make one language the default (URL without prefix) — exactly one row
     * may carry the flag, and the default is always active.
     */
    public function setDefault(int $id): void
    {
        $this->db->table($this->table)->update(['is_default' => 0]);
        $this->db->table($this->table)->where('id', $id)->update(['is_default' => 1, 'is_active' => 1]);
        $this->flushCache([]);
    }

    public static function isReservedPrefix(string $prefix): bool
    {
        return in_array(strtolower($prefix), self::RESERVED_PREFIXES, true);
    }

    protected function flushCache(array $eventData): array
    {
        \Config\Services::cache()->delete(self::CACHE_KEY);

        return $eventData;
    }
}
