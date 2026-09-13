<?php
// This homepage is a bespoke sales/booking landing page, not a blog-post
// listing. Every heading, paragraph, list, price and image below comes
// from homepage_content() — Admin -> Homepage — so a non-technical admin
// can rewrite this whole page without touching theme files. What's
// hardcoded is markup/structure only; see App\Libraries\Homepage\HomepageContent
// for the schema and shipped defaults.

$hc = homepage_content();

$seo = $hc['seo'];

// Built through the app's own seo_meta_tags()/MetaBuilder (Config\Services::locale())
// rather than a hand-rolled <head> block, so this bespoke landing page still
// gets robots/OG/Twitter tags AND — when Admin -> Languages is turned on —
// the same hreflang alternate links every other page gets. Only the
// title/description/canonical/image are overridden, from Admin -> Homepage.
$locale         = \Config\Services::locale();
$localeEnabled  = $locale->enabled();
$currentLocale  = $localeEnabled ? $locale->current() : ($seo['html_lang'] ?: 'en');
$alternates     = [];

if ($localeEnabled) {
    foreach ($locale->active() as $lang) {
        $alternates[$lang['code']] = $locale->homeUrl($lang['code']);
    }
    $locale->setAlternates($alternates);
}

$customSeo = seo_meta_tags([
    'title'     => site_setting('site_title', 'Snorkel Penida'),
    'slug'      => '',
    'excerpt'   => $seo['meta_description'],
    'locale'    => $currentLocale,
    'post_type' => 'page', // -> og:type "website" rather than "article"
], [
    'meta_title'       => $seo['meta_title'],
    'meta_description' => $seo['meta_description'],
    'canonical_url'    => $seo['canonical_url'] ?: null,
    'og_image'         => $seo['og_image'] ?: null,
], $alternates);

// Swiper (carousel lib, ~140KB of CSS+JS) is only used by the hero's mobile
// highlight carousel and the pricing/"why choose us"/testimonials carousels
// below — loaded conditionally so every other page skips it entirely (see
// layouts/header.php / footer.php).
$needsSwiper = ($hc['hero']['enabled'] && $hc['hero']['highlights'] !== [])
    || ($hc['why']['enabled'] && $hc['why']['items'] !== [])
    || ($hc['pricing']['enabled'] && $hc['pricing']['packages'] !== [])
    || ($hc['testimonials']['enabled'] && $hc['testimonials']['items'] !== []);

theme_header([
    'seoHtml'        => $customSeo,
    'htmlLang'       => $seo['html_lang'] ?: $currentLocale,
    // The hero photo fills the space behind the nav, so the nav starts
    // transparent over it and only turns solid once the page is scrolled
    // (see .st-nav--transparent / initNavScroll() in main.js). Falls back
    // to the normal solid nav — and the body's usual top padding — the
    // moment the hero is turned off.
    'navTransparent' => $hc['hero']['enabled'],
    'bodyClass'      => $hc['hero']['enabled'] ? 'st-body--hero' : '',
    'needsSwiper'    => $needsSwiper,
]);

/**
 * "Lead text <em>accent</em>" — splits the headline on the first
 * occurrence of the configured accent phrase so it renders in the
 * italic accent color, wherever it falls in the sentence.
 */
$renderHeadline = static function (string $title, string $accent) {
    if ($accent !== '' && ($pos = mb_stripos($title, $accent)) !== false) {
        $before = mb_substr($title, 0, $pos);
        $match  = mb_substr($title, $pos, mb_strlen($accent));
        $after  = mb_substr($title, $pos + mb_strlen($accent));

        echo esc($before) . '<em class="st-accent-word">' . esc($match) . '</em>' . esc($after);
        return;
    }

    echo esc($title);
};
?>

<?php if ($hc['hero']['enabled']): $s = $hc['hero']; ?>
<!-- Hero: full-bleed photo behind the (transparent-until-scrolled) nav,
     headline + tagline bottom-left, numbered highlight cards bottom-right. -->
<section class="st-hero st-hero--cover">
    <?php if ($s['image'] !== ''): ?>
        <div class="st-hero__bg">
            <img src="<?= esc($s['image'], 'attr') ?>" alt="<?= esc($s['image_alt'] ?: $s['title'], 'attr') ?>" loading="eager" fetchpriority="high">
        </div>
    <?php endif; ?>
    <div class="st-hero__scrim" aria-hidden="true"></div>

    <div class="st-hero__grid">
        <div class="st-hero__copy st-reveal">
            <h1 class="st-hero__title"><?php $renderHeadline($s['title'], $s['accent']); ?></h1>
            <?php if ($s['tagline'] !== ''): ?><p class="st-hero__tagline"><?= esc($s['tagline']) ?></p><?php endif; ?>
        </div>

        <?php if ($s['highlights'] !== []): ?>
            <!-- Desktop: plain stacked list (.st-hero__highlights, unchanged).
                 Mobile (<=900px): the same cards become a vertical, one-
                 card-at-a-time Swiper (small square card so the photo stays
                 visible) with its dot indicator running down the side, not
                 underneath — see initHeroHighlights() in main.js and the
                 min-width:901px reset in style.css that neutralizes Swiper's
                 own CSS when JS never initializes it on larger screens.
                 The pagination dots live OUTSIDE .st-hero__highlights-swiper
                 on purpose: that element is Swiper's clipped (overflow:
                 hidden) slide viewport, so anything meant to show beside it
                 rather than get clipped with the other slides must be a
                 sibling, not a child. -->
            <div class="st-hero__highlights st-reveal">
                <div class="swiper st-hero__highlights-swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($s['highlights'] as $i => $item): ?>
                            <div class="swiper-slide">
                                <div class="st-hero__highlight">
                                    <span class="st-hero__highlight-num"><?= sprintf('%02d', $i + 1) ?></span>
                                    <div class="st-hero__highlight-body">
                                        <h3 class="st-hero__highlight-title"><?= esc($item['title'] ?? '') ?></h3>
                                        <?php if (! empty($item['text'])): ?><p class="st-hero__highlight-text"><?= esc($item['text']) ?></p><?php endif; ?>
                                        <?php if (! empty($item['link_label'])): ?>
                                            <a class="st-hero__highlight-link" href="<?= esc($item['link_url'] ?: '#', 'attr') ?>">
                                                <?= esc($item['link_label']) ?> <?= st_icon('arrow-right') ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="st-hero__highlights-dots swiper-pagination"></div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($hc['why']['enabled']): $s = $hc['why']; ?>
<!-- Why choose us -->
<section class="st-why" id="why-us">
    <div class="st-section-inner">
        <div class="st-section-head st-reveal">
            <?php if ($s['eyebrow'] !== ''): ?><p class="st-eyebrow" style="justify-content:center;display:flex;"><?= esc($s['eyebrow']) ?></p><?php endif; ?>
            <h2 class="st-heading"><?= esc($s['heading']) ?></h2>
            <?php if ($s['subheading'] !== ''): ?><p class="st-subheading"><?= esc($s['subheading']) ?></p><?php endif; ?>
        </div>

        <?php if ($s['items'] !== []): ?>
            <div class="swiper st-why__swiper st-reveal">
                <div class="swiper-wrapper">
                    <?php foreach ($s['items'] as $item): ?>
                        <div class="swiper-slide">
                            <div class="st-why-card">
                                <?php if (! empty($item['icon'])): ?><span class="st-why-card__icon"><?= st_icon($item['icon']) ?></span><?php endif; ?>
                                <h3><?= esc($item['title'] ?? '') ?></h3>
                                <p><?= esc($item['text'] ?? '') ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="swiper-pagination"></div>
                <div class="st-why__nav">
                    <button class="swiper-button-prev" aria-label="Anterior"></button>
                    <button class="swiper-button-next" aria-label="Siguiente"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($hc['pricing']['enabled'] && $hc['pricing']['packages'] !== []): $s = $hc['pricing']; ?>
<!-- Pricing -->
<section class="st-pricing" id="pricing">
    <div class="st-section-inner">
        <div class="st-section-head st-reveal">
            <h2 class="st-heading"><?= esc($s['heading']) ?></h2>
            <?php if ($s['subheading'] !== ''): ?><p class="st-subheading"><?= esc($s['subheading']) ?></p><?php endif; ?>
        </div>

        <div class="swiper st-pricing__swiper st-reveal">
            <div class="swiper-wrapper">
                <?php foreach ($s['packages'] as $pkg): ?>
                    <div class="swiper-slide">
                        <div class="st-price-card <?= ! empty($pkg['featured']) ? 'st-price-card--featured' : '' ?>">
                            <?php if (! empty($pkg['ribbon'])): ?>
                                <span class="st-price-card__ribbon"><?= esc($pkg['ribbon']) ?></span>
                            <?php endif; ?>
                            <h3 class="st-price-card__name">
                                <?php if (! empty($pkg['icon'])): ?><?= st_icon($pkg['icon']) ?> <?php endif; ?>
                                <?= esc($pkg['name'] ?? '') ?>
                            </h3>
                            <?php if (! empty($pkg['audience'])): ?><p class="st-price-card__audience"><?= esc($pkg['audience']) ?></p><?php endif; ?>
                            <div class="st-price-card__price">
                                <?php if (! empty($pkg['price_old'])): ?><span class="st-price-card__old"><?= esc($pkg['price_old']) ?></span><?php endif; ?>
                                <span class="st-price-card__now"><?= esc($pkg['price_now'] ?? '') ?></span>
                            </div>
                            <?php if (! empty($pkg['unit'])): ?><p class="st-price-card__unit"><?= esc($pkg['unit']) ?></p><?php endif; ?>
                            <?php if (! empty($pkg['features'])): ?>
                                <ul class="st-price-card__list">
                                    <?php foreach ($pkg['features'] as $feature): ?>
                                        <li><?= st_icon('circle-check') ?> <?= esc($feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (! empty($pkg['rating'])): ?>
                                <p class="st-price-card__rating"><?= str_repeat(st_icon('star-filled'), 5) ?> <span><?= esc($pkg['rating']) ?>/5 viajeros</span></p>
                            <?php endif; ?>
                            <?php if (! empty($pkg['cta_label'])): ?>
                                <a class="st-btn st-btn--cta st-btn--block" href="<?= esc($pkg['cta_url'] ?: '#', 'attr') ?>" target="_blank" rel="noopener"><?= esc($pkg['cta_label']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="swiper-pagination"></div>
            <div class="st-pricing__nav">
                <button class="swiper-button-prev" aria-label="Anterior"></button>
                <button class="swiper-button-next" aria-label="Siguiente"></button>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($hc['compare']['enabled'] && $hc['compare']['rows'] !== []): $s = $hc['compare']; ?>
<!-- Boat comparison -->
<section class="st-compare">
    <div class="st-section-inner st-section-inner--narrow">
        <div class="st-section-head st-reveal">
            <h2 class="st-heading"><?= esc($s['heading']) ?></h2>
        </div>

        <div class="st-compare-table-wrap st-reveal">
            <table class="st-compare-table">
                <thead>
                    <tr>
                        <th><?= esc($s['col_feature']) ?></th>
                        <th><?= esc($s['col_a']) ?></th>
                        <th><?= esc($s['col_b']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($s['rows'] as $row): ?>
                        <tr>
                            <td><?= esc($row['feature'] ?? '') ?></td>
                            <td><?= esc($row['a'] ?? '') ?></td>
                            <td><?= esc($row['b'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($s['note'] !== ''): ?><p class="st-compare-note st-reveal"><?= esc($s['note']) ?></p><?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($hc['schedule']['enabled']): $s = $hc['schedule']; ?>
<!-- Departure schedule -->
<section class="st-schedule">
    <div class="st-section-inner">
        <div class="st-section-head st-reveal">
            <h2 class="st-heading"><?= esc($s['heading']) ?></h2>
            <?php if ($s['subheading'] !== ''): ?><p class="st-subheading"><?= esc($s['subheading']) ?></p><?php endif; ?>
        </div>

        <?php if ($s['sessions'] !== []): ?>
            <div class="st-schedule__grid">
                <?php foreach ($s['sessions'] as $session): ?>
                    <div class="st-schedule-card st-reveal">
                        <?php if (! empty($session['icon'])): ?><span class="st-schedule-card__icon"><?= st_icon($session['icon']) ?></span><?php endif; ?>
                        <p class="st-schedule-card__time"><?= esc($session['title'] ?? '') ?></p>
                        <dl>
                            <?php if (! empty($session['checkin'])): ?><dt>Check-in</dt><dd><?= esc($session['checkin']) ?></dd><?php endif; ?>
                            <?php if (! empty($session['best_for'])): ?><dt>Mejor para</dt><dd><?= esc($session['best_for']) ?></dd><?php endif; ?>
                            <?php if (! empty($session['recommended'])): ?><dt>Recomendado para</dt><dd><?= esc($session['recommended']) ?></dd><?php endif; ?>
                        </dl>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($s['notice_items'] !== []): ?>
            <div class="st-notice st-reveal">
                <span class="st-notice__icon"><?= st_icon('alert-triangle') ?></span>
                <div>
                    <?php if ($s['notice_title'] !== ''): ?><h3><?= esc($s['notice_title']) ?></h3><?php endif; ?>
                    <ul>
                        <?php foreach ($s['notice_items'] as $line): ?>
                            <li><?= homepage_bold_prefix($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($hc['steps']['enabled'] && $hc['steps']['items'] !== []): $s = $hc['steps']; ?>
<!-- How it works -->
<section class="st-steps">
    <div class="st-section-inner">
        <div class="st-section-head st-reveal">
            <h2 class="st-heading"><?= esc($s['heading']) ?></h2>
        </div>

        <div class="st-steps__grid">
            <?php foreach ($s['items'] as $i => $item): ?>
                <div class="st-step st-reveal">
                    <span class="st-step__icon">
                        <?php if (! empty($item['icon'])): ?><?= st_icon($item['icon']) ?><?php endif; ?>
                        <span class="st-step__number"><?= (int) $i + 1 ?></span>
                    </span>
                    <h3><?= esc($item['title'] ?? '') ?></h3>
                    <p><?= esc($item['text'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($hc['checklist']['enabled'] && ($hc['checklist']['included'] !== [] || $hc['checklist']['excluded'] !== [])): $s = $hc['checklist']; ?>
<!-- Includes / excludes -->
<section class="st-checklist">
    <div class="st-section-inner">
        <div class="st-section-head st-reveal">
            <h2 class="st-heading"><?= esc($s['heading']) ?></h2>
            <?php if ($s['subheading'] !== ''): ?><p class="st-subheading"><?= esc($s['subheading']) ?></p><?php endif; ?>
        </div>

        <div class="st-checklist__grid">
            <?php if ($s['included'] !== []): ?>
                <div class="st-checklist-col st-checklist-col--included st-reveal">
                    <h3 class="st-checklist-col__title"><?= esc($s['included_title']) ?></h3>
                    <ul>
                        <?php foreach ($s['included'] as $line): ?>
                            <li><?= st_icon('circle-check') ?> <?= esc($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($s['excluded'] !== []): ?>
                <div class="st-checklist-col st-checklist-col--excluded st-reveal">
                    <h3 class="st-checklist-col__title"><?= esc($s['excluded_title']) ?></h3>
                    <ul>
                        <?php foreach ($s['excluded'] as $line): ?>
                            <li><?= st_icon('x') ?> <?= esc($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($hc['packing']['enabled'] && $hc['packing']['items'] !== []): $s = $hc['packing']; ?>
<!-- What to bring -->
<section class="st-packing">
    <div class="st-section-inner">
        <div class="st-section-head st-reveal">
            <h2 class="st-heading"><?= esc($s['heading']) ?></h2>
            <?php if ($s['subheading'] !== ''): ?><p class="st-subheading"><?= esc($s['subheading']) ?></p><?php endif; ?>
        </div>

        <div class="st-packing__grid">
            <?php foreach ($s['items'] as $item): ?>
                <div class="st-packing-item st-reveal">
                    <?php if (! empty($item['icon'])): ?><?= st_icon($item['icon']) ?><?php endif; ?>
                    <span><?= esc($item['label'] ?? '') ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($s['tip'] !== ''): ?>
            <div class="st-tip st-reveal">
                <span><?= st_icon('bulb') ?></span>
                <p><?= homepage_bold_prefix($s['tip']) ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($hc['testimonials']['enabled'] && $hc['testimonials']['items'] !== []): $s = $hc['testimonials']; ?>
<!-- Testimonials -->
<section class="st-testimonials">
    <div class="st-section-inner">
        <div class="st-section-head st-reveal">
            <h2 class="st-heading"><?= esc($s['heading']) ?></h2>
            <?php if ($s['subheading'] !== ''): ?><p class="st-subheading"><?= esc($s['subheading']) ?></p><?php endif; ?>
        </div>

        <div class="swiper st-testimonials__swiper st-reveal">
            <div class="swiper-wrapper">
                <?php foreach ($s['items'] as $item): ?>
                    <div class="swiper-slide">
                        <div class="st-testimonial">
                            <?php $rating = max(0, min(5, (int) ($item['rating'] ?? 5))); ?>
                            <div class="st-testimonial__stars"><?= str_repeat(st_icon('star-filled'), $rating) ?></div>
                            <p>&ldquo;<?= esc($item['quote'] ?? '') ?>&rdquo;</p>
                            <p class="st-testimonial__author"><?= esc($item['author'] ?? '') ?></p>
                            <?php if (! empty($item['location'])): ?><p class="st-testimonial__location"><?= esc($item['location']) ?></p><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="swiper-pagination"></div>
            <div class="st-testimonials__nav">
                <button class="swiper-button-prev" aria-label="Anterior"></button>
                <button class="swiper-button-next" aria-label="Siguiente"></button>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($hc['faq']['enabled'] && $hc['faq']['items'] !== []): $s = $hc['faq']; ?>
<!-- FAQ -->
<section class="st-faq" id="faq">
    <div class="st-section-inner st-section-inner--narrow">
        <div class="st-section-head st-reveal">
            <h2 class="st-heading"><?= esc($s['heading']) ?></h2>
            <?php if ($s['subheading'] !== ''): ?><p class="st-subheading"><?= esc($s['subheading']) ?></p><?php endif; ?>
        </div>

        <div class="st-faq__list">
            <?php foreach ($s['items'] as $item): ?>
                <details class="st-faq__item st-reveal">
                    <summary><?= esc($item['question'] ?? '') ?> <?= st_icon('chevron-down') ?></summary>
                    <p><?= esc($item['answer'] ?? '') ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($hc['final_cta']['enabled']): $s = $hc['final_cta']; ?>
<!-- Final CTA -->
<section class="st-final-cta">
    <div class="st-section-inner">
        <div class="st-final-cta__inner st-reveal">
            <h2 class="st-heading"><?= esc($s['heading']) ?></h2>
            <?php if ($s['subheading'] !== ''): ?><p class="st-subheading" style="margin-bottom:0;"><?= esc($s['subheading']) ?></p><?php endif; ?>

            <?php if ($s['urgency'] !== ''): ?>
                <span class="st-final-cta__stat"><?= st_icon('flame') ?> <?= esc($s['urgency']) ?></span>
            <?php endif; ?>

            <div class="st-final-cta__ctas">
                <?php if ($s['cta_primary_label'] !== ''): ?>
                    <a class="st-btn st-btn--cta" href="<?= esc($s['cta_primary_url'] ?: '#', 'attr') ?>"><?= esc($s['cta_primary_label']) ?></a>
                <?php endif; ?>
                <?php if ($s['cta_secondary_label'] !== ''): ?>
                    <a class="st-btn st-btn--outline" href="<?= esc(st_wa_link($s['cta_secondary_url']) ?: '#', 'attr') ?>" target="_blank" rel="noopener">
                        <?= st_icon('brand-whatsapp') ?> <?= esc($s['cta_secondary_label']) ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($s['whatsapp_label'] !== '' || $s['hours'] !== ''): ?>
                <div class="st-final-cta__contact">
                    <?php if ($s['whatsapp_label'] !== ''): ?>
                        <a class="st-final-cta__phone" href="<?= esc(st_wa_link($s['whatsapp_url']) ?: '#', 'attr') ?>" target="_blank" rel="noopener"><?= st_icon('brand-whatsapp') ?> <?= esc($s['whatsapp_label']) ?></a>
                    <?php endif; ?>
                    <?php if ($s['hours'] !== ''): ?>
                        <span class="st-final-cta__hours"><?= st_icon('clock') ?> <?= esc($s['hours']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php theme_footer(['needsSwiper' => $needsSwiper]); ?>
