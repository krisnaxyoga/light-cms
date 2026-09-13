<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Media Library gets a proper "Title" field, distinct from `alt_text`
 * (accessibility copy) and `filename` (the on-disk name, now editable —
 * see Admin\MediaController::update()). Mirrors the Title/Alt Text/Caption
 * split every mainstream media library uses.
 */
class AddTitleToMediaTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('media', [
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'filename',
            ],
        ]);

        // Backfill from the filename so existing items aren't left blank —
        // same "prettify" transform ImageProcessor::altTextFromFilename()
        // uses for a fresh upload's default alt text, done in PHP (not raw
        // SQL string surgery) so it's the exact same, already-tested logic.
        $rows = $this->db->table('media')->select('id, filename')->get()->getResultArray();

        foreach ($rows as $row) {
            $name = pathinfo($row['filename'], PATHINFO_FILENAME);
            $name = trim(preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', $name)) ?? '');
            $title = $name === '' ? $row['filename'] : mb_convert_case($name, MB_CASE_TITLE);

            $this->db->table('media')->where('id', $row['id'])->update(['title' => $title]);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('media', 'title');
    }
}
