<?php get_header(); ?>

<header class="archive-header">
    <?php the_archive_title('<h1 class="archive-title">', '</h1>'); ?>
    <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
</header>

<?php if (have_posts()) : ?>
    <?php while (have_posts()) : the_post(); ?>
        <?php get_template_part('template-parts/content', get_post_type()); ?>
    <?php endwhile; ?>
    <?php the_posts_pagination(); ?>
<?php else : ?>
    <p><?php esc_html_e('Nothing here yet.', 'lightcms-classic'); ?></p>
<?php endif; ?>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
