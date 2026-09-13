<?php

namespace App\Libraries\Deploy;

use App\Models\ActivityLogModel;
use App\Models\SettingModel;
use RuntimeException;

/**
 * Deploys code updates from a GitLab repository, working on a server with
 * no `git`, SSH, or shell access at all — and automatically switching to
 * real `git` over SSH the moment it detects the server actually does have
 * that access, without any setting to flip by hand:
 *
 *   - CAPABLE (shell_exec/exec allowed, `git` on PATH, and a non-interactive
 *     SSH probe to GitLab actually authenticates — see detectGitCapability()):
 *     uses real `git fetch` + `git merge --ff-only` (or a one-time
 *     `git clone` if this is the very first deploy), so every later pull is
 *     a fast, incremental, full-history git operation. Detected fresh on
 *     every pull(), so access gained *after* this was first configured
 *     (e.g. a host later enables shell_exec, or a deploy key gets added)
 *     is picked up automatically, no reconfiguration needed.
 *   - NOT CAPABLE (the common shared-hosting case): falls back to pure
 *     HTTPS + PHP's zip extension — ask GitLab's REST API for the latest
 *     commit, download it as a .zip via GitLab's repository/archive
 *     endpoint, extract it. No `git` binary involved at all.
 *
 * Either way, before applying anything:
 *   - The live codebase is backed up (zipped, same exclusion list) so a bad
 *     deploy can be undone from the same admin screen — this matters a lot
 *     more than usual here, since no shell access also means no other way
 *     to fix a broken deploy by hand.
 *   - Every file gets copied over the live one *except* anything in
 *     self::PROTECTED_PATHS — .env, writable/, and public/uploads/ are
 *     server state, not application code, and must never be touched.
 *
 * The SSH capability probe is deliberately paranoid about never hanging a
 * request: every git/ssh invocation forces `BatchMode=yes` (refuse instantly
 * rather than block on a password/passphrase prompt that will never come)
 * and a short ConnectTimeout, on top of gating the whole path behind
 * shell_exec()/exec() actually being callable in the first place.
 *
 * What this deliberately does NOT do:
 *   - Run `composer install`. vendor/ is gitignored (not in a git checkout
 *     or the archive), so a dependency change needs it — pull() tries
 *     shell_exec() if the host allows it, and otherwise just says so in the
 *     result so nothing silently ships with stale dependencies.
 *   - Run database migrations. A schema change needs `php spark migrate`;
 *     pull() flags it if app/Database/Migrations changed so the admin knows
 *     to run it (via the same shell_exec() path, or by hand if that's blocked).
 *   - Require a `git_deploy` encryption key to be pre-configured. The access
 *     token is encrypted at rest when Config\Encryption::$key is set (see
 *     README: `php spark key:generate`); with no key configured yet it's
 *     stored as plain text rather than refusing to work, since a first
 *     deploy without any shell access to run that command still has to work.
 */
class GitLabDeployer
{
    /**
     * Paths (relative to ROOTPATH) a sync/restore must never create,
     * overwrite, or delete. Checked as an exact segment match so
     * "public/uploads" doesn't also match an unrelated
     * "public/uploads-backup" directory.
     */
    protected const PROTECTED_PATHS = ['.env', 'writable', 'public/uploads', '.git'];

    protected const SETTINGS_PREFIX = 'git_deploy_';

    protected SettingModel $settings;
    protected string $rootPath;

    public function __construct(?SettingModel $settings = null, ?string $rootPath = null)
    {
        $this->settings = $settings ?? new SettingModel();
        $this->rootPath = rtrim($rootPath ?? ROOTPATH, '/\\') . DIRECTORY_SEPARATOR;

        // Deploying into an empty/unrelated directory would be a
        // configuration bug, not a normal runtime condition — fail loudly
        // rather than silently writing files somewhere unexpected.
        if (! is_dir($this->rootPath . 'app') || ! is_dir($this->rootPath . 'public')) {
            throw new RuntimeException('GitLabDeployer: root path does not look like a LightCMS install: ' . $this->rootPath);
        }
    }

    public function isConfigured(): bool
    {
        $config = $this->config();

        return $config['gitlab_url'] !== '' && $config['project_id'] !== '' && $config['branch'] !== '';
    }

    /**
     * @return array{gitlab_url: string, project_id: string, branch: string, access_token: string, webhook_secret: string}
     */
    public function config(): array
    {
        return [
            'gitlab_url'     => rtrim((string) $this->settings->get(self::SETTINGS_PREFIX . 'gitlab_url', 'https://gitlab.com'), '/'),
            'project_id'     => (string) $this->settings->get(self::SETTINGS_PREFIX . 'project_id', ''),
            'branch'         => (string) $this->settings->get(self::SETTINGS_PREFIX . 'branch', 'main'),
            'access_token'   => $this->decryptToken((string) $this->settings->get(self::SETTINGS_PREFIX . 'access_token', '')),
            'webhook_secret' => (string) $this->settings->get(self::SETTINGS_PREFIX . 'webhook_secret', ''),
        ];
    }

    /**
     * @param array{gitlab_url?: string, project_id?: string, branch?: string, access_token?: string, webhook_secret?: string} $values
     */
    public function saveConfig(array $values): void
    {
        if (isset($values['gitlab_url'])) {
            $this->settings->setValue(self::SETTINGS_PREFIX . 'gitlab_url', rtrim(trim($values['gitlab_url']), '/'), false);
        }

        if (isset($values['project_id'])) {
            $this->settings->setValue(self::SETTINGS_PREFIX . 'project_id', trim($values['project_id']), false);
        }

        if (isset($values['branch'])) {
            $this->settings->setValue(self::SETTINGS_PREFIX . 'branch', trim($values['branch']) ?: 'main', false);
        }

        // A blank submitted token means "leave the saved one alone" — the
        // admin form never re-displays the real token, so an empty field
        // must not be interpreted as "clear it".
        if (isset($values['access_token']) && trim($values['access_token']) !== '') {
            $this->settings->setValue(self::SETTINGS_PREFIX . 'access_token', $this->encryptToken(trim($values['access_token'])), false);
        }

        if (isset($values['webhook_secret']) && trim($values['webhook_secret']) !== '') {
            $this->settings->setValue(self::SETTINGS_PREFIX . 'webhook_secret', trim($values['webhook_secret']), false);
        }
    }

    /**
     * @return array{sha: string, short_sha: string, message: string, date: string}
     */
    public function getRemoteLatestCommit(): array
    {
        $config = $this->config();
        $path   = '/api/v4/projects/' . rawurlencode($config['project_id']) . '/repository/commits/' . rawurlencode($config['branch']);

        $commit = $this->apiGet($config, $path);

        return [
            'sha'       => $commit['id'],
            'short_sha' => $commit['short_id'] ?? substr($commit['id'], 0, 8),
            'message'   => trim(explode("\n", (string) ($commit['title'] ?? $commit['message'] ?? ''))[0]),
            'date'      => $commit['committed_date'] ?? $commit['created_at'] ?? '',
        ];
    }

    /**
     * @return array{sha: string|null, short_sha: string|null, at: string|null, message: string|null}
     */
    public function getLastDeployed(): array
    {
        return [
            'sha'       => $this->settings->get(self::SETTINGS_PREFIX . 'last_sha'),
            'short_sha' => $this->settings->get(self::SETTINGS_PREFIX . 'last_short_sha'),
            'at'        => $this->settings->get(self::SETTINGS_PREFIX . 'last_at'),
            'message'   => $this->settings->get(self::SETTINGS_PREFIX . 'last_message'),
        ];
    }

    /**
     * Public, display-only wrapper around detectGitCapability() — lets
     * Admin -> Deploy show which method a pull() would actually use right
     * now, without exposing the SSH remote URL or the rest of the
     * detection internals as part of this class's public API.
     *
     * @return array{mode: 'git'|'archive', is_git_repo: bool}
     */
    public function describeMode(): array
    {
        if (! $this->isConfigured()) {
            return ['mode' => 'archive', 'is_git_repo' => false];
        }

        $capability = $this->detectGitCapability($this->config());

        return [
            'mode'        => $capability['usable'] ? 'git' : 'archive',
            'is_git_repo' => $capability['is_git_repo'],
        ];
    }

    /**
     * Downloads and applies the latest commit on the configured branch.
     *
     * @return array{success: bool, message: string, old_sha: ?string, new_sha: ?string, files_written: int, backup_path: ?string, composer_note: ?string, migrations_note: ?string}
     */
    public function pull(?int $triggeredByUserId = null): array
    {
        if (! $this->isConfigured()) {
            return $this->failure('GitLab connection is not configured yet (project and branch are required).');
        }

        $config     = $this->config();
        $capability = $this->detectGitCapability($config);

        try {
            $remote = $capability['usable']
                ? $this->getRemoteLatestCommitViaGit($capability['remote_url'], $config['branch'])
                : $this->getRemoteLatestCommit();
        } catch (RuntimeException $e) {
            return $this->failure('Could not reach GitLab: ' . $e->getMessage());
        }

        $last = $this->getLastDeployed();

        if ($last['sha'] === $remote['sha']) {
            return [
                'success'         => true,
                'message'         => 'Already up to date (' . $remote['short_sha'] . ')' . ($capability['usable'] ? ' — via git/SSH.' : '.'),
                'old_sha'         => $last['sha'],
                'new_sha'         => $remote['sha'],
                'files_written'   => 0,
                'backup_path'     => null,
                'composer_note'   => null,
                'migrations_note' => null,
            ];
        }

        $workDir = $this->rootPath . 'writable' . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR;
        $this->ensureDir($workDir);
        $this->ensureDir($workDir . 'backups');

        $zipPath     = $workDir . 'download-' . $remote['short_sha'] . '.zip';
        $extractPath = $workDir . 'extract-' . $remote['short_sha'] . DIRECTORY_SEPARATOR;

        try {
            // 1. Back up the live codebase first — this is the "no other
            //    way to fix it by hand" safety net, taken the same way
            //    regardless of which method below fetches the new code.
            $backupPath = $workDir . 'backups' . DIRECTORY_SEPARATOR . 'backup-' . date('Ymd-His') . '-' . ($last['short_sha'] ?: 'initial') . '.zip';
            $this->zipDirectory($this->rootPath, $backupPath);
            $this->pruneOldBackups($workDir . 'backups', 5);

            if ($capability['usable']) {
                // 2a. Real git, over SSH — fetch+fast-forward an existing
                //     checkout, or clone once if this is the first deploy.
                $written = $this->applyViaGit($capability, $config, $remote);
            } else {
                // 2b. No usable shell/SSH — download the commit as a .zip
                //     via GitLab's own archive endpoint, no `git` involved.
                $this->downloadArchive($config, $remote['sha'], $zipPath);
                $this->extractZip($zipPath, $extractPath);
                $sourceRoot = $this->findSingleSubdirectory($extractPath);
                $written    = $this->copyRecursive($sourceRoot, $this->rootPath);
            }
        } catch (RuntimeException $e) {
            $this->cleanup($zipPath, $extractPath);

            return $this->failure('Deploy failed: ' . $e->getMessage());
        }

        $this->cleanup($zipPath, $extractPath);

        $composerNote   = in_array('composer.lock', $written, true) ? $this->tryComposerInstall() : null;
        $migrationsNote = $this->touchedMigrations($written) ? $this->tryMigrate() : null;

        $this->settings->setValue(self::SETTINGS_PREFIX . 'last_sha', $remote['sha'], false);
        $this->settings->setValue(self::SETTINGS_PREFIX . 'last_short_sha', $remote['short_sha'], false);
        $this->settings->setValue(self::SETTINGS_PREFIX . 'last_at', date('Y-m-d H:i:s'), false);
        $this->settings->setValue(self::SETTINGS_PREFIX . 'last_message', $remote['message'], false);

        // Best-effort audit trail — a deploy that copied every file
        // correctly must still report success even if this write itself
        // fails for some unrelated reason (e.g. no HTTP request context,
        // as when this runs from a CLI command; ActivityLogModel::record()
        // reads the current request's user agent, which only exists on a
        // real IncomingRequest).
        try {
            (new ActivityLogModel())->record($triggeredByUserId, 'git_deploy_pull', 'deploy', null);
        } catch (\Throwable $e) {
            log_message('warning', 'GitLabDeployer: could not write the activity log entry: ' . $e->getMessage());
        }

        $via = $capability['usable'] ? ' via git/SSH' : ' via archive download';

        return [
            'success'         => true,
            'message'         => 'Deployed ' . $remote['short_sha'] . ' (' . $remote['message'] . ')' . $via . ' — ' . count($written) . ' files updated.',
            'old_sha'         => $last['sha'],
            'new_sha'         => $remote['sha'],
            'files_written'   => count($written),
            'backup_path'     => basename($backupPath),
            'composer_note'   => $composerNote,
            'migrations_note' => $migrationsNote,
        ];
    }

    /**
     * @return list<array{file: string, size: int, created_at: string}>
     */
    public function listBackups(): array
    {
        $dir = $this->rootPath . 'writable/deploy/backups';

        if (! is_dir($dir)) {
            return [];
        }

        $backups = [];

        foreach (glob($dir . '/backup-*.zip') ?: [] as $file) {
            $backups[] = [
                'file'       => basename($file),
                'size'       => filesize($file) ?: 0,
                'created_at' => date('Y-m-d H:i:s', filemtime($file) ?: time()),
            ];
        }

        usort($backups, static fn (array $a, array $b) => strcmp($b['file'], $a['file']));

        return $backups;
    }

    /**
     * Restores the codebase from a previously taken backup zip — the
     * rollback path when a pull() goes wrong and there's no SSH access to
     * fix files by hand any other way.
     *
     * @return array{success: bool, message: string}
     */
    public function restoreBackup(string $filename, ?int $triggeredByUserId = null): array
    {
        $filename = basename($filename); // no path traversal via the filename param
        $path     = $this->rootPath . 'writable/deploy/backups/' . $filename;

        if (! is_file($path) || ! str_ends_with($filename, '.zip') || ! str_starts_with($filename, 'backup-')) {
            return ['success' => false, 'message' => 'Backup file not found.'];
        }

        $extractPath = $this->rootPath . 'writable/deploy/restore-tmp/';

        try {
            $this->extractZip($path, $extractPath);
            $written = $this->copyRecursive($extractPath, $this->rootPath);
        } catch (RuntimeException $e) {
            $this->removeDir($extractPath);

            return ['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
        }

        $this->removeDir($extractPath);

        try {
            (new ActivityLogModel())->record($triggeredByUserId, 'git_deploy_restore', 'deploy', null);
        } catch (\Throwable $e) {
            log_message('warning', 'GitLabDeployer: could not write the activity log entry: ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Restored ' . count($written) . ' files from ' . $filename . '.'];
    }

    // ------------------------------------------------------------------
    // Git over SSH — used automatically instead of the HTTPS/archive path
    // the moment it's detected as actually working (see class docblock).
    // ------------------------------------------------------------------

    /**
     * Checks — quickly, and without ever risking a hung request — whether
     * this server can do a real `git` deploy over SSH: shell_exec()/exec()
     * must be callable, the `git` binary must be on PATH, and a
     * non-interactive `git ls-remote` against the configured project must
     * actually authenticate. Every one of those can silently be false on a
     * typical shared host, which is the normal case this whole class exists
     * for — so this always fails closed to "not usable" rather than
     * throwing, and pull() falls back to the archive method.
     *
     * @param array{gitlab_url: string, project_id: string, branch: string} $config
     *
     * @return array{usable: bool, remote_url: ?string, is_git_repo: bool}
     */
    protected function detectGitCapability(array $config): array
    {
        $result = ['usable' => false, 'remote_url' => null, 'is_git_repo' => is_dir($this->rootPath . '.git')];

        if (! $this->shellExecAvailable()) {
            return $result;
        }

        $gitVersion = @shell_exec('git --version 2>&1');

        if ($gitVersion === null || ! str_contains($gitVersion, 'git version')) {
            return $result;
        }

        $host   = parse_url($config['gitlab_url'], PHP_URL_HOST) ?: 'gitlab.com';
        $result['remote_url'] = "git@{$host}:{$config['project_id']}.git";

        // BatchMode=yes is the load-bearing safety flag here: without it,
        // a host key prompt or a passphrase-protected key with no agent
        // would block on a TTY that never comes, hanging this PHP request
        // (and, on a busy site, eventually the whole worker pool) instead
        // of just failing. ConnectTimeout is a second layer under that for
        // slow/unreachable networks rather than auth prompts specifically.
        $probe = @shell_exec($this->gitSshEnv() . ' timeout 10 git ls-remote '
            . escapeshellarg($result['remote_url']) . ' HEAD 2>&1');

        $result['usable'] = $probe !== null && preg_match('/^[0-9a-f]{40}\s/', trim((string) $probe)) === 1;

        return $result;
    }

    /**
     * `GIT_SSH_COMMAND=...` prefix (see detectGitCapability()'s docblock
     * for why every flag here matters) shared by every git invocation that
     * talks to the network, so none of them can hang the request.
     */
    protected function gitSshEnv(): string
    {
        return 'GIT_SSH_COMMAND=' . escapeshellarg('ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10');
    }

    /**
     * @return array{sha: string, short_sha: string, message: string, date: string}
     */
    protected function getRemoteLatestCommitViaGit(string $remoteUrl, string $branch): array
    {
        $output = @shell_exec($this->gitSshEnv() . ' timeout 15 git ls-remote '
            . escapeshellarg($remoteUrl) . ' ' . escapeshellarg('refs/heads/' . $branch) . ' 2>&1');

        if ($output === null || ! preg_match('/^([0-9a-f]{40})\s/', trim((string) $output), $m)) {
            throw new RuntimeException("git ls-remote could not find branch '{$branch}' (" . trim((string) $output) . ')');
        }

        // `git ls-remote` only gives a sha — a message/date needs one more
        // (still fast: single commit, no history) network round trip.
        $sha     = $m[1];
        $subject = trim((string) @shell_exec($this->gitSshEnv() . ' timeout 15 git log -1 --format=%s ' . escapeshellarg($sha) . ' 2>&1'));

        return [
            'sha'       => $sha,
            'short_sha' => substr($sha, 0, 8),
            'message'   => $subject !== '' ? $subject : '(no commit message)',
            'date'      => '',
        ];
    }

    /**
     * @param array{remote_url: string, is_git_repo: bool} $capability
     * @param array{branch: string}                        $config
     * @param array{sha: string}                            $remote
     *
     * @return list<string> relative paths of every file changed by this deploy
     */
    protected function applyViaGit(array $capability, array $config, array $remote): array
    {
        if ($capability['is_git_repo']) {
            return $this->applyViaGitFetch($capability['remote_url'], $config['branch'], $remote['sha']);
        }

        return $this->applyViaGitClone($capability['remote_url'], $config['branch']);
    }

    /**
     * @return list<string>
     */
    protected function applyViaGitFetch(string $remoteUrl, string $branch, string $targetSha): array
    {
        $git = 'git -C ' . escapeshellarg(rtrim($this->rootPath, '/\\'));

        $before = trim((string) @shell_exec("{$git} rev-parse HEAD 2>&1"));

        // Not checked for null/empty here — a quiet, successful `git fetch`
        // routinely produces zero output, and shell_exec() returns null for
        // "ran fine, nothing to print" exactly the same as for "didn't run
        // at all". The rev-parse comparison below is the one reliable
        // success signal: it reflects the actual end state, not however
        // talkative this particular git/network call happened to be.
        @shell_exec($this->gitSshEnv() . " {$git} fetch " . escapeshellarg($remoteUrl)
            . ' ' . escapeshellarg($branch) . ':refs/remotes/gitlab-deploy/' . escapeshellarg($branch) . ' --quiet 2>&1');

        // --ff-only refuses rather than clobbers if the checkout has
        // diverged (e.g. someone committed directly on the server) —
        // exactly the "stop and tell a human" behaviour a deploy tool
        // should have for anything it can't resolve safely on its own.
        @shell_exec("{$git} merge --ff-only refs/remotes/gitlab-deploy/" . escapeshellarg($branch) . ' 2>&1');
        $after = trim((string) @shell_exec("{$git} rev-parse HEAD 2>&1"));

        if (strcasecmp($after, $targetSha) !== 0) {
            throw new RuntimeException('git fetch/merge did not reach ' . substr($targetSha, 0, 8)
                . ' (the server checkout may have diverged from ' . $branch . ' — resolve this by hand over SSH, then pull again)');
        }

        $diffOutput = @shell_exec("{$git} diff --name-only " . escapeshellarg($before) . ' ' . escapeshellarg($after) . ' 2>&1');

        return array_values(array_filter(array_map('trim', explode("\n", (string) $diffOutput))));
    }

    /**
     * First-ever deploy on a server that already has working shell + SSH
     * access: clones into a throwaway temp directory (git needs an empty
     * target), reuses the exact same copyRecursive() exclusion logic the
     * archive path already relies on to bring those files into the live
     * install, then plants the resulting .git so every later pull() on
     * this server takes the fast applyViaGitFetch() path instead.
     *
     * @return list<string>
     */
    protected function applyViaGitClone(string $remoteUrl, string $branch): array
    {
        $tempClone = $this->rootPath . 'writable' . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'clone-tmp' . DIRECTORY_SEPARATOR;
        $this->removeDir($tempClone);

        $cloneOutput = @shell_exec($this->gitSshEnv() . ' timeout 120 git clone --branch ' . escapeshellarg($branch)
            . ' --single-branch ' . escapeshellarg($remoteUrl) . ' ' . escapeshellarg(rtrim($tempClone, '/\\')) . ' 2>&1');

        if (! is_dir($tempClone . '.git')) {
            $this->removeDir($tempClone);
            throw new RuntimeException('git clone failed: ' . trim((string) $cloneOutput));
        }

        // copyRecursive() treats .git as a protected path (correctly so
        // for the archive-extraction case, where it must never appear at
        // all) — so it's moved into place separately, deliberately, here.
        $written = $this->copyRecursive($tempClone, $this->rootPath);

        $this->removeDir($this->rootPath . '.git');
        rename($tempClone . '.git', $this->rootPath . '.git');
        $this->removeDir($tempClone);

        return $written;
    }

    // ------------------------------------------------------------------
    // HTTP
    // ------------------------------------------------------------------

    /**
     * @param array{gitlab_url: string, project_id: string, branch: string, access_token: string} $config
     */
    protected function apiGet(array $config, string $path): array
    {
        $ch = curl_init($config['gitlab_url'] . $path);

        $headers = ['Accept: application/json'];
        if ($config['access_token'] !== '') {
            $headers[] = 'PRIVATE-TOKEN: ' . $config['access_token'];
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('network error (' . $error . ')');
        }

        if ($status !== 200) {
            $decoded = json_decode((string) $body, true);
            throw new RuntimeException('GitLab API returned HTTP ' . $status . ($decoded['message'] ?? '' ? ': ' . $decoded['message'] : ''));
        }

        $decoded = json_decode((string) $body, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('GitLab API returned an unexpected response.');
        }

        return $decoded;
    }

    /**
     * @param array{gitlab_url: string, project_id: string, access_token: string} $config
     */
    protected function downloadArchive(array $config, string $sha, string $destination): void
    {
        $url = $config['gitlab_url'] . '/api/v4/projects/' . rawurlencode($config['project_id'])
            . '/repository/archive.zip?sha=' . rawurlencode($sha);

        $fh = fopen($destination, 'wb');

        if ($fh === false) {
            throw new RuntimeException("could not open {$destination} for writing (check writable/deploy/ permissions)");
        }

        $headers = [];
        if ($config['access_token'] !== '') {
            $headers[] = 'PRIVATE-TOKEN: ' . $config['access_token'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FILE           => $fh,
            CURLOPT_TIMEOUT        => 300, // a full-repo archive can be slow on a big project
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $ok     = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if (! $ok || $status !== 200) {
            @unlink($destination);
            throw new RuntimeException('archive download failed (HTTP ' . $status . ($error !== '' ? ", {$error}" : '') . ')');
        }

        if (! is_file($destination) || filesize($destination) < 100) {
            @unlink($destination);
            throw new RuntimeException('downloaded archive looks empty or truncated');
        }
    }

    // ------------------------------------------------------------------
    // Filesystem
    // ------------------------------------------------------------------

    protected function extractZip(string $zipPath, string $destDir): void
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException("the PHP 'zip' extension is not available on this server");
        }

        $this->ensureDir($destDir);

        $zip = new \ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('could not open the downloaded archive');
        }

        if (! $zip->extractTo($destDir)) {
            $zip->close();
            throw new RuntimeException('could not extract the downloaded archive (disk full? permissions?)');
        }

        $zip->close();
    }

    /**
     * GitLab's archive.zip always wraps its contents in one folder named
     * "{project}-{branch}-{short sha}/" — find and return it so callers
     * copy from the real source root, not from around it.
     */
    protected function findSingleSubdirectory(string $dir): string
    {
        $entries = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));

        if (count($entries) === 1 && is_dir($dir . $entries[0])) {
            return rtrim($dir . $entries[0], '/\\') . DIRECTORY_SEPARATOR;
        }

        // Not the expected single-folder shape — fall back to using the
        // extraction root itself rather than failing outright.
        return $dir;
    }

    /**
     * Copies every file from $from to $to, skipping self::PROTECTED_PATHS
     * (matched relative to $to) entirely — those directories/files are
     * never created, overwritten, descended into, or deleted.
     *
     * @return list<string> relative paths of every file actually written
     */
    protected function copyRecursive(string $from, string $to): array
    {
        $from    = rtrim($from, '/\\') . DIRECTORY_SEPARATOR;
        $to      = rtrim($to, '/\\') . DIRECTORY_SEPARATOR;
        $written = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = substr($item->getPathname(), strlen($from));
            $relative = str_replace('\\', '/', $relative);

            if ($this->isProtected($relative)) {
                continue;
            }

            $target = $to . $relative;

            if ($item->isDir()) {
                $this->ensureDir($target);
                continue;
            }

            if ($item->isLink()) {
                continue; // defensive: never follow/copy symlinks from an untrusted archive
            }

            $this->ensureDir(dirname($target));

            if (! copy($item->getPathname(), $target)) {
                throw new RuntimeException("could not write {$relative} (check file permissions)");
            }

            $written[] = $relative;
        }

        return $written;
    }

    protected function isProtected(string $relativePath): bool
    {
        foreach (self::PROTECTED_PATHS as $protected) {
            if ($relativePath === $protected || str_starts_with($relativePath, $protected . '/')) {
                return true;
            }
        }

        return false;
    }

    protected function zipDirectory(string $sourceDir, string $zipPath): void
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException("the PHP 'zip' extension is not available on this server");
        }

        $this->ensureDir(dirname($zipPath));

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('could not create the backup archive');
        }

        $sourceDir = rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceDir)));

            if ($this->isProtected($relative) || $item->isDir() || $item->isLink()) {
                continue;
            }

            $zip->addFile($item->getPathname(), $relative);
        }

        $zip->close();
    }

    protected function pruneOldBackups(string $backupDir, int $keep): void
    {
        $files = glob($backupDir . '/backup-*.zip') ?: [];
        rsort($files);

        foreach (array_slice($files, $keep) as $stale) {
            @unlink($stale);
        }
    }

    protected function cleanup(string $zipPath, string $extractPath): void
    {
        @unlink($zipPath);
        $this->removeDir($extractPath);
    }

    protected function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    protected function ensureDir(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("could not create directory {$dir}");
        }
    }

    // ------------------------------------------------------------------
    // Optional post-deploy steps (best-effort — never fatal if unavailable)
    // ------------------------------------------------------------------

    protected function touchedMigrations(array $written): bool
    {
        foreach ($written as $path) {
            if (str_starts_with($path, 'app/Database/Migrations/')) {
                return true;
            }
        }

        return false;
    }

    protected function tryComposerInstall(): string
    {
        if (! $this->shellExecAvailable()) {
            return 'composer.lock changed — this host does not allow shell_exec(), so run "composer install --no-dev" yourself (via your host\'s terminal/SSH-free console if it has one).';
        }

        $output = shell_exec('cd ' . escapeshellarg($this->rootPath) . ' && composer install --no-dev --optimize-autoloader 2>&1');

        return $output !== null
            ? 'composer.lock changed — ran "composer install --no-dev" automatically.'
            : 'composer.lock changed — attempted "composer install" but got no output; verify vendor/ manually.';
    }

    protected function tryMigrate(): string
    {
        if (! $this->shellExecAvailable()) {
            return 'Migration files changed — this host does not allow shell_exec(), so run "php spark migrate" yourself.';
        }

        $php    = PHP_BINARY ?: 'php';
        $output = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($this->rootPath . 'spark') . ' migrate 2>&1');

        return $output !== null
            ? "Migration files changed — ran \"php spark migrate\" automatically. Output:\n" . trim($output)
            : 'Migration files changed — attempted "php spark migrate" but got no output; run it manually to be safe.';
    }

    protected function shellExecAvailable(): bool
    {
        if (! function_exists('shell_exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('shell_exec', $disabled, true);
    }

    // ------------------------------------------------------------------
    // Token at rest
    // ------------------------------------------------------------------

    protected function encryptToken(string $token): string
    {
        if (config(\Config\Encryption::class)->key === '') {
            // No encryption key configured yet (see README: php spark
            // key:generate) — store as-is rather than blocking a first
            // deploy that, by definition, has no SSH access to run that
            // command. Same trust boundary as every other settings value
            // already stored in this table.
            return 'plain:' . $token;
        }

        return 'enc:' . base64_encode(service('encrypter')->encrypt($token));
    }

    protected function decryptToken(string $stored): string
    {
        if ($stored === '') {
            return '';
        }

        if (str_starts_with($stored, 'plain:')) {
            return substr($stored, 6);
        }

        if (str_starts_with($stored, 'enc:')) {
            try {
                return service('encrypter')->decrypt(base64_decode(substr($stored, 4)));
            } catch (\Throwable) {
                return ''; // key rotated/changed since — treat as unset rather than fatal
            }
        }

        return $stored; // legacy/unrecognized format — return as-is
    }

    protected function failure(string $message): array
    {
        return [
            'success'         => false,
            'message'         => $message,
            'old_sha'         => null,
            'new_sha'         => null,
            'files_written'   => 0,
            'backup_path'     => null,
            'composer_note'   => null,
            'migrations_note' => null,
        ];
    }
}
