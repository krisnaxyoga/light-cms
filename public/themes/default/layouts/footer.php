</main>
<footer class="lcms-site-footer">
    <div class="lcms-container lcms-site-footer__inner">
        <div class="lcms-footer__widgets">
            <div class="lcms-footer__col">
                <h4><?= esc(theme_engine()->config()['widget_areas']['footer_1'] ?? 'Footer Column 1') ?></h4>
                <?php foreach (theme_menu('footer') as $item): ?>
                    <a class="lcms-footer__link" href="<?= esc($item['url'] ?? '#', 'attr') ?>"><?= esc($item['title'] ?? '') ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <p class="lcms-footer__copyright">
            &copy; <?= date('Y') ?> <?= esc(site_setting('site_title', 'LightCMS')) ?>. All rights reserved.
        </p>
    </div>
</footer>
<?php theme_scripts(); ?>
</body>
</html>
