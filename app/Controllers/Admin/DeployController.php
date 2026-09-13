<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Deploy\GitLabDeployer;

/**
 * Admin -> Deploy: pull the latest commit from a GitLab repository. Works
 * with no `git`/SSH/shell access on the server at all (HTTPS + a .zip
 * archive download), and automatically switches to real `git` over SSH the
 * moment that's detected as actually working, with nothing to configure by
 * hand either way. See App\Libraries\Deploy\GitLabDeployer for exactly what
 * each mode does (and never touches), and the automatic pre-deploy backup
 * this screen can restore from regardless of which mode ran.
 */
class DeployController extends BaseController
{
    public function index()
    {
        $deployer = new GitLabDeployer();
        $config   = $deployer->config();

        $remote     = null;
        $remoteErr  = null;
        $upToDate   = null;

        if ($deployer->isConfigured()) {
            try {
                $remote   = $deployer->getRemoteLatestCommit();
                $upToDate = $remote['sha'] === $deployer->getLastDeployed()['sha'];
            } catch (\RuntimeException $e) {
                $remoteErr = $e->getMessage();
            }
        }

        return view('admin/deploy/index', [
            'config'     => $config,
            'hasToken'   => $config['access_token'] !== '',
            'last'       => $deployer->getLastDeployed(),
            'remote'     => $remote,
            'remoteErr'  => $remoteErr,
            'upToDate'   => $upToDate,
            'backups'    => $deployer->listBackups(),
            'webhookUrl' => site_url('deploy/webhook'),
            'mode'       => $deployer->describeMode(),
        ]);
    }

    public function saveConfig()
    {
        $deployer = new GitLabDeployer();
        $deployer->saveConfig([
            'gitlab_url'     => (string) $this->request->getPost('gitlab_url'),
            'project_id'     => (string) $this->request->getPost('project_id'),
            'branch'         => (string) $this->request->getPost('branch'),
            'access_token'   => (string) $this->request->getPost('access_token'),
            'webhook_secret' => (string) $this->request->getPost('webhook_secret'),
        ]);

        session()->setFlashdata('success', 'GitLab connection settings saved.');

        return redirect()->to('/admin/deploy');
    }

    public function pull()
    {
        $result = (new GitLabDeployer())->pull(session('userId'));

        if ($result['success']) {
            $notes = array_filter([$result['composer_note'] ?? null, $result['migrations_note'] ?? null]);
            session()->setFlashdata('success', $result['message'] . ($notes ? ' ' . implode(' ', $notes) : ''));
        } else {
            session()->setFlashdata('error', $result['message']);
        }

        return redirect()->to('/admin/deploy');
    }

    public function restore(string $filename)
    {
        $result = (new GitLabDeployer())->restoreBackup($filename, session('userId'));

        session()->setFlashdata($result['success'] ? 'success' : 'error', $result['message']);

        return redirect()->to('/admin/deploy');
    }
}
