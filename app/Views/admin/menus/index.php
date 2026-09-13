<?= view('admin/_header', ['title' => 'Menus']) ?>

<style>
    /* Scoped to this screen only — everything else here is daisyUI/Tailwind
       (already compiled into admin-ui.css), this is just the handful of
       rules the nested builder needs that no utility class covers. */
    .lcms-menu-tree, .lcms-menu-tree ul { list-style: none; margin: 0; padding: 0; }
    .lcms-menu-tree ul { margin-top: 6px; }
    .lcms-menu-item { margin-bottom: 6px; }
    .lcms-menu-item__row {
        display: flex; align-items: center; gap: 8px;
        border: 1px solid oklch(var(--b3)); border-radius: var(--rounded-btn, .5rem);
        background: oklch(var(--b1)); padding: 8px 10px; font-size: .8125rem;
    }
    .lcms-menu-item__handle { opacity: .35; font-size: .7rem; letter-spacing: .1em; }
    .lcms-menu-item__title { font-weight: 600; }
    .lcms-menu-item__url { opacity: .55; font-size: .75rem; margin-left: 4px; }
    .lcms-menu-item__actions { margin-left: auto; display: flex; gap: 2px; flex-shrink: 0; }
    .lcms-menu-item__actions button {
        border: none; background: transparent; cursor: pointer; padding: 4px 6px;
        border-radius: .25rem; font-size: .8125rem; line-height: 1;
    }
    .lcms-menu-item__actions button:hover { background: oklch(var(--b2)); }
    .lcms-menu-item__actions button:disabled { opacity: .25; cursor: default; }
    .lcms-menu-item__actions button:disabled:hover { background: transparent; }
    .lcms-menu-item__edit { margin-top: 6px; padding: 10px; border: 1px dashed oklch(var(--b3)); border-radius: var(--rounded-btn, .5rem); }
    .lcms-menu-empty { padding: 24px; text-align: center; opacity: .6; font-size: .875rem; }
    .lcms-menu-results { max-height: 260px; overflow-y: auto; border: 1px solid oklch(var(--b3)); border-radius: var(--rounded-btn, .5rem); }
    .lcms-menu-results label { display: flex; gap: 8px; align-items: baseline; padding: 6px 10px; font-size: .8125rem; cursor: pointer; }
    .lcms-menu-results label:hover { background: oklch(var(--b2)); }
</style>

<div class="mb-4 flex flex-wrap items-center gap-2">
    <?php foreach ($menus as $m): ?>
        <a href="<?= site_url('admin/menus/' . $m['id']) ?>"
           class="btn btn-sm <?= ($menu && (int) $menu['id'] === (int) $m['id']) ? 'btn-primary' : 'btn-ghost' ?>">
            <?= esc($m['name']) ?>
            <?php if (! empty($m['location'])): ?>
                <span class="badge badge-ghost badge-xs ml-1"><?= esc($locations[$m['location']] ?? $m['location']) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>

    <form method="post" action="<?= site_url('admin/menus') ?>" class="flex items-center gap-1">
        <?= csrf_field() ?>
        <input type="text" name="name" placeholder="New menu name" required
               class="input input-bordered input-sm w-40">
        <button type="submit" class="btn btn-sm">+ Create</button>
    </form>
</div>

<?php if (! $menu): ?>
    <div class="card border border-base-300 bg-base-100">
        <div class="card-body items-center py-12 text-center">
            <h2 class="card-title">No menus yet</h2>
            <p class="text-sm opacity-70">Create a menu above, then build it into a silo: add pages/posts/categories on the left, then indent items under a parent to make them sub-topics.</p>
        </div>
    </div>
<?php else: ?>

    <section class="card mb-4 border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <form method="post" id="lcms-menu-settings" action="<?= site_url('admin/menus/' . $menu['id']) ?>" class="flex flex-wrap items-end gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="items_json" id="lcms-menu-items-json">
                <label class="form-control">
                    <div class="label py-1"><span class="label-text">Menu name</span></div>
                    <input type="text" name="name" value="<?= esc($menu['name']) ?>" required class="input input-bordered input-sm w-56">
                </label>
                <label class="form-control">
                    <div class="label py-1"><span class="label-text">Theme location</span></div>
                    <select name="location" class="select select-bordered select-sm w-56">
                        <option value="">— Not assigned —</option>
                        <?php foreach ($locations as $slug => $label): ?>
                            <option value="<?= esc($slug) ?>"
                                <?= $menu['location'] === $slug ? 'selected' : '' ?>
                                <?= in_array($slug, $takenLocations, true) ? 'disabled' : '' ?>>
                                <?= esc($label) ?><?= in_array($slug, $takenLocations, true) ? ' (used by another menu)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" id="lcms-menu-save" class="btn btn-primary btn-sm">Save menu</button>
                <span class="text-sm opacity-60" id="lcms-menu-count"></span>
            </form>

            <form method="post" action="<?= site_url('admin/menus/' . $menu['id'] . '/delete') ?>"
                  onsubmit="return confirm('Delete this menu and all its items?')" class="ml-auto">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost btn-xs text-error">Delete this menu</button>
            </form>
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-[320px_1fr]">
        <section class="card border border-base-300 bg-base-100">
            <div class="card-body gap-3 p-4 md:p-5">
                <h2 class="card-title text-base">Add menu items</h2>

                <div role="tablist" class="tabs tabs-boxed tabs-sm">
                    <a role="tab" class="tab tab-active" data-lcms-tab="custom">Custom link</a>
                    <a role="tab" class="tab" data-lcms-tab="page">Pages</a>
                    <a role="tab" class="tab" data-lcms-tab="post">Posts</a>
                    <a role="tab" class="tab" data-lcms-tab="category">Categories</a>
                </div>

                <div data-lcms-panel="custom" class="flex flex-col gap-2">
                    <label class="form-control w-full">
                        <div class="label py-1"><span class="label-text">Link text</span></div>
                        <input type="text" id="lcms-custom-title" class="input input-bordered input-sm w-full" placeholder="e.g. Contact us">
                    </label>
                    <label class="form-control w-full">
                        <div class="label py-1"><span class="label-text">URL</span></div>
                        <input type="text" id="lcms-custom-url" class="input input-bordered input-sm w-full" placeholder="https:// or /path">
                    </label>
                    <label class="label cursor-pointer justify-start gap-2 py-0">
                        <input type="checkbox" id="lcms-custom-target" class="checkbox checkbox-xs">
                        <span class="label-text">Open in new tab</span>
                    </label>
                    <button type="button" class="btn btn-sm btn-primary" id="lcms-custom-add">Add to menu</button>
                </div>

                <?php foreach (['page' => 'pages', 'post' => 'posts', 'category' => 'categories'] as $type => $noun): ?>
                    <div data-lcms-panel="<?= $type ?>" class="hidden flex-col gap-2">
                        <input type="text" class="input input-bordered input-sm w-full" placeholder="Search <?= $noun ?>&hellip;" data-lcms-search="<?= $type ?>">
                        <div class="lcms-menu-results" data-lcms-results="<?= $type ?>">
                            <p class="p-3 text-xs opacity-50">Start typing, or leave blank and search to list the most recent <?= $noun ?>.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" data-lcms-add-selected="<?= $type ?>">Add selected to menu</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card border border-base-300 bg-base-100">
            <div class="card-body gap-3 p-4 md:p-5">
                <h2 class="card-title text-base">Menu structure</h2>
                <p class="text-sm opacity-70">
                    Drag isn't needed — use <strong>&larr; &rarr;</strong> to nest an item under the one above it (that's how a silo's sub-topics are built) and <strong>&uarr; &darr;</strong> to reorder.
                </p>
                <div id="lcms-menu-tree"></div>
            </div>
        </section>
    </div>

    <script type="application/json" id="lcms-menu-data"><?= json_encode([
        'items'       => $items,
        'searchUrl'   => site_url('admin/menus/search'),
        'multilang'   => $multilang,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <script src="<?= base_url('assets/admin/js/menu-builder.js') ?>"></script>
<?php endif; ?>

<?= view('admin/_footer') ?>
