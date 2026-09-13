<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Multi-language content (Polylang/WPML model): every translation is its
 * own posts row, tagged with a `locale` and linked to its siblings through
 * a shared `translation_group_id`. Categories and tags stay shared across
 * languages (one "travel" category lists each language's own posts), so
 * they need no schema change.
 *
 * Post slug uniqueness moves from global to per-language — the same slug
 * may exist once per locale — which is what lets /about-us and /id/about-us
 * both resolve. Existing rows are stamped 'en' (the site's default
 * language), so nothing about current URLs changes.
 */
class AddMultiLanguageSupport extends Migration
{
    public function up(): void
    {
        // --- languages ------------------------------------------------------
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 10],
            'url_prefix'  => ['type' => 'VARCHAR', 'constraint' => 10],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'native_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'is_default'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order'  => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addUniqueKey('url_prefix');
        $this->forge->createTable('languages', true, ['ENGINE' => 'InnoDB']);

        $now = date('Y-m-d H:i:s');
        $this->db->table('languages')->insert([
            'code'        => 'en',
            'url_prefix'  => 'en',
            'name'        => 'English',
            'native_name' => 'English',
            'is_default'  => 1,
            'is_active'   => 1,
            'sort_order'  => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // --- locale + translation group on posts ---------------------------
        $this->forge->addColumn('posts', [
            'locale' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'en',
                'after'      => 'slug',
            ],
            'translation_group_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'locale',
            ],
        ]);

        // The unique key name comes from CreatePostsTable (Forge names a
        // single-column unique key after the column).
        $this->forge->dropKey('posts', 'slug', false);
        $this->db->query('ALTER TABLE `posts` ADD UNIQUE KEY `posts_locale_slug` (`locale`, `slug`)');
        $this->db->query('ALTER TABLE `posts` ADD KEY `idx_locale` (`locale`)');
        $this->db->query('ALTER TABLE `posts` ADD KEY `idx_translation_group` (`translation_group_id`)');

        // Existing posts are each their own translation group.
        $this->db->query('UPDATE `posts` SET `translation_group_id` = `id` WHERE `translation_group_id` IS NULL');
    }

    public function down(): void
    {
        $this->forge->dropKey('posts', 'idx_translation_group', false);
        $this->forge->dropKey('posts', 'idx_locale', false);
        $this->forge->dropKey('posts', 'posts_locale_slug', false);
        $this->forge->dropColumn('posts', ['translation_group_id', 'locale']);
        $this->db->query('ALTER TABLE `posts` ADD UNIQUE KEY `slug` (`slug`)');

        $this->forge->dropTable('languages', true);
    }
}
