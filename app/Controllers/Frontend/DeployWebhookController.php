<?php

namespace App\Controllers\Frontend;

use App\Controllers\BaseController;
use App\Libraries\Deploy\GitLabDeployer;

/**
 * Public (no admin session) endpoint GitLab's own "Push events" webhook
 * calls after every push, so the server can auto-update itself with zero
 * manual clicks — the fully-hands-off counterpart to the "Pull now" button
 * in Admin -> Deploy. Not gated by the adminAuth filter since GitLab has no
 * way to carry an admin session cookie; gated instead by the X-Gitlab-Token
 * header GitLab sends on every webhook request, checked against the secret
 * configured in Admin -> Deploy (set on the GitLab project's Webhooks page
 * as "Secret Token" — GitLab, not this app, is what sends that header).
 *
 * Deliberately exempted from CSRF in Config\Filters (GitLab's POST body has
 * no CI4 CSRF token) the same way wp-admin/* already is for the same reason.
 */
class DeployWebhookController extends BaseController
{
    public function handle()
    {
        $deployer = new GitLabDeployer();
        $config   = $deployer->config();

        if ($config['webhook_secret'] === '') {
            log_message('warning', 'Deploy webhook called but no webhook secret is configured — ignoring.');

            return $this->response->setStatusCode(403)->setBody('Webhook not configured.');
        }

        $provided = (string) $this->request->getHeaderLine('X-Gitlab-Token');

        // Constant-time comparison — this header is effectively a bearer
        // secret, and a naive === comparison here would leak timing
        // information an attacker could use to guess it byte by byte.
        if ($provided === '' || ! hash_equals($config['webhook_secret'], $provided)) {
            log_message('warning', 'Deploy webhook called with an invalid or missing X-Gitlab-Token header.');

            return $this->response->setStatusCode(403)->setBody('Invalid token.');
        }

        $result = $deployer->pull(null);

        return $this->response->setJSON([
            'success' => $result['success'],
            'message' => $result['message'],
        ])->setStatusCode($result['success'] ? 200 : 500);
    }
}
