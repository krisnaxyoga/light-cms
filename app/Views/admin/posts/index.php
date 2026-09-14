<?= view('admin/_header', ['title' => ucfirst($postType) . 's']) ?>

<?php $multilang = $multilang ?? false; ?>

<div class="mb-4 flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
        <p class="text-sm opacity-70"><?= (int) $pager->getTotal() ?> total</p>
        <?php if ($multilang): ?>
            <form method="get" class="flex items-center gap-2">
                <?php if (! empty($_GET['status'])): ?>
                    <input type="hidden" name="status" value="<?= esc($_GET['status']) ?>">
                <?php endif; ?>
                <select name="locale" class="select select-bordered select-xs" onchange="this.form.submit()">
                    <option value="">All languages</option>
                    <?php foreach ($languages ?? [] as $lang): ?>
                        <option value="<?= esc($lang['code']) ?>" <?= ($localeFilter ?? '') === $lang['code'] ? 'selected' : '' ?>><?= esc($lang['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </div>
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
                <tr><th class="w-16"></th><th>Title</th><?php if ($multilang): ?><th>Language</th><?php endif; ?><th>Status</th><th>SEO</th><th>Updated</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr class="hover">
                        <td>
                            <a href="<?= site_url('admin/posts/' . $post['id'] . '/edit') ?>" class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-base-300 bg-base-200" title="Edit">
                                <?php if (! empty($post['featured_image'])): ?>
                                    <img src="<?= esc($post['featured_image'], 'attr') ?>" alt="" loading="lazy" class="h-full w-full object-cover">
                                <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 4.5h18v15H3v-15z" />
                                    </svg>
                                <?php endif; ?>
                            </a>
                        </td>
                        <td>
                            <a class="link link-hover font-medium" href="<?= site_url('admin/posts/' . $post['id'] . '/edit') ?>">
                                <?= esc($post['title']) ?>
                            </a>
                        </td>
                        <?php if ($multilang): ?>
                            <td><span class="badge badge-ghost badge-sm font-mono uppercase"><?= esc($post['locale'] ?? '') ?></span></td>
                        <?php endif; ?>
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
                    <tr><td colspan="<?= $multilang ? 7 : 6 ?>" class="py-8 text-center opacity-60">No <?= esc($postType) ?>s yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="lcms-pager mt-4"><?= $pager->links() ?></div>

<?= view('admin/_footer') ?>
