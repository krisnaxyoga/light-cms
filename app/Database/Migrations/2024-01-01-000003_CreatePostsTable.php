<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePostsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'title'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'content'         => ['type' => 'LONGTEXT', 'null' => true],
            'excerpt'         => ['type' => 'TEXT', 'null' => true],
            'author_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'post_type'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'post'],
            'status'          => ['type' => 'ENUM', 'constraint' => ['draft', 'published', 'scheduled', 'trash'], 'default' => 'draft'],
            'featured_image'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'comment_status'  => ['type' => 'ENUM', 'constraint' => ['open', 'closed'], 'default' => 'open'],
            'view_count'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'published_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('slug', false, false, 'idx_slug');
        $this->forge->addKey('status', false, false, 'idx_status');
        $this->forge->addKey('post_type', false, false, 'idx_type');
        $this->forge->addKey('author_id', false, false, 'idx_author');
        $this->forge->addKey('published_at', false, false, 'idx_published');
        $this->forge->createTable('posts', true, ['ENGINE' => 'InnoDB']);

        // FULLTEXT index (not portable via Forge, added with a raw statement)
        $this->db->query('ALTER TABLE posts ADD FULLTEXT idx_search (title, content)');
    }

    public function down()
    {
        $this->forge->dropTable('posts', true);
    }
}
