<?php

namespace App\Libraries\WordPress;

use App\Models\SettingModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;

/**
 * get_option()/update_option() storage.
 *
 * Options that LightCMS already owns are *aliased* onto the `settings`
 * table (see self::ALIASES) so a WP plugin reading get_option('blogname')
 * sees the title set in LightCMS admin, and a plugin writing it updates
 * the real setting. Everything else lives in `wp_options`, WP-style,
 * including the serialization behaviour of arrays/objects.
 */
class OptionStore
{
    /** WP option name => LightCMS settings key. */
    public const ALIASES = [
        'blogname'        => 'site_title',
        'blogdescription' => 'site_description',
        'posts_per_page'  => 'posts_per_page',
        'admin_email'     => 'admin_email',
        'date_format'     => 'date_format',
        'time_format'     => 'time_format',
        'timezone_string' => 'timezone',
    ];

    /** Options computed from the environment; never written. */
    protected const DERIVED = ['home', 'siteurl', 'template', 'stylesheet', 'blog_charset', 'permalink_structure', 'show_on_front'];

    protected array $cache = [];
    protected bool $loaded = false;

    public function __construct(protected ?SettingModel $settings = null)
    {
        $this->settings ??= new SettingModel();
    }

    public function get(string $name, mixed $default = false): mixed
    {
        if (in_array($name, self::DERIVED, true)) {
            return $this->derived($name, $default);
        }

        if (isset(self::ALIASES[$name])) {
            $value = $this->settings->get(self::ALIASES[$name], null);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        $this->loadAutoloaded();

        if (array_key_exists($name, $this->cache)) {
            // null is the negative-cache marker for "looked up, not there",
            // so a later call with a different default still gets it.
            return $this->cache[$name] ?? $default;
        }

        try {
            $row = $this->table()->where('option_name', $name)->get()->getRowArray();
        } catch (DatabaseException $e) {
            log_message('error', 'WP compat: cannot read wp_options — run `php spark migrate`. (' . $e->getMessage() . ')');

            return $default;
        }

        if ($row === null) {
            $this->cache[$name] = null; // negative cache; distinguish from stored null

            return $default;
        }

        return $this->cache[$name] = static::maybeUnserialize($row['option_value']);
    }

    public function has(string $name): bool
    {
        $this->loadAutoloaded();

        if (array_key_exists($name, $this->cache) && $this->cache[$name] !== null) {
            return true;
        }

        return $this->table()->where('option_name', $name)->countAllResults() > 0;
    }

    public function update(string $name, mixed $value, bool|string $autoload = true): bool
    {
        if (in_array($name, self::DERIVED, true)) {
            return false;
        }

        if (isset(self::ALIASES[$name])) {
            $this->settings->setValue(self::ALIASES[$name], is_scalar($value) ? (string) $value : json_encode($value));
            $this->cache[$name] = $value;

            return true;
        }

        $stored   = static::maybeSerialize($value);
        $autoload = is_string($autoload) ? $autoload : ($autoload ? 'yes' : 'no');
        $exists   = $this->table()->where('option_name', $name)->countAllResults() > 0;

        if ($exists) {
            $this->table()->where('option_name', $name)->update(['option_value' => $stored, 'autoload' => $autoload]);
        } else {
            $this->table()->insert(['option_name' => $name, 'option_value' => $stored, 'autoload' => $autoload]);
        }

        $this->cache[$name] = $value;

        return true;
    }

    public function add(string $name, mixed $value, bool|string $autoload = true): bool
    {
        if ($this->has($name)) {
            return false;
        }

        return $this->update($name, $value, $autoload);
    }

    public function delete(string $name): bool
    {
        unset($this->cache[$name]);

        if (isset(self::ALIASES[$name]) || in_array($name, self::DERIVED, true)) {
            return false;
        }

        return (bool) $this->table()->where('option_name', $name)->delete();
    }

    /** Options whose name starts with $prefix — used by the plugins screen. */
    public function startingWith(string $prefix): array
    {
        $rows = $this->table()->like('option_name', $prefix, 'after')->get()->getResultArray();
        $out  = [];

        foreach ($rows as $row) {
            $out[$row['option_name']] = static::maybeUnserialize($row['option_value']);
        }

        return $out;
    }

    protected function derived(string $name, mixed $default): mixed
    {
        $theme = new ThemeRepository();

        return match ($name) {
            'home', 'siteurl'    => rtrim(base_url(), '/'),
            'template'           => $theme->activeParentSlug() ?? $theme->activeSlug() ?? '',
            'stylesheet'         => $theme->activeSlug() ?? '',
            'blog_charset'       => 'UTF-8',
            'permalink_structure'=> '/%postname%/',
            'show_on_front'      => 'posts',
            default              => $default,
        };
    }

    protected function loadAutoloaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        try {
            $rows = $this->table()->where('autoload', 'yes')->get()->getResultArray();
        } catch (DatabaseException $e) {
            log_message('error', 'WP compat: cannot read wp_options — run `php spark migrate`. (' . $e->getMessage() . ')');

            return;
        }

        foreach ($rows as $row) {
            $this->cache[$row['option_name']] = static::maybeUnserialize($row['option_value']);
        }
    }

    protected function table()
    {
        return Database::connect()->table('wp_options');
    }

    public static function maybeSerialize(mixed $value): ?string
    {
        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return $value === null ? null : (string) $value;
    }

    public static function maybeUnserialize(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! preg_match('/^[aOs]:\d+:/', $value) && $value !== 'b:0;' && $value !== 'b:1;' && ! preg_match('/^[bdi]:/', $value)) {
            return $value;
        }

        // Objects are only unserialized into classes the app knows about;
        // a third-party option value must never instantiate arbitrary code.
        $result = @unserialize($value, ['allowed_classes' => ['stdClass', 'WP_Post', 'WP_Term', 'WP_Error']]);

        return $result === false && $value !== 'b:0;' ? $value : $result;
    }
}
