<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds storage for the image pipeline's generated variants (WebP +
 * thumbnail sizes) — PRD ADDENDUM §4. Not in the original PRD §4 schema,
 * so it's a separate migration rather than rewriting CreateMediaTable.
 */
class AddVariantsToMediaTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('media', [
            'variants' => ['type' => 'JSON', 'null' => true, 'after' => 'height'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('media', 'variants');
    }
}
