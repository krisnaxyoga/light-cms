<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PostModel;
use App\Libraries\Editor\BlockRenderer;
use App\Libraries\Editor\BlockParser;

class TmpFullPost extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'tmp:fullpost';
    protected $description = 'temp';

    public function run(array $params)
    {
        $model = new PostModel();
        $p = $model->find(169);

        CLI::write('=== RAW CONTENT JSON ===');
        CLI::write((string) $p['content']);
        CLI::write('');
        CLI::write('=== PARSED BLOCK TYPES ===');
        $blocks = (new BlockParser())->parse($p['content'] ?? '[]');
        foreach ($blocks as $i => $b) {
            CLI::write($i . ': ' . $b['type']);
        }
        CLI::write('');
        CLI::write('=== RENDERED HTML ===');
        $html = (new BlockRenderer())->render($p['content'] ?? '[]');
        CLI::write($html);
    }
}
