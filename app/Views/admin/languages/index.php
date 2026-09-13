<?= view('admin/_header', ['title' => 'Languages']) ?>

<?php $default = null; foreach ($languages as $lang) { if ((int) $lang['is_default'] === 1) { $default = $lang; break; } } ?>

<div class="grid gap-4 lg:grid-cols-2">
    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Multi-language</h2>

            <form method="post" action="<?= site_url('admin/languages/toggle') ?>">
                <?= csrf_field() ?>
                <label class="label cursor-pointer justify-start gap-3 py-1">
                    <input type="checkbox" name="multilang_enabled" class="toggle toggle-primary"
                           onchange="this.form.submit()" <?= $enabled ? 'checked' : '' ?>>
                    <span class="label-text font-medium">
                        <?= $enabled ? 'Enabled' : 'Disabled' ?>
                        <span class="badge badge-sm <?= $enabled ? 'badge-success' : 'badge-ghost' ?> ml-2"><?= $enabled ? 'on' : 'off' ?></span>
                    </span>
                </label>
            </form>

            <?php if ($enabled): ?>
                <p class="text-sm opacity-70">
                    The default language (<strong><?= esc($default['name'] ?? 'English') ?></strong>) keeps plain URLs like
                    <code>/your-post</code>. Every other active language is served under its prefix, e.g.
                    <code>/id/your-post</code>, with a language switcher in the theme and
                    <code>hreflang</code> tags + a multilingual sitemap generated automatically.
                </p>
            <?php else: ?>
                <p class="text-sm opacity-70">
                    The site currently runs in a single language. Turn this on, then add the languages you
                    want below — each gets its own URL prefix (for example <code>/id</code> for Indonesian).
                </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Add a language</h2>
            <form method="post" action="<?= site_url('admin/languages') ?>" class="flex flex-col gap-3">
                <?= csrf_field() ?>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="form-control w-full">
                        <div class="label py-1"><span class="label-text">Code (ISO)</span></div>
                        <input type="text" name="code" value="<?= esc(old('code', '')) ?>" placeholder="id" required
                               maxlength="10" class="input input-bordered input-sm w-full">
                    </label>
                    <label class="form-control w-full">
                        <div class="label py-1"><span class="label-text">URL prefix</span></div>
                        <input type="text" name="url_prefix" value="<?= esc(old('url_prefix', '')) ?>" placeholder="same as code"
                               maxlength="10" class="input input-bordered input-sm w-full">
                    </label>
                    <label class="form-control w-full">
                        <div class="label py-1"><span class="label-text">Name</span></div>
                        <input type="text" name="name" value="<?= esc(old('name', '')) ?>" placeholder="Indonesian" required
                               maxlength="100" class="input input-bordered input-sm w-full">
                    </label>
                    <label class="form-control w-full">
                        <div class="label py-1"><span class="label-text">Native name (shown in the switcher)</span></div>
                        <input type="text" name="native_name" value="<?= esc(old('native_name', '')) ?>" placeholder="Bahasa Indonesia"
                               maxlength="100" class="input input-bordered input-sm w-full">
                    </label>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs opacity-60">Reserved prefixes: <?= esc(implode(', ', $reserved)) ?></span>
                    <button type="submit" class="btn btn-primary btn-sm ml-auto">Add language</button>
                </div>
            </form>
        </div>
    </section>
</div>

<div class="card mt-4 border border-base-300 bg-base-100">
    <div class="overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Language</th>
                    <th>Code</th>
                    <th>URL prefix</th>
                    <th>Example URL</th>
                    <th>Content</th>
                    <th>Active</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($languages as $lang): ?>
                    <?php $isDefault = (int) $lang['is_default'] === 1; $formId = 'lang-' . (int) $lang['id']; ?>
                    <tr class="hover align-top">
                        <td>
                            <form id="<?= $formId ?>" method="post" action="<?= site_url('admin/languages/' . $lang['id']) ?>"><?= csrf_field() ?></form>
                            <div class="flex flex-col gap-1">
                                <input type="text" name="name" form="<?= $formId ?>" value="<?= esc($lang['name']) ?>" required
                                       class="input input-bordered input-xs w-40" placeholder="Name">
                                <input type="text" name="native_name" form="<?= $formId ?>" value="<?= esc($lang['native_name'] ?? '') ?>"
                                       class="input input-bordered input-xs w-40" placeholder="Native name">
                            </div>
                            <?php if ($isDefault): ?><span class="badge badge-primary badge-sm mt-1">default</span><?php endif; ?>
                        </td>
                        <td>
                            <input type="text" name="code" form="<?= $formId ?>" value="<?= esc($lang['code']) ?>" required
                                   maxlength="10" class="input input-bordered input-xs w-20 font-mono">
                        </td>
                        <td>
                            <input type="text" name="url_prefix" form="<?= $formId ?>" value="<?= esc($lang['url_prefix']) ?>" required
                                   maxlength="10" class="input input-bordered input-xs w-24 font-mono">
                        </td>
                        <td class="font-mono text-xs opacity-70">
                            <?= $isDefault ? esc(base_url('your-post')) : esc(base_url($lang['url_prefix'] . '/your-post')) ?>
                        </td>
                        <td class="opacity-70"><?= (int) ($postCounts[$lang['code']] ?? 0) ?> items</td>
                        <td>
                            <?php if ($isDefault): ?>
                                <span class="badge badge-success badge-sm">always</span>
                            <?php else: ?>
                                <form method="post" action="<?= site_url('admin/languages/' . $lang['id'] . '/toggle') ?>">
                                    <?= csrf_field() ?>
                                    <input type="checkbox" class="toggle toggle-primary toggle-sm" onchange="this.form.submit()"
                                           <?= (int) $lang['is_active'] === 1 ? 'checked' : '' ?>>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex flex-wrap justify-end gap-1">
                                <button type="submit" form="<?= $formId ?>" class="btn btn-ghost btn-xs">Save</button>
                                <?php if (! $isDefault): ?>
                                    <form method="post" action="<?= site_url('admin/languages/' . $lang['id'] . '/default') ?>"
                                          onsubmit="return confirm('Make <?= esc($lang['name'], 'js') ?> the default language? Its URLs lose the prefix and the current default gets one.')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-ghost btn-xs">Make default</button>
                                    </form>
                                    <form method="post" action="<?= site_url('admin/languages/' . $lang['id'] . '/delete') ?>"
                                          onsubmit="return confirm('Delete this language?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-ghost btn-xs text-error">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($languages)): ?>
                    <tr><td colspan="7" class="py-8 text-center opacity-60">No languages configured.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="mt-3 text-xs opacity-60">
    Each post or page belongs to one language. Open a post and use its “Translations” panel to create the
    same content in another language — the copies are linked, so the language switcher and
    <code>hreflang</code> tags point at the right translation.
</p>

<?= view('admin/_footer') ?>
