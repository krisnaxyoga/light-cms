<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;
use App\Models\UserModel;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/admin');
        }

        return view('admin/login');
    }

    public function attempt()
    {
        $login    = (string) $this->request->getPost('login');
        $password = (string) $this->request->getPost('password');

        $user = (new UserModel())->verifyCredentials($login, $password);

        if (! $user) {
            session()->setFlashdata('error', 'Invalid credentials.');

            return redirect()->to('/admin/login')->withInput();
        }

        session()->regenerate();
        session()->set([
            'isLoggedIn'  => true,
            'userId'      => $user['id'],
            'username'    => $user['username'],
            'displayName' => $user['display_name'],
            'roleId'      => $user['role_id'],
        ]);

        (new ActivityLogModel())->record((int) $user['id'], 'login', 'user', (int) $user['id']);

        return redirect()->to('/admin');
    }

    public function logout()
    {
        $userId = session()->get('userId');
        session()->destroy();

        if ($userId) {
            (new ActivityLogModel())->record((int) $userId, 'logout', 'user', (int) $userId);
        }

        return redirect()->to('/admin/login');
    }
}
