<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;
use App\Models\CommentModel;
use App\Models\NotificationModel;
use App\Models\PostModel;
use App\Models\SEOModel;
use App\Models\UserModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $postModel = new PostModel();
        $seoModel  = new SEOModel();

        $stats = [
            'total_posts'      => $postModel->where('post_type', 'post')->countAllResults(),
            'total_pages'      => $postModel->where('post_type', 'page')->countAllResults(),
            'total_users'      => (new UserModel())->countAllResults(),
            'pending_comments' => (new CommentModel())->pendingCount(),
            'avg_seo_score'    => (float) ($seoModel->selectAvg('seo_score', 'avg_score')->first()['avg_score'] ?? 0),
            'disk_usage_mb'    => round($this->dirSize(WRITEPATH) / 1024 / 1024, 2),
            'php_version'      => PHP_VERSION,
            'memory_usage_mb'  => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        $data = [
            'stats'          => $stats,
            'recentPosts'    => $postModel->select('id, title, slug, status, updated_at')->orderBy('updated_at', 'DESC')->findAll(5),
            'recentActivity' => (new ActivityLogModel())->recent(10),
            'notifications'  => (new NotificationModel())->unread(10),
        ];

        return view('admin/dashboard', $data);
    }

    /**
     * PRD ADDENDUM §13 AutoNotifier — dismiss the dashboard notice list.
     */
    public function markNotificationsRead()
    {
        (new NotificationModel())->markAllRead();

        return redirect()->to('/admin');
    }

    protected function dirSize(string $path): int
    {
        $size = 0;

        foreach (glob(rtrim($path, '/') . '/*') ?: [] as $item) {
            $size += is_dir($item) ? $this->dirSize($item) : (filesize($item) ?: 0);
        }

        return $size;
    }
}
