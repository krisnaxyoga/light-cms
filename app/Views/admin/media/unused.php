<?= view('admin/_header', ['title' => 'Unused Media']) ?>

<div class="alert alert-warning mb-4 text-sm">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z" />
    </svg>
    <span>
        Media with no matching reference in any post's featured image or content
        (PRD §3.2.4 “unused media detector”). This is a heuristic, not a guarantee — review before deleting.
    </span>
</div>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    <?php foreach ($media as $item): ?>
        <div class="card border border-base-300 bg-base-100">
            <?php if (str_starts_with($item['filetype'] ?? '', 'image/')): ?>
                <figure class="aspect-square overflow-hidden bg-base-200">
                    <img src="<?= base_url($item['filepath']) ?>" alt="<?= esc($item['alt_text'] ?? '') ?>"
                         loading="lazy" class="h-full w-full object-cover">
                </figure>
            <?php endif; ?>
            <div class="card-body gap-2 p-3">
                <p class="truncate text-xs" title="<?= esc($item['filename'], 'attr') ?>"><?= esc($item['filename']) ?></p>
                <form method="post" action="<?= site_url('admin/media/' . $item['id'] . '/delete') ?>"
                      onsubmit="return confirm('Delete this file?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-xs w-full text-error">Delete</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (empty($media)): ?>
    <div class="card border border-base-300 bg-base-100">
        <div class="card-body items-center py-10 text-center opacity-60">
            Nothing found — everything appears to be in use.
        </div>
    </div>
<?php endif; ?>

<?= view('admin/_footer') ?>
