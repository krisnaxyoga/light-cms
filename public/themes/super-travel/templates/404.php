<?php theme_header(['seoHtml' => '<title>Page Not Found</title><meta name="robots" content="noindex,follow">']); ?>

<section class="st-404">
    <div class="st-section-inner st-section-inner--narrow st-404__inner">
        <p class="st-404__eyebrow st-reveal">Error 404</p>
        <h1 class="st-404__title st-reveal">Lost the <em class="st-accent-word">trail</em></h1>
        <p class="st-404__text st-reveal">The page you were looking for could not be found.</p>

        <?php if (! empty($suggestions)): ?>
            <div class="st-404__suggestions st-reveal">
                <p>Did you mean:</p>
                <ul>
                    <?php foreach ($suggestions as $slug): ?>
                        <li><a href="<?= esc(site_url($slug), 'attr') ?>"><?= esc($slug) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <a class="st-nav__cta st-reveal" href="<?= base_url() ?>">Back to homepage</a>
    </div>
</section>

<?php theme_footer(); ?>
