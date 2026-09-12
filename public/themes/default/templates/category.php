<?php theme_header(['seoHtml' => $seoHtml ?? null]); ?>

<div class="lcms-content-layout lcms-content-layout--<?= esc(theme_option('sidebar_position', 'right')) ?>">
    <div class="lcms-content">
        <h1 class="lcms-archive__title">Category: <?= esc($category['name'] ?? '') ?></h1>
        <?php if (! empty($category['description'])): ?>
            <p class="lcms-archive__description"><?= esc($category['description']) ?></p>
        <?php endif; ?>

        <?php foreach ($posts ?? [] as $post): ?>
            <article class="lcms-post-card">
                <h2 class="lcms-post-card__title">
                    <a href="<?= esc(site_url($post['slug']), 'attr') ?>"><?= esc($post['title']) ?></a>
                </h2>
                <p class="lcms-post-card__meta"><?= esc(lcms_time_ago($post['published_at'] ?? '')) ?></p>
                <p class="lcms-post-card__excerpt"><?= esc(lcms_excerpt($post['excerpt'] ?? '', 30)) ?></p>
            </article>
        <?php endforeach; ?>

        <?php if (empty($posts)): ?>
            <p class="lcms-empty">No posts in this category yet.</p>
        <?php endif; ?>

        <?php if (! empty($pager)): ?>
            <?= $pager->links() ?>
        <?php endif; ?>
    </div>

    <?php if (theme_option('sidebar_position', 'right') !== 'none'): ?>
        <?php theme_sidebar(['categories' => $categories ?? []]); ?>
    <?php endif; ?>
</div>

<?php theme_footer(); ?>
