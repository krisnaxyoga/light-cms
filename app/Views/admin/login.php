<!doctype html>
<html lang="en" data-theme="lightcms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in &middot; LightCMS Admin</title>
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin-ui.css') ?>">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('lcms-theme');
                if (saved === 'lightcms' || saved === 'lightcmsdark') {
                    document.documentElement.dataset.theme = saved;
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="bg-base-200 text-base-content">
<div class="flex min-h-screen items-center justify-center p-4">
    <div class="card w-full max-w-sm border border-base-300 bg-base-100 shadow-sm">
        <form class="card-body gap-4" method="post" action="<?= site_url('admin/login') ?>">
            <?= csrf_field() ?>

            <div class="text-center">
                <h1 class="text-xl font-semibold tracking-tight">Light<span class="text-primary">CMS</span></h1>
                <p class="mt-1 text-sm opacity-70">Sign in to the admin</p>
            </div>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-error py-2 text-sm">
                    <span><?= esc(session()->getFlashdata('error')) ?></span>
                </div>
            <?php endif; ?>

            <label class="form-control w-full">
                <div class="label py-1"><span class="label-text">Username or email</span></div>
                <input type="text" name="login" value="<?= esc(old('login') ?? '') ?>" required autofocus
                       class="input input-bordered w-full" autocomplete="username">
            </label>

            <label class="form-control w-full">
                <div class="label py-1"><span class="label-text">Password</span></div>
                <input type="password" name="password" required class="input input-bordered w-full" autocomplete="current-password">
            </label>

            <button type="submit" class="btn btn-primary w-full">Log in</button>
        </form>
    </div>
</div>
</body>
</html>
