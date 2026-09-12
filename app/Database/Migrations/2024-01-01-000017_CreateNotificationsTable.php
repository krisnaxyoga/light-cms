<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * In-dashboard admin notifications (PRD ADDENDUM §13).
 */
class CreateNotificationsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'info'], // info|warning|error
            'title'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'message'     => ['type' => 'TEXT', 'null' => true],
            'action_url'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'action_text' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'is_read'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('is_read', false, false, 'idx_read');
        $this->forge->addKey('created_at', false, false, 'idx_created');
        $this->forge->createTable('notifications', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('notifications', true);
    }
}
