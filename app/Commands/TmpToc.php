<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PostModel;
use App\Libraries\Editor\BlockRenderer;

class TmpToc extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'tmp:toc';
    protected $description = 'temp';

    public function run(array $params)
    {
        require_once FCPATH . 'themes/super-travel/functions.php';

        $model = new PostModel();
        $p = $model->find(169);
        $html = (new BlockRenderer())->render($p['content'] ?? '[]');

        [$out, $toc] = st_extract_toc($html);

        CLI::write('=== TOC ENTRIES (' . count($toc) . ') ===');
        foreach ($toc as $t) {
            CLI::write($t['level'] . ' | ' . $t['id'] . ' | ' . $t['text']);
        }
        CLI::write('');
        CLI::write('=== OUTPUT HTML LENGTH: ' . strlen($out) . ' (input was ' . strlen($html) . ') ===');
        CLI::write($out);
    }
}
