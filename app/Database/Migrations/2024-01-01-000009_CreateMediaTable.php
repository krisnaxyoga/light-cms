<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMediaTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'filename'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'filepath'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'filetype'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'filesize'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'width'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'height'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'alt_text'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'caption'     => ['type' => 'TEXT', 'null' => true],
            'uploaded_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('filetype', false, false, 'idx_type');
        $this->forge->addKey('uploaded_by', false, false, 'idx_uploader');
        $this->forge->createTable('media', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('media', true);
    }
}
