<?php theme_header(['seoHtml' => '<title>Page Not Found</title><meta name="robots" content="noindex,follow">']); ?>

<div class="lcms-content-layout lcms-content-layout--full">
    <div class="lcms-content lcms-404">
        <h1>404</h1>
        <p>The page you were looking for could not be found.</p>

        <?php if (! empty($suggestions)): ?>
            <p>Did you mean:</p>
            <ul>
                <?php foreach ($suggestions as $slug): ?>
                    <li><a href="<?= esc(site_url($slug), 'attr') ?>"><?= esc($slug) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <a class="lcms-btn lcms-btn--primary" href="<?= base_url() ?>">Back to homepage</a>
    </div>
</div>

<?php theme_footer(); ?>
