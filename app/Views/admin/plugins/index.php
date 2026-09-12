<?= view('admin/_header', ['title' => 'Plugins']) ?>

<?php if (! $enabled): ?>
    <div class="alert alert-error mb-4 text-sm">
        <span>
            The WordPress compatibility layer is disabled in
            <code class="rounded bg-base-200 px-1">app/Config/WordPress.php</code>
            (<code class="rounded bg-base-200 px-1">$enabled = false</code>).
            Plugins cannot be activated until it is switched on.
        </span>
    </div>
<?php endif; ?>

<?php foreach ($failures as $failure): ?>
    <div class="alert alert-error mb-4 text-sm">
        <span><strong><?= esc($failure['plugin']) ?></strong> was deactivated after an error: <?= esc($failure['error']) ?></span>
    </div>
<?php endforeach; ?>

<p class="mb-4 text-sm opacity-70">
    Drop a plugin folder into <code class="rounded bg-base-200 px-1">public/wp-content/plugins/</code> and it shows up here.
    Check one before activating with <code class="rounded bg-base-200 px-1">php spark wp:doctor</code>.
</p>

<div class="card border border-base-300 bg-base-100">
    <div class="overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr><th>Plugin</th><th>Version</th><th>Description</th><th>Status</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($plugins === []): ?>
                    <tr>
                        <td colspan="5" class="py-8 text-center opacity-60">
                            No plugins found in <code class="rounded bg-base-200 px-1">public/wp-content/plugins/</code>.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($plugins as $file => $plugin): ?>
                    <?php
                    $isActive = in_array($file, $active, true);
                    $token    = rtrim(strtr(base64_encode($file), '+/', '-_'), '=');
                    ?>
                    <tr class="hover">
                        <td>
                            <div class="font-medium"><?= esc($plugin['Name']) ?></div>
                            <div class="font-mono text-xs opacity-60"><?= esc($file) ?></div>
                        </td>
                        <td class="whitespace-nowrap opacity-70"><?= esc($plugin['Version'] ?: '—') ?></td>
                        <td class="max-w-md text-xs opacity-70">
                            <?= esc(mb_strimwidth(strip_tags($plugin['Description']), 0, 140, '…')) ?>
                        </td>
                        <td>
                            <span class="badge badge-sm <?= $isActive ? 'badge-success' : 'badge-ghost' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <form method="post" action="<?= site_url('admin/plugins/' . $token . ($isActive ? '/deactivate' : '/activate')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-xs <?= $isActive ? 'btn-ghost' : 'btn-primary' ?>">
                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= view('admin/_footer') ?>
