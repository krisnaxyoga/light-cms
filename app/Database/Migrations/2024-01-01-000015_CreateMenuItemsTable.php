<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMenuItemsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'menu_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'parent_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'url'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'target'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'css_class'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('menu_id', false, false, 'idx_menu');
        $this->forge->addKey('parent_id', false, false, 'idx_parent');
        $this->forge->addKey('position', false, false, 'idx_position');
        $this->forge->createTable('menu_items', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('menu_items', true);
    }
}
