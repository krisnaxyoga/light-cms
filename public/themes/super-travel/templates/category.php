<?php theme_header(['seoHtml' => $seoHtml ?? null]); ?>

<section class="st-portfolio st-portfolio--archive">
    <div class="st-section-inner">
        <p class="st-article__kicker st-reveal">Category</p>
        <h1 class="st-portfolio__heading st-reveal"><?= esc($category['name'] ?? '') ?></h1>
        <?php if (! empty($category['description'])): ?>
            <p class="st-portfolio__intro st-reveal"><?= esc($category['description']) ?></p>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
            <p class="st-empty">No posts in this category yet.</p>
        <?php else: ?>
            <div class="st-portfolio__grid">
                <?php foreach ($posts as $post): ?>
                    <article class="st-portfolio__item st-reveal">
                        <a class="st-portfolio__media" href="<?= esc(post_url($post), 'attr') ?>">
                            <?php if (! empty($post['featured_image'])): ?>
                                <img src="<?= esc($post['featured_image'], 'attr') ?>" alt="<?= esc($post['title'], 'attr') ?>" loading="lazy">
                            <?php else: ?>
                                <div class="st-portfolio__placeholder" aria-hidden="true"></div>
                            <?php endif; ?>
                            <span class="st-portfolio__view">View story</span>
                        </a>
                        <p class="st-portfolio__label"><?= esc(strtoupper(lcms_time_ago($post['published_at'] ?? ''))) ?></p>
                        <h3 class="st-portfolio__title">
                            <a href="<?= esc(post_url($post), 'attr') ?>"><?= esc($post['title']) ?></a>
                        </h3>
                        <p class="st-portfolio__meta"><?= esc(lcms_excerpt($post['excerpt'] ?? '', 20)) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (! empty($pager)): ?>
                <div class="st-pager"><?= $pager->links() ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php theme_footer(); ?>
