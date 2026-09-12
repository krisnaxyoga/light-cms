<?php get_header(); ?>

<h1 class="archive-title">
    <?php printf(esc_html__('Search results for: %s', 'lightcms-classic'), '<span>' . get_search_query() . '</span>'); ?>
</h1>

<?php get_search_form(); ?>

<?php if (have_posts()) : ?>
    <?php while (have_posts()) : the_post(); ?>
        <?php get_template_part('template-parts/content', 'search'); ?>
    <?php endwhile; ?>
    <?php the_posts_pagination(); ?>
<?php else : ?>
    <p><?php esc_html_e('No results.', 'lightcms-classic'); ?></p>
<?php endif; ?>

<?php get_footer(); ?>
