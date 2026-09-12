<?php get_header(); ?>

<?php while (have_posts()) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class('entry'); ?>>
        <h1 class="entry-title"><?php the_title(); ?></h1>

        <p class="entry-meta">
            <?php echo esc_html(get_the_date()); ?>
            <?php if (get_the_author()) : ?> &middot; <?php the_author(); ?><?php endif; ?>
        </p>

        <?php if (has_post_thumbnail()) : ?>
            <div class="entry-thumbnail"><?php the_post_thumbnail('large'); ?></div>
        <?php endif; ?>

        <div class="entry-content"><?php the_content(); ?></div>

        <footer class="entry-footer"><?php the_tags('<span class="tags">', ', ', '</span>'); ?></footer>
    </article>

    <?php the_post_navigation(); ?>
    <?php comments_template(); ?>
<?php endwhile; ?>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
