<?php theme_header(['seoHtml' => $seoHtml ?? null]); ?>

<div class="lcms-content-layout lcms-content-layout--<?= esc(theme_option('sidebar_position', 'right')) ?>">
    <div class="lcms-content">
        <h1 class="lcms-archive__title"><?= esc($archiveTitle ?? 'Archive') ?></h1>

        <?php foreach ($posts ?? [] as $post): ?>
            <article class="lcms-post-card">
                <h2 class="lcms-post-card__title">
                    <a href="<?= esc(site_url($post['slug']), 'attr') ?>"><?= esc($post['title']) ?></a>
                </h2>
                <p class="lcms-post-card__meta"><?= esc(lcms_time_ago($post['published_at'] ?? '')) ?></p>
                <p class="lcms-post-card__excerpt"><?= esc(lcms_excerpt($post['excerpt'] ?? '', 30)) ?></p>
            </article>
        <?php endforeach; ?>

        <?php if (! empty($pager)): ?>
            <?= $pager->links() ?>
        <?php endif; ?>
    </div>

    <?php if (theme_option('sidebar_position', 'right') !== 'none'): ?>
        <?php theme_sidebar(['categories' => $categories ?? []]); ?>
    <?php endif; ?>
</div>

<?php theme_footer(); ?>
