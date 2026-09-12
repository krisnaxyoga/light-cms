<?php theme_header(['seoHtml' => $seoHtml ?? null]); ?>

<div class="lcms-content-layout lcms-content-layout--<?= esc(theme_option('sidebar_position', 'right')) ?>">
    <article class="lcms-content lcms-single">
        <header class="lcms-single__header">
            <h1 class="lcms-single__title"><?= esc($post['title']) ?></h1>
            <p class="lcms-single__meta">
                By <?= esc($post['author_name'] ?? 'Unknown') ?>
                &middot; <?= esc(lcms_time_ago($post['published_at'] ?? '')) ?>
                &middot; <?= lcms_reading_time($post['content'] ?? '') ?> min read
                <?php if (! empty($post['categories'])): ?>
                    &middot;
                    <?php foreach ($post['categories'] as $category): ?>
                        <a href="<?= esc(site_url('category/' . $category['slug']), 'attr') ?>"><?= esc($category['name']) ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </p>
        </header>

        <?php if (! empty($post['featured_image'])): ?>
            <img class="lcms-single__image" src="<?= esc($post['featured_image'], 'attr') ?>" alt="<?= esc($post['title'], 'attr') ?>">
        <?php endif; ?>

        <div class="lcms-single__body">
            <?= $post['content_html'] ?? '' ?>
        </div>

        <?php if (! empty($post['tags'])): ?>
            <p class="lcms-single__tags">
                <?php foreach ($post['tags'] as $tag): ?>
                    <a class="lcms-tag" href="<?= esc(site_url('tag/' . $tag['slug']), 'attr') ?>">#<?= esc($tag['name']) ?></a>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>

        <?php $related = theme_get_related_posts($post); ?>
        <?php if (! empty($related)): ?>
            <section class="lcms-related">
                <h3>Related Posts</h3>
                <ul>
                    <?php foreach ($related as $item): ?>
                        <li><a href="<?= esc(site_url($item['slug']), 'attr') ?>"><?= esc($item['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (($post['comment_status'] ?? 'open') === 'open'): ?>
            <section class="lcms-comments">
                <h3><?= count($comments ?? []) ?> Comments</h3>
                <?php foreach ($comments ?? [] as $comment): ?>
                    <div class="lcms-comment">
                        <strong><?= esc($comment['author_name']) ?></strong>
                        <p><?= esc($comment['content']) ?></p>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </article>

    <?php if (theme_option('sidebar_position', 'right') !== 'none'): ?>
        <?php theme_sidebar(['categories' => $categories ?? []]); ?>
    <?php endif; ?>
</div>

<?php theme_footer(); ?>
