<?php

namespace App\Commands;

use App\Libraries\WordPress\Headers;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `php spark wp:doctor [theme|plugin] [slug]`
 *
 * Static compatibility check for a WordPress theme or plugin: parses every
 * PHP file, collects the functions and classes it calls, and reports the
 * ones the LightCMS compatibility layer does not define — i.e. the calls
 * that would be fatal at runtime.
 *
 * It is deliberately conservative: a name it cannot resolve statically
 * (variable functions, call_user_func with a computed name) is not
 * reported, so a clean report means "nothing obviously missing", not
 * "guaranteed to work".
 */
class WpDoctorCommand extends BaseCommand
{
    protected $group       = 'LightCMS';
    protected $name        = 'wp:doctor';
    protected $description = 'Check a WordPress theme or plugin against the LightCMS compatibility layer.';
    protected $usage       = 'wp:doctor [theme|plugin|path|all] [slug|/absolute/path]';
    protected $arguments   = [
        'theme|plugin|all' => 'What to check in public/wp-content (default: all).',
        'slug'             => 'Optional: a single theme slug or plugin file to check.',
        'path'             => 'Use "path /absolute/dir" to check a theme or plugin that is not installed yet.',
    ];

    /** Calls every PHP file makes that we should ignore (language constructs). */
    private const IGNORED = [
        'array', 'echo', 'print', 'isset', 'unset', 'empty', 'list', 'exit', 'die', 'include',
        'include_once', 'require', 'require_once', 'eval', 'compact', 'extract', 'fn', 'function',
        'match', 'if', 'elseif', 'else', 'while', 'for', 'foreach', 'switch', 'return', 'catch', 'use',
    ];

    public function run(array $params): int
    {
        $runtime = lcms_wp_boot('core');
        $what    = $params[0] ?? 'all';
        $slug    = $params[1] ?? null;

        $targets = [];

        // `wp:doctor path /some/theme` checks a directory in place — handy
        // for auditing a theme before copying it into wp-content.
        if ($what === 'path') {
            $path = $slug !== null ? rtrim($slug, '/') : '';

            if ($path === '' || ! is_dir($path)) {
                CLI::error('Give an absolute directory: php spark wp:doctor path /path/to/theme');

                return EXIT_ERROR;
            }

            $isTheme = is_file($path . '/style.css');

            $targets[] = [
                'type' => $isTheme ? 'theme' : 'plugin',
                'name' => $isTheme
                    ? (Headers::parse($path . '/style.css', Headers::THEME)['Name'] ?: basename($path))
                    : basename($path),
                'slug' => $path,
                'path' => $path,
            ];
        }

        if ($what === 'theme' || $what === 'all') {
            foreach ($runtime->themes()->available() as $themeSlug => $theme) {
                if ($slug === null || $slug === $themeSlug) {
                    $targets[] = ['type' => 'theme', 'name' => $theme['Name'], 'slug' => $themeSlug, 'path' => $theme['path']];
                }
            }
        }

        if ($what === 'plugin' || $what === 'all') {
            foreach ($runtime->plugins()->available() as $file => $plugin) {
                if ($slug === null || $slug === $file || $slug === dirname($file)) {
                    $targets[] = ['type' => 'plugin', 'name' => $plugin['Name'], 'slug' => $file, 'path' => $plugin['dir']];
                }
            }
        }

        if ($targets === []) {
            CLI::error('Nothing to check. Put themes in public/wp-content/themes and plugins in public/wp-content/plugins.');

            return EXIT_ERROR;
        }

        $worst = 0;

        foreach ($targets as $target) {
            $worst = max($worst, $this->report($target));
        }

        return $worst > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }

    private function report(array $target): int
    {
        CLI::write('');
        CLI::write(CLI::color(strtoupper($target['type']) . ': ' . $target['name'] . ' (' . $target['slug'] . ')', 'yellow'));

        $files = $this->phpFiles($target['path']);

        if ($files === []) {
            CLI::write('  No PHP files found.', 'red');

            return 1;
        }

        [$calls, $classes, $defined, $definedClasses] = $this->collect($files);

        $missingFunctions = [];

        foreach ($calls as $name => $count) {
            if (function_exists($name) || isset($defined[$name]) || in_array($name, self::IGNORED, true)) {
                continue;
            }

            $missingFunctions[$name] = $count;
        }

        $missingClasses = [];

        foreach ($classes as $name => $count) {
            if (class_exists($name) || interface_exists($name) || isset($definedClasses[$name])) {
                continue;
            }

            $missingClasses[$name] = $count;
        }

        arsort($missingFunctions);
        arsort($missingClasses);

        CLI::write('  Files scanned: ' . count($files) . ', distinct function calls: ' . count($calls));

        foreach ($this->structuralNotes($target) as $note) {
            CLI::write('  ' . CLI::color('note: ', 'blue') . $note);
        }

        if ($missingFunctions === [] && $missingClasses === []) {
            CLI::write('  ' . CLI::color('OK', 'green') . ' — every function and class it calls exists in the compatibility layer.');

            return 0;
        }

        if ($missingFunctions !== []) {
            CLI::write('  ' . CLI::color('Missing functions (' . count($missingFunctions) . '):', 'red'));

            foreach ($missingFunctions as $name => $count) {
                CLI::write('    - ' . $name . '()  ×' . $count);
            }
        }

        if ($missingClasses !== []) {
            CLI::write('  ' . CLI::color('Missing classes (' . count($missingClasses) . '):', 'red'));

            foreach ($missingClasses as $name => $count) {
                CLI::write('    - ' . $name . '  ×' . $count);
            }
        }

        CLI::write('  Each missing name is a fatal error if that code path runs. Either avoid the feature,');
        CLI::write('  or add the function to app/Libraries/WordPress/api/ and re-run this check.');

        return 1;
    }

    /** @return list<string> */
    private function phpFiles(string $path): array
    {
        if (! is_dir($path)) {
            return is_file($path) ? [$path] : [];
        }

        $files    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php'
                && ! str_contains($file->getPathname(), '/node_modules/')
                && ! str_contains($file->getPathname(), '/vendor/')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return array{0: array<string,int>, 1: array<string,int>, 2: array<string,bool>, 3: array<string,bool>}
     */
    private function collect(array $files): array
    {
        $calls          = [];
        $classes        = [];
        $defined        = [];
        $definedClasses = [];

        foreach ($files as $file) {
            $code   = (string) @file_get_contents($file);
            $tokens = @token_get_all($code);

            if ($tokens === false) {
                continue;
            }

            $count = count($tokens);

            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];

                if (! is_array($token)) {
                    continue;
                }

                // function foo() / class Foo — definitions inside the package
                if ($token[0] === T_FUNCTION) {
                    $next = $this->nextMeaningful($tokens, $i);

                    if ($next !== null && is_array($next) && $next[0] === T_STRING) {
                        $defined[strtolower($next[1])] = true;
                    }

                    continue;
                }

                if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                    $next = $this->nextMeaningful($tokens, $i);

                    if ($next !== null && is_array($next) && $next[0] === T_STRING) {
                        $definedClasses[$next[1]] = true;
                    }

                    continue;
                }

                if ($token[0] === T_NEW || $token[0] === T_EXTENDS || $token[0] === T_IMPLEMENTS) {
                    $next = $this->nextMeaningful($tokens, $i);

                    if ($next !== null && is_array($next) && $next[0] === T_STRING) {
                        $classes[$next[1]] = ($classes[$next[1]] ?? 0) + 1;
                    }

                    continue;
                }

                if ($token[0] !== T_STRING) {
                    continue;
                }

                // A call is T_STRING followed by "(" and not preceded by
                // -> :: function new $ (method calls / definitions).
                $previous = $this->previousMeaningful($tokens, $i);

                if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_NULLSAFE_OBJECT_OPERATOR, T_CLASS, T_STRING], true)) {
                    continue;
                }

                $next = $this->nextMeaningful($tokens, $i);

                if ($next === '(') {
                    $name = strtolower($token[1]);
                    $calls[$name] = ($calls[$name] ?? 0) + 1;
                }
            }
        }

        return [$calls, $classes, $defined, $definedClasses];
    }

    private function nextMeaningful(array $tokens, int $index)
    {
        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }

    private function previousMeaningful(array $tokens, int $index)
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }

    /** Feature-level warnings that a name-by-name scan cannot see. */
    private function structuralNotes(array $target): array
    {
        $notes = [];

        if ($target['type'] === 'theme') {
            if (is_file($target['path'] . '/theme.json')) {
                $notes[] = 'theme.json present: block/FSE features (global styles, block templates) are NOT emulated.';
            }

            if (is_dir($target['path'] . '/templates') || is_dir($target['path'] . '/parts')) {
                $notes[] = 'templates/ or parts/ directory found: this looks like a block theme, which the layer does not render.';
            }

            if (! is_file($target['path'] . '/index.php')) {
                $notes[] = 'No index.php: the template hierarchy has no final fallback.';
            }

            $header = Headers::parse($target['path'] . '/style.css', Headers::THEME);

            if (($header['Template'] ?? '') !== '' && ! is_dir(dirname($target['path']) . '/' . $header['Template'])) {
                $notes[] = "Parent theme '{$header['Template']}' is not installed.";
            }
        }

        foreach (['woocommerce', 'elementor', 'acf_', 'get_field(', 'vc_map'] as $needle) {
            if ($this->grep($target['path'], $needle)) {
                $notes[] = "References '{$needle}' — that ecosystem plugin is not part of the compatibility layer; install it too, or expect that code path to fail.";
            }
        }

        return $notes;
    }

    private function grep(string $path, string $needle): bool
    {
        foreach (array_slice($this->phpFiles($path), 0, 400) as $file) {
            if (str_contains((string) @file_get_contents($file), $needle)) {
                return true;
            }
        }

        return false;
    }
}
