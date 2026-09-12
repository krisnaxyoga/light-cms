<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePostTagsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'post_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tag_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['post_id', 'tag_id']);
        $this->forge->addKey('post_id', false, false, 'idx_post');
        $this->forge->addKey('tag_id', false, false, 'idx_tag');
        $this->forge->createTable('post_tags', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('post_tags', true);
    }
}
