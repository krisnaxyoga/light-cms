<?= view('admin/_header', ['title' => 'Deploy']) ?>

<div class="mb-4">
    <h2 class="text-lg font-semibold">Deploy from GitLab</h2>
    <p class="text-sm opacity-70">
        Pulls the latest commit straight from a GitLab repository. Works with no <code>git</code> binary,
        SSH, or shell access at all — and automatically switches to real, fast <code>git</code> over SSH
        the moment that's detected as actually working on this server, no setting to flip by hand. Every
        pull backs up the current files first either way, so a bad deploy can be undone from this same screen.
    </p>

    <?php if ($config['project_id']): ?>
        <?php if ($mode['mode'] === 'git'): ?>
            <div class="badge badge-success gap-1 mt-2">
                Active mode: git over SSH (fast, incremental<?= $mode['is_git_repo'] ? '' : ' — will clone once, then stay this way' ?>)
            </div>
        <?php else: ?>
            <div class="badge badge-ghost gap-1 mt-2">Active mode: archive download over HTTPS (no shell/SSH access detected on this server)</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="grid gap-4 lg:grid-cols-2">
    <!-- ============================== Status ============================== -->
    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h3 class="card-title text-base">Status</h3>

            <?php if (! $config['project_id']): ?>
                <p class="text-sm opacity-70">Not configured yet — fill in the connection details on the right.</p>
            <?php else: ?>
                <div class="grid gap-1 text-sm">
                    <p><span class="opacity-60">Repository:</span> <?= esc($config['gitlab_url']) ?> / <?= esc($config['project_id']) ?></p>
                    <p><span class="opacity-60">Branch:</span> <?= esc($config['branch']) ?></p>
                </div>

                <div class="divider my-1"></div>

                <div class="grid gap-1 text-sm">
                    <p class="font-medium">Currently deployed</p>
                    <?php if ($last['sha']): ?>
                        <p><code><?= esc($last['short_sha']) ?></code> — <?= esc($last['message']) ?></p>
                        <p class="text-xs opacity-60"><?= esc($last['at']) ?></p>
                    <?php else: ?>
                        <p class="opacity-60">Nothing deployed through this screen yet.</p>
                    <?php endif; ?>
                </div>

                <div class="grid gap-1 text-sm">
                    <p class="font-medium">Latest on <?= esc($config['branch']) ?></p>
                    <?php if ($remoteErr): ?>
                        <p class="text-error"><?= esc($remoteErr) ?></p>
                    <?php elseif ($remote): ?>
                        <p><code><?= esc($remote['short_sha']) ?></code> — <?= esc($remote['message']) ?></p>
                        <?php if ($upToDate): ?>
                            <span class="badge badge-success badge-sm">Up to date</span>
                        <?php else: ?>
                            <span class="badge badge-warning badge-sm">Update available</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= site_url('admin/deploy/pull') ?>" class="card-actions mt-2"
                      onsubmit="return confirm('Pull and apply the latest commit now? The current files will be backed up first.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm" <?= $remoteErr ? 'disabled' : '' ?>>
                        Pull latest now
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================== Connection settings ============================== -->
    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3 p-4 md:p-5">
            <h3 class="card-title text-base">Connection</h3>

            <form method="post" action="<?= site_url('admin/deploy/config') ?>" class="grid gap-3">
                <?= csrf_field() ?>

                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">GitLab URL</span></div>
                    <input type="text" name="gitlab_url" value="<?= esc($config['gitlab_url']) ?>"
                           class="input input-bordered input-sm w-full" placeholder="https://gitlab.com">
                    <div class="label py-1"><span class="label-text-alt opacity-70">Change this only for a self-hosted GitLab instance.</span></div>
                </label>

                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Project</span></div>
                    <input type="text" name="project_id" value="<?= esc($config['project_id']) ?>"
                           class="input input-bordered input-sm w-full" placeholder="namespace/project or the numeric project ID">
                </label>

                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Branch</span></div>
                    <input type="text" name="branch" value="<?= esc($config['branch']) ?>"
                           class="input input-bordered input-sm w-full" placeholder="main">
                </label>

                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Access token</span></div>
                    <input type="password" name="access_token" value=""
                           class="input input-bordered input-sm w-full"
                           placeholder="<?= $hasToken ? 'Saved — leave blank to keep it' : 'Required for a private repository' ?>">
                    <div class="label py-1">
                        <span class="label-text-alt opacity-70">
                            A GitLab <strong>Deploy Token</strong> (Settings &rarr; Repository &rarr; Deploy tokens) with
                            <code>read_repository</code> scope is safer than a personal access token here.
                            Leave blank for a public repository.
                        </span>
                    </div>
                </label>

                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-primary btn-sm">Save connection</button>
                </div>
            </form>
        </div>
    </section>

    <!-- ============================== Auto-deploy webhook ============================== -->
    <section class="card border border-base-300 bg-base-100 lg:col-span-2">
        <div class="card-body gap-3 p-4 md:p-5">
            <h3 class="card-title text-base">Auto-deploy on push (optional)</h3>
            <p class="text-sm opacity-70">
                Add a webhook on the GitLab project's <strong>Settings &rarr; Webhooks</strong> page so every push
                deploys automatically — no need to come back to this screen at all.
            </p>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="form-control w-full">
                    <div class="label py-1"><span class="label-text">Webhook URL (paste into GitLab)</span></div>
                    <input type="text" readonly value="<?= esc($webhookUrl) ?>" class="input input-bordered input-sm w-full font-mono text-xs" onclick="this.select()">
                </label>

                <form method="post" action="<?= site_url('admin/deploy/config') ?>" class="form-control w-full">
                    <?= csrf_field() ?>
                    <div class="label py-1"><span class="label-text">Secret token (paste the same value into GitLab)</span></div>
                    <div class="join w-full">
                        <input type="text" name="webhook_secret" value=""
                               class="input input-bordered input-sm join-item w-full"
                               placeholder="<?= $config['webhook_secret'] !== '' ? 'Saved — leave blank to keep it' : 'e.g. a long random string' ?>">
                        <button type="submit" class="btn btn-sm join-item">Save</button>
                    </div>
                </form>
            </div>

            <p class="text-xs opacity-60">
                GitLab sends this same value back in an <code>X-Gitlab-Token</code> header on every webhook call, which
                is how the endpoint tells a real GitLab push apart from anyone else who finds the URL — set "Trigger" to
                <strong>Push events</strong> for the branch above.
            </p>
        </div>
    </section>

    <!-- ============================== Backups ============================== -->
    <section class="card border border-base-300 bg-base-100 lg:col-span-2">
        <div class="card-body gap-3 p-4 md:p-5">
            <h3 class="card-title text-base">Backups</h3>
            <p class="text-sm opacity-70">
                Taken automatically before every pull (last 5 kept). Restoring puts every file back exactly as it
                was at that point — <code>.env</code>, <code>writable/</code> and uploaded media are never touched
                either way.
            </p>

            <?php if (empty($backups)): ?>
                <p class="text-sm opacity-60">No backups yet — one is created the first time you pull.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Taken</th><th>Size</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><?= esc($backup['created_at']) ?></td>
                                    <td><?= esc(number_format($backup['size'] / 1024 / 1024, 1)) ?> MB</td>
                                    <td class="text-right">
                                        <form method="post" action="<?= site_url('admin/deploy/restore/' . rawurlencode($backup['file'])) ?>"
                                              onsubmit="return confirm('Restore the codebase to this backup? Anything deployed after it will be overwritten.');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-ghost btn-xs">Restore</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?= view('admin/_footer') ?>
