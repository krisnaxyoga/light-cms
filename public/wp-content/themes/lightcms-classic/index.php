<?php get_header(); ?>

<?php if (have_posts()) : ?>
    <?php while (have_posts()) : the_post(); ?>
        <?php get_template_part('template-parts/content', get_post_type()); ?>
    <?php endwhile; ?>

    <?php the_posts_pagination(); ?>
<?php else : ?>
    <p><?php esc_html_e('Nothing published yet.', 'lightcms-classic'); ?></p>
<?php endif; ?>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
