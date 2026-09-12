<?= view('admin/_header', ['title' => 'Dashboard']) ?>

<?php if (! empty($notifications)): ?>
    <section class="card mb-6 border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="card-title text-base">Notifications</h2>
                <form method="post" action="<?= site_url('admin/notifications/read-all') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-xs">Mark all read</button>
                </form>
            </div>

            <ul class="flex flex-col divide-y divide-base-300">
                <?php foreach ($notifications as $notification): ?>
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm">
                        <span class="badge badge-sm <?= $notification['type'] === 'error' ? 'badge-error' : 'badge-warning' ?>">
                            <?= esc($notification['type']) ?>
                        </span>
                        <strong><?= esc($notification['title']) ?></strong>
                        <span class="opacity-70"><?= esc($notification['message']) ?></span>
                        <?php if (! empty($notification['action_url'])): ?>
                            <a class="link link-primary" href="<?= esc($notification['action_url'], 'attr') ?>">
                                <?= esc($notification['action_text'] ?: 'View') ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php
$cards = [
    ['label' => 'Posts',             'value' => $stats['total_posts']],
    ['label' => 'Pages',             'value' => $stats['total_pages']],
    ['label' => 'Users',             'value' => $stats['total_users']],
    ['label' => 'Pending comments',  'value' => $stats['pending_comments']],
    ['label' => 'Avg SEO score',     'value' => round($stats['avg_seo_score'])],
    ['label' => 'Peak memory',       'value' => $stats['memory_usage_mb'] . ' MB', 'desc' => 'this request'],
    ['label' => 'writable/ on disk', 'value' => $stats['disk_usage_mb'] . ' MB'],
    ['label' => 'PHP',               'value' => $stats['php_version']],
];
?>
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($cards as $card): ?>
        <div class="stats border border-base-300 bg-base-100">
            <div class="stat gap-1 px-4 py-3">
                <div class="stat-title text-xs"><?= esc($card['label']) ?></div>
                <div class="stat-value text-2xl"><?= esc((string) $card['value']) ?></div>
                <?php if (! empty($card['desc'])): ?>
                    <div class="stat-desc text-xs"><?= esc($card['desc']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Recently updated content</h2>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr><th>Title</th><th>Status</th><th>Updated</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPosts as $post): ?>
                            <tr class="hover">
                                <td>
                                    <a class="link link-hover font-medium" href="<?= site_url('admin/posts/' . $post['id'] . '/edit') ?>">
                                        <?= esc($post['title']) ?>
                                    </a>
                                </td>
                                <td><?= status_badge((string) $post['status']) ?></td>
                                <td class="whitespace-nowrap opacity-70"><?= esc(lcms_time_ago($post['updated_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($recentPosts)): ?>
                            <tr><td colspan="3" class="text-center opacity-60">Nothing published yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Recent activity</h2>
            <ul class="flex flex-col divide-y divide-base-300 text-sm">
                <?php foreach ($recentActivity as $entry): ?>
                    <li class="flex flex-wrap items-center gap-2 py-2">
                        <span class="badge badge-ghost badge-sm"><?= esc($entry['action']) ?></span>
                        <span class="opacity-70">on <?= esc($entry['entity_type']) ?></span>
                        <span class="ml-auto text-xs opacity-60"><?= esc(lcms_time_ago($entry['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>

                <?php if (empty($recentActivity)): ?>
                    <li class="py-2 opacity-60">No activity logged yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </section>
</div>

<?= view('admin/_footer') ?>
