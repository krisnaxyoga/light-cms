<?= view('admin/_header', ['title' => 'Settings']) ?>

<?php
// Text input + "Choose from Media Library" button + live thumbnail preview,
// wired up by public/assets/admin/js/homepage.js (initImageFields() there
// binds any [data-lcms-image-field] on the page, not just its own).
$imageField = function (string $name, string $label, string $value, ?string $hint = null) {
    ?>
    <div class="form-control w-full" data-lcms-image-field>
        <div class="label py-1"><span class="label-text"><?= esc($label) ?></span></div>
        <div class="flex items-center gap-3">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-base-300 bg-base-200">
                <img data-role="preview" src="<?= esc($value, 'attr') ?>" alt="" class="h-full w-full object-cover <?= $value === '' ? 'hidden' : '' ?>">
                <span data-role="placeholder" class="text-[10px] uppercase opacity-50 <?= $value !== '' ? 'hidden' : '' ?>">No image</span>
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <input type="text" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>"
                       data-role="url" class="input input-bordered input-sm w-full" placeholder="https://...">
                <div class="flex gap-2">
                    <button type="button" class="btn btn-ghost btn-xs" data-action="browse">Choose from Media Library</button>
                    <button type="button" class="btn btn-ghost btn-xs text-error" data-action="clear">Clear</button>
                </div>
                <?php if ($hint): ?>
                    <span class="text-xs opacity-60"><?= esc($hint) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
};
?>

<a href="<?= site_url('admin/homepage') ?>" class="card mb-4 border border-primary/30 bg-primary/5 transition-colors hover:bg-primary/10">
    <div class="card-body flex-row items-center justify-between gap-3 p-4">
        <div>
            <h2 class="text-sm font-semibold">Homepage content</h2>
            <p class="text-xs opacity-70">Edit every heading, paragraph, price and image on the front page, the Blog header, and the single-post booking box.</p>
        </div>
        <span class="btn btn-primary btn-sm shrink-0">Open &rarr;</span>
    </div>
</a>

<form method="post" action="<?= site_url('admin/settings') ?>" class="grid gap-4 lg:grid-cols-2">
    <?= csrf_field() ?>

    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Site</h2>

            <label class="form-control w-full">
                <div class="label py-1"><span class="label-text">Site title</span></div>
                <input type="text" name="site_title" value="<?= esc($settings['site_title'] ?? '') ?>"
                       class="input input-bordered input-sm w-full">
            </label>

            <label class="form-control w-full">
                <div class="label py-1"><span class="label-text">Site description</span></div>
                <textarea name="site_description" rows="2" class="textarea textarea-bordered w-full"><?= esc($settings['site_description'] ?? '') ?></textarea>
            </label>

            <label class="form-control w-full">
                <div class="label py-1"><span class="label-text">Timezone</span></div>
                <input type="text" name="timezone" value="<?= esc($settings['timezone'] ?? '') ?>"
                       class="input input-bordered input-sm w-full" placeholder="Asia/Makassar">
            </label>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Branding</h2>

            <?= $imageField('site_logo', 'Logo', $settings['site_logo'] ?? '', 'Shown in the header and footer in place of the site title. Leave empty to keep the text logo.') ?>
            <?= $imageField('site_favicon', 'Favicon', $settings['site_favicon'] ?? '', 'Shown in the browser tab. A square PNG or .ico, at least 32×32px, works best.') ?>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Contact</h2>

            <label class="form-control w-full max-w-xs">
                <div class="label py-1"><span class="label-text">WhatsApp number</span></div>
                <input type="text" name="whatsapp_number" value="<?= esc($settings['whatsapp_number'] ?? '') ?>"
                       class="input input-bordered input-sm w-full" placeholder="+62 822 8263 8682">
                <div class="label py-1">
                    <span class="label-text-alt opacity-70">International format with country code. Used to build every WhatsApp button and link on the site — the floating chat button, the footer, and every "Reservar por WhatsApp" CTA. Change it once here instead of hunting down every link.</span>
                </div>
            </label>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Content</h2>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Posts per page</span></div>
                    <input type="number" name="posts_per_page" min="1" max="50"
                           value="<?= esc($settings['posts_per_page'] ?? '10') ?>" class="input input-bordered input-sm w-full">
                </label>

                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Excerpt length (words)</span></div>
                    <input type="number" name="excerpt_length" min="10" max="200"
                           value="<?= esc($settings['excerpt_length'] ?? '55') ?>" class="input input-bordered input-sm w-full">
                </label>
            </div>

            <label class="form-control w-full">
                <div class="label py-1"><span class="label-text">SEO title separator</span></div>
                <input type="text" name="seo_separator" maxlength="3" value="<?= esc($settings['seo_separator'] ?? '-') ?>"
                       class="input input-bordered input-sm w-24">
            </label>

            <label class="form-control w-full">
                <div class="label py-1"><span class="label-text">Default social share image (URL)</span></div>
                <input type="text" name="default_og_image" value="<?= esc($settings['default_og_image'] ?? '') ?>"
                       class="input input-bordered input-sm w-full">
                <div class="label py-1">
                    <span class="label-text-alt opacity-70">Used for Open Graph/Twitter Card when a post has no featured image.</span>
                </div>
            </label>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Analytics</h2>

            <label class="form-control w-full max-w-xs">
                <div class="label py-1"><span class="label-text">Google Analytics measurement ID</span></div>
                <input type="text" name="google_analytics_id" value="<?= esc($settings['google_analytics_id'] ?? '') ?>"
                       class="input input-bordered input-sm w-full" placeholder="G-XXXXXXXXXX">
                <div class="label py-1">
                    <span class="label-text-alt opacity-70">Adds the gtag.js tracking snippet to every page. Leave empty to disable tracking.</span>
                </div>
            </label>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100 lg:col-span-2">
        <div class="card-body gap-3 p-4 md:p-5">
            <h2 class="card-title text-base">Search engines</h2>

            <label class="label cursor-pointer justify-start gap-3 py-1">
                <input type="checkbox" name="robots_index" class="toggle toggle-primary toggle-sm"
                       <?= ($settings['robots_index'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span class="label-text">Allow search engines to index the site</span>
            </label>

            <label class="label cursor-pointer justify-start gap-3 py-1">
                <input type="checkbox" name="robots_follow" class="toggle toggle-primary toggle-sm"
                       <?= ($settings['robots_follow'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span class="label-text">Allow search engines to follow links</span>
            </label>

            <label class="form-control w-full max-w-xs">
                <div class="label py-1"><span class="label-text">Crawl delay (seconds, optional)</span></div>
                <input type="number" name="robots_crawl_delay" min="0" max="60"
                       value="<?= esc($settings['robots_crawl_delay'] ?? '') ?>" class="input input-bordered input-sm w-full">
            </label>

            <p class="text-sm opacity-70">robots.txt is regenerated automatically whenever you save this form.</p>

            <div class="card-actions justify-end">
                <button type="submit" class="btn btn-primary btn-sm">Save settings</button>
            </div>
        </div>
    </section>
</form>

<!-- Media picker modal for the Logo/Favicon fields above, shared with
     Admin -> Homepage's image fields via public/assets/admin/js/homepage.js. -->
<dialog id="lcms-media-modal" class="modal">
    <div class="modal-box max-w-2xl">
        <h3 class="mb-3 text-base font-semibold">Choose an image</h3>
        <div id="lcms-media-modal-grid" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
            <p class="col-span-full py-8 text-center text-sm opacity-60">Loading…</p>
        </div>
        <div class="modal-action">
            <form method="dialog"><button class="btn btn-sm">Cancel</button></form>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
    window.LCMS_HOMEPAGE = {
        mediaListUrl: <?= json_encode(site_url('admin/media/list?type=image')) ?>,
    };
</script>
<script src="<?= base_url('assets/admin/js/homepage.js') ?>"></script>

<?= view('admin/_footer') ?>
