<?php

namespace App\Libraries\WordPress;

/**
 * Minimal gettext .mo reader, so load_theme_textdomain() actually
 * translates instead of echoing the source string. Handles the standard
 * (little- and big-endian) binary layout including plural forms, which is
 * all a theme's language pack contains.
 */
class MoFile
{
    /** @var array<string, string|list<string>> */
    protected array $entries = [];

    public static function load(string $file): ?self
    {
        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $data = file_get_contents($file);

        if ($data === false || strlen($data) < 24) {
            return null;
        }

        $magic = substr($data, 0, 4);

        if ($magic === "\xde\x12\x04\x95") {
            $format = 'V'; // little endian
        } elseif ($magic === "\x95\x04\x12\xde") {
            $format = 'N'; // big endian
        } else {
            return null;
        }

        $header = unpack($format . 'revision/' . $format . 'count/' . $format . 'originals/' . $format . 'translations', substr($data, 4, 16));

        if ($header === false) {
            return null;
        }

        $mo = new self();

        for ($i = 0; $i < $header['count']; $i++) {
            $originalMeta    = unpack($format . 'length/' . $format . 'offset', substr($data, $header['originals'] + $i * 8, 8));
            $translationMeta = unpack($format . 'length/' . $format . 'offset', substr($data, $header['translations'] + $i * 8, 8));

            if ($originalMeta === false || $translationMeta === false) {
                continue;
            }

            $original    = substr($data, $originalMeta['offset'], $originalMeta['length']);
            $translation = substr($data, $translationMeta['offset'], $translationMeta['length']);

            if ($original === '') {
                continue; // metadata entry
            }

            $mo->entries[$original] = str_contains($translation, "\0") ? explode("\0", $translation) : $translation;
        }

        return $mo;
    }

    public function translate(string $text, string $context = ''): ?string
    {
        $key = $context === '' ? $text : $context . "\x04" . $text;

        if (! isset($this->entries[$key])) {
            return null;
        }

        $entry = $this->entries[$key];

        return is_array($entry) ? ($entry[0] ?? null) : $entry;
    }

    public function translatePlural(string $single, string $plural, int $number, string $context = ''): ?string
    {
        $key   = ($context === '' ? '' : $context . "\x04") . $single . "\0" . $plural;
        $entry = $this->entries[$key] ?? $this->entries[($context === '' ? '' : $context . "\x04") . $single] ?? null;

        if ($entry === null) {
            return null;
        }

        if (! is_array($entry)) {
            return $entry;
        }

        // Only the common "n != 1" rule is applied; languages with richer
        // plural rules fall back to the second form.
        return $number === 1 ? ($entry[0] ?? null) : ($entry[1] ?? $entry[0] ?? null);
    }
}
