<?php
/**
 * Admin -> Homepage: every wording, CTA link and image on the Super
 * Travel front page, the /blog header and the single-post booking CTA.
 * One JSON document (App\Libraries\Homepage\HomepageContent), rendered
 * here as a per-section accordion with generic field/repeater helpers so
 * adding a schema field only means editing HomepageContent, not this view.
 *
 * Field naming matches HomepageContent::fromPost(): <section>[<field>],
 * or <section>[<field>][<index>][<subfield>] inside a repeater.
 */

/** @var array $content */
/** @var array $schema */

$v = static fn (array $section, string $key, $default = '') => $section[$key] ?? $default;

// -- Field renderers ------------------------------------------------------
// Each takes the fully-qualified name prefix ("hero[title]") and prints
// one form-control. Kept as closures (not a helper file) since this
// naming/markup only makes sense wired to this one screen.

$textField = function (string $name, string $label, string $value, array $opts = []) {
    $isTextarea = ! empty($opts['textarea']);
    $hint       = $opts['hint'] ?? null;
    ?>
    <label class="form-control w-full">
        <div class="label py-1"><span class="label-text"><?= esc($label) ?></span></div>
        <?php if ($isTextarea): ?>
            <textarea name="<?= esc($name, 'attr') ?>" rows="<?= (int) ($opts['rows'] ?? 2) ?>"
                      class="textarea textarea-bordered textarea-sm w-full"><?= esc($value) ?></textarea>
        <?php else: ?>
            <input type="text" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>"
                   class="input input-bordered input-sm w-full">
        <?php endif; ?>
        <?php if ($hint): ?>
            <div class="label py-1"><span class="label-text-alt opacity-70"><?= esc($hint) ?></span></div>
        <?php endif; ?>
    </label>
    <?php
};

$linesField = function (string $name, string $label, array $items, ?string $hint = null, int $rows = 4) {
    ?>
    <label class="form-control w-full">
        <div class="label py-1"><span class="label-text"><?= esc($label) ?></span></div>
        <textarea name="<?= esc($name, 'attr') ?>" rows="<?= $rows ?>"
                  class="textarea textarea-bordered textarea-sm w-full font-mono text-xs"><?= esc(implode("\n", $items)) ?></textarea>
        <div class="label py-1"><span class="label-text-alt opacity-70"><?= esc($hint ?? 'One per line.') ?></span></div>
    </label>
    <?php
};

$boolField = function (string $name, string $label, bool $checked) {
    ?>
    <label class="label cursor-pointer justify-start gap-3 py-1">
        <input type="checkbox" name="<?= esc($name, 'attr') ?>" value="1" class="toggle toggle-primary toggle-sm" <?= $checked ? 'checked' : '' ?>>
        <span class="label-text"><?= esc($label) ?></span>
    </label>
    <?php
};

// Text input + "Choose from Media Library" button + live thumbnail
// preview. Populated/driven by public/assets/admin/js/homepage.js.
$imageField = function (string $name, string $label, string $url, string $alt = '') {
    ?>
    <div class="form-control w-full" data-lcms-image-field>
        <div class="label py-1"><span class="label-text"><?= esc($label) ?></span></div>
        <div class="flex items-center gap-3">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-base-300 bg-base-200">
                <img data-role="preview" src="<?= esc($url, 'attr') ?>" alt="" class="h-full w-full object-cover <?= $url === '' ? 'hidden' : '' ?>">
                <span data-role="placeholder" class="text-[10px] uppercase opacity-50 <?= $url !== '' ? 'hidden' : '' ?>">No image</span>
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <input type="text" name="<?= esc($name, 'attr') ?>" value="<?= esc($url, 'attr') ?>"
                       data-role="url" class="input input-bordered input-sm w-full" placeholder="https://...">
                <div class="flex gap-2">
                    <button type="button" class="btn btn-ghost btn-xs" data-action="browse">Choose from Media Library</button>
                    <button type="button" class="btn btn-ghost btn-xs text-error" data-action="clear">Clear</button>
                </div>
            </div>
        </div>
    </div>
    <?php
};

// Generic repeater: renders existing rows + a couple of blank spare rows
// (dropped on save if left empty — see HomepageContent::normalise()), a
// <template> for JS "Add row", and per-row "Remove" buttons.
$repeater = function (string $section, string $field, array $subSchema, array $items, array $labels, int $spareRows = 2) use ($textField, $linesField, $boolField) {
    // $row is passed as a parameter (not captured via `use`) specifically
    // so each call sees its own data — a `use ($row)` closure would close
    // over the value at *definition* time and render every row identically.
    $renderRow = function (int $index, array $row) use ($section, $field, $subSchema, $labels, $textField, $linesField, $boolField) {
        ?>
        <div class="lcms-repeater__row card border border-base-300 bg-base-100" data-role="row">
            <div class="card-body gap-2 p-3">
                <div class="grid gap-2 sm:grid-cols-2">
                    <?php foreach ($subSchema as $subKey => $subType):
                        $name  = "{$section}[{$field}][{$index}][{$subKey}]";
                        $label = $labels[$subKey] ?? ucfirst(str_replace('_', ' ', $subKey));
                        $value = $row[$subKey] ?? '';
                        $span  = in_array($subKey, $labels['__wide'] ?? [], true) ? ' sm:col-span-2' : '';
                        ?>
                        <div class="<?= trim($span) ?>">
                            <?php if ($subType === 'lines'): ?>
                                <?= $linesField($name, $label, is_array($value) ? $value : [], null, 3) ?>
                            <?php elseif ($subType === 'bool'): ?>
                                <?= $boolField($name, $label, (bool) $value) ?>
                            <?php else: ?>
                                <?= $textField($name, $label, (string) $value) ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-actions justify-end">
                    <button type="button" class="btn btn-ghost btn-xs text-error" data-action="remove-row">Remove</button>
                </div>
            </div>
        </div>
        <?php
    };

    $existing = array_values($items);
    $count    = count($existing) + $spareRows;
    ?>
    <div class="lcms-repeater grid gap-3" data-repeater data-section="<?= esc($section, 'attr') ?>" data-field="<?= esc($field, 'attr') ?>">
        <?php for ($i = 0; $i < $count; $i++):
            $renderRow($i, $existing[$i] ?? []);
        endfor; ?>
    </div>

    <template data-repeater-template>
        <?php $renderRow(0, []); ?>
    </template>

    <button type="button" class="btn btn-outline btn-sm mt-3" data-action="add-row">+ Add another</button>
    <?php
};

$sec = static fn (string $key) => $content[$key] ?? [];
?>
<?= view('admin/_header', ['title' => 'Homepage']) ?>

<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <div>
        <h2 class="text-lg font-semibold">Homepage content</h2>
        <p class="text-sm opacity-70">Every heading, paragraph, button label and image on the front page, the Blog header and the single-post booking box. Changes go live for guests after the page cache clears (immediately after saving).</p>
    </div>
    <a href="<?= site_url('/') ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">View site</a>
</div>

<form method="post" action="<?= site_url('admin/homepage') ?>" id="lcms-homepage-form" class="grid gap-4">
    <?= csrf_field() ?>

    <!-- ============================== SEO ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox" checked>
        <div class="collapse-title text-base font-semibold">SEO &amp; meta tags</div>
        <div class="collapse-content grid gap-3 sm:grid-cols-2">
            <?php $s = $sec('seo'); ?>
            <?= $textField('seo[meta_title]', 'Meta title', $v($s, 'meta_title'), ['hint' => 'Shown in the browser tab and Google search results.']) ?>
            <?= $textField('seo[html_lang]', 'Page language code', $v($s, 'html_lang'), ['hint' => 'e.g. es, en, id — sets <html lang="...">.']) ?>
            <div class="sm:col-span-2">
                <?= $textField('seo[meta_description]', 'Meta description', $v($s, 'meta_description'), ['textarea' => true, 'rows' => 2]) ?>
            </div>
            <?= $textField('seo[canonical_url]', 'Canonical URL', $v($s, 'canonical_url')) ?>
            <?= $imageField('seo[og_image]', 'Social share image (Open Graph)', $v($s, 'og_image')) ?>
        </div>
    </div>

    <!-- ============================== HERO ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox" checked>
        <div class="collapse-title text-base font-semibold">Hero section</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('hero'); ?>
            <?= $boolField('hero[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <?= $textField('hero[title]', 'Headline (H1)', $v($s, 'title'), ['textarea' => true, 'rows' => 2]) ?>
            <?= $textField('hero[accent]', 'Word/phrase shown in italic accent color inside the headline', $v($s, 'accent'), ['hint' => 'Must match a word or phrase inside the headline above.']) ?>
            <?= $textField('hero[tagline]', 'Tagline below the headline', $v($s, 'tagline'), ['textarea' => true, 'rows' => 2]) ?>

            <?= $imageField('hero[image]', 'Hero background photo (full-width)', $v($s, 'image')) ?>
            <?= $textField('hero[image_alt]', 'Hero photo alt text', $v($s, 'image_alt')) ?>

            <div>
                <div class="label py-1"><span class="label-text font-medium">Highlight cards (numbered 01, 02, 03… over the photo)</span></div>
                <?= $repeater('hero', 'highlights', ['title' => 'text', 'text' => 'text', 'link_label' => 'text', 'link_url' => 'text'], $v($s, 'highlights', []), ['title' => 'Title', 'text' => 'Short text', 'link_label' => 'Link label (optional)', 'link_url' => 'Link URL', '__wide' => ['text']]) ?>
            </div>
        </div>
    </div>

    <!-- ============================== WHY US ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">Why choose us</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('why'); ?>
            <?= $boolField('why[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <div class="grid gap-3 sm:grid-cols-3">
                <?= $textField('why[eyebrow]', 'Small label above the heading', $v($s, 'eyebrow')) ?>
                <?= $textField('why[heading]', 'Heading', $v($s, 'heading')) ?>
                <?= $textField('why[subheading]', 'Subheading', $v($s, 'subheading')) ?>
            </div>
            <div>
                <div class="label py-1"><span class="label-text font-medium">Reasons (icon + title + text)</span></div>
                <p class="mb-2 text-xs opacity-60">Icon names come from <a href="https://tabler.io/icons" target="_blank" rel="noopener" class="link">tabler.io/icons</a> — type the name only, e.g. <code>award</code>.</p>
                <?= $repeater('why', 'items', ['icon' => 'text', 'title' => 'text', 'text' => 'text'], $v($s, 'items', []), ['icon' => 'Icon name', 'title' => 'Title', 'text' => 'Text', '__wide' => ['text']]) ?>
            </div>
        </div>
    </div>

    <!-- ============================== PRICING ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">Pricing packages</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('pricing'); ?>
            <?= $boolField('pricing[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <?= $textField('pricing[heading]', 'Heading', $v($s, 'heading')) ?>
                <?= $textField('pricing[subheading]', 'Subheading', $v($s, 'subheading')) ?>
            </div>
            <div>
                <div class="label py-1"><span class="label-text font-medium">Packages (shown as a swipeable carousel)</span></div>
                <?php
                $pkgSub = ['name' => 'text', 'ribbon' => 'text', 'featured' => 'bool', 'icon' => 'text', 'audience' => 'text', 'price_old' => 'text', 'price_now' => 'text', 'unit' => 'text', 'features' => 'lines', 'rating' => 'text', 'cta_label' => 'text', 'cta_url' => 'text'];
                $pkgLabels = [
                    'name' => 'Package name', 'ribbon' => 'Ribbon text (optional)', 'featured' => 'Highlight this card',
                    'icon' => 'Icon name (optional, tabler.io/icons)', 'audience' => 'Who it is for',
                    'price_old' => 'Old price (optional, shown struck through)', 'price_now' => 'Price', 'unit' => 'Price unit / group size',
                    'features' => 'Feature list', 'rating' => 'Rating out of 5 (e.g. 4.8)', 'cta_label' => 'Button label', 'cta_url' => 'Button link (WhatsApp URL etc.)',
                    '__wide' => ['audience', 'features'],
                ];
                echo $repeater('pricing', 'packages', $pkgSub, $v($s, 'packages', []), $pkgLabels, 1);
                ?>
            </div>
        </div>
    </div>

    <!-- ============================== BOAT COMPARISON ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">Boat comparison table</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('compare'); ?>
            <?= $boolField('compare[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <?= $textField('compare[heading]', 'Heading', $v($s, 'heading')) ?>
            <div class="grid gap-3 sm:grid-cols-3">
                <?= $textField('compare[col_feature]', 'First column header', $v($s, 'col_feature')) ?>
                <?= $textField('compare[col_a]', 'Second column header', $v($s, 'col_a')) ?>
                <?= $textField('compare[col_b]', 'Third column header', $v($s, 'col_b')) ?>
            </div>
            <div>
                <div class="label py-1"><span class="label-text font-medium">Rows</span></div>
                <?= $repeater('compare', 'rows', ['feature' => 'text', 'a' => 'text', 'b' => 'text'], $v($s, 'rows', []), ['feature' => 'Row label', 'a' => 'Column 2 value', 'b' => 'Column 3 value']) ?>
            </div>
            <?= $textField('compare[note]', 'Note below the table', $v($s, 'note'), ['textarea' => true, 'rows' => 2]) ?>
        </div>
    </div>

    <!-- ============================== SCHEDULE ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">Departure schedule</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('schedule'); ?>
            <?= $boolField('schedule[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <?= $textField('schedule[heading]', 'Heading', $v($s, 'heading')) ?>
                <?= $textField('schedule[subheading]', 'Subheading', $v($s, 'subheading')) ?>
            </div>
            <div>
                <div class="label py-1"><span class="label-text font-medium">Departure sessions</span></div>
                <?= $repeater('schedule', 'sessions', ['icon' => 'text', 'title' => 'text', 'checkin' => 'text', 'best_for' => 'text', 'recommended' => 'text'], $v($s, 'sessions', []), ['icon' => 'Icon name', 'title' => 'Title + time', 'checkin' => 'Check-in time', 'best_for' => 'Best for', 'recommended' => 'Recommended for', '__wide' => ['best_for', 'recommended']], 1) ?>
            </div>
            <?= $textField('schedule[notice_title]', 'Notice box title', $v($s, 'notice_title')) ?>
            <?= $linesField('schedule[notice_items]', 'Notice box items', $v($s, 'notice_items', []), 'One per line. Use "Label: rest of sentence" to bold the label.') ?>
        </div>
    </div>

    <!-- ============================== STEPS ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">How it works</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('steps'); ?>
            <?= $boolField('steps[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <?= $textField('steps[heading]', 'Heading', $v($s, 'heading')) ?>
            <div>
                <div class="label py-1"><span class="label-text font-medium">Steps</span></div>
                <?= $repeater('steps', 'items', ['icon' => 'text', 'title' => 'text', 'text' => 'text'], $v($s, 'items', []), ['icon' => 'Icon name', 'title' => 'Title', 'text' => 'Text', '__wide' => ['text']]) ?>
            </div>
        </div>
    </div>

    <!-- ============================== INCLUDED / EXCLUDED ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">What's included / not included</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('checklist'); ?>
            <?= $boolField('checklist[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <?= $textField('checklist[heading]', 'Heading', $v($s, 'heading')) ?>
                <?= $textField('checklist[subheading]', 'Subheading', $v($s, 'subheading')) ?>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <?= $textField('checklist[included_title]', 'Included column title', $v($s, 'included_title')) ?>
                    <?= $linesField('checklist[included]', 'Included items', $v($s, 'included', []), null, 6) ?>
                </div>
                <div>
                    <?= $textField('checklist[excluded_title]', 'Not included column title', $v($s, 'excluded_title')) ?>
                    <?= $linesField('checklist[excluded]', 'Not included items', $v($s, 'excluded', []), null, 6) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================== PACKING LIST ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">What to bring</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('packing'); ?>
            <?= $boolField('packing[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <?= $textField('packing[heading]', 'Heading', $v($s, 'heading')) ?>
                <?= $textField('packing[subheading]', 'Subheading', $v($s, 'subheading')) ?>
            </div>
            <div>
                <div class="label py-1"><span class="label-text font-medium">Items</span></div>
                <?= $repeater('packing', 'items', ['icon' => 'text', 'label' => 'text'], $v($s, 'items', []), ['icon' => 'Icon name', 'label' => 'Item label'], 3) ?>
            </div>
            <?= $textField('packing[tip]', 'Tip box text', $v($s, 'tip'), ['textarea' => true, 'rows' => 2]) ?>
        </div>
    </div>

    <!-- ============================== TESTIMONIALS ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">Testimonials</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('testimonials'); ?>
            <?= $boolField('testimonials[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <?= $textField('testimonials[heading]', 'Heading', $v($s, 'heading')) ?>
                <?= $textField('testimonials[subheading]', 'Subheading', $v($s, 'subheading')) ?>
            </div>
            <div>
                <div class="label py-1"><span class="label-text font-medium">Reviews</span></div>
                <?= $repeater('testimonials', 'items', ['quote' => 'text', 'author' => 'text', 'location' => 'text', 'rating' => 'text'], $v($s, 'items', []), ['quote' => 'Quote', 'author' => 'Author name', 'location' => 'Location', 'rating' => 'Rating out of 5', '__wide' => ['quote']], 1) ?>
            </div>
        </div>
    </div>

    <!-- ============================== FAQ ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">FAQ</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('faq'); ?>
            <?= $boolField('faq[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <?= $textField('faq[heading]', 'Heading', $v($s, 'heading')) ?>
                <?= $textField('faq[subheading]', 'Subheading', $v($s, 'subheading')) ?>
            </div>
            <div>
                <div class="label py-1"><span class="label-text font-medium">Questions</span></div>
                <?= $repeater('faq', 'items', ['question' => 'text', 'answer' => 'text'], $v($s, 'items', []), ['question' => 'Question', 'answer' => 'Answer', '__wide' => ['answer']], 1) ?>
            </div>
        </div>
    </div>

    <!-- ============================== FINAL CTA ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">Final call-to-action</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('final_cta'); ?>
            <?= $boolField('final_cta[enabled]', 'Show this section', (bool) $v($s, 'enabled', true)) ?>
            <?= $textField('final_cta[heading]', 'Heading', $v($s, 'heading')) ?>
            <?= $textField('final_cta[subheading]', 'Subheading', $v($s, 'subheading'), ['textarea' => true, 'rows' => 2]) ?>
            <?= $textField('final_cta[urgency]', 'Urgency line (e.g. recent bookings)', $v($s, 'urgency')) ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <?= $textField('final_cta[cta_primary_label]', 'Primary button label', $v($s, 'cta_primary_label')) ?>
                <?= $textField('final_cta[cta_primary_url]', 'Primary button link', $v($s, 'cta_primary_url')) ?>
                <?= $textField('final_cta[cta_secondary_label]', 'Secondary (WhatsApp) button label', $v($s, 'cta_secondary_label')) ?>
                <?= $textField('final_cta[cta_secondary_url]', 'Secondary button link', $v($s, 'cta_secondary_url')) ?>
                <?= $textField('final_cta[whatsapp_label]', 'WhatsApp line text', $v($s, 'whatsapp_label')) ?>
                <?= $textField('final_cta[whatsapp_url]', 'WhatsApp link', $v($s, 'whatsapp_url')) ?>
            </div>
            <?= $textField('final_cta[hours]', 'Opening hours line', $v($s, 'hours')) ?>
        </div>
    </div>

    <!-- ============================== BLOG LISTING ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">Blog listing (/blog)</div>
        <div class="collapse-content grid gap-3 sm:grid-cols-2">
            <?php $s = $sec('blog'); ?>
            <?= $textField('blog[heading]', 'Heading', $v($s, 'heading')) ?>
            <?= $textField('blog[intro]', 'Intro text', $v($s, 'intro')) ?>
            <?= $textField('blog[meta_title]', 'Meta title', $v($s, 'meta_title')) ?>
            <?= $textField('blog[meta_description]', 'Meta description', $v($s, 'meta_description'), ['textarea' => true, 'rows' => 2]) ?>
        </div>
    </div>

    <!-- ============================== SINGLE POST CTA ============================== -->
    <div class="collapse collapse-arrow border border-base-300 bg-base-100">
        <input type="checkbox">
        <div class="collapse-title text-base font-semibold">Single blog post — booking CTA box</div>
        <div class="collapse-content grid gap-3">
            <?php $s = $sec('single_cta'); ?>
            <?= $boolField('single_cta[enabled]', 'Show this box at the end of every post', (bool) $v($s, 'enabled', true)) ?>
            <?= $textField('single_cta[heading]', 'Heading', $v($s, 'heading')) ?>
            <?= $textField('single_cta[text]', 'Text', $v($s, 'text'), ['textarea' => true, 'rows' => 2]) ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <?= $textField('single_cta[cta_label]', 'Button label', $v($s, 'cta_label')) ?>
                <?= $textField('single_cta[cta_url]', 'Button link', $v($s, 'cta_url')) ?>
            </div>
        </div>
    </div>

    <div class="sticky bottom-4 z-10 flex justify-end">
        <button type="submit" class="btn btn-primary shadow-lg">Save homepage content</button>
    </div>
</form>

<!-- Media picker modal, shared by every [data-lcms-image-field] -->
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
