    </main>
    <footer class="site-footer">
        <p>&copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?>.
            <?php echo esc_html((string) get_theme_mod('footer_text', '')); ?></p>
        <?php if (has_nav_menu('footer')) : ?>
            <?php wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'menu_class' => 'menu']); ?>
        <?php endif; ?>
    </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
