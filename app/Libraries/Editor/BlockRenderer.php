<?php

namespace App\Libraries\Editor;

/**
 * Turns parsed block data into HTML (PRD §3.2.A "Core Blocks" + §6.2 sample).
 * Renderers are closures keyed by block type so a theme or plugin can
 * register/override one via registerBlock() without touching this class.
 */
class BlockRenderer
{
    /** @var array<string, callable> */
    protected array $blocks = [];

    public function __construct(protected BlockParser $parser = new BlockParser())
    {
        $this->registerDefaultBlocks();
    }

    public function registerBlock(string $type, callable $renderer): void
    {
        $this->blocks[$type] = $renderer;
    }

    /**
     * @param string $blocksJson raw JSON as saved by the block editor
     */
    public function render(string $blocksJson): string
    {
        $blocks = $this->parser->parse($blocksJson);
        $html   = '';

        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block);
        }

        return $html;
    }

    protected function renderBlock(array $block): string
    {
        $type    = $block['type'] ?? '';
        $attrs   = $block['attrs'] ?? [];
        $content = $block['content'] ?? '';

        if (isset($this->blocks[$type])) {
            return ($this->blocks[$type])($attrs, $content, $this);
        }

        return '<!-- Unknown block: ' . esc($type) . ' -->';
    }

    /**
     * Every attribute this class emits is inside double quotes, so plain
     * HTML escaping (which covers `"`, `&`, `<`, `>`, `'`) is enough and
     * keeps the output readable. The 'attr' context is for *unquoted*
     * attributes and would turn every space and slash into an entity —
     * legible to browsers, but not to crawlers or regex-based SEO tools
     * reading href/rel.
     */
    protected function attr(array $attrs, string $key, string $default = ''): string
    {
        return esc((string) ($attrs[$key] ?? $default));
    }

    /**
     * Builds ` href="…" target="…" rel="…"` for a block that carries a
     * link (currently the image block — a linked/backlinked image, the
     * same thing rich text gets from an inline <a>).
     *
     * Returns '' when there's no usable link, so callers can simply skip
     * the wrapper.
     */
    protected function linkAttributes(array $attrs): string
    {
        $href = $this->safeUrl((string) ($attrs['href'] ?? ''));

        if ($href === '') {
            return '';
        }

        $newTab = ($attrs['link_target'] ?? '') === '_blank';
        $rel    = preg_split('/\s+/', strtolower(trim((string) ($attrs['rel'] ?? ''))), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($newTab) {
            // Without these, the opened page can reach back through
            // window.opener and rewrite this one (tabnabbing).
            $rel = array_merge($rel, ['noopener', 'noreferrer']);
        }

        $rel = array_values(array_unique($rel));

        return ' href="' . esc($href) . '"'
            . ($newTab ? ' target="_blank"' : '')
            . ($rel !== [] ? ' rel="' . esc(implode(' ', $rel)) . '"' : '');
    }

    /**
     * Allows only http(s), mailto:, tel: and site-relative URLs. Block
     * content is authored by a trusted user, but a pasted `javascript:`
     * URL is exactly how that assumption gets broken — so this is
     * enforced at render time rather than trusting the editor's own check.
     */
    protected function safeUrl(string $url): string
    {
        $url = trim($url);

        return preg_match('#^(https?://|mailto:|tel:|/|\#|\./|\.\./)#i', $url) === 1 ? $url : '';
    }

    protected function registerDefaultBlocks(): void
    {
        $this->blocks['paragraph'] = fn ($attrs, $content) => '<p>' . $content . '</p>';

        $this->blocks['heading'] = function ($attrs, $content) {
            $level = max(1, min(6, (int) ($attrs['level'] ?? 2)));

            return "<h{$level}>{$content}</h{$level}>";
        };

        $this->blocks['image'] = function ($attrs) {
            $alt     = $this->attr($attrs, 'alt');
            $align   = $this->attr($attrs, 'align');
            $url     = $this->attr($attrs, 'url');
            $caption = (string) ($attrs['caption'] ?? '');
            $link    = $this->linkAttributes($attrs);

            // The alignment class rides on the outermost element, so a
            // float/centre still works once the <img> is wrapped in a
            // <figure> (caption) or an <a> (link).
            $outerClass = $align !== '' ? " class=\"{$align}\"" : '';

            $img = "<img src=\"{$url}\" alt=\"{$alt}\"" . ($caption === '' && $link === '' ? " class=\"{$align}\"" : '') . ' loading="lazy">';

            if ($link !== '') {
                $img = '<a' . $link . ($caption === '' ? $outerClass : '') . '>' . $img . '</a>';
            }

            if ($caption !== '') {
                return "<figure{$outerClass}>{$img}<figcaption>" . esc($caption) . '</figcaption></figure>';
            }

            return $img;
        };

        $this->blocks['gallery'] = function ($attrs) {
            $html = '<div class="lcms-gallery">';
            foreach ($attrs['images'] ?? [] as $image) {
                $url = esc((string) ($image['url'] ?? ''));
                $alt = esc((string) ($image['alt'] ?? ''));
                $html .= "<img src=\"{$url}\" alt=\"{$alt}\" loading=\"lazy\">";
            }

            return $html . '</div>';
        };

        $this->blocks['video'] = function ($attrs) {
            if (! empty($attrs['embed'])) {
                return '<div class="lcms-video-embed">' . $attrs['embed'] . '</div>';
            }

            $url = $this->attr($attrs, 'url');

            return "<video src=\"{$url}\" controls></video>";
        };

        $this->blocks['audio'] = function ($attrs) {
            $url = $this->attr($attrs, 'url');

            return "<audio src=\"{$url}\" controls></audio>";
        };

        $this->blocks['quote'] = function ($attrs, $content) {
            $cite = $attrs['cite'] ?? null;
            $html = "<blockquote>{$content}";
            $html .= $cite ? '<cite>' . esc($cite) . '</cite>' : '';

            return $html . '</blockquote>';
        };

        $this->blocks['code'] = function ($attrs, $content) {
            $language = $this->attr($attrs, 'language', 'plaintext');

            return "<pre><code class=\"language-{$language}\">" . htmlspecialchars($content) . '</code></pre>';
        };

        $this->blocks['table'] = function ($attrs) {
            $html = '<table class="lcms-table"><tbody>';
            foreach ($attrs['rows'] ?? [] as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . $cell . '</td>';
                }
                $html .= '</tr>';
            }

            return $html . '</tbody></table>';
        };

        $this->blocks['list'] = function ($attrs) {
            $tag   = ($attrs['ordered'] ?? false) ? 'ol' : 'ul';
            $items = array_map(static fn ($item) => '<li>' . $item . '</li>', $attrs['items'] ?? []);

            return "<{$tag}>" . implode('', $items) . "</{$tag}>";
        };

        $this->blocks['button'] = function ($attrs, $content) {
            $url   = $this->attr($attrs, 'url', '#');
            $class = $this->attr($attrs, 'style', 'primary');

            return "<a class=\"lcms-btn lcms-btn--{$class}\" href=\"{$url}\">{$content}</a>";
        };

        $this->blocks['separator'] = fn () => '<hr class="lcms-separator">';

        $this->blocks['spacer'] = function ($attrs) {
            $height = (int) ($attrs['height'] ?? 40);

            return "<div class=\"lcms-spacer\" style=\"height:{$height}px\"></div>";
        };

        $this->blocks['columns'] = function ($attrs, $content, $renderer) {
            $html = '<div class="lcms-columns">';
            foreach ($attrs['columns'] ?? [] as $column) {
                $html .= '<div class="lcms-column">';
                foreach ($column['blocks'] ?? [] as $childBlock) {
                    $html .= $renderer->renderBlock($childBlock);
                }
                $html .= '</div>';
            }

            return $html . '</div>';
        };

        $this->blocks['embed'] = function ($attrs) {
            $url = $this->attr($attrs, 'url');

            return "<div class=\"lcms-embed\" data-embed-url=\"{$url}\"></div>";
        };

        // Custom HTML — trusted author markup, emitted verbatim (see the
        // trust note on BlockParser). Mirrors Gutenberg's "Custom HTML".
        $this->blocks['html'] = fn ($attrs, $content) => $content;

        // --- A couple of the "Advanced Blocks" (PRD §3.2.A.2) -------------
        $this->blocks['accordion'] = function ($attrs) {
            $html = '<div class="lcms-accordion">';
            foreach ($attrs['items'] ?? [] as $item) {
                $title = esc((string) ($item['title'] ?? ''));
                $body  = $item['content'] ?? '';
                $html .= "<details class=\"lcms-accordion__item\"><summary>{$title}</summary><div>{$body}</div></details>";
            }

            return $html . '</div>';
        };

        $this->blocks['cta'] = function ($attrs) {
            $title   = esc((string) ($attrs['title'] ?? ''));
            $text    = esc((string) ($attrs['text'] ?? ''));
            $url     = $this->attr($attrs, 'url', '#');
            $button  = esc((string) ($attrs['button_label'] ?? 'Learn more'));

            return "<div class=\"lcms-cta\"><h3>{$title}</h3><p>{$text}</p>"
                . "<a class=\"lcms-btn lcms-btn--primary\" href=\"{$url}\">{$button}</a></div>";
        };
    }
}
