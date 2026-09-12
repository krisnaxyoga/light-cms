<?php theme_header(['seoHtml' => $seoHtml ?? null]); ?>

<div class="lcms-content-layout lcms-content-layout--full">
    <article class="lcms-content lcms-page">
        <h1 class="lcms-page__title"><?= esc($post['title']) ?></h1>
        <div class="lcms-page__body">
            <?= $post['content_html'] ?? '' ?>
        </div>
    </article>
</div>

<?php theme_footer(); ?>
