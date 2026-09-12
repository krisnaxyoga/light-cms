<?= view('admin/_header', ['title' => 'Profile']) ?>

<div class="grid gap-4 lg:grid-cols-3">

    <section class="card border border-base-300 bg-base-100 lg:col-span-2">
        <form method="post" action="<?= site_url('admin/profile') ?>" class="card-body gap-3 p-4 md:p-5">
            <?= csrf_field() ?>

            <h2 class="card-title text-base">Account</h2>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Display name</span></div>
                    <input type="text" name="display_name" maxlength="100"
                           value="<?= esc(old('display_name', $user['display_name'] ?? '')) ?>"
                           class="input input-bordered input-sm w-full">
                    <div class="label py-1">
                        <span class="label-text-alt opacity-70">Shown as the post author. Falls back to the username.</span>
                    </div>
                </label>

                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Username</span></div>
                    <input type="text" name="username" required minlength="3" maxlength="60"
                           value="<?= esc(old('username', $user['username'])) ?>"
                           class="input input-bordered input-sm w-full">
                </label>

                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Email</span></div>
                    <input type="email" name="email" required maxlength="100"
                           value="<?= esc(old('email', $user['email'])) ?>"
                           class="input input-bordered input-sm w-full">
                </label>

                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Avatar URL</span></div>
                    <input type="text" name="avatar" maxlength="255"
                           value="<?= esc(old('avatar', $user['avatar'] ?? '')) ?>"
                           class="input input-bordered input-sm w-full">
                    <div class="label py-1">
                        <span class="label-text-alt opacity-70">Optional. A URL, or a path from the Media library.</span>
                    </div>
                </label>
            </div>

            <div class="divider my-1"></div>

            <label class="form-control w-full max-w-sm">
                <div class="label py-1"><span class="label-text">Current password</span></div>
                <input type="password" name="confirm_password" autocomplete="current-password"
                       class="input input-bordered input-sm w-full">
                <div class="label py-1">
                    <span class="label-text-alt opacity-70">Only required when you change your username or email.</span>
                </div>
            </label>

            <div class="card-actions justify-end">
                <button type="submit" class="btn btn-primary btn-sm">Save profile</button>
            </div>
        </form>
    </section>

    <aside class="card h-fit border border-base-300 bg-base-100">
        <div class="card-body items-center gap-2 p-5 text-center">
            <div class="avatar placeholder">
                <div class="w-16 rounded-full bg-primary text-primary-content">
                    <?php if (! empty($user['avatar'])): ?>
                        <img src="<?= esc($user['avatar'], 'attr') ?>" alt="<?= esc($user['username']) ?>">
                    <?php else: ?>
                        <span class="text-xl"><?= esc(strtoupper(mb_substr((string) ($user['display_name'] ?: $user['username']), 0, 1))) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="font-medium"><?= esc($user['display_name'] ?: $user['username']) ?></p>
            <p class="text-xs opacity-70"><?= esc($user['email']) ?></p>
            <div class="mt-1 flex flex-wrap justify-center gap-1">
                <span class="badge badge-ghost badge-sm"><?= esc($role) ?></span>
                <span class="badge badge-sm <?= ($user['status'] ?? '') === 'active' ? 'badge-success' : 'badge-warning' ?>">
                    <?= esc($user['status'] ?? 'unknown') ?>
                </span>
            </div>
            <p class="mt-2 text-xs opacity-60">
                Member since <?= esc(date('j M Y', strtotime((string) ($user['created_at'] ?? 'now')))) ?>
            </p>
        </div>
    </aside>

    <section class="card border border-base-300 bg-base-100 lg:col-span-2">
        <form method="post" action="<?= site_url('admin/profile/password') ?>" class="card-body gap-3 p-4 md:p-5">
            <?= csrf_field() ?>

            <h2 class="card-title text-base">Change password</h2>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Current password</span></div>
                    <input type="password" name="current_password" required autocomplete="current-password"
                           class="input input-bordered input-sm w-full">
                </label>

                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">New password</span></div>
                    <input type="password" name="new_password" required minlength="8" maxlength="72"
                           autocomplete="new-password" class="input input-bordered input-sm w-full">
                </label>

                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Confirm new password</span></div>
                    <input type="password" name="new_password_confirm" required minlength="8" maxlength="72"
                           autocomplete="new-password" class="input input-bordered input-sm w-full">
                </label>
            </div>

            <p class="text-sm opacity-70">
                At least 8 characters (maximum 72 — bcrypt ignores anything past that), different from the current
                one, and not your username or email.
            </p>

            <div class="card-actions justify-end">
                <button type="submit" class="btn btn-primary btn-sm">Change password</button>
            </div>
        </form>
    </section>
</div>

<?= view('admin/_footer') ?>
