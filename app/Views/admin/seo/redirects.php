<?= view('admin/_header', ['title' => 'SEO & Redirects']) ?>

<div class="grid gap-4 lg:grid-cols-2">
    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Sitemap &amp; robots.txt</h2>
            <p class="text-sm opacity-70">
                Both regenerate automatically (sitemap on publish/unpublish, robots.txt on settings save) —
                these are the manual “just in case” buttons.
            </p>
            <div class="flex flex-wrap gap-2">
                <form method="post" action="<?= site_url('admin/seo/sitemap') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm">Regenerate sitemap.xml</button>
                </form>
                <form method="post" action="<?= site_url('admin/seo/robots') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm">Regenerate robots.txt</button>
                </form>
            </div>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">New redirect</h2>
            <form method="post" action="<?= site_url('admin/seo/redirects') ?>" class="flex flex-col gap-3">
                <?= csrf_field() ?>
                <div class="grid gap-2 sm:grid-cols-2">
                    <input type="text" name="source_url" placeholder="/old-path" required
                           class="input input-bordered input-sm w-full">
                    <input type="text" name="target_url" placeholder="/new-path or https://…" required
                           class="input input-bordered input-sm w-full">
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <select name="redirect_type" class="select select-bordered select-sm">
                        <option value="301">301 (permanent)</option>
                        <option value="302">302 (temporary)</option>
                        <option value="307">307 (temporary, method-preserving)</option>
                    </select>
                    <label class="label cursor-pointer gap-2 py-0">
                        <input type="checkbox" name="is_regex" class="checkbox checkbox-sm">
                        <span class="label-text">Regex</span>
                    </label>
                    <button type="submit" class="btn btn-sm ml-auto">Add redirect</button>
                </div>
            </form>
        </div>
    </section>
</div>

<div class="card mt-4 border border-base-300 bg-base-100">
    <div class="overflow-x-auto">
        <table class="table table-sm">
            <thead><tr><th>Source</th><th>Target</th><th>Type</th><th>Hits</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($redirects as $redirect): ?>
                    <tr class="hover">
                        <td class="font-mono text-xs"><?= esc($redirect['source_url']) ?></td>
                        <td class="font-mono text-xs"><?= esc($redirect['target_url']) ?></td>
                        <td><span class="badge badge-ghost badge-sm"><?= esc($redirect['redirect_type']) ?></span></td>
                        <td class="opacity-70"><?= (int) $redirect['hits'] ?></td>
                        <td class="text-right">
                            <form method="post" action="<?= site_url('admin/seo/redirects/' . $redirect['id'] . '/delete') ?>"
                                  onsubmit="return confirm('Delete this redirect?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-ghost btn-xs text-error">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($redirects)): ?>
                    <tr><td colspan="5" class="py-8 text-center opacity-60">No redirects configured.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="lcms-pager mt-4"><?= $pager->links() ?></div>

<section class="card mt-4 border border-base-300 bg-base-100">
    <div class="card-body gap-3 p-4 md:p-5">
        <h2 class="card-title text-base">Recent 404s</h2>
        <p class="text-sm opacity-70">PRD ADDENDUM §8: turn a repeated miss into a redirect above.</p>
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead><tr><th>URL</th><th>Referrer</th><th>When</th></tr></thead>
                <tbody>
                    <?php foreach ($notFounds as $entry): ?>
                        <tr class="hover">
                            <td class="font-mono text-xs"><?= esc($entry['url']) ?></td>
                            <td class="truncate text-xs opacity-70"><?= esc($entry['referer'] ?? '—') ?></td>
                            <td class="whitespace-nowrap opacity-70"><?= esc(lcms_time_ago($entry['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($notFounds)): ?>
                        <tr><td colspan="3" class="py-6 text-center opacity-60">No 404s logged yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?= view('admin/_footer') ?>
