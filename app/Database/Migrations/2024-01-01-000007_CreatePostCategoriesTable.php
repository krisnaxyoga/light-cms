<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePostCategoriesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'post_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'category_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['post_id', 'category_id']);
        $this->forge->addKey('post_id', false, false, 'idx_post');
        $this->forge->addKey('category_id', false, false, 'idx_category');
        $this->forge->createTable('post_categories', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('post_categories', true);
    }
}
