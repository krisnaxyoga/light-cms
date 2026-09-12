<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * post/user/term meta for the WordPress compat layer. LightCMS has no
 * meta concept of its own, so unlike wp_options these are the real
 * storage — custom fields written by a WP theme or plugin live here and
 * are readable from LightCMS via wp_post_meta() / MetaStore.
 */
class CreateWpMetaTables extends Migration
{
    public function up(): void
    {
        foreach ([
            'wp_postmeta' => 'post_id',
            'wp_usermeta' => 'user_id',
            'wp_termmeta' => 'term_id',
        ] as $table => $objectColumn) {
            $idColumn = $table === 'wp_usermeta' ? 'umeta_id' : 'meta_id';

            $this->forge->addField([
                $idColumn      => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                $objectColumn  => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
                'meta_key'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'meta_value'   => ['type' => 'LONGTEXT', 'null' => true],
            ]);
            $this->forge->addPrimaryKey($idColumn);
            $this->forge->addKey($objectColumn);
            $this->forge->addKey('meta_key');
            $this->forge->createTable($table, true, ['ENGINE' => 'InnoDB']);
        }
    }

    public function down(): void
    {
        foreach (['wp_postmeta', 'wp_usermeta', 'wp_termmeta'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
