<?php
/**
 * Post / page edit screen — Gutenberg-style block editor (PRD §3.2).
 *
 * Standalone full-screen layout (no admin/_header chrome), same as
 * WordPress's default fullscreen editor. The block editor itself is
 * public/assets/admin/js/block-editor.js; it keeps the hidden
 * <input name="content"> in sync so the form posts exactly what the old
 * JSON textarea did — PostController is unchanged.
 */
$isEdit          = $post !== null;
// A new post opened as a translation (?source=...) is pre-filled from the
// source row; it is still a "create", so $isEdit stays false.
$post            = $post ?? $prefill ?? null;
$multilang       = $multilang ?? false;
$languages       = $languages ?? [];
$translations    = $translations ?? [];
$currentLocale   = $post['locale'] ?? \Config\Services::locale()->defaultCode();
$seo             = $post['seo_meta'] ?? [];
$postCategoryIds = array_map('intval', array_column($post['categories'] ?? [], 'id'));
$postTagNames    = implode(', ', array_column($post['tags'] ?? [], 'name'));
$status          = $post['status'] ?? 'draft';
$isPublished     = $status === 'published';

// If content is stored but doesn't decode into a block array (hand-edited
// row, bad import, truncated column), hand the editor the raw text instead
// of an empty canvas — otherwise opening and saving the post would quietly
// destroy it.
$rawContent   = (string) ($post['content'] ?? '');
$decoded      = json_decode($rawContent, true);
$contentBroke = $rawContent !== '' && ! is_array($decoded);

$editorConfig = [
    'postId'     => $post['id'] ?? null,
    'postType'   => $postType,
    'blocks'     => is_array($decoded) ? $decoded : [],
    'rawContent' => $contentBroke ? $rawContent : null,
    'updatedAt' => ! empty($post['updated_at']) ? date(DATE_ATOM, strtotime($post['updated_at'])) : null,
    // Permalink preview base — carries the language prefix (/id/...) for
    // non-default languages.
    'siteUrl'   => \Config\Services::locale()->url('', $currentLocale),
    'csrf'      => ['name' => csrf_token(), 'hash' => csrf_hash()],
    'endpoints' => [
        'analyze'   => site_url('admin/posts/analyze'),
        'upload'    => site_url('admin/media/upload'),
        'mediaList' => site_url('admin/media/list'),
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isEdit ? 'Edit' : 'New' ?> <?= esc(ucfirst($postType)) ?> &middot; LightCMS</title>
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/block-editor.css') ?>">
</head>
<body class="lcms-admin lcms-editor-page lcms-sidebar-open">

<form method="post"
      action="<?= $isEdit ? site_url('admin/posts/' . $post['id']) : site_url('admin/posts') ?>"
      id="lcms-post-form" class="lcms-editor" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="post_type" value="<?= esc($postType) ?>">
    <input type="hidden" name="content" id="lcms-content" value="<?= esc($post['content'] ?? '[]') ?>">
    <input type="hidden" name="translation_group_id" value="<?= esc($post['translation_group_id'] ?? '') ?>">
    <?php if (! $multilang): ?>
        <input type="hidden" name="locale" value="<?= esc($currentLocale) ?>">
    <?php endif; ?>

    <!-- ============================== Top bar ============================== -->
    <header class="lcms-topbar">
        <div class="lcms-topbar__group">
            <a class="lcms-topbar__btn lcms-topbar__back" href="<?= site_url($postType === 'page' ? 'admin/pages' : 'admin/posts') ?>" title="Back to <?= esc($postType) ?>s" aria-label="Back">
                <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            </a>
            <div class="lcms-topbar__group lcms-topbar__group--tools">
                <button type="button" class="lcms-topbar__btn lcms-topbar__btn--inserter" id="lcms-inserter-toggle" title="Add block" aria-label="Add block">
                    <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/></svg>
                </button>
                <button type="button" class="lcms-topbar__btn" id="lcms-undo" title="Undo (⌘Z)" aria-label="Undo" disabled>
                    <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62C8.77 11.23 10.54 10.5 12.5 10.5c3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg>
                </button>
                <button type="button" class="lcms-topbar__btn" id="lcms-redo" title="Redo (⇧⌘Z)" aria-label="Redo" disabled>
                    <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg>
                </button>
                <span class="lcms-topbar__divider"></span>
                <span class="lcms-topbar__meta" id="lcms-word-count">0 words</span>
                <span class="lcms-topbar__meta">SEO <span id="lcms-seo-score-top" class="lcms-seo-badge lcms-seo-badge--<?= esc(service('seoAnalyzer')->rating((int) ($seo['seo_score'] ?? 0))) ?>"><?= (int) ($seo['seo_score'] ?? 0) ?></span></span>
            </div>
        </div>

        <div class="lcms-topbar__group">
            <span class="lcms-topbar__status" id="lcms-save-indicator"></span>
            <?php if ($isEdit && $isPublished): ?>
                <a class="lcms-topbar__btn" href="<?= post_url($post, $currentLocale) ?>" target="_blank" rel="noopener" title="View on site">
                    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
                    Preview
                </a>
            <?php endif; ?>
            <?php if (! $isPublished): ?>
                <button type="button" class="lcms-topbar__btn" id="lcms-save-draft">Save draft</button>
            <?php endif; ?>
            <button type="button" class="lcms-topbar__btn lcms-topbar__btn--primary" id="lcms-publish"><?= $isPublished ? 'Update' : 'Publish' ?></button>
            <button type="button" class="lcms-topbar__btn" id="lcms-sidebar-toggle" title="Settings" aria-label="Toggle settings sidebar">
                <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6a3.6 3.6 0 1 1 0-7.2 3.6 3.6 0 0 1 0 7.2z"/></svg>
            </button>
            <button type="button" class="lcms-topbar__btn" id="lcms-more" title="Options" aria-label="Options">
                <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
            </button>
        </div>
    </header>

    <div class="lcms-notices" id="lcms-notices">
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="lcms-alert lcms-alert--error">
                <ul><?php foreach (session()->getFlashdata('errors') as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="lcms-editor__main">
        <!-- ============================ Canvas ============================ -->
        <div class="lcms-canvas-wrap" id="lcms-canvas-wrap" data-device="desktop">
            <div class="lcms-editor__paper">
                <textarea class="lcms-title" id="lcms-title" name="title" rows="1" placeholder="Add title" required><?= esc($post['title'] ?? '') ?></textarea>
                <div id="lcms-canvas" class="lcms-canvas"></div>
            </div>
        </div>

        <!-- ============================ Sidebar =========================== -->
        <aside class="lcms-sidebar is-open" id="lcms-sidebar">
            <div class="lcms-sidebar__tabs">
                <button type="button" class="lcms-sidebar__tab is-active" id="lcms-tab-post"><?= esc(ucfirst($postType)) ?></button>
                <button type="button" class="lcms-sidebar__tab" id="lcms-tab-block">Block</button>
            </div>

            <div class="lcms-sidebar__panel" id="lcms-panel-post">
                <details class="lcms-panel-group" open>
                    <summary>Summary</summary>
                    <div class="lcms-panel-group__body">
                        <div class="lcms-field">
                            <label>Status</label>
                            <select name="status">
                                <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled'] as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lcms-field">
                            <label>Publish date</label>
                            <input type="datetime-local" name="published_at" value="<?= esc(str_replace(' ', 'T', substr($post['published_at'] ?? '', 0, 16))) ?>">
                            <span class="lcms-hint">Leave empty to publish immediately; pick a future date with status “Scheduled”.</span>
                        </div>
                        <div class="lcms-field">
                            <label>Permalink</label>
                            <input type="text" name="slug" value="<?= esc($post['slug'] ?? '') ?>" placeholder="auto-generated from title">
                            <span class="lcms-permalink" id="lcms-permalink-preview"></span>
                        </div>
                        <label class="lcms-check"><input type="checkbox" name="comment_status" <?= ($post['comment_status'] ?? 'open') === 'open' ? 'checked' : '' ?>> Allow comments</label>
                        <?php if ($isPublished): ?>
                            <button type="button" class="lcms-btn" id="lcms-switch-draft">Switch to draft</button>
                        <?php endif; ?>
                    </div>
                </details>

                <?php if ($multilang): ?>
                    <details class="lcms-panel-group" open>
                        <summary>Language &amp; translations</summary>
                        <div class="lcms-panel-group__body">
                            <div class="lcms-field">
                                <label>Language</label>
                                <select name="locale">
                                    <?php foreach ($languages as $lang): ?>
                                        <option value="<?= esc($lang['code']) ?>" <?= $currentLocale === $lang['code'] ? 'selected' : '' ?>>
                                            <?= esc($lang['name']) ?><?= (int) $lang['is_default'] === 1 ? ' (default)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if (! empty($post['source_title'])): ?>
                                <span class="lcms-hint">Translating “<?= esc($post['source_title']) ?>” — replace the copied text with the translation, then publish.</span>
                            <?php endif; ?>

                            <?php if ($isEdit): ?>
                                <?php foreach ($languages as $lang): ?>
                                    <?php if ($lang['code'] === $currentLocale) { continue; } ?>
                                    <?php $sibling = $translations[$lang['code']] ?? null; ?>
                                    <div class="lcms-field__row">
                                        <span style="flex:1"><?= esc($lang['name']) ?></span>
                                        <?php if ($sibling): ?>
                                            <a class="lcms-mini-btn" href="<?= site_url('admin/posts/' . $sibling['id'] . '/edit') ?>" title="<?= esc($sibling['title'], 'attr') ?>">
                                                Edit (<?= esc($sibling['status']) ?>)
                                            </a>
                                        <?php else: ?>
                                            <a class="lcms-mini-btn" href="<?= site_url('admin/' . ($postType === 'page' ? 'pages' : 'posts') . '/create?source=' . $post['id'] . '&locale=' . $lang['code']) ?>">
                                                + Add translation
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif (empty($post['source_title'])): ?>
                                <span class="lcms-hint">Save first — then you can add translations from here.</span>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <details class="lcms-panel-group" open>
                    <summary>Featured image</summary>
                    <div class="lcms-panel-group__body">
                        <img class="lcms-featured-preview" data-lcms-preview-for="[name=featured_image]" alt="" hidden>
                        <div class="lcms-field__row">
                            <input type="text" name="featured_image" value="<?= esc($post['featured_image'] ?? '') ?>" placeholder="https://…">
                            <button type="button" class="lcms-btn" data-lcms-media-target="[name=featured_image]">Choose</button>
                        </div>
                        <button type="button" class="lcms-mini-btn" data-lcms-clear="[name=featured_image]">Remove featured image</button>
                    </div>
                </details>

                <details class="lcms-panel-group">
                    <summary>Excerpt</summary>
                    <div class="lcms-panel-group__body">
                        <textarea name="excerpt" rows="3" placeholder="Auto-generated from content when empty"><?= esc($post['excerpt'] ?? '') ?></textarea>
                    </div>
                </details>

                <?php if ($postType === 'post'): ?>
                    <details class="lcms-panel-group" open>
                        <summary>Categories</summary>
                        <div class="lcms-panel-group__body">
                            <?php if (empty($categories)): ?>
                                <span class="lcms-hint">No categories yet.</span>
                            <?php endif; ?>
                            <?php foreach ($categories as $category): ?>
                                <label class="lcms-check">
                                    <input type="checkbox" name="categories[]" value="<?= (int) $category['id'] ?>" <?= in_array((int) $category['id'], $postCategoryIds, true) ? 'checked' : '' ?>>
                                    <?= esc($category['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <details class="lcms-panel-group" open>
                        <summary>Tags</summary>
                        <div class="lcms-panel-group__body">
                            <input type="text" name="tags" value="<?= esc($postTagNames) ?>" placeholder="comma, separated, tags">
                        </div>
                    </details>
                <?php endif; ?>

                <details class="lcms-panel-group" open>
                    <summary>SEO <span id="lcms-seo-score" class="lcms-seo-badge lcms-seo-badge--<?= esc(service('seoAnalyzer')->rating((int) ($seo['seo_score'] ?? 0))) ?>"><?= (int) ($seo['seo_score'] ?? 0) ?></span></summary>
                    <div class="lcms-panel-group__body">
                        <div class="lcms-field">
                            <label>Focus keyword</label>
                            <input type="text" name="focus_keyword" value="<?= esc($seo['focus_keyword'] ?? '') ?>">
                        </div>
                        <div class="lcms-field">
                            <label>SEO title</label>
                            <input type="text" name="meta_title" value="<?= esc($seo['meta_title'] ?? '') ?>" placeholder="%title% - %sitename%">
                        </div>
                        <div class="lcms-field">
                            <label>Meta description</label>
                            <textarea name="meta_description" rows="3" maxlength="320" placeholder="Auto-generated from content when empty"><?= esc($seo['meta_description'] ?? '') ?></textarea>
                            <span class="lcms-hint"><span id="lcms-meta-desc-count">0</span>/320 · sweet spot 120–155</span>
                        </div>
                        <div class="lcms-field">
                            <label>Canonical URL</label>
                            <input type="text" name="canonical_url" value="<?= esc($seo['canonical_url'] ?? '') ?>">
                        </div>
                        <div class="lcms-field">
                            <label>Social share image</label>
                            <div class="lcms-field__row">
                                <input type="text" name="og_image" value="<?= esc($seo['og_image'] ?? '') ?>" placeholder="Falls back to featured image">
                                <button type="button" class="lcms-btn" data-lcms-media-target="[name=og_image]">Choose</button>
                            </div>
                        </div>
                        <label class="lcms-check"><input type="checkbox" name="robots_index" <?= ($seo['robots_index'] ?? 1) ? 'checked' : '' ?>> Allow indexing</label>
                        <label class="lcms-check"><input type="checkbox" name="robots_follow" <?= ($seo['robots_follow'] ?? 1) ? 'checked' : '' ?>> Follow links</label>
                        <button type="button" id="lcms-analyze-btn" class="lcms-btn">Analyze now</button>
                        <ul id="lcms-seo-suggestions" class="lcms-seo-suggestions"></ul>
                    </div>
                </details>
            </div>

            <div class="lcms-sidebar__panel" id="lcms-panel-block" hidden></div>
        </aside>
    </div>
</form>

<script type="application/json" id="lcms-editor-config"><?= json_encode($editorConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= base_url('assets/admin/js/block-editor.js') ?>"></script>
<script>
    LcmsBlockEditor.init(JSON.parse(document.getElementById('lcms-editor-config').textContent));
</script>
</body>
</html>
