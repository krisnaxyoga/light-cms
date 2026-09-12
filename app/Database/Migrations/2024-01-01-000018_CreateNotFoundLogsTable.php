<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 404 tracking for the auto-redirect-suggestion / spike-notification
 * automation (PRD ADDENDUM §8 "Smart 404 to Redirect Converter").
 */
class CreateNotFoundLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'url'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'referer'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('url', false, false, 'idx_url');
        $this->forge->addKey('created_at', false, false, 'idx_created');
        $this->forge->createTable('not_found_logs', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('not_found_logs', true);
    }
}
