</main>
<footer class="st-footer">
    <div class="st-footer__grid">
        <div class="st-footer__brand">
            <a class="st-footer__logo" href="<?= locale_url() ?>">
                <?php if ($logo = site_setting('site_logo', '')): ?>
                    <img class="st-footer__logo-img" src="<?= esc($logo, 'attr') ?>" alt="<?= esc(site_setting('site_title', 'Snorkel Penida'), 'attr') ?>">
                <?php else: ?>
                    <?= esc(site_setting('site_title', 'Snorkel Penida')) ?>
                <?php endif; ?>
            </a>
            <p class="st-footer__mission"><?= esc(site_setting('site_description', '')) ?></p>
            <a class="st-footer__phone" href="<?= esc(st_wa_link(whatsapp_url()), 'attr') ?>" target="_blank" rel="noopener">
                <?= st_icon('brand-whatsapp') ?> <?= esc(whatsapp_number()) ?>
            </a>
        </div>

        <div class="st-footer__columns">
            <?php foreach (st_footer_columns() as $column): ?>
                <div class="st-footer__col">
                    <h4 class="st-footer__col-title"><?= esc($column['title']) ?></h4>
                    <?php foreach ($column['children'] as $link): ?>
                        <a class="st-footer__link" href="<?= esc($link['url'] ?? '#', 'attr') ?>"><?= esc($link['title'] ?? '') ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="st-footer__bottom">
        <p>&copy; <?= date('Y') ?> <?= esc(site_setting('site_title', 'Snorkel Penida')) ?>. All rights reserved.</p>
    </div>
</footer>

<!-- Floating actions: fixed to the viewport (not the footer itself), so
     they follow the visitor on every page, not just once they scroll here. -->
<a class="st-floating st-floating--wa" href="<?= esc(st_wa_link(whatsapp_url()), 'attr') ?>"
   target="_blank" rel="noopener" aria-label="Chatea por WhatsApp">
    <?= st_icon('brand-whatsapp') ?>
</a>
<button type="button" class="st-floating st-floating--top" id="st-back-to-top" aria-label="Volver arriba">
    <?= st_icon('arrow-up') ?>
</button>

<?php if (! empty($needsSwiper)): ?>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
<?php endif; ?>
<?php theme_scripts(); ?>
</body>
</html>
