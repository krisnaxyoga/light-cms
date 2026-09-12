<?= view('admin/_header', ['title' => 'Themes']) ?>

<h2 class="mb-3 text-sm font-semibold uppercase tracking-wide opacity-60">LightCMS themes</h2>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($themes as $theme): ?>
        <?php $screenshot = FCPATH . 'themes/' . $theme['slug'] . '/screenshot.png'; ?>
        <div class="card border bg-base-100 <?= ! empty($theme['is_active']) ? 'border-primary' : 'border-base-300' ?>">
            <figure class="aspect-video overflow-hidden bg-base-200">
                <?php if (is_file($screenshot)): ?>
                    <img src="<?= base_url('themes/' . $theme['slug'] . '/screenshot.png') ?>"
                         alt="<?= esc($theme['name']) ?>" class="h-full w-full object-cover">
                <?php else: ?>
                    <span class="text-sm opacity-50">No preview</span>
                <?php endif; ?>
            </figure>
            <div class="card-body gap-2 p-4">
                <h3 class="card-title text-base"><?= esc($theme['name']) ?></h3>
                <p class="text-xs opacity-70">
                    v<?= esc($theme['version'] ?? '1.0.0') ?> by <?= esc($theme['author'] ?: 'Unknown') ?>
                </p>
                <div class="card-actions mt-1">
                    <?php if (! empty($theme['is_active'])): ?>
                        <span class="badge badge-primary">Active</span>
                    <?php else: ?>
                        <form method="post" action="<?= site_url('admin/themes/' . $theme['slug'] . '/activate') ?>" class="w-full">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-sm w-full">Activate</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide opacity-60">WordPress themes</h2>

<?php if (! $wpEnabled): ?>
    <div class="alert text-sm">
        <span>The WordPress compatibility layer is disabled in <code class="rounded bg-base-200 px-1">app/Config/WordPress.php</code>.</span>
    </div>
<?php elseif ($wpThemes === []): ?>
    <div class="card border border-dashed border-base-300 bg-base-100">
        <div class="card-body items-center gap-1 py-8 text-center text-sm opacity-70">
            <p>No WordPress themes found.</p>
            <p>
                Drop a theme folder into <code class="rounded bg-base-200 px-1">public/wp-content/themes/</code> —
                it is detected by the <code class="rounded bg-base-200 px-1">Theme Name:</code> header in its
                <code class="rounded bg-base-200 px-1">style.css</code>.
            </p>
        </div>
    </div>
<?php else: ?>
    <p class="mb-3 text-sm opacity-70">
        Classic PHP themes run through the compatibility layer. Check one first with
        <code class="rounded bg-base-200 px-1">php spark wp:doctor &lt;slug&gt;</code>.
    </p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($wpThemes as $theme): ?>
            <div class="card border bg-base-100 <?= $theme['is_active'] ? 'border-primary' : 'border-base-300' ?>">
                <figure class="aspect-video overflow-hidden bg-base-200">
                    <?php if ($theme['screenshot'] !== ''): ?>
                        <img src="<?= esc($theme['screenshot'], 'attr') ?>" alt="<?= esc($theme['name']) ?>"
                             class="h-full w-full object-cover">
                    <?php else: ?>
                        <span class="text-sm opacity-50">No preview</span>
                    <?php endif; ?>
                </figure>
                <div class="card-body gap-2 p-4">
                    <h3 class="card-title text-base">
                        <?= esc($theme['name']) ?>
                        <?php if ($theme['is_child']): ?>
                            <span class="badge badge-ghost badge-sm">child</span>
                        <?php endif; ?>
                    </h3>
                    <p class="text-xs opacity-70">
                        v<?= esc($theme['version'] ?: '—') ?> by <?= esc($theme['author'] ?: 'Unknown') ?>
                        <?php if ($theme['is_child']): ?>
                            <br>Parent: <code class="rounded bg-base-200 px-1"><?= esc($theme['parent']) ?></code>
                        <?php endif; ?>
                    </p>
                    <div class="card-actions mt-1">
                        <?php if ($theme['is_active']): ?>
                            <span class="badge badge-primary">Active</span>
                        <?php else: ?>
                            <form method="post" action="<?= site_url('admin/wp-themes/' . $theme['slug'] . '/activate') ?>" class="w-full">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-primary btn-sm w-full">Activate</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= view('admin/_footer') ?>
