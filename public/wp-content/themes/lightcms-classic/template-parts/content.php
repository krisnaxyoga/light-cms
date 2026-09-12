<article id="post-<?php the_ID(); ?>" <?php post_class('entry'); ?>>
    <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>

    <p class="entry-meta">
        <?php echo esc_html(get_the_date()); ?>
        <?php if (get_the_author()) : ?> &middot; <?php the_author(); ?><?php endif; ?>
        <?php $categories = get_the_category_list(', '); ?>
        <?php if ($categories) : ?> &middot; <?php echo $categories; ?><?php endif; ?>
    </p>

    <?php if (has_post_thumbnail()) : ?>
        <div class="entry-thumbnail"><?php the_post_thumbnail('medium'); ?></div>
    <?php endif; ?>

    <div class="entry-summary"><?php the_excerpt(); ?></div>
</article>
