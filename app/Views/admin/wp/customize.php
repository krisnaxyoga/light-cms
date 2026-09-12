<?= view('admin/_header', ['title' => $title]) ?>

<p class="mb-4 text-sm opacity-70">
    Options the active WordPress theme registered through <code class="rounded bg-base-200 px-1">customize_register</code>.
    There is no live preview here — saving stores the theme mods and the site picks them up on the next load.
</p>

<?php if ($settings === []): ?>
    <div class="alert text-sm"><span>This theme registers no Customizer settings.</span></div>
<?php else: ?>
    <?php
    $bySection = [];
    foreach ($controls as $control) {
        $bySection[$control->section ?: 'general'][] = $control;
    }
    ?>

    <form method="post" action="<?= site_url('admin/wp/customize') ?>" class="grid gap-4 lg:grid-cols-2">
        <?= csrf_field() ?>

        <?php foreach ($bySection as $sectionId => $sectionControls): ?>
            <section class="card border border-base-300 bg-base-100">
                <div class="card-body gap-3 p-4 md:p-5">
                    <h2 class="card-title text-base">
                        <?= esc($sections[$sectionId]->title ?? ucfirst(str_replace(['_', '-'], ' ', (string) $sectionId))) ?>
                    </h2>

                    <?php foreach ($sectionControls as $control): ?>
                        <?php
                        $settingId = is_array($control->settings) ? (string) reset($control->settings) : (string) $control->settings;
                        $setting   = $settings[$settingId] ?? null;
                        $value     = $setting !== null ? $setting->value() : '';
                        $fieldName = str_replace(['[', ']'], ['_', ''], $settingId);
                        ?>
                        <?php if ($control->type === 'checkbox'): ?>
                            <label class="label cursor-pointer justify-start gap-3 py-1">
                                <input type="checkbox" id="<?= esc($fieldName) ?>" name="<?= esc($fieldName) ?>" value="1"
                                       class="toggle toggle-primary toggle-sm" <?= $value ? 'checked' : '' ?>>
                                <span class="label-text"><?= esc($control->label ?: $settingId) ?></span>
                            </label>
                        <?php else: ?>
                            <label class="form-control w-full">
                                <div class="label py-1"><span class="label-text"><?= esc($control->label ?: $settingId) ?></span></div>

                                <?php if ($control->type === 'textarea'): ?>
                                    <textarea id="<?= esc($fieldName) ?>" name="<?= esc($fieldName) ?>" rows="4"
                                              class="textarea textarea-bordered w-full"><?= esc((string) $value) ?></textarea>
                                <?php elseif ($control->type === 'select' && $control->choices !== []): ?>
                                    <select id="<?= esc($fieldName) ?>" name="<?= esc($fieldName) ?>" class="select select-bordered select-sm w-full">
                                        <?php foreach ($control->choices as $choiceValue => $label): ?>
                                            <option value="<?= esc((string) $choiceValue) ?>" <?= (string) $choiceValue === (string) $value ? 'selected' : '' ?>>
                                                <?= esc((string) $label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($control->type === 'color'): ?>
                                    <input type="color" id="<?= esc($fieldName) ?>" name="<?= esc($fieldName) ?>"
                                           value="<?= esc((string) ($value ?: '#000000')) ?>"
                                           class="input input-bordered input-sm h-9 w-20 p-1">
                                <?php else: ?>
                                    <input type="text" id="<?= esc($fieldName) ?>" name="<?= esc($fieldName) ?>"
                                           value="<?= esc((string) $value) ?>" class="input input-bordered input-sm w-full">
                                <?php endif; ?>

                                <?php if ($control->description !== ''): ?>
                                    <div class="label py-1"><span class="label-text-alt opacity-70"><?= esc($control->description) ?></span></div>
                                <?php endif; ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="lg:col-span-2">
            <button type="submit" class="btn btn-primary btn-sm">Save options</button>
        </div>
    </form>
<?php endif; ?>

<?= view('admin/_footer') ?>
