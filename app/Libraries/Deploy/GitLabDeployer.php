<?php

namespace App\Libraries\Deploy;

use App\Models\ActivityLogModel;
use App\Models\SettingModel;
use RuntimeException;

/**
 * Deploy code from GitLab or GitHub. Works with no git/SSH/shell access,
 * and automatically switches to real git over SSH when detected.
 */
class GitLabDeployer
{
    protected const PROTECTED_PATHS = ['.env', 'writable', 'public/uploads', '.git'];
    protected const SETTINGS_PREFIX = 'git_deploy_';

    protected SettingModel $settings;
    protected string $rootPath;

    public function __construct(?SettingModel $settings = null, ?string $rootPath = null)
    {
        $this->settings = $settings ?? new SettingModel();
        $this->rootPath = rtrim($rootPath ?? ROOTPATH, '/\\') . DIRECTORY_SEPARATOR;

        if (! is_dir($this->rootPath . 'app') || ! is_dir($this->rootPath . 'public')) {
            throw new RuntimeException('GitLabDeployer: root path does not look like a LightCMS install: ' . $this->rootPath);
        }
    }

    public function isConfigured(): bool
    {
        $config = $this->config();
        return $config['gitlab_url'] !== '' && $config['project_id'] !== '' && $config['branch'] !== '';
    }

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
        if (isset($values['access_token']) && trim($values['access_token']) !== '') {
            $this->settings->setValue(self::SETTINGS_PREFIX . 'access_token', $this->encryptToken(trim($values['access_token'])), false);
        }
        if (isset($values['webhook_secret']) && trim($values['webhook_secret']) !== '') {
            $this->settings->setValue(self::SETTINGS_PREFIX . 'webhook_secret', trim($values['webhook_secret']), false);
        }
    }

    protected function detectPlatform(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        return str_contains($host, 'github.com') ? 'github' : 'gitlab';
    }

    public function getRemoteLatestCommit(): array
    {
        $config   = $this->config();
        $platform = $this->detectPlatform($config['gitlab_url']);

        return $platform === 'github'
            ? $this->getGitHubLatestCommit($config)
            : $this->getGitLabLatestCommit($config);
    }

    protected function getGitHubLatestCommit(array $config): array
    {
        $projectId = str_replace('%2F', '/', rawurlencode($config['project_id']));
        $path      = '/repos/' . $projectId . '/commits/' . rawurlencode($config['branch']);
        $commit    = $this->apiGetGitHub($config, $path);

        return [
            'sha'       => $commit['sha'],
            'short_sha' => substr($commit['sha'], 0, 8),
            'message'   => trim(explode("\n", (string) ($commit['commit']['message'] ?? ''))[0]),
            'date'      => $commit['commit']['committer']['date'] ?? '',
        ];
    }

    protected function getGitLabLatestCommit(array $config): array
    {
        $path   = '/api/v4/projects/' . rawurlencode($config['project_id']) . '/repository/commits/' . rawurlencode($config['branch']);
        $commit = $this->apiGetGitLab($config, $path);

        return [
            'sha'       => $commit['id'],
            'short_sha' => $commit['short_id'] ?? substr($commit['id'], 0, 8),
            'message'   => trim(explode("\n", (string) ($commit['title'] ?? $commit['message'] ?? ''))[0]),
            'date'      => $commit['committed_date'] ?? $commit['created_at'] ?? '',
        ];
    }

    public function getLastDeployed(): array
    {
        return [
            'sha'       => $this->settings->get(self::SETTINGS_PREFIX . 'last_sha'),
            'short_sha' => $this->settings->get(self::SETTINGS_PREFIX . 'last_short_sha'),
            'at'        => $this->settings->get(self::SETTINGS_PREFIX . 'last_at'),
            'message'   => $this->settings->get(self::SETTINGS_PREFIX . 'last_message'),
        ];
    }

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

    public function pull(?int $triggeredByUserId = null): array
    {
        if (! $this->isConfigured()) {
            return $this->failure('Repository connection is not configured yet.');
        }

        $config     = $this->config();
        $capability = $this->detectGitCapability($config);

        try {
            $remote = $capability['usable']
                ? $this->getRemoteLatestCommitViaGit($capability['remote_url'], $config['branch'])
                : $this->getRemoteLatestCommit();
        } catch (\RuntimeException $e) {
            return $this->failure('Could not reach repository: ' . $e->getMessage());
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
            $backupPath = $workDir . 'backups' . DIRECTORY_SEPARATOR . 'backup-' . date('Ymd-His') . '-' . ($last['short_sha'] ?: 'initial') . '.zip';
            $this->zipDirectory($this->rootPath, $backupPath);
            $this->pruneOldBackups($workDir . 'backups', 5);

            if ($capability['usable']) {
                $written = $this->applyViaGit($capability, $config, $remote);
            } else {
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

        try {
            (new ActivityLogModel())->record($triggeredByUserId, 'git_deploy_pull', 'deploy', null);
        } catch (\Throwable $e) {
            log_message('warning', 'Deployer: could not write activity log: ' . $e->getMessage());
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

    public function restoreBackup(string $filename, ?int $triggeredByUserId = null): array
    {
        $filename = basename($filename);
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
            log_message('warning', 'Deployer: could not write activity log: ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Restored ' . count($written) . ' files from ' . $filename . '.'];
    }

    // ------------------------------------------------------------------
    // Git over SSH
    // ------------------------------------------------------------------

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

        $platform = $this->detectPlatform($config['gitlab_url']);
        $host     = parse_url($config['gitlab_url'], PHP_URL_HOST) ?: ($platform === 'github' ? 'github.com' : 'gitlab.com');

        $result['remote_url'] = "git@{$host}:{$config['project_id']}.git";

        $probe = @shell_exec($this->gitSshEnv() . ' timeout 10 git ls-remote '
            . escapeshellarg($result['remote_url']) . ' HEAD 2>&1');

        $result['usable'] = $probe !== null && preg_match('/^[0-9a-f]{40}\s/', trim((string) $probe)) === 1;

        return $result;
    }

    protected function gitSshEnv(): string
    {
        return 'GIT_SSH_COMMAND=' . escapeshellarg('ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10');
    }

    protected function getRemoteLatestCommitViaGit(string $remoteUrl, string $branch): array
    {
        $output = @shell_exec($this->gitSshEnv() . ' timeout 15 git ls-remote '
            . escapeshellarg($remoteUrl) . ' ' . escapeshellarg('refs/heads/' . $branch) . ' 2>&1');

        if ($output === null || ! preg_match('/^([0-9a-f]{40})\s/', trim((string) $output), $m)) {
            throw new RuntimeException("git ls-remote could not find branch '{$branch}'");
        }

        $sha     = $m[1];
        $subject = trim((string) @shell_exec($this->gitSshEnv() . ' timeout 15 git log -1 --format=%s ' . escapeshellarg($sha) . ' 2>&1'));

        return [
            'sha'       => $sha,
            'short_sha' => substr($sha, 0, 8),
            'message'   => $subject !== '' ? $subject : '(no commit message)',
            'date'      => '',
        ];
    }

    protected function applyViaGit(array $capability, array $config, array $remote): array
    {
        if ($capability['is_git_repo']) {
            return $this->applyViaGitFetch($capability['remote_url'], $config['branch'], $remote['sha']);
        }
        return $this->applyViaGitClone($capability['remote_url'], $config['branch']);
    }

    protected function applyViaGitFetch(string $remoteUrl, string $branch, string $targetSha): array
    {
        $git   = 'git -C ' . escapeshellarg(rtrim($this->rootPath, '/\\'));
        $before = trim((string) @shell_exec("{$git} rev-parse HEAD 2>&1"));

        @shell_exec($this->gitSshEnv() . " {$git} fetch " . escapeshellarg($remoteUrl)
            . ' ' . escapeshellarg($branch) . ':refs/remotes/gitlab-deploy/' . escapeshellarg($branch) . ' --quiet 2>&1');

        @shell_exec("{$git} merge --ff-only refs/remotes/gitlab-deploy/" . escapeshellarg($branch) . ' 2>&1');
        $after = trim((string) @shell_exec("{$git} rev-parse HEAD 2>&1"));

        if (strcasecmp($after, $targetSha) !== 0) {
            throw new RuntimeException('git fetch/merge did not reach ' . substr($targetSha, 0, 8));
        }

        $diffOutput = @shell_exec("{$git} diff --name-only " . escapeshellarg($before) . ' ' . escapeshellarg($after) . ' 2>&1');
        return array_values(array_filter(array_map('trim', explode("\n", (string) $diffOutput))));
    }

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

        $written = $this->copyRecursive($tempClone, $this->rootPath);

        $this->removeDir($this->rootPath . '.git');
        rename($tempClone . '.git', $this->rootPath . '.git');
        $this->removeDir($tempClone);

        return $written;
    }

    // ------------------------------------------------------------------
    // HTTP API — GitHub
    // ------------------------------------------------------------------

    protected function apiGetGitHub(array $config, string $path): array
    {
        $url = 'https://api.github.com' . $path;
        $ch  = curl_init($url);

        $headers = ['Accept: application/vnd.github.v3+json', 'User-Agent: LightCMS-Deployer'];
        if ($config['access_token'] !== '') {
            $headers[] = 'Authorization: token ' . $config['access_token'];
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
            throw new RuntimeException('GitHub API returned HTTP ' . $status . (isset($decoded['message']) ? ': ' . $decoded['message'] : ''));
        }
        $decoded = json_decode((string) $body, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('GitHub API returned an unexpected response.');
        }
        return $decoded;
    }

    // ------------------------------------------------------------------
    // HTTP API — GitLab
    // ------------------------------------------------------------------

    protected function apiGetGitLab(array $config, string $path): array
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
            throw new RuntimeException('GitLab API returned HTTP ' . $status . (isset($decoded['message']) ? ': ' . $decoded['message'] : ''));
        }
        $decoded = json_decode((string) $body, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('GitLab API returned an unexpected response.');
        }
        return $decoded;
    }

    // ------------------------------------------------------------------
    // Archive download
    // ------------------------------------------------------------------

    protected function downloadArchive(array $config, string $sha, string $destination): void
    {
        $platform = $this->detectPlatform($config['gitlab_url']);

        if ($platform === 'github') {
            $projectId = str_replace('%2F', '/', rawurlencode($config['project_id']));
            $url       = 'https://api.github.com/repos/' . $projectId . '/zipball/' . rawurlencode($sha);
        } else {
            $url = $config['gitlab_url'] . '/api/v4/projects/' . rawurlencode($config['project_id'])
                . '/repository/archive.zip?sha=' . rawurlencode($sha);
        }

        $fh = fopen($destination, 'wb');
        if ($fh === false) {
            throw new RuntimeException("could not open {$destination} for writing");
        }

        $headers = ['User-Agent: LightCMS-Deployer'];
        if ($platform === 'github') {
            $headers[] = 'Accept: application/vnd.github.v3+zip';
            if ($config['access_token'] !== '') {
                $headers[] = 'Authorization: token ' . $config['access_token'];
            }
        } else {
            if ($config['access_token'] !== '') {
                $headers[] = 'PRIVATE-TOKEN: ' . $config['access_token'];
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FILE           => $fh,
            CURLOPT_TIMEOUT        => 300,
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
            throw new RuntimeException('archive download failed (HTTP ' . $status . ')');
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
            throw new RuntimeException("the PHP 'zip' extension is not available");
        }
        $this->ensureDir($destDir);
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('could not open the downloaded archive');
        }
        if (! $zip->extractTo($destDir)) {
            $zip->close();
            throw new RuntimeException('could not extract the downloaded archive');
        }
        $zip->close();
    }

    protected function findSingleSubdirectory(string $dir): string
    {
        $entries = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
        if (count($entries) === 1 && is_dir($dir . $entries[0])) {
            return rtrim($dir . $entries[0], '/\\') . DIRECTORY_SEPARATOR;
        }
        return $dir;
    }

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
                continue;
            }

            $this->ensureDir(dirname($target));

            if (! copy($item->getPathname(), $target)) {
                throw new RuntimeException("could not write {$relative}");
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
            throw new RuntimeException("the PHP 'zip' extension is not available");
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
    // Post-deploy steps
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
            return 'composer.lock changed — run "composer install --no-dev" yourself.';
        }
        $output = shell_exec('cd ' . escapeshellarg($this->rootPath) . ' && composer install --no-dev --optimize-autoloader 2>&1');
        return $output !== null
            ? 'composer.lock changed — ran "composer install --no-dev" automatically.'
            : 'composer.lock changed — verify vendor/ manually.';
    }

    protected function tryMigrate(): string
    {
        if (! $this->shellExecAvailable()) {
            return 'Migration files changed — run "php spark migrate" yourself.';
        }
        $php    = PHP_BINARY ?: 'php';
        $output = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($this->rootPath . 'spark') . ' migrate 2>&1');
        return $output !== null
            ? "Migration files changed — ran \"php spark migrate\" automatically."
            : 'Migration files changed — run "php spark migrate" manually.';
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
    // Token
    // ------------------------------------------------------------------

    protected function encryptToken(string $token): string
    {
        if (config(\Config\Encryption::class)->key === '') {
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
                return '';
            }
        }
        return $stored;
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
