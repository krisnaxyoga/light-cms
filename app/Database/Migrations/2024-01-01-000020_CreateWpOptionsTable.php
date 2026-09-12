<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The WordPress options table, for the compat layer's get_option()/
 * update_option(). Core options that LightCMS already owns (site title,
 * posts per page, ...) are *not* stored here — OptionStore maps those
 * onto the `settings` table so both CMSes see one value.
 */
class CreateWpOptionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'option_id'    => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'option_name'  => ['type' => 'VARCHAR', 'constraint' => 191, 'default' => ''],
            'option_value' => ['type' => 'LONGTEXT', 'null' => true],
            'autoload'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'yes'],
        ]);
        $this->forge->addPrimaryKey('option_id');
        $this->forge->addUniqueKey('option_name');
        $this->forge->addKey('autoload');
        $this->forge->createTable('wp_options', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('wp_options', true);
    }
}
