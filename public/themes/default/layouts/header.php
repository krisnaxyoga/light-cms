<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $seoHtml ?? '<title>' . esc(site_setting('site_title', 'LightCMS')) . '</title>' ?>
    <?php theme_styles(); ?>
</head>
<body class="lcms-layout lcms-layout--<?= esc(theme_option('layout', 'boxed')) ?>">
<header class="lcms-site-header">
    <div class="lcms-container lcms-site-header__inner">
        <a class="lcms-logo" href="<?= base_url() ?>"><?= esc(site_setting('site_title', 'LightCMS')) ?></a>
        <nav class="lcms-nav" aria-label="Primary">
            <ul>
                <?php foreach (theme_menu('primary') as $item): ?>
                    <li>
                        <a href="<?= esc($item['url'] ?? '#', 'attr') ?>"<?= ! empty($item['target']) ? ' target="' . esc($item['target'], 'attr') . '"' : '' ?>>
                            <?= esc($item['title'] ?? '') ?>
                        </a>
                        <?php if (! empty($item['children'])): ?>
                            <ul class="lcms-nav__submenu">
                                <?php foreach ($item['children'] as $child): ?>
                                    <li><a href="<?= esc($child['url'] ?? '#', 'attr') ?>"><?= esc($child['title'] ?? '') ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</header>
<main class="lcms-container lcms-main">
