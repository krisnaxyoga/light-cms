<?= view('admin/_header', ['title' => 'Media Library']) ?>

<style>
    /* Scoped to this screen — everything else here is daisyUI/Tailwind
       (compiled into admin-ui.css); these are the handful of rules the
       compression picker needs that no utility class covers. */
    .lcms-compress-option { transition: border-color .15s, background .15s; }
    .lcms-compress-option:hover { border-color: oklch(var(--p) / .5); }
    .lcms-compress-option--active { border-color: oklch(var(--p)); background: oklch(var(--p) / .08); }
</style>

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

    <!-- Compression picker: appears for image files only (any size/format —
         the browser decodes it, so this works regardless) once the browser
         has drawn a few size/quality candidates onto a <canvas>. Non-image
         files (PDF/MP4/MP3) skip straight to uploading, unchanged. -->
    <div id="lcms-compress-picker" class="card-body gap-3 border-t border-base-300 p-4" hidden>
        <p class="text-sm opacity-70">Choose a compressed version to upload — the server still re-processes it either way, this just saves the upload itself being larger than it needs to be.</p>
        <div id="lcms-compress-options" class="grid grid-cols-2 gap-3 sm:grid-cols-4"></div>
        <div class="flex items-center gap-2">
            <button type="button" id="lcms-compress-upload" class="btn btn-primary btn-sm" disabled>Upload selected</button>
            <button type="button" id="lcms-compress-cancel" class="btn btn-ghost btn-sm">Cancel</button>
        </div>
    </div>
</form>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    <?php foreach ($media as $item): ?>
        <div class="card border border-base-300 bg-base-100" data-media-id="<?= (int) $item['id'] ?>">
            <figure class="aspect-square overflow-hidden bg-base-200">
                <?php if (str_starts_with($item['filetype'] ?? '', 'image/')): ?>
                    <img src="<?= base_url($item['filepath']) ?>" alt="<?= esc($item['alt_text'] ?? '') ?>"
                         loading="lazy" class="h-full w-full object-cover" data-media-preview>
                <?php else: ?>
                    <span class="badge badge-ghost"><?= esc($item['filetype'] ?? 'file') ?></span>
                <?php endif; ?>
            </figure>
            <div class="card-body gap-2 p-3">
                <p class="truncate text-xs" title="<?= esc($item['filename'], 'attr') ?>" data-media-filename-display><?= esc($item['filename']) ?></p>

                <div class="flex gap-1">
                    <button type="button" class="btn btn-ghost btn-xs flex-1" data-media-edit-toggle>Edit</button>
                    <form method="post" action="<?= site_url('admin/media/' . $item['id'] . '/delete') ?>"
                          onsubmit="return confirm('Delete this file?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-ghost btn-xs text-error">Delete</button>
                    </form>
                </div>

                <div class="lcms-media-edit hidden flex flex-col gap-2" data-media-edit-panel>
                    <label class="form-control w-full">
                        <div class="label py-0.5"><span class="label-text text-xs">Title</span></div>
                        <input type="text" class="input input-bordered input-xs w-full" data-media-field="title" value="<?= esc($item['title'] ?? '') ?>">
                    </label>
                    <label class="form-control w-full">
                        <div class="label py-0.5"><span class="label-text text-xs">Alt text</span></div>
                        <input type="text" class="input input-bordered input-xs w-full" data-media-field="alt_text" value="<?= esc($item['alt_text'] ?? '') ?>">
                    </label>
                    <label class="form-control w-full">
                        <div class="label py-0.5"><span class="label-text text-xs">File name</span></div>
                        <div class="join w-full">
                            <input type="text" class="input input-bordered input-xs join-item w-full" data-media-field="filename"
                                   value="<?= esc(pathinfo($item['filename'], PATHINFO_FILENAME)) ?>">
                            <span class="btn btn-xs btn-disabled join-item">.<?= esc(pathinfo($item['filename'], PATHINFO_EXTENSION)) ?></span>
                        </div>
                    </label>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn btn-primary btn-xs flex-1" data-media-save>Save</button>
                        <span class="text-xs opacity-60" data-media-status></span>
                    </div>
                </div>
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
(function () {
    var fileInput   = document.getElementById('lcms-upload-input');
    var statusEl    = document.getElementById('lcms-upload-status');
    var picker      = document.getElementById('lcms-compress-picker');
    var optionsEl   = document.getElementById('lcms-compress-options');
    var uploadBtn   = document.getElementById('lcms-compress-upload');
    var cancelBtn   = document.getElementById('lcms-compress-cancel');

    // Candidates offered for the user to pick from — the point isn't one
    // "correct" answer, it's letting them trade size for quality themselves.
    // Longest side is capped per preset (never upscaled — see compressTo()),
    // quality is the canvas JPEG/WebP encoder quality (0-1).
    var PRESETS = [
        { label: 'Light',    maxSide: 2000, quality: 0.85 },
        { label: 'Balanced', maxSide: 1600, quality: 0.75 },
        { label: 'Small',    maxSide: 1000, quality: 0.6 },
    ];

    var selectedBlob = null;
    var selectedExt  = 'webp';
    var originalName = '';

    function formatSize(bytes) {
        return bytes < 1024 ? bytes + ' B' : (bytes / 1024).toFixed(0) + ' KB';
    }

    /** Feature-detect canvas WebP encoding once; falls back to JPEG where it's missing. */
    function supportsCanvasWebP() {
        var c = document.createElement('canvas');
        c.width = c.height = 1;
        return c.toDataURL('image/webp').indexOf('data:image/webp') === 0;
    }

    var outputType = supportsCanvasWebP() ? 'image/webp' : 'image/jpeg';
    var outputExt  = outputType === 'image/webp' ? 'webp' : 'jpg';

    /**
     * Draws $img onto a canvas no larger than maxSide on its longest side
     * (never upscales a smaller original) and encodes it at $quality.
     * Orientation: browsers already decode <img> pixel data pre-rotated
     * for EXIF orientation, so drawImage() here needs no extra handling.
     */
    function compressTo(img, maxSide, quality) {
        var scale = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
        var w = Math.max(1, Math.round(img.naturalWidth * scale));
        var h = Math.max(1, Math.round(img.naturalHeight * scale));

        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        canvas.getContext('2d').drawImage(img, 0, 0, w, h);

        return new Promise(function (resolve) {
            canvas.toBlob(function (blob) { resolve({ blob: blob, width: w, height: h }); }, outputType, quality);
        });
    }

    function renderOption(container, key, label, blob, width, height, isDefault) {
        var url = URL.createObjectURL(blob);
        var wrap = document.createElement('label');
        wrap.className = 'lcms-compress-option flex cursor-pointer flex-col gap-1 rounded border border-base-300 p-2 text-center text-xs';

        wrap.innerHTML =
            '<input type="radio" name="lcms-compress-choice" class="hidden" value="' + key + '"' + (isDefault ? ' checked' : '') + '>' +
            '<img src="' + url + '" class="aspect-square w-full rounded object-cover" alt="">' +
            '<span class="font-medium">' + label + '</span>' +
            '<span class="opacity-60">' + width + '&times;' + height + ' &middot; ' + formatSize(blob.size) + '</span>';

        var input = wrap.querySelector('input');
        input.addEventListener('change', function () {
            container.querySelectorAll('.lcms-compress-option').forEach(function (el) { el.classList.remove('lcms-compress-option--active'); });
            wrap.classList.add('lcms-compress-option--active');
            selectedBlob = blob;
            selectedExt  = key === 'original' ? (originalName.split('.').pop() || outputExt) : outputExt;
            uploadBtn.disabled = false;
        });

        container.appendChild(wrap);

        if (isDefault) {
            input.dispatchEvent(new Event('change'));
        }
    }

    function resetPicker() {
        picker.hidden = true;
        optionsEl.innerHTML = '';
        selectedBlob = null;
        uploadBtn.disabled = true;
        fileInput.value = '';
    }

    function doUpload(file) {
        var data = new FormData();
        data.append('file', file, file.name || ('upload.' + selectedExt));
        data.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

        statusEl.textContent = 'Uploading…';

        fetch('<?= site_url('admin/media/upload') ?>', { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                statusEl.textContent = result.error || 'Uploaded!';
                if (!result.error) { window.location.reload(); }
            })
            .catch(function () { statusEl.textContent = 'Upload failed.'; });
    }

    fileInput.addEventListener('change', function () {
        var file = this.files[0];

        if (!file) {
            return;
        }

        // Non-images (PDF/MP4/MP3) skip compression entirely — nothing to
        // pick between, just upload it as before.
        if (file.type.indexOf('image/') !== 0) {
            doUpload(file);
            return;
        }

        originalName = file.name;

        var img = new Image();
        img.onload = function () {
            optionsEl.innerHTML = '';

            // The original is always an option too — compression here is a
            // convenience, never a requirement.
            renderOption(optionsEl, 'original', 'Original', file, img.naturalWidth, img.naturalHeight, false);

            Promise.all(PRESETS.map(function (preset) {
                return compressTo(img, preset.maxSide, preset.quality).then(function (r) {
                    return { key: preset.label.toLowerCase(), label: preset.label, blob: r.blob, width: r.width, height: r.height };
                });
            })).then(function (results) {
                results.forEach(function (r, i) {
                    // "Balanced" pre-selected — a sensible default without
                    // making the user decide before they've even seen sizes.
                    renderOption(optionsEl, r.key, r.label, r.blob, r.width, r.height, r.label === 'Balanced');
                });
                picker.hidden = false;
                URL.revokeObjectURL(img.src);
            });
        };
        img.onerror = function () {
            // Couldn't be decoded as an image client-side for some reason —
            // don't block the upload, just skip straight to it.
            doUpload(file);
        };
        img.src = URL.createObjectURL(file);
    });

    uploadBtn.addEventListener('click', function () {
        if (!selectedBlob) {
            return;
        }

        var base = (originalName.replace(/\.[^.]+$/, '') || 'upload');
        var namedFile = new File([selectedBlob], base + '.' + selectedExt, { type: selectedBlob.type || 'application/octet-stream' });

        doUpload(namedFile);
        resetPicker();
    });

    cancelBtn.addEventListener('click', resetPicker);
})();

// Media edit panel: toggle open, save title/alt text/filename via the
// same admin/media/{id} endpoint MediaController::update() exposes.
// Filename is edited without its extension (kept fixed, shown as a
// disabled suffix) — see the "join" input group in the panel above.
document.querySelectorAll('[data-media-edit-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var card  = btn.closest('[data-media-id]');
        var panel = card.querySelector('[data-media-edit-panel]');
        panel.classList.toggle('hidden');
    });
});

document.querySelectorAll('[data-media-save]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var card   = btn.closest('[data-media-id]');
        var id     = card.getAttribute('data-media-id');
        var status = card.querySelector('[data-media-status]');
        var body   = new FormData();

        body.append('title', card.querySelector('[data-media-field="title"]').value);
        body.append('alt_text', card.querySelector('[data-media-field="alt_text"]').value);
        body.append('filename', card.querySelector('[data-media-field="filename"]').value);
        body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

        status.textContent = 'Saving…';

        fetch('<?= site_url('admin/media') ?>/' + id, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result.error) {
                    status.textContent = result.error;
                    return;
                }

                status.textContent = 'Saved';
                card.querySelector('[data-media-filename-display]').textContent = result.filename;
                card.querySelector('[data-media-filename-display]').title = result.filename;

                var img = card.querySelector('[data-media-preview]');
                if (img) {
                    img.src = result.url + '?t=' + Date.now(); // cache-bust after a rename
                    img.alt = result.alt;
                }

                setTimeout(function () { status.textContent = ''; }, 1500);
            })
            .catch(function () { status.textContent = 'Failed to save.'; });
    });
});
</script>

<?= view('admin/_footer') ?>
