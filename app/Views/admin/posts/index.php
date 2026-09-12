<?= view('admin/_header', ['title' => ucfirst($postType) . 's']) ?>

<div class="mb-4 flex items-center justify-between gap-3">
    <p class="text-sm opacity-70"><?= (int) $pager->getTotal() ?> total</p>
    <a class="btn btn-primary btn-sm" href="<?= site_url('admin/' . ($postType === 'page' ? 'pages' : 'posts') . '/create') ?>">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        New <?= esc(ucfirst($postType)) ?>
    </a>
</div>

<div class="card border border-base-300 bg-base-100">
    <div class="overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr><th>Title</th><th>Status</th><th>SEO</th><th>Updated</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr class="hover">
                        <td>
                            <a class="link link-hover font-medium" href="<?= site_url('admin/posts/' . $post['id'] . '/edit') ?>">
                                <?= esc($post['title']) ?>
                            </a>
                        </td>
                        <td><?= status_badge((string) $post['status']) ?></td>
                        <td><?= $post['seo_score'] !== null ? seo_score_badge((int) $post['seo_score']) : '<span class="opacity-40">&mdash;</span>' ?></td>
                        <td class="whitespace-nowrap opacity-70"><?= esc(lcms_time_ago($post['updated_at'])) ?></td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <?php if ($post['status'] !== 'trash'): ?>
                                    <form method="post" action="<?= site_url('admin/posts/' . $post['id'] . '/trash') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-ghost btn-xs">Trash</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= site_url('admin/posts/' . $post['id'] . '/restore') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-ghost btn-xs">Restore</button>
                                    </form>
                                    <form method="post" action="<?= site_url('admin/posts/' . $post['id'] . '/delete') ?>" onsubmit="return confirm('Delete permanently?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-ghost btn-xs text-error">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($posts)): ?>
                    <tr><td colspan="5" class="py-8 text-center opacity-60">No <?= esc($postType) ?>s yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="lcms-pager mt-4"><?= $pager->links() ?></div>

<?= view('admin/_footer') ?>
