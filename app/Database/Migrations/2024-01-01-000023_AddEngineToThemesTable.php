<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A themes row can now describe either a native LightCMS theme
 * (public/themes/<slug>) or a WordPress theme (public/wp-content/themes/
 * <slug>). `engine` says which loader owns the request; `parent_slug`
 * carries the Template: header of a child theme.
 */
class AddEngineToThemesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('themes', [
            'engine' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'native',
                'after'      => 'slug',
            ],
            'parent_slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'engine',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('themes', ['engine', 'parent_slug']);
    }
}
