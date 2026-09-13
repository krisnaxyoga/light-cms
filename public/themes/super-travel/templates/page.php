<?php
$trail       = st_breadcrumb_trail($post);
$needsSwiper = st_page_needs_swiper($post['content'] ?? '[]');

theme_header([
    'seoHtml'     => ($seoHtml ?? null) . seo_breadcrumb_schema($trail) . seo_schema_data_tag($post['seo_meta']['schema_data'] ?? null),
    'needsSwiper' => $needsSwiper,
]);
?>

<!-- Pages open on a full-height typographic hero (dark ground, two blurred
     accent orbs, a huge title with an outlined "Nusa Penida" second line —
     see .lcms-page-hero below), then a bento grid of cards (st_render_bento(),
     functions.php). Both are the deliberate visual break from a blog post's
     classic single-column read (single.php, untouched). -->
<article class="st-article st-article--page">
    <section class="lcms-page-hero">
        <span class="lcms-page-hero__orb lcms-page-hero__orb--a" aria-hidden="true"></span>
        <span class="lcms-page-hero__orb lcms-page-hero__orb--b" aria-hidden="true"></span>

        <div class="lcms-page-hero__top">
            <?= theme_breadcrumbs($trail) ?>
        </div>

        <?php [$heroMain, $heroAccent] = st_split_hero_title($post['title']); ?>
        <h1 class="lcms-page-hero__title st-reveal">
            <span><?= esc($heroMain) ?></span>
            <span class="lcms-outline"><?= esc($heroAccent) ?></span>
        </h1>

        <div class="lcms-page-hero__bottom">
            <?php if (! empty($post['excerpt'])): ?>
                <p class="lcms-page-hero__tag"><?= esc($post['excerpt']) ?></p>
            <?php endif; ?>
            <a class="lcms-page-hero__scroll" href="#lcms-page-content" aria-label="Desplázate para ver más">
                <?= st_icon('arrow-down') ?>
            </a>
        </div>
    </section>

    <div class="st-section-inner">
        <?php if (! empty($post['featured_image'])): ?>
            <img class="lcms-page-photo st-reveal" src="<?= esc($post['featured_image'], 'attr') ?>" alt="<?= esc($post['title'], 'attr') ?>">
        <?php endif; ?>

        <div id="lcms-page-content">
            <?= st_render_bento($post['content'] ?? '[]') ?>
        </div>
    </div>
</article>

<?php theme_footer(['needsSwiper' => $needsSwiper]); ?>
