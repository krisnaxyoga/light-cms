<?php

namespace App\Commands;

use App\Models\ActivityLogModel;
use App\Models\CommentModel;
use App\Models\NotFoundLogModel;
use App\Models\PostModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Config\LightCMS as LightCMSConfig;

/**
 * `php spark lightcms:cleanup` — the scheduled housekeeping job from
 * PRD ADDENDUM §10 (DatabaseAutoCleanup). This scaffold has no built-in
 * scheduler, so wire it up with a real OS cron, e.g.:
 *
 *   0 3 * * * cd /path/to/lightcms && php spark lightcms:cleanup >> writable/logs/cleanup.log 2>&1
 */
class CleanupCommand extends BaseCommand
{
    protected $group       = 'LightCMS';
    protected $name        = 'lightcms:cleanup';
    protected $description = 'Purge old trash/spam/logs and OPTIMIZE TABLE every table.';

    public function run(array $params)
    {
        $config = config(LightCMSConfig::class);

        (new PostModel())
            ->where('status', 'trash')
            ->where('updated_at <', date('Y-m-d H:i:s', strtotime("-{$config->cleanupTrashDays} days")))
            ->delete();
        CLI::write('Deleted old trashed posts/pages.', 'green');

        (new CommentModel())
            ->where('status', 'spam')
            ->where('created_at <', date('Y-m-d H:i:s', strtotime("-{$config->cleanupSpamCommentDays} days")))
            ->delete();
        CLI::write('Deleted old spam comments.', 'green');

        (new ActivityLogModel())
            ->where('created_at <', date('Y-m-d H:i:s', strtotime("-{$config->cleanupActivityLogDays} days")))
            ->delete();
        CLI::write('Deleted old activity logs.', 'green');

        (new NotFoundLogModel())
            ->where('created_at <', date('Y-m-d H:i:s', strtotime("-{$config->cleanupNotFoundLogDays} days")))
            ->delete();
        CLI::write('Deleted old 404 logs.', 'green');

        $this->runWordPressCron();

        $this->optimizeTables();
        CLI::write('OPTIMIZE TABLE run on every table.', 'green');

        CLI::write('Cleanup complete.', 'yellow');
    }

    /**
     * WP-Cron events registered by a theme or plugin. LightCMS has no
     * loopback pseudo-cron, so due events are fired here, from the same
     * real cron entry that runs the rest of this command.
     */
    protected function runWordPressCron(): void
    {
        if (! lcms_wp_enabled()) {
            return;
        }

        $GLOBALS['lightcms_doing_cron'] = true;

        try {
            lcms_wp_boot(lcms_wp_active() ? 'theme' : 'plugins');
            $fired = wp_cron_run_due();
        } catch (\Throwable $e) {
            CLI::write('WP cron failed: ' . $e->getMessage(), 'red');

            return;
        }

        CLI::write(
            $fired === []
                ? 'No WordPress cron events were due.'
                : 'Ran WordPress cron events: ' . implode(', ', array_unique($fired)),
            'green'
        );
    }

    protected function optimizeTables(): void
    {
        $db = Database::connect();

        foreach ($db->listTables() as $table) {
            // Table names come straight from the DB's own catalog, not
            // user input, so building this string directly is safe.
            $db->query('OPTIMIZE TABLE ' . $db->escapeIdentifiers($table));
        }
    }
}
