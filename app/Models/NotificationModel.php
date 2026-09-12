<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table         = 'notifications';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $allowedFields = ['type', 'title', 'message', 'action_url', 'action_text', 'is_read'];

    public function unread(int $limit = 10): array
    {
        return $this->where('is_read', 0)->orderBy('created_at', 'DESC')->findAll($limit);
    }

    public function unreadCount(): int
    {
        return $this->where('is_read', 0)->countAllResults();
    }

    public function markRead(int $id): bool
    {
        return $this->update($id, ['is_read' => 1]);
    }

    public function markAllRead(): bool
    {
        return $this->set('is_read', 1)->where('is_read', 0)->update();
    }

    /**
     * Avoid spamming the same notification repeatedly — e.g. only one
     * "low SEO score" notice per post per day.
     */
    public function existsRecently(string $title, int $withinMinutes): bool
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$withinMinutes} minutes"));

        return $this->where('title', $title)->where('created_at >=', $since)->countAllResults() > 0;
    }
}
