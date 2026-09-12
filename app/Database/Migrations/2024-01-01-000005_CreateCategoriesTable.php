<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCategoriesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'parent_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'meta_title'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'meta_description' => ['type' => 'VARCHAR', 'constraint' => 320, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('slug', false, false, 'idx_slug');
        $this->forge->addKey('parent_id', false, false, 'idx_parent');
        $this->forge->createTable('categories', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('categories', true);
    }
}
