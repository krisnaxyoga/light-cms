<?php
/**
 * Admin shell — Tailwind 3 + daisyUI 4 (built to assets/admin/css/admin-ui.css).
 *
 * The sidebar and the mobile dropdown render the same $navItems list, so a
 * menu entry is only ever declared once.
 */
$navItems = [
    ['url' => 'admin',              'label' => 'Dashboard',      'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['url' => 'admin/posts',        'label' => 'Posts',          'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ['url' => 'admin/pages',        'label' => 'Pages',          'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
    ['url' => 'admin/media',        'label' => 'Media',          'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['url' => 'admin/seo/redirects', 'label' => 'SEO & Redirects', 'icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'],
    ['url' => 'admin/themes',       'label' => 'Themes',         'icon' => 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01'],
    ['url' => 'admin/plugins',      'label' => 'Plugins',        'icon' => 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z'],
    ['url' => 'admin/settings',     'label' => 'Settings',       'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
    ['url' => 'admin/profile',      'label' => 'Profile',        'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
];

$currentPath = trim(service('request')->getPath(), '/');

$isCurrent = static function (string $url) use ($currentPath): bool {
    // "admin" must not light up on every child route, but "admin/posts"
    // should stay active on "admin/posts/12/edit".
    return $url === 'admin' ? $currentPath === 'admin' : str_starts_with($currentPath, $url);
};
?>
<!doctype html>
<html lang="en" data-theme="lightcms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Dashboard') ?> &middot; LightCMS Admin</title>
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin-ui.css') ?>">
    <script>
        // Applied before first paint so the saved theme never flashes.
        (function () {
            try {
                var saved = localStorage.getItem('lcms-theme');
                if (saved === 'lightcms' || saved === 'lightcmsdark') {
                    document.documentElement.dataset.theme = saved;
                }
            } catch (e) { /* private mode: keep the default theme */ }
        })();
    </script>
</head>
<body class="bg-base-200 text-base-content">
<div class="flex min-h-screen">

    <aside class="hidden lg:flex w-60 shrink-0 flex-col border-r border-base-300 bg-base-100">
        <a href="<?= site_url('admin') ?>" class="flex items-center gap-2 px-5 h-14 border-b border-base-300">
            <span class="text-lg font-semibold tracking-tight">Light<span class="text-primary">CMS</span></span>
        </a>
        <ul class="menu menu-sm gap-0.5 p-3 w-full">
            <?php foreach ($navItems as $item): ?>
                <li>
                    <a href="<?= site_url($item['url']) ?>" class="<?= $isCurrent($item['url']) ? 'active font-medium' : '' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="<?= $item['icon'] ?>" />
                        </svg>
                        <?= esc($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="mt-auto p-3">
            <a href="<?= site_url('/') ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm w-full justify-start gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                View site
            </a>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="navbar sticky top-0 z-30 min-h-14 gap-2 border-b border-base-300 bg-base-100 px-3 md:px-5">
            <div class="dropdown lg:hidden">
                <div tabindex="0" role="button" class="btn btn-ghost btn-sm btn-square" aria-label="Menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </div>
                <ul tabindex="0" class="menu dropdown-content menu-sm z-40 mt-2 w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                    <?php foreach ($navItems as $item): ?>
                        <li><a href="<?= site_url($item['url']) ?>" class="<?= $isCurrent($item['url']) ? 'active' : '' ?>"><?= esc($item['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-base font-semibold md:text-lg"><?= esc($title ?? 'Dashboard') ?></h1>
            </div>

            <div class="flex flex-none items-center gap-1">
                <label class="swap swap-rotate btn btn-ghost btn-sm btn-circle" title="Toggle dark mode">
                    <input type="checkbox" id="lcms-theme-toggle" class="hidden">
                    <svg class="swap-off h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <svg class="swap-on h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </label>

                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-sm gap-2">
                        <span class="hidden max-w-[10rem] truncate sm:inline"><?= esc(session('displayName') ?? session('username') ?? 'Account') ?></span>
                        <div class="avatar placeholder">
                            <div class="w-6 rounded-full bg-primary text-primary-content">
                                <span class="text-xs"><?= esc(strtoupper(mb_substr((string) (session('displayName') ?? session('username') ?? '?'), 0, 1))) ?></span>
                            </div>
                        </div>
                    </div>
                    <ul tabindex="0" class="menu dropdown-content menu-sm z-40 mt-2 w-44 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                        <li><a href="<?= site_url('admin/profile') ?>">Profile</a></li>
                        <li><a href="<?= site_url('/') ?>" target="_blank" rel="noopener">View site</a></li>
                        <li><a href="<?= site_url('admin/logout') ?>" class="text-error">Log out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 p-4 md:p-6">
            <?php if (session()->getFlashdata('success')): ?>
                <div class="lcms-alert alert alert-success mb-4 transition-opacity duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?= esc(session()->getFlashdata('success')) ?></span>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="lcms-alert alert alert-error mb-4 transition-opacity duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?= esc(session()->getFlashdata('error')) ?></span>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-error mb-4 items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <ul class="list-inside list-disc text-sm">
                        <?php foreach (session()->getFlashdata('errors') as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
