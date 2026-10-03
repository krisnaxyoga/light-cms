<?php

namespace App\Controllers\Frontend;

use App\Controllers\BaseController;
use App\Libraries\Deploy\GitLabDeployer;

/**
 * Auto-deploy endpoint CI/CD calls after every push, so the server can
 * update itself with zero manual clicks — the fully-hands-off counterpart
 * to the "Pull now" button in Admin -> Deploy. Not gated by the adminAuth
 * filter since CI systems carry no admin session cookie; gated instead by
 * a shared secret, verified per platform:
 *
 * - GitLab "Push events" webhook: the X-Gitlab-Token header, checked
 *   against the secret configured in Admin -> Deploy (set on the GitLab
 *   project's Webhooks page as "Secret Token").
 * - GitHub "push" webhook: the X-Hub-Signature-256 HMAC-SHA256 header over
 *   the raw request body, keyed with the same secret (set on the GitHub
 *   repo's Settings -> Webhooks page as "Secret").
 *
 * Deliberately exempted from CSRF in Config\Filters (webhook POST bodies
 * carry no CI4 CSRF token) the same way wp-admin/* already is for the same
 * reason.
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

        // GitHub signs the raw body instead of sending the secret itself.
        $githubSignature = (string) $this->request->getHeaderLine('X-Hub-Signature-256');
        if ($githubSignature !== '') {
            return $this->handleGitHub($config, $githubSignature);
        }

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

    /**
     * GitHub webhook delivery: verify the HMAC-SHA256 signature of the raw
     * body, then act on the event. "ping" (sent when the webhook is created)
     * is acknowledged without deploying; "push" to the configured branch
     * triggers a pull; anything else is acknowledged and ignored so a
     * misconfigured event selection can't break the site.
     */
    protected function handleGitHub(array $config, string $githubSignature)
    {
        $payload  = (string) $this->request->getBody();
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $config['webhook_secret']);

        if (! hash_equals($expected, $githubSignature)) {
            log_message('warning', 'Deploy webhook called with an invalid X-Hub-Signature-256 header.');

            return $this->response->setStatusCode(403)->setBody('Invalid signature.');
        }

        $event = (string) $this->request->getHeaderLine('X-GitHub-Event');

        if ($event === 'ping') {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'pong — webhook registered, waiting for push events.',
            ]);
        }

        if ($event !== 'push') {
            return $this->response->setJSON([
                'success' => true,
                'message' => "Ignoring GitHub event '{$event}' — only push events deploy.",
            ]);
        }

        $body = json_decode($payload, true);
        $ref  = is_array($body) ? (string) ($body['ref'] ?? '') : '';

        if ($ref !== 'refs/heads/' . $config['branch']) {
            return $this->response->setJSON([
                'success' => true,
                'message' => "Ignoring push to '{$ref}' — deploys track '{$config['branch']}'.",
            ]);
        }

        $result = (new GitLabDeployer())->pull(null);

        return $this->response->setJSON([
            'success' => $result['success'],
            'message' => $result['message'],
        ])->setStatusCode($result['success'] ? 200 : 500);
    }
}
