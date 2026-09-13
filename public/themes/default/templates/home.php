<?php theme_header(['seoHtml' => $seoHtml ?? null]); ?>

<div class="lcms-content-layout lcms-content-layout--<?= esc(theme_option('sidebar_position', 'right')) ?>">
    <div class="lcms-content">
        <?php if (empty($posts)): ?>
            <p class="lcms-empty">No posts published yet.</p>
        <?php endif; ?>

        <?php foreach ($posts ?? [] as $post): ?>
            <article class="lcms-post-card">
                <?php if (! empty($post['featured_image'])): ?>
                    <a href="<?= esc(post_url($post), 'attr') ?>">
                        <img class="lcms-post-card__image" src="<?= esc($post['featured_image'], 'attr') ?>" alt="<?= esc($post['title'], 'attr') ?>" loading="lazy">
                    </a>
                <?php endif; ?>
                <h2 class="lcms-post-card__title">
                    <a href="<?= esc(post_url($post), 'attr') ?>"><?= esc($post['title']) ?></a>
                </h2>
                <p class="lcms-post-card__meta">
                    <?= esc(lcms_time_ago($post['published_at'] ?? '')) ?>
                    &middot; <?= lcms_reading_time($post['excerpt'] ?? '') ?> min read
                </p>
                <p class="lcms-post-card__excerpt"><?= esc(lcms_excerpt($post['excerpt'] ?? '', 30)) ?></p>
                <a class="lcms-btn lcms-btn--primary" href="<?= esc(post_url($post), 'attr') ?>">Read more</a>
            </article>
        <?php endforeach; ?>

        <?php if (! empty($pager)): ?>
            <?= $pager->links() ?>
        <?php endif; ?>
    </div>

    <?php if (theme_option('sidebar_position', 'right') !== 'none'): ?>
        <?php theme_sidebar(['categories' => $categories ?? [], 'recentPosts' => $recentPosts ?? []]); ?>
    <?php endif; ?>
</div>

<?php theme_footer(); ?>
