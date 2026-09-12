<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'username', 'email', 'password', 'display_name',
        'role_id', 'avatar', 'status',
    ];

    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[60]|is_unique[users.username,id,{id}]',
        'email'    => 'required|valid_email|max_length[100]|is_unique[users.email,id,{id}]',
        'status'   => 'in_list[active,inactive,banned]',
        // CI4 only substitutes the {id} placeholder above when 'id' is both
        // present in the validated data and has a rule of its own; callers
        // updating a row pass it in, and doProtectFields() strips it again
        // before the row reaches SQL (allowedFields has no 'id').
        'id'       => 'permit_empty|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'username' => [
            'is_unique' => 'That username is already taken.',
        ],
        'email' => [
            'is_unique'   => 'That email address already belongs to another account.',
            'valid_email' => 'Enter a valid email address.',
        ],
    ];

    // Never expose password hashes when the model returns rows
    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];

    protected function hashPassword(array $data): array
    {
        if (isset($data['data']['password']) && $data['data']['password'] !== '') {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['data']['password']);
        }

        return $data;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', $username)->first();
    }

    public function verifyCredentials(string $login, string $password): ?array
    {
        $user = $this->groupStart()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->groupEnd()
            ->where('status', 'active')
            ->first();

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);

            return $user;
        }

        return null;
    }
}
