<?= view('admin/_header', ['title' => 'Media Library']) ?>

<div class="mb-4 flex flex-wrap items-center gap-2">
    <form method="get" class="join">
        <input type="text" name="q" value="<?= esc($term ?? '') ?>" placeholder="Search filename…"
               class="input input-bordered input-sm join-item w-56">
        <button type="submit" class="btn btn-sm join-item">Search</button>
    </form>
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/media/unused') ?>">Show unused</a>
</div>

<form id="lcms-upload-form" class="card mb-6 border border-dashed border-base-300 bg-base-100">
    <div class="card-body flex-row flex-wrap items-center gap-3 p-4">
        <input type="file" name="file" id="lcms-upload-input" accept="image/*,.pdf,.mp4,.mp3"
               class="file-input file-input-bordered file-input-sm w-full max-w-xs">
        <span id="lcms-upload-status" class="text-sm opacity-70"></span>
    </div>
</form>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    <?php foreach ($media as $item): ?>
        <div class="card border border-base-300 bg-base-100">
            <figure class="aspect-square overflow-hidden bg-base-200">
                <?php if (str_starts_with($item['filetype'] ?? '', 'image/')): ?>
                    <img src="<?= base_url($item['filepath']) ?>" alt="<?= esc($item['alt_text'] ?? '') ?>"
                         loading="lazy" class="h-full w-full object-cover">
                <?php else: ?>
                    <span class="badge badge-ghost"><?= esc($item['filetype'] ?? 'file') ?></span>
                <?php endif; ?>
            </figure>
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
        <div class="card-body items-center py-10 text-center opacity-60">No media uploaded yet.</div>
    </div>
<?php endif; ?>

<script>
document.getElementById('lcms-upload-input').addEventListener('change', function () {
    var file = this.files[0];
    if (!file) { return; }

    var data = new FormData();
    data.append('file', file);
    data.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    document.getElementById('lcms-upload-status').textContent = 'Uploading...';

    fetch('<?= site_url('admin/media/upload') ?>', { method: 'POST', body: data })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            document.getElementById('lcms-upload-status').textContent = result.error || 'Uploaded!';
            if (!result.error) { window.location.reload(); }
        });
});
</script>

<?= view('admin/_footer') ?>
