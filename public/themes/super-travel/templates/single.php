<?php
$trail = st_breadcrumb_trail($post);
theme_header(['seoHtml' => ($seoHtml ?? null) . seo_breadcrumb_schema($trail) . seo_schema_data_tag($post['seo_meta']['schema_data'] ?? null)]);

// Table of contents: needs at least two H2/H3s to be worth showing
// (st_extract_toc() returns an empty $toc otherwise). $contentHtml (with
// matching #ids written into its headings) replaces $post['content_html']
// below so the links actually land somewhere.
[$contentHtml, $toc] = st_extract_toc($post['content_html'] ?? '');
$share = st_share_links($post);
?>

<article class="st-article">
    <div class="st-section-inner st-section-inner--narrow">
        <?= theme_breadcrumbs($trail) ?>

        <header class="st-article__header st-reveal">
            <?php if (! empty($post['categories'])): ?>
                <p class="st-article__kicker">
                    <?php foreach ($post['categories'] as $category): ?>
                        <a href="<?= esc(site_url('category/' . $category['slug']), 'attr') ?>"><?= esc($category['name']) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <h1 class="st-article__title"><?= esc($post['title']) ?></h1>
            <p class="st-article__meta">
                <?= esc($post['author_name'] ?? 'Unknown') ?>
                &bull; <?= esc(lcms_time_ago($post['published_at'] ?? '')) ?>
                &bull; <?= lcms_reading_time($post['content_html'] ?? '') ?> min read
            </p>
        </header>

        <div class="st-share st-reveal" role="group" aria-label="Compartir este artículo">
            <a class="st-share__btn st-share__btn--facebook" href="<?= esc($share['facebook'], 'attr') ?>" target="_blank" rel="noopener" aria-label="Compartir en Facebook"><?= st_icon('brand-facebook') ?></a>
            <a class="st-share__btn st-share__btn--whatsapp" href="<?= esc($share['whatsapp'], 'attr') ?>" target="_blank" rel="noopener" aria-label="Compartir por WhatsApp"><?= st_icon('brand-whatsapp') ?></a>
            <a class="st-share__btn st-share__btn--threads" href="<?= esc($share['threads'], 'attr') ?>" target="_blank" rel="noopener" aria-label="Compartir en Threads"><?= st_icon('brand-threads') ?></a>
            <a class="st-share__btn st-share__btn--x" href="<?= esc($share['x'], 'attr') ?>" target="_blank" rel="noopener" aria-label="Compartir en X"><?= st_icon('brand-x') ?></a>
            <a class="st-share__btn st-share__btn--email" href="<?= esc($share['email'], 'attr') ?>" aria-label="Compartir por correo"><?= st_icon('mail') ?></a>
            <button type="button" class="st-share__btn st-share__btn--copy" data-share-copy="<?= esc($share['url'], 'attr') ?>" aria-label="Copiar enlace">
                <?= st_icon('link') ?>
                <span class="st-share__tooltip" role="status">&iexcl;Enlace copiado!</span>
            </button>
            <button type="button" class="st-share__btn st-share__btn--more" data-share-native="<?= esc($share['url'], 'attr') ?>" data-share-title="<?= esc($share['title'], 'attr') ?>" aria-label="M&aacute;s opciones para compartir">
                <?= st_icon('plus') ?>
                <span class="st-share__tooltip" role="status">&iexcl;Enlace copiado!</span>
            </button>
        </div>

        <?php if (! empty($post['featured_image'])): ?>
            <img class="st-article__image st-reveal" src="<?= esc($post['featured_image'], 'attr') ?>" alt="<?= esc($post['title'], 'attr') ?>">
        <?php endif; ?>

        <?php if ($toc !== []): ?>
            <nav class="st-toc st-reveal" aria-label="Tabla de contenidos">
                <p class="st-toc__title"><?= st_icon('list') ?> Contenido</p>
                <ol class="st-toc__list">
                    <?php foreach ($toc as $item): ?>
                        <li class="st-toc__item st-toc__item--h<?= (int) $item['level'] ?>">
                            <a href="#<?= esc($item['id'], 'attr') ?>"><?= esc($item['text']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>

        <div class="st-prose st-reveal">
            <?= $contentHtml ?>
        </div>

        <?php $cta = homepage_content('single_cta'); ?>
        <?php if (! empty($cta['enabled']) && ($cta['heading'] !== '' || $cta['text'] !== '')): ?>
            <div class="st-single-cta st-reveal">
                <?php if ($cta['heading'] !== ''): ?><h3><?= esc($cta['heading']) ?></h3><?php endif; ?>
                <?php if ($cta['text'] !== ''): ?><p><?= esc($cta['text']) ?></p><?php endif; ?>
                <?php if ($cta['cta_label'] !== ''): ?>
                    <a class="st-btn st-btn--cta" href="<?= esc($cta['cta_url'] ?: '#', 'attr') ?>"><?= esc($cta['cta_label']) ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (! empty($post['tags'])): ?>
            <p class="st-article__tags">
                <?php foreach ($post['tags'] as $tag): ?>
                    <a class="st-tag" href="<?= esc(site_url('tag/' . $tag['slug']), 'attr') ?>">#<?= esc($tag['name']) ?></a>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>

        <?php $related = theme_get_related_posts($post); ?>
        <?php if (! empty($related)): ?>
            <section class="st-related st-reveal">
                <h3 class="st-related__title">Related stories</h3>
                <div class="st-related__grid">
                    <?php foreach ($related as $item): ?>
                        <a class="st-related__item" href="<?= esc(post_url($item), 'attr') ?>">
                            <?php if (! empty($item['featured_image'])): ?>
                                <img src="<?= esc($item['featured_image'], 'attr') ?>" alt="<?= esc($item['title'], 'attr') ?>" loading="lazy">
                            <?php endif; ?>
                            <span><?= esc($item['title']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (($post['comment_status'] ?? 'open') === 'open' && ! empty($comments)): ?>
            <section class="st-comments st-reveal">
                <h3 class="st-comments__title"><?= count($comments) ?> Comments</h3>
                <?php foreach ($comments as $comment): ?>
                    <div class="st-comment">
                        <strong><?= esc($comment['author_name']) ?></strong>
                        <p><?= esc($comment['content']) ?></p>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</article>

<?php theme_footer(); ?>
