<?= view('admin/_header', ['title' => $title]) ?>

<?php foreach ($notices as $notice): ?>
    <div class="alert mb-4 text-sm <?= $notice['type'] === 'error' ? 'alert-error' : 'alert-success' ?>">
        <span><?= esc($notice['message']) ?></span>
    </div>
<?php endforeach; ?>

<?php if (count($menus) > 1): ?>
    <div role="tablist" class="tabs tabs-bordered mb-4 justify-start overflow-x-auto">
        <?php foreach ($menus as $menu): ?>
            <a role="tab" href="<?= site_url('admin/wp/' . $menu['slug']) ?>"
               class="tab <?= $menu['slug'] === $slug ? 'tab-active' : '' ?>"><?= esc($menu['title']) ?></a>
            <?php foreach ($menu['children'] as $child): ?>
                <a role="tab" href="<?= site_url('admin/wp/' . $child['slug']) ?>"
                   class="tab <?= $child['slug'] === $slug ? 'tab-active' : '' ?>"><?= esc($child['title']) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card border border-base-300 bg-base-100">
    <div class="card-body p-4 md:p-6">
        <!-- Plugin-generated wp-admin markup, echoed as-is; the .lcms-wp-screen
             rules in resources/css/admin.css map it onto daisyUI components. -->
        <div class="wrap lcms-wp-screen"><?= $content ?></div>
    </div>
</div>

<?= view('admin/_footer') ?>
