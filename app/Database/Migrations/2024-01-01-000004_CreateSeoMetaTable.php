<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSeoMetaTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'post_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'meta_title'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'meta_description' => ['type' => 'VARCHAR', 'constraint' => 320, 'null' => true],
            'meta_keywords'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'canonical_url'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'robots_index'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'robots_follow'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'og_title'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'og_description'   => ['type' => 'VARCHAR', 'constraint' => 320, 'null' => true],
            'og_image'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'twitter_card'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'schema_data'      => ['type' => 'JSON', 'null' => true],
            'focus_keyword'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'seo_score'        => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('post_id');
        $this->forge->addKey('post_id', false, false, 'idx_post');
        $this->forge->createTable('seo_meta', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('seo_meta', true);
    }
}
