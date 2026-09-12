<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = ['setting_key', 'setting_value', 'autoload'];

    /**
     * All autoloaded settings as a flat key => value map, cached for
     * queryCacheTTL (PRD §3.6.A.3: "settings cache").
     */
    public function getAutoloaded(): array
    {
        $cache = \Config\Services::cache();
        $cached = $cache->get('settings_autoload');

        if ($cached !== null) {
            return $cached;
        }

        $rows = $this->where('autoload', 1)->findAll();
        $map  = [];

        foreach ($rows as $row) {
            $map[$row['setting_key']] = $row['setting_value'];
        }

        $cache->save('settings_autoload', $map, config(\Config\LightCMS::class)->queryCacheTTL);

        return $map;
    }

    public function get(string $key, $default = null)
    {
        $row = $this->where('setting_key', $key)->first();

        return $row['setting_value'] ?? $default;
    }

    /**
     * Named setValue() rather than set() — Model::set() is already taken
     * by the query-builder's SET-clause method and PHP forbids an
     * incompatible override of it.
     */
    public function setValue(string $key, $value, bool $autoload = true): bool
    {
        $existing = $this->where('setting_key', $key)->first();

        $result = $existing
            ? (bool) $this->update($existing['id'], ['setting_value' => $value])
            : (bool) $this->insert(['setting_key' => $key, 'setting_value' => $value, 'autoload' => $autoload ? 1 : 0]);

        \Config\Services::cache()->delete('settings_autoload');

        return $result;
    }
}
