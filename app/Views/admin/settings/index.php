<?= view('admin/_header', ['title' => 'Settings']) ?>

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

<?= view('admin/_footer') ?>
