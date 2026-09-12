<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;
use App\Models\UserModel;

/**
 * The logged-in user's own account screen (PRD §3.5.A): display name,
 * username, email, avatar, and a password change.
 *
 * Two separate forms on one page, because they have different rules:
 * changing the *identity* fields (username/email) or the password both
 * require the current password, so a hijacked session cannot silently
 * take the account over by moving the recovery address.
 */
class ProfileController extends BaseController
{
    /** Columns the screen may read; the password hash is never selected. */
    private const VISIBLE_FIELDS = 'id, username, email, display_name, avatar, role_id, status, created_at';

    public function index()
    {
        $user = $this->currentUser();

        if ($user === null) {
            return redirect()->to('/admin/login');
        }

        return view('admin/profile/index', [
            'user' => $user,
            'role' => $this->roleName((int) ($user['role_id'] ?? 0)),
        ]);
    }

    public function update()
    {
        $user = $this->currentUser();

        if ($user === null) {
            return redirect()->to('/admin/login');
        }

        $model = new UserModel();

        $data = [
            'id'           => $user['id'],
            'username'     => trim((string) $this->request->getPost('username')),
            'email'        => trim((string) $this->request->getPost('email')),
            'display_name' => trim((string) $this->request->getPost('display_name')),
            'avatar'       => trim((string) $this->request->getPost('avatar')),
        ];

        $identityChanged = $data['username'] !== $user['username'] || $data['email'] !== $user['email'];

        if ($identityChanged && ! $this->confirmsWithCurrentPassword((int) $user['id'])) {
            session()->setFlashdata('error', 'Enter your current password to change your username or email.');

            return redirect()->to('/admin/profile')->withInput();
        }

        if (! $model->update($user['id'], $data)) {
            session()->setFlashdata('errors', $model->errors() ?: ['Could not save your profile.']);

            return redirect()->to('/admin/profile')->withInput();
        }

        // The header greeting and any username-based lookups read the
        // session, so it has to follow the row.
        session()->set([
            'username'    => $data['username'],
            'displayName' => $data['display_name'] !== '' ? $data['display_name'] : $data['username'],
        ]);

        (new ActivityLogModel())->record((int) $user['id'], 'update_profile', 'user', (int) $user['id']);

        session()->setFlashdata('success', 'Profile updated.');

        return redirect()->to('/admin/profile');
    }

    public function updatePassword()
    {
        $user = $this->currentUser();

        if ($user === null) {
            return redirect()->to('/admin/login');
        }

        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('new_password_confirm');

        $errors = $this->passwordErrors($user, $current, $new, $confirm);

        if ($errors !== []) {
            session()->setFlashdata('errors', $errors);

            return redirect()->to('/admin/profile');
        }

        $model = new UserModel();

        // Only the password is written: passing the other columns would
        // re-run their uniqueness rules against this same row for nothing.
        if (! $model->update($user['id'], ['id' => $user['id'], 'password' => $new])) {
            session()->setFlashdata('errors', $model->errors() ?: ['Could not change your password.']);

            return redirect()->to('/admin/profile');
        }

        // A new session id after a credential change, so a session that was
        // captured earlier stops being useful.
        session()->regenerate(true);

        (new ActivityLogModel())->record((int) $user['id'], 'change_password', 'user', (int) $user['id']);

        session()->setFlashdata('success', 'Password changed. Your other sessions were not signed out — LightCMS keeps one session per browser.');

        return redirect()->to('/admin/profile');
    }

    /**
     * @return list<string> Human-readable problems; empty means "go ahead".
     */
    private function passwordErrors(array $user, string $current, string $new, string $confirm): array
    {
        $errors = [];

        if (! $this->passwordMatches((int) $user['id'], $current)) {
            $errors[] = 'Your current password is not correct.';
        }

        if (strlen($new) < 8) {
            $errors[] = 'The new password must be at least 8 characters.';
        }

        // bcrypt silently ignores everything past 72 bytes, which would make
        // a longer password weaker than it looks — reject instead.
        if (strlen($new) > 72) {
            $errors[] = 'The new password must be 72 characters or fewer.';
        }

        if ($new !== $confirm) {
            $errors[] = 'The new password and its confirmation do not match.';
        }

        if ($new !== '' && $new === $current) {
            $errors[] = 'The new password must be different from the current one.';
        }

        if ($new !== '' && (strcasecmp($new, $user['username']) === 0 || strcasecmp($new, $user['email']) === 0)) {
            $errors[] = 'The new password must not be your username or email address.';
        }

        return $errors;
    }

    /** Re-reads the hash directly: model rows deliberately do not carry it around. */
    private function passwordMatches(int $userId, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        $row = (new UserModel())->select('password')->find($userId);

        return $row !== null && password_verify($password, (string) $row['password']);
    }

    private function confirmsWithCurrentPassword(int $userId): bool
    {
        return $this->passwordMatches($userId, (string) $this->request->getPost('confirm_password'));
    }

    private function currentUser(): ?array
    {
        $id = (int) (session()->get('userId') ?? 0);

        if ($id <= 0) {
            return null;
        }

        return (new UserModel())->select(self::VISIBLE_FIELDS)->find($id);
    }

    private function roleName(int $roleId): string
    {
        if ($roleId <= 0) {
            return 'No role';
        }

        $role = \Config\Database::connect()->table('roles')->where('id', $roleId)->get()->getRowArray();

        return (string) ($role['name'] ?? $role['slug'] ?? 'Unknown role');
    }
}
