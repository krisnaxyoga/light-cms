<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCommentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'post_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'parent_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'author_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'author_email' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'author_ip'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'content'      => ['type' => 'TEXT', 'null' => true],
            'status'       => ['type' => 'ENUM', 'constraint' => ['pending', 'approved', 'spam', 'trash'], 'default' => 'pending'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('post_id', false, false, 'idx_post');
        $this->forge->addKey('status', false, false, 'idx_status');
        $this->forge->addKey('parent_id', false, false, 'idx_parent');
        $this->forge->createTable('comments', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('comments', true);
    }
}
