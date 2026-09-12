<?php

namespace App\Libraries\WordPress;

/**
 * wp_enqueue_style()/wp_enqueue_script() and the printing side of
 * wp_head()/wp_footer(). Dependencies are resolved topologically, so a
 * script that depends on 'jquery' is printed after it, and registered
 * handles are only printed once they are actually enqueued.
 */
class Assets
{
    /** @var array<string, array<string, mixed>> */
    protected array $styles = [];
    protected array $scripts = [];

    /** @var list<string> */
    protected array $enqueuedStyles = [];
    protected array $enqueuedScripts = [];

    /** @var list<string> */
    protected array $doneStyles = [];
    protected array $doneScripts = [];

    /** Handles bundled with the compat layer (WP ships these in wp-includes). */
    protected array $coreScripts = [
        'jquery'            => ['src' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js', 'deps' => [], 'ver' => '3.7.1'],
        'jquery-core'       => ['src' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js', 'deps' => [], 'ver' => '3.7.1'],
        'jquery-migrate'    => ['src' => '', 'deps' => [], 'ver' => '3.4.1'],
    ];

    public function registerStyle(string $handle, string|false $src, array $deps = [], mixed $ver = false, string $media = 'all'): void
    {
        $this->styles[$handle] = [
            'src' => $src, 'deps' => $deps, 'ver' => $ver, 'media' => $media,
            'inline_after' => [], 'inline_before' => [],
        ];
    }

    public function registerScript(string $handle, string|false $src, array $deps = [], mixed $ver = false, bool|array $args = false): void
    {
        $inFooter = is_array($args) ? (bool) ($args['in_footer'] ?? false) : (bool) $args;
        $strategy = is_array($args) ? (string) ($args['strategy'] ?? '') : '';

        $this->scripts[$handle] = [
            'src' => $src, 'deps' => $deps, 'ver' => $ver,
            'in_footer' => $inFooter, 'strategy' => $strategy,
            'l10n' => [], 'inline_after' => [], 'inline_before' => [],
        ];
    }

    public function enqueueStyle(string $handle, string|false $src = '', array $deps = [], mixed $ver = false, string $media = 'all'): void
    {
        if ($src !== '' && $src !== false) {
            $this->registerStyle($handle, $src, $deps, $ver, $media);
        }

        if (! isset($this->styles[$handle])) {
            $this->registerStyle($handle, false, $deps, $ver, $media);
        }

        if (! in_array($handle, $this->enqueuedStyles, true)) {
            $this->enqueuedStyles[] = $handle;
        }
    }

    public function enqueueScript(string $handle, string|false $src = '', array $deps = [], mixed $ver = false, bool|array $args = false): void
    {
        if ($src !== '' && $src !== false) {
            $this->registerScript($handle, $src, $deps, $ver, $args);
        }

        if (! isset($this->scripts[$handle])) {
            if (isset($this->coreScripts[$handle])) {
                $core = $this->coreScripts[$handle];
                $this->registerScript($handle, $core['src'], $core['deps'], $core['ver'], $args);
            } else {
                $this->registerScript($handle, false, $deps, $ver, $args);
            }
        }

        if (! in_array($handle, $this->enqueuedScripts, true)) {
            $this->enqueuedScripts[] = $handle;
        }
    }

    public function dequeueStyle(string $handle): void
    {
        $this->enqueuedStyles = array_values(array_diff($this->enqueuedStyles, [$handle]));
    }

    public function dequeueScript(string $handle): void
    {
        $this->enqueuedScripts = array_values(array_diff($this->enqueuedScripts, [$handle]));
    }

    public function deregisterStyle(string $handle): void
    {
        unset($this->styles[$handle]);
        $this->dequeueStyle($handle);
    }

    public function deregisterScript(string $handle): void
    {
        unset($this->scripts[$handle]);
        $this->dequeueScript($handle);
    }

    public function localize(string $handle, string $objectName, array $data): bool
    {
        if (! isset($this->scripts[$handle])) {
            return false;
        }

        $this->scripts[$handle]['l10n'][$objectName] = $data;

        return true;
    }

    public function addInlineScript(string $handle, string $code, string $position = 'after'): bool
    {
        if (! isset($this->scripts[$handle])) {
            return false;
        }

        $this->scripts[$handle]['inline_' . ($position === 'before' ? 'before' : 'after')][] = $code;

        return true;
    }

    public function addInlineStyle(string $handle, string $code): bool
    {
        if (! isset($this->styles[$handle])) {
            return false;
        }

        $this->styles[$handle]['inline_after'][] = $code;

        return true;
    }

    public function isEnqueued(string $handle, string $type = 'script', string $list = 'enqueued'): bool
    {
        $enqueued  = $type === 'style' ? $this->enqueuedStyles : $this->enqueuedScripts;
        $registered = $type === 'style' ? $this->styles : $this->scripts;
        $done      = $type === 'style' ? $this->doneStyles : $this->doneScripts;

        return match ($list) {
            'registered' => isset($registered[$handle]),
            'done'       => in_array($handle, $done, true),
            default      => in_array($handle, $enqueued, true),
        };
    }

    /** Styles are all printed in the head, like WP's default behaviour. */
    public function printStyles(): string
    {
        $html = [];

        foreach ($this->resolve($this->enqueuedStyles, $this->styles, $this->doneStyles) as $handle) {
            $style = $this->styles[$handle];
            $this->doneStyles[] = $handle;

            if ($style['src'] !== false && $style['src'] !== '') {
                $html[] = sprintf(
                    '<link rel="stylesheet" id="%s-css" href="%s" media="%s">',
                    esc_attr($handle),
                    esc_url($this->url($style['src'], $style['ver'])),
                    esc_attr($style['media'] ?: 'all')
                );
            }

            foreach ($style['inline_after'] as $code) {
                $html[] = "<style id=\"" . esc_attr($handle) . "-inline-css\">\n{$code}\n</style>";
            }
        }

        return implode("\n", $html);
    }

    /** @param bool $footer Print the footer group (in_footer scripts) or the head group. */
    public function printScripts(bool $footer): string
    {
        $html    = [];
        $pending = array_values(array_filter(
            $this->enqueuedScripts,
            fn (string $handle) => (bool) ($this->scripts[$handle]['in_footer'] ?? false) === $footer
        ));

        // A head script's dependency must print in the head too, even if
        // it was registered with in_footer.
        foreach ($this->resolve($pending, $this->scripts, $this->doneScripts) as $handle) {
            $script = $this->scripts[$handle];
            $this->doneScripts[] = $handle;

            foreach ($script['l10n'] as $objectName => $data) {
                $html[] = '<script id="' . esc_attr($handle) . '-js-extra">var '
                    . preg_replace('/[^A-Za-z0-9_$]/', '', $objectName) . ' = '
                    . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
            }

            foreach ($script['inline_before'] as $code) {
                $html[] = '<script id="' . esc_attr($handle) . '-js-before">' . $code . '</script>';
            }

            if ($script['src'] !== false && $script['src'] !== '') {
                $attr = match ($script['strategy']) {
                    'defer' => ' defer',
                    'async' => ' async',
                    default => '',
                };

                $html[] = sprintf(
                    '<script src="%s" id="%s-js"%s></script>',
                    esc_url($this->url($script['src'], $script['ver'])),
                    esc_attr($handle),
                    $attr
                );
            }

            foreach ($script['inline_after'] as $code) {
                $html[] = '<script id="' . esc_attr($handle) . '-js-after">' . $code . '</script>';
            }
        }

        return implode("\n", $html);
    }

    /**
     * Depth-first dependency walk. Handles already printed are skipped;
     * a missing dependency is ignored rather than fatal (WP does the same).
     *
     * @return list<string>
     */
    protected function resolve(array $handles, array $registry, array $done): array
    {
        $order   = [];
        $visited = [];

        $walk = function (string $handle) use (&$walk, &$order, &$visited, $registry, $done): void {
            if (isset($visited[$handle]) || in_array($handle, $done, true) || ! isset($registry[$handle])) {
                return;
            }

            $visited[$handle] = true;

            foreach ($registry[$handle]['deps'] ?? [] as $dep) {
                $walk((string) $dep);
            }

            $order[] = $handle;
        };

        foreach ($handles as $handle) {
            $walk($handle);
        }

        return $order;
    }

    protected function url(string $src, mixed $ver): string
    {
        if (str_starts_with($src, '//') || preg_match('#^https?://#i', $src) === 1) {
            $url = $src;
        } elseif (str_starts_with($src, '/')) {
            $url = rtrim(base_url(), '/') . $src;
        } else {
            $url = base_url($src);
        }

        if ($ver === null || $ver === false || $ver === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'ver=' . rawurlencode((string) $ver);
    }
}
