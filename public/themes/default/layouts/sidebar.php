<aside class="lcms-sidebar" role="complementary">
    <?php if (! empty($categories)): ?>
        <section class="lcms-widget lcms-widget--categories">
            <h3 class="lcms-widget__title">Categories</h3>
            <ul>
                <?php foreach ($categories as $category): ?>
                    <li><a href="<?= esc(site_url('category/' . $category['slug']), 'attr') ?>"><?= esc($category['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if (! empty($recentPosts)): ?>
        <section class="lcms-widget lcms-widget--recent">
            <h3 class="lcms-widget__title">Recent Posts</h3>
            <ul>
                <?php foreach ($recentPosts as $recent): ?>
                    <li><a href="<?= esc(site_url($recent['slug']), 'attr') ?>"><?= esc($recent['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</aside>
