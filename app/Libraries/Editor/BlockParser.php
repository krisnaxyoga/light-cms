<?php

namespace App\Libraries\Editor;

/**
 * Decodes and normalizes the JSON payload saved by the block editor
 * (PRD §3.2.A). Never throws on malformed input — a broken autosave
 * blob should degrade to an empty post body, not a 500 page.
 *
 * NOTE: block "content" is trusted HTML written by an authenticated
 * author through the editor UI, not sanitized here. If LightCMS ever
 * allows lower-trust roles (e.g. Contributor) to submit raw block JSON
 * directly to the API, run it through an HTML purifier before storage.
 */
class BlockParser
{
    public function parse(string|array $blocksData): array
    {
        $blocks = is_array($blocksData) ? $blocksData : json_decode($blocksData, true);

        if (! is_array($blocks)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($block) => $this->normalize($block),
            $blocks
        )));
    }

    protected function normalize(mixed $block): ?array
    {
        if (! is_array($block) || empty($block['type']) || ! is_string($block['type'])) {
            return null;
        }

        return [
            'type'    => $block['type'],
            'attrs'   => is_array($block['attrs'] ?? null) ? $block['attrs'] : [],
            'content' => is_string($block['content'] ?? null) ? $block['content'] : '',
        ];
    }

    /**
     * Serialize normalized blocks back to the JSON stored on posts.content.
     */
    public function serialize(array $blocks): string
    {
        return json_encode(array_values($blocks), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Flatten all blocks' plain-text content — used by the SEO analyzer's
     * word count and by search indexing.
     */
    public function toPlainText(array $blocks): string
    {
        $text = [];

        foreach ($blocks as $block) {
            if (($block['content'] ?? '') !== '') {
                $text[] = strip_tags($block['content']);
            }
        }

        return implode(' ', $text);
    }
}
