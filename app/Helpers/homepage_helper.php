<?php

use App\Libraries\Homepage\HomepageContent;

if (! function_exists('homepage_content')) {
    /**
     * Admin-editable front-page / blog / single-post CTA copy, images and
     * section toggles (Admin -> Homepage). Merged over the shipped
     * defaults so every key in HomepageContent::schema() is always present.
     *
     * Pass a dotted path to read one value: homepage_content('hero.title').
     */
    function homepage_content(?string $path = null, mixed $default = null): mixed
    {
        $content = HomepageContent::get();

        if ($path === null) {
            return $content;
        }

        $node = $content;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return $default;
            }

            $node = $node[$segment];
        }

        return $node;
    }
}

if (! function_exists('homepage_bold_prefix')) {
    /**
     * "Label: rest of sentence" -> "<strong>Label:</strong> rest of sentence"
     * (escaped). Lets a plain one-per-line textarea carry the bolded
     * lead-in the notice/tip boxes use, with no markup typed by the admin.
     */
    function homepage_bold_prefix(string $line): string
    {
        if (preg_match('/^([^:]{2,40}):\s*(.+)$/u', $line, $m)) {
            return '<strong>' . esc($m[1]) . ':</strong> ' . esc($m[2]);
        }

        return esc($line);
    }
}
