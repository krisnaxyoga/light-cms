<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRedirectsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'source_url'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'target_url'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'redirect_type'  => ['type' => 'ENUM', 'constraint' => ['301', '302', '307'], 'default' => '301'],
            'hits'           => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_regex'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('source_url', false, false, 'idx_source');
        $this->forge->createTable('redirects', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('redirects', true);
    }
}
