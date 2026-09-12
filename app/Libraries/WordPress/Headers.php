<?php

namespace App\Libraries\WordPress;

/**
 * Reads the file-header block WordPress uses to describe themes
 * (style.css) and plugins (main PHP file). Same rules as WP: only the
 * first 8 KB is scanned, and a field may be commented out with * or #.
 */
class Headers
{
    public const THEME = [
        'Name'        => 'Theme Name',
        'ThemeURI'    => 'Theme URI',
        'Description' => 'Description',
        'Author'      => 'Author',
        'AuthorURI'   => 'Author URI',
        'Version'     => 'Version',
        'Template'    => 'Template',
        'Status'      => 'Status',
        'Tags'        => 'Tags',
        'TextDomain'  => 'Text Domain',
        'DomainPath'  => 'Domain Path',
        'RequiresWP'  => 'Requires at least',
        'RequiresPHP' => 'Requires PHP',
        'License'     => 'License',
    ];

    public const PLUGIN = [
        'Name'        => 'Plugin Name',
        'PluginURI'   => 'Plugin URI',
        'Version'     => 'Version',
        'Description' => 'Description',
        'Author'      => 'Author',
        'AuthorURI'   => 'Author URI',
        'TextDomain'  => 'Text Domain',
        'DomainPath'  => 'Domain Path',
        'Network'     => 'Network',
        'RequiresWP'  => 'Requires at least',
        'RequiresPHP' => 'Requires PHP',
        'License'     => 'License',
    ];

    /**
     * @param array<string, string> $fields key => header label
     *
     * @return array<string, string>
     */
    public static function parse(string $file, array $fields): array
    {
        $data = array_fill_keys(array_keys($fields), '');

        if (! is_file($file) || ! is_readable($file)) {
            return $data;
        }

        $handle = fopen($file, 'r');

        if ($handle === false) {
            return $data;
        }

        $contents = (string) fread($handle, 8192);
        fclose($handle);

        // Normalise line endings so the per-line regex below always matches.
        $contents = str_replace("\r", "\n", $contents);

        foreach ($fields as $key => $label) {
            $pattern = '/^[ \t\/*#@]*' . preg_quote($label, '/') . ':(.*)$/mi';

            if (preg_match($pattern, $contents, $match) === 1) {
                // Strip a trailing comment terminator from one-line blocks.
                $data[$key] = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]) ?? '');
            }
        }

        return $data;
    }
}
