<?php

/**
 * Super Travel theme bootstrap. Loaded once per request by ThemeEngine —
 * everything here runs before any layout/template output, so only
 * register things; don't echo.
 */

use App\Libraries\Theme\Theme;

Theme::registerMenus([
    'primary' => 'Primary Menu',
    'footer'  => 'Footer Menu',
]);

// The footer is laid out as N columns, one per top-level "footer" menu
// item, with that item's children as the links inside the column (see
// "Footer" in layouts/footer.php). No separate widget areas are needed
// for that, so only the brand-side widget area is registered.
Theme::registerWidgetArea('footer_1', ['name' => 'Footer Column 1']);

Theme::addSupport('custom-logo');
Theme::addSupport('post-thumbnails');

Theme::enqueueStyle('super-travel', 'assets/css/style.css');
Theme::enqueueScript('super-travel', 'assets/js/main.js');

if (! function_exists('theme_get_related_posts')) {
    /**
     * Same-category related posts for single.php, most recent first.
     */
    function theme_get_related_posts(array $post, int $limit = 3): array
    {
        $categoryIds = array_column($post['categories'] ?? [], 'id');

        if ($categoryIds === []) {
            return [];
        }

        return \Config\Database::connect()->table('posts p')
            ->select('p.id, p.title, p.slug, p.post_type, p.locale, p.featured_image, p.published_at')
            ->distinct()
            ->join('post_categories pc', 'pc.post_id = p.id')
            ->whereIn('pc.category_id', $categoryIds)
            ->where('p.id !=', $post['id'])
            ->where('p.locale', $post['locale'] ?? current_locale())
            ->where('p.post_type', 'post')
            ->where('p.status', 'published')
            ->orderBy('p.published_at', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }
}

if (! function_exists('st_icon')) {
    /**
     * Outputs a Tabler Icons <i> tag (webfont loaded in layouts/header.php)
     * instead of an emoji character — keeps glyphs crisp at any size,
     * themeable via currentColor, and screen-reader silent by default.
     * Icon names: https://tabler.io/icons (kebab-case, no "ti-" prefix here).
     */
    function st_icon(string $name, string $extraClass = ''): string
    {
        $class = 'ti ti-' . $name . ($extraClass !== '' ? ' ' . $extraClass : '');

        return '<i class="' . esc($class, 'attr') . '" aria-hidden="true"></i>';
    }
}

if (! function_exists('st_primary_menu')) {
    /**
     * Primary nav items — real "primary" menu from Admin -> Menus when one
     * has been configured, otherwise a sensible default (Home / Blog /
     * Service / Booking) so the header is never empty out of the box.
     * Once an admin builds a real "Primary Menu", it takes over automatically.
     */
    function st_primary_menu(): array
    {
        $configured = theme_menu('primary');

        if ($configured !== []) {
            return $configured;
        }

        return [
            ['title' => 'Home', 'url' => locale_url(), 'children' => []],
            ['title' => 'Blog', 'url' => locale_url('blog'), 'children' => []],
            ['title' => 'Service', 'url' => locale_url() . '#why-us', 'children' => []],
            ['title' => 'Booking', 'url' => locale_url() . '#pricing', 'children' => []],
        ];
    }
}

if (! function_exists('st_wa_link')) {
    /**
     * Appends a pre-filled Spanish inquiry message to a generic "chat with
     * us" WhatsApp link (floating button, footer phone line, final-CTA
     * WhatsApp buttons) so tapping it opens the chat with the message
     * already typed in, rather than a blank conversation.
     *
     * Deliberately NOT used on the pricing package buttons in home.php —
     * those already carry a message naming the specific package
     * ("Quiero reservar Group Saver"), which is more useful than a
     * generic one and must not be overwritten.
     *
     * Safe to call on any URL: non-WhatsApp links pass through unchanged,
     * and a link that already has its own ?text=... (an admin who
     * customized theme.json/Admin -> Homepage) is left alone too.
     */
    function st_wa_link(string $url, ?string $message = null): string
    {
        if ($url === '' || (! str_contains($url, 'wa.me/') && ! str_contains($url, 'api.whatsapp.com/'))) {
            return $url;
        }

        if (str_contains($url, 'text=')) {
            return $url;
        }

        $message ??= theme_option(
            'whatsapp_message',
            "Hola Snorkel Penida, estoy interesado/a en tus tours de snorkel. ¿Me podr\u{00ed}as dar m\u{00e1}s informaci\u{00f3}n?"
        );

        $glue = str_contains($url, '?') ? '&' : '?';

        return $url . $glue . 'text=' . rawurlencode($message);
    }
}

if (! function_exists('st_breadcrumb_trail')) {
    /**
     * Home -> [Blog ->] Title for a post, or Home -> [pillar page ->]
     * Title for a page. There's no page-hierarchy field in this CMS
     * (PostModel has no parent_id), so every SEO landing page nests one
     * level under the "Snorkeling en Nusa Penida" pillar page rather than
     * needing a hand-maintained per-slug map — the pillar itself, and any
     * future page, just falls back to sitting directly under Home.
     *
     * @return array<int, array{name: string, url: string}> Home first, current page last.
     */
    function st_breadcrumb_trail(array $post): array
    {
        $home  = ['name' => 'Inicio', 'url' => locale_url()];
        $trail = [$home];

        if (($post['post_type'] ?? 'post') === 'post') {
            $trail[] = ['name' => 'Blog', 'url' => locale_url('blog')];
        } elseif (($post['slug'] ?? '') !== 'snorkeling-nusa-penida') {
            $trail[] = ['name' => 'Snorkeling en Nusa Penida', 'url' => locale_url('snorkeling-nusa-penida')];
        }

        $trail[] = ['name' => $post['title'] ?? '', 'url' => post_url($post)];

        return $trail;
    }
}

if (! function_exists('st_split_hero_title')) {
    /**
     * Every page title in this theme's content ends in "Nusa Penida" —
     * splitting it out as its own accent line (see .lcms-page-hero__title
     * .lcms-outline) reads as a real two-part headline ("Snorkeling en
     * Manta Bay" / "NUSA PENIDA") instead of just repeating the same
     * words under themselves. Falls back to showing the outline line as
     * a plain "Nusa Penida" stamp under the full title for anything that
     * doesn't already end that way.
     *
     * @return array{0: string, 1: string} [main line, outlined line]
     */
    function st_split_hero_title(string $title): array
    {
        if (preg_match('/^(.*?)[\s,]+(?:en\s+)?Nusa Penida$/iu', trim($title), $matches) && trim($matches[1]) !== '') {
            return [trim($matches[1]), 'Nusa Penida'];
        }

        return [$title, 'Nusa Penida'];
    }
}

if (! function_exists('st_render_bento')) {
    /**
     * Renders a Page's block content as a bento grid instead of one long
     * flowing article — the deliberate visual split from blog Posts, which
     * keep the classic top-to-bottom read in single.php untouched. Content
     * before the first heading/CTA stays a normal lead paragraph under the
     * title; everything from there on becomes a grid of cards, each sized
     * by what it actually holds (see st_bento_cell_class()) rather than a
     * fixed rhythm, with grid-auto-flow:dense in the CSS backfilling the
     * gaps a wide card leaves.
     */
    function st_render_bento(string $blocksJson): string
    {
        $blocks = (new \App\Libraries\Editor\BlockParser())->parse($blocksJson);
        [$lead, $sections] = st_split_bento_sections($blocks);

        $html = '';

        if ($lead !== []) {
            $html .= '<div class="st-prose lcms-page-lead st-reveal">' . st_render_blocks($lead) . '</div>';
        }

        if ($sections === []) {
            return $html;
        }

        $html .= '<div class="lcms-bento">';

        foreach ($sections as $section) {
            $variant = st_bento_cell_class($section);
            $class   = trim('lcms-bento__cell ' . $variant);
            $inner   = $variant === 'lcms-bento__cell--gallery'
                ? st_render_gallery_swiper($section)
                : st_render_blocks($section);

            $html .= '<div class="' . esc($class, 'attr') . ' st-reveal"><div class="st-prose">' . $inner . '</div></div>';
        }

        return $html . '</div>';
    }
}

if (! function_exists('st_split_bento_sections')) {
    /**
     * Groups a flat block list into [$leadBlocks, $sections]: a new card
     * starts at every heading (any level — an h3 FAQ-style question gets
     * its own small card same as an h2 section) and every CTA or Quote
     * block always closes out on its own, so neither one ever gets buried
     * inside a text-heavy card or absorbs whatever content happens to
     * follow it. (A Quote block used to fall through to "just another
     * block in the current section" — on a page ending FAQ-pair -> Quote
     * -> CTA, that silently merged the last FAQ answer and the quote into
     * one card, styled entirely as the quote's dark-on-primary-blue
     * treatment; the FAQ's own link text then rendered in the default
     * link blue on that same blue background — invisible.)
     *
     * A section left holding only a heading (typically two headings back
     * to back — an H2 section title immediately followed by the next
     * H3) reads as an empty, awkward card once rendered, so it's folded
     * into whichever neighbouring section it introduces: forward when
     * one follows, otherwise backward into the previous card instead of
     * standing alone.
     *
     * @return array{0: array, 1: array<int, array>}
     */
    function st_split_bento_sections(array $blocks): array
    {
        $lead     = [];
        $sections = [];
        $current  = null;

        foreach ($blocks as $block) {
            if ($block['type'] === 'cta' || $block['type'] === 'quote') {
                if ($current !== null) {
                    $sections[] = $current;
                    $current    = null;
                }
                $sections[] = [$block];
                continue;
            }

            if ($block['type'] === 'heading') {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $current = [$block];
                continue;
            }

            if ($current === null) {
                $lead[] = $block;
            } else {
                $current[] = $block;
            }
        }

        if ($current !== null) {
            $sections[] = $current;
        }

        return [$lead, st_merge_orphan_headings($sections)];
    }
}

if (! function_exists('st_merge_orphan_headings')) {
    /**
     * A section holding nothing but a lone heading — no paragraph, list,
     * image, anything — merges into the next section (it reads as that
     * section's own title) or, failing that, the previous one, rather
     * than rendering as its own near-empty card. CTA/Quote sections are
     * always exactly one block already and never headings, so they're
     * untouched by this either way.
     *
     * @param array<int, array> $sections
     * @return array<int, array>
     */
    function st_merge_orphan_headings(array $sections): array
    {
        // Single forward pass: fold any orphan heading into the section
        // right after it; if it's the very last section, fold it into
        // the one right before instead.
        $result = [];

        for ($i = 0; $i < count($sections); $i++) {
            $section = $sections[$i];
            $isOrphanHeading = count($section) === 1 && $section[0]['type'] === 'heading';

            if (! $isOrphanHeading) {
                $result[] = $section;
                continue;
            }

            if ($i + 1 < count($sections)) {
                $sections[$i + 1] = array_merge($section, $sections[$i + 1]);
                continue;
            }

            if ($result !== []) {
                $result[count($result) - 1] = array_merge($result[count($result) - 1], $section);
            } else {
                $result[] = $section;
            }
        }

        return $result;
    }
}

if (! function_exists('st_bento_cell_class')) {
    /**
     * Card size/style follows content, not decoration — see the matching
     * .lcms-bento__cell--* rules in style.css for what each one looks
     * like. Checked in this order because a section can hold more than
     * one qualifying block (e.g. a heading + an image + a paragraph
     * should read as --feature, not just "has a heading"):
     *
     *  1. cta                              -> accent card, own row
     *  2. quote                            -> large pull-quote card
     *  3. a Gallery block (2+ photos), with or without text alongside
     *                                      -> full-width Swiper card
     *  4. a single image + text alongside  -> two-column photo+text card
     *  5. a single image alone             -> hover-zoom gallery card
     *  6. table, embedded HTML, or 4+ blocks -> wide card
     *  7. a list (ul/ol)                   -> "capabilities" list card
     *  8. short h3+paragraph (an FAQ pair) -> compact card
     *  9. anything else                    -> default card
     *
     * A multi-photo Gallery always gets the full-width treatment even
     * next to a heading/paragraph — squeezed into a --feature cell's half
     * -width image column, several photos read as a cramped vertical
     * stack rather than something worth swiping through.
     */
    function st_bento_cell_class(array $blocks): string
    {
        $types      = array_column($blocks, 'type');
        $hasGallery = in_array('gallery', $types, true);
        $hasImage   = in_array('image', $types, true);
        $hasText    = in_array('paragraph', $types, true) || in_array('heading', $types, true);

        if (in_array('cta', $types, true)) {
            return 'lcms-bento__cell--cta';
        }

        if (in_array('quote', $types, true)) {
            return 'lcms-bento__cell--quote';
        }

        if ($hasGallery) {
            return 'lcms-bento__cell--gallery';
        }

        if ($hasImage && $hasText) {
            return 'lcms-bento__cell--feature';
        }

        if ($hasImage) {
            return 'lcms-bento__cell--gallery';
        }

        if (in_array('table', $types, true) || in_array('html', $types, true) || count($blocks) >= 4) {
            return 'lcms-bento__cell--wide';
        }

        if (in_array('list', $types, true)) {
            return 'lcms-bento__cell--capabilities';
        }

        $level = null;
        foreach ($blocks as $block) {
            if ($block['type'] === 'heading') {
                $level = (int) ($block['attrs']['level'] ?? 2);
                break;
            }
        }

        if ($level !== null && $level >= 3 && count($blocks) <= 2) {
            return 'lcms-bento__cell--sm';
        }

        return '';
    }
}

if (! function_exists('st_render_blocks')) {
    /** Re-renders one already-parsed block subset through BlockRenderer. */
    function st_render_blocks(array $blocks): string
    {
        if ($blocks === []) {
            return '';
        }

        $json = (new \App\Libraries\Editor\BlockParser())->serialize($blocks);

        return (new \App\Libraries\Editor\BlockRenderer())->render($json);
    }
}

if (! function_exists('st_render_gallery_swiper')) {
    /**
     * A Gallery block's own images as a Swiper carousel (2-up desktop,
     * 1-up mobile — initSwiper('.lcms-page-gallery', ...) in main.js)
     * instead of BlockRenderer's plain grid-of-<img> markup. Scoped to
     * just a --gallery bento cell's own rendering path (st_render_bento())
     * rather than changing what the Gallery block itself renders as
     * everywhere it's used — a blog post's gallery, for instance, is
     * unaffected. Any other block sharing this section (unlikely — a
     * --gallery cell is media with no accompanying text, see
     * st_bento_cell_class()) still renders normally via BlockRenderer.
     */
    function st_render_gallery_swiper(array $blocks): string
    {
        $html = '';

        foreach ($blocks as $block) {
            if ($block['type'] !== 'gallery') {
                $html .= st_render_blocks([$block]);
                continue;
            }

            $images = $block['attrs']['images'] ?? [];

            if ($images === []) {
                continue;
            }

            $html .= '<div class="swiper lcms-page-gallery"><div class="swiper-wrapper">';

            foreach ($images as $image) {
                $url = esc((string) ($image['url'] ?? ''), 'attr');
                $alt = esc((string) ($image['alt'] ?? ''), 'attr');
                $html .= '<div class="swiper-slide"><img src="' . $url . '" alt="' . $alt . '" loading="lazy"></div>';
            }

            $html .= '</div><div class="swiper-pagination"></div>'
                . '<div class="lcms-page-gallery__nav">'
                . '<button class="swiper-button-prev" aria-label="Anterior"></button>'
                . '<button class="swiper-button-next" aria-label="Siguiente"></button>'
                . '</div></div>';
        }

        return $html;
    }
}

if (! function_exists('st_page_needs_swiper')) {
    /**
     * Whether a Page's content contains a Gallery block — the only thing
     * on a Page that uses Swiper. Checked before loading Swiper's CSS/JS
     * (page.php's theme_header()/theme_footer() calls) so a page without
     * a gallery skips the ~140KB entirely, same as every other
     * conditional load in this theme (see home.php's $needsSwiper).
     */
    function st_page_needs_swiper(string $blocksJson): bool
    {
        foreach ((new \App\Libraries\Editor\BlockParser())->parse($blocksJson) as $block) {
            if ($block['type'] === 'gallery') {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('st_extract_toc')) {
    /**
     * Pulls every H2/H3 out of an already-rendered post body, gives each
     * one a stable, de-duplicated id (written straight into the returned
     * HTML, not just the list), and hands back both — so the Table of
     * Contents can link to plain #anchors with no JS required to make
     * the jump work (the theme's global `scroll-behavior: smooth` covers
     * the animation). Only single.php uses this; the blog listing has no
     * single document to build a contents list from, and Pages render as
     * a bento grid of cards (st_render_bento()) rather than a linear read,
     * where a jump-list doesn't fit the layout.
     *
     * @return array{0: string, 1: list<array{id: string, text: string, level: int}>}
     */
    function st_extract_toc(string $html): array
    {
        if (trim($html) === '' || (! str_contains($html, '<h2') && ! str_contains($html, '<h3'))) {
            return [$html, []];
        }

        helper('url'); // url_title() — usually autoloaded already, but not guaranteed

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        // DOMDocument's bundled HTML4 parser assumes Latin-1 unless every
        // non-ASCII byte is escaped first — without this, accented
        // characters and ñ (this content is Spanish) come back mangled.
        $safe = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $safe . '</div>');
        libxml_clear_errors();

        $headings = (new \DOMXPath($dom))->query('//h2 | //h3');

        if ($headings === false || $headings->length === 0) {
            return [$html, []];
        }

        $entries = [];
        $seen    = [];

        foreach ($headings as $heading) {
            $text = trim($heading->textContent);

            if ($text === '') {
                continue;
            }

            $slug = url_title($text, '-', true) ?: 'section';
            $id   = $slug;

            for ($i = 2; isset($seen[$id]); $i++) {
                $id = $slug . '-' . $i;
            }
            $seen[$id] = true;

            $heading->setAttribute('id', $id);

            $entries[] = [
                'id'    => $id,
                'text'  => $text,
                'level' => (int) substr($heading->nodeName, 1), // 'h2' -> 2, 'h3' -> 3
            ];
        }

        // A single heading isn't worth a contents box — bail out having
        // still written the ids (harmless) but signal "nothing to show".
        if (count($entries) < 2) {
            return [$html, []];
        }

        $wrapper = $dom->getElementsByTagName('div')->item(0);
        $out     = '';

        foreach ($wrapper->childNodes as $node) {
            $out .= $dom->saveHTML($node);
        }

        return [$out, $entries];
    }
}

if (! function_exists('st_share_links')) {
    /**
     * Share URLs for the row of buttons in single.php: Facebook, X and
     * Threads' own web share-intent endpoints, a generic (no fixed
     * recipient) WhatsApp share, and a mailto: — all plain links, so five
     * of the row's seven buttons work with zero JS. The remaining two
     * (copy link, "share to any app" via the Web Share API) are wired up
     * in main.js against the 'url'/'title' entries this also returns.
     *
     * Deliberately separate from st_wa_link(): that one messages this
     * business's own WhatsApp number with a canned inquiry; this shares
     * the article to whichever contact the visitor picks.
     */
    function st_share_links(array $post): array
    {
        $url   = post_url($post);
        $title = $post['title'] ?? '';

        return [
            'url'      => $url,
            'title'    => $title,
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
            'whatsapp' => 'https://wa.me/?text=' . rawurlencode($title . ' ' . $url),
            'threads'  => 'https://www.threads.net/intent/post?text=' . rawurlencode($title . ' ' . $url),
            'x'        => 'https://twitter.com/intent/tweet?url=' . rawurlencode($url) . '&text=' . rawurlencode($title),
            'email'    => 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($url),
        ];
    }
}

if (! function_exists('st_footer_columns')) {
    /**
     * Groups the "footer" menu's top-level items into columns, each
     * with its own children as links — lets an admin model "Navigation
     * / Social / Locations" style columns from Admin -> Menus, with no
     * extra schema needed.
     */
    function st_footer_columns(): array
    {
        return array_map(static fn (array $item) => [
            'title'    => $item['title'] ?? '',
            'url'      => $item['url'] ?? null,
            'children' => $item['children'] ?? [],
        ], theme_menu('footer'));
    }
}
