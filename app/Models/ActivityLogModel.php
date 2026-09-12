<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table         = 'activity_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowedFields = [
        'user_id', 'action', 'entity_type', 'entity_id', 'ip_address', 'user_agent',
    ];

    public function record(?int $userId, string $action, string $entityType, ?int $entityId = null): bool
    {
        $request = \Config\Services::request();

        return (bool) $this->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'ip_address'  => $request->getIPAddress(),
            'user_agent'  => (string) $request->getUserAgent(),
        ]);
    }

    public function recent(int $limit = 20): array
    {
        return $this->orderBy('created_at', 'DESC')->findAll($limit);
    }
}
