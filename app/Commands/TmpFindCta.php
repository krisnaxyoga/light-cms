<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PostModel;

class TmpFindCta extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'tmp:findcta';
    protected $description = 'temp';

    public function run(array $params)
    {
        $model = new PostModel();
        $posts = $model->like('content', 'Empieza a organizar')->findAll();

        foreach ($posts as $p) {
            CLI::write('id=' . $p['id'] . ' type=' . $p['post_type'] . ' slug=' . $p['slug'] . ' status=' . $p['status'] . ' updated=' . $p['updated_at']);
        }

        if ($posts === []) {
            CLI::write('No posts/pages contain that CTA text.');
        }
    }
}
