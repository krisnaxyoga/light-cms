<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $favicon = site_setting('site_favicon', ''); ?>
    <link rel="icon" href="<?= esc($favicon !== '' ? $favicon : base_url('favicon.ico'), 'attr') ?>">
    <?= $seoHtml ?? '<title>' . esc(site_setting('site_title', 'LightCMS')) . '</title>' ?>
    <?php theme_styles(); ?>
</head>
<body class="lcms-layout lcms-layout--<?= esc(theme_option('layout', 'boxed')) ?>">
<header class="lcms-site-header">
    <div class="lcms-container lcms-site-header__inner">
        <a class="lcms-logo" href="<?= locale_url() ?>"><?= esc(site_setting('site_title', 'LightCMS')) ?></a>
        <nav class="lcms-nav" aria-label="Primary">
            <?= theme_render_menu_items(theme_menu('primary'), 'lcms-nav__submenu') ?>
        </nav>
        <?php $languages = theme_language_switcher(); ?>
        <?php if ($languages !== []): ?>
            <nav class="lcms-lang" aria-label="Language">
                <ul>
                    <?php foreach ($languages as $lang): ?>
                        <li>
                            <a href="<?= esc($lang['url'], 'attr') ?>" hreflang="<?= esc($lang['code'], 'attr') ?>" lang="<?= esc($lang['code'], 'attr') ?>"
                               class="<?= $lang['is_current'] ? 'is-active' : '' ?>" title="<?= esc($lang['native_name'], 'attr') ?>">
                                <?= esc(strtoupper($lang['code'])) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</header>
<main class="lcms-container lcms-main">
