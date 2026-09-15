<!doctype html>
<html lang="<?= esc($htmlLang ?? service('request')->getLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Tints the mobile browser's own chrome (Android status bar / iOS
         Safari bar) to match the brand instead of default black/white —
         part of the "feels like an app" polish, not just responsive CSS. -->
    <meta name="theme-color" content="#0077B6">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <?php $favicon = site_setting('site_favicon', ''); ?>
    <link rel="icon" href="<?= esc($favicon !== '' ? $favicon : base_url('favicon.ico'), 'attr') ?>">
    <?php if ($favicon !== ''): ?>
        <link rel="apple-touch-icon" href="<?= esc($favicon, 'attr') ?>">
    <?php endif; ?>
    <?php if ($gaId = site_setting('google_analytics_id', '')): ?>
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= esc($gaId, 'attr') ?>"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());

          gtag('config', '<?= esc($gaId, 'js') ?>');
        </script>
    <?php endif; ?>
    <?= $seoHtml ?? '<title>' . esc(site_setting('site_title', 'Super Travel')) . '</title>' ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=League+Spartan:ital,wght@0,400;0,500;0,700;0,800;0,900;1,600&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.46.0/dist/tabler-icons.min.css">
    <?php if (! empty($needsSwiper)): ?>
        <!-- Only the homepage's pricing carousel needs Swiper — loading it
             on every page (single posts, category, blog, 404) would just be
             ~140KB of unused CSS/JS slowing down mobile loads for nothing. -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <?php endif; ?>
    <?php theme_styles(); ?>
</head>
<body class="st-body <?= esc($bodyClass ?? '') ?>">
<header class="st-nav<?= ! empty($navTransparent) ? ' st-nav--transparent' : '' ?>" id="st-nav">
    <div class="st-nav__inner">
        <a class="st-nav__logo" href="<?= locale_url() ?>">
            <?php if ($logo = site_setting('site_logo', '')): ?>
                <img class="st-nav__logo-img" src="<?= esc($logo, 'attr') ?>" alt="<?= esc(site_setting('site_title', 'Snorkel Penida'), 'attr') ?>">
            <?php else: ?>
                <?= esc(site_setting('site_title', 'Snorkel Penida')) ?>
            <?php endif; ?>
        </a>

        <nav class="st-nav__menu" id="st-nav-menu" aria-label="Primary">
            <?= theme_render_menu_items(st_primary_menu(), 'st-nav__submenu') ?>
        </nav>

        <?php $languages = theme_language_switcher(); ?>
        <?php if ($languages !== []): ?>
            <div class="st-nav__lang" aria-label="Language">
                <?php foreach ($languages as $lang): ?>
                    <a href="<?= esc($lang['url'], 'attr') ?>" hreflang="<?= esc($lang['code'], 'attr') ?>" lang="<?= esc($lang['code'], 'attr') ?>"
                       class="<?= $lang['is_current'] ? 'is-active' : '' ?>" title="<?= esc($lang['native_name'], 'attr') ?>">
                        <?= esc(strtoupper($lang['code'])) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php $ctaUrl = 'https://wa.me/6282282638682?text=Hello+Snorkeling+Penida%2C+I%27m+interested+in+your+snorkeling+trips.+Could+you+please+provide+more+information%3F'; ?>
        <a class="st-nav__cta" href="<?= esc($ctaUrl, 'attr') ?>" target="_blank" rel="noopener">
            <?= esc(theme_option('cta_label', 'Contáctanos por WhatsApp')) ?>
        </a>

        <button class="st-nav__toggle" id="st-nav-toggle" type="button" aria-expanded="false" aria-controls="st-nav-menu" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>
<main class="st-main">
