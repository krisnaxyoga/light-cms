<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\WordPress\Registry;
use App\Models\ActivityLogModel;

/**
 * The Plugins screen: everything found in public/wp-content/plugins, with
 * activate/deactivate. Activation loads the plugin immediately so a fatal
 * error surfaces as a flash message here rather than a white screen on
 * the next request.
 */
class PluginController extends BaseController
{
    public function index()
    {
        $runtime = lcms_wp_boot('plugins');
        $runtime->bootAdmin();

        $repository = $runtime->plugins();

        return view('admin/plugins/index', [
            'plugins'  => $repository->available(),
            'active'   => $repository->active(),
            'failures' => $runtime->pluginFailures(),
            'notices'  => Registry::$adminNotices,
            'enabled'  => lcms_wp_enabled(),
        ]);
    }

    public function activate(string $plugin)
    {
        $plugin = $this->decode($plugin);
        $result = lcms_wp_boot('plugins')->plugins()->activate($plugin);

        if ($result === true) {
            (new ActivityLogModel())->record(session('userId'), 'activate_plugin', 'plugin', null);
            session()->setFlashdata('success', "Plugin '{$plugin}' activated.");
        } else {
            session()->setFlashdata('error', "Could not activate '{$plugin}': {$result}");
        }

        return redirect()->to('/admin/plugins');
    }

    public function deactivate(string $plugin)
    {
        $plugin = $this->decode($plugin);

        if (lcms_wp_boot('plugins')->plugins()->deactivate($plugin)) {
            (new ActivityLogModel())->record(session('userId'), 'deactivate_plugin', 'plugin', null);
            session()->setFlashdata('success', "Plugin '{$plugin}' deactivated.");
        } else {
            session()->setFlashdata('error', "Plugin '{$plugin}' was not active.");
        }

        return redirect()->to('/admin/plugins');
    }

    /** Plugin ids contain a slash ("akismet/akismet.php"), so routes carry them base64url-encoded. */
    protected function decode(string $plugin): string
    {
        $decoded = base64_decode(strtr($plugin, '-_', '+/'), true);

        return $decoded === false ? $plugin : $decoded;
    }
}
