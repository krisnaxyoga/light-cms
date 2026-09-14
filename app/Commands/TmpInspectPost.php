<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PostModel;

class TmpInspectPost extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'tmp:inspectpost';
    protected $description = 'temp';

    public function run(array $params)
    {
        require_once FCPATH . 'themes/super-travel/functions.php';

        $model = new PostModel();
        $p = $model->find(159);

        CLI::write('slug=' . $p['slug'] . ' updated=' . $p['updated_at']);
        CLI::write('content raw length: ' . strlen((string) $p['content']));

        $html = st_render_bento($p['content'] ?? '[]');
        CLI::write('bento html length: ' . strlen($html));
        CLI::write('bento html: ' . $html);
    }
}
