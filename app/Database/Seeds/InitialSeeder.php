<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // --- Roles -----------------------------------------------------
        $roles = [
            ['name' => 'super_admin', 'permissions' => ['*']],
            ['name' => 'administrator', 'permissions' => ['manage_content', 'manage_users', 'manage_settings']],
            ['name' => 'editor', 'permissions' => ['publish_content', 'edit_others_content', 'moderate_comments']],
            ['name' => 'author', 'permissions' => ['publish_own_content', 'edit_own_content']],
            ['name' => 'contributor', 'permissions' => ['submit_content']],
            ['name' => 'subscriber', 'permissions' => ['read']],
        ];

        foreach ($roles as $role) {
            $this->db->table('roles')->insert([
                'name'        => $role['name'],
                'permissions' => json_encode($role['permissions']),
                'created_at'  => $now,
            ]);
        }

        $superAdminRoleId = $this->db->table('roles')->select('id')->where('name', 'super_admin')->get()->getRow('id');

        // --- Default admin user -----------------------------------------
        $this->db->table('users')->insert([
            'username'     => 'admin',
            'email'        => 'admin@lightcms.local',
            'password'     => password_hash('ChangeMe123!', PASSWORD_BCRYPT),
            'display_name' => 'Administrator',
            'role_id'      => $superAdminRoleId,
            'status'       => 'active',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        // --- Default theme ------------------------------------------------
        $this->db->table('themes')->insert([
            'name'       => 'Default Theme',
            'slug'       => 'default',
            'version'    => '1.0.0',
            'author'     => 'LightCMS',
            'is_active'  => 1,
            'settings'   => json_encode([
                'layout'           => 'boxed',
                'sidebar_position' => 'right',
                'primary_color'    => '#0077b6',
                'secondary_color'  => '#ff6b5a',
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // --- Menus ---------------------------------------------------------
        $this->db->table('menus')->insert(['name' => 'Primary Menu', 'slug' => 'primary', 'location' => 'primary', 'created_at' => $now]);
        $this->db->table('menus')->insert(['name' => 'Footer Menu', 'slug' => 'footer', 'location' => 'footer', 'created_at' => $now]);

        $primaryMenuId = $this->db->table('menus')->select('id')->where('slug', 'primary')->get()->getRow('id');
        $this->db->table('menu_items')->insert([
            'menu_id'    => $primaryMenuId,
            'title'      => 'Home',
            'url'        => '/',
            'position'   => 1,
            'created_at' => $now,
        ]);

        // --- Core settings ---------------------------------------------------
        $settings = [
            'site_title'         => 'LightCMS',
            'site_description'   => 'Ultra-lightweight CMS with built-in SEO tooling',
            'posts_per_page'     => '10',
            'excerpt_length'     => '55',
            'timezone'           => 'Asia/Jakarta',
            'active_theme'       => 'default',
            'seo_separator'      => '-',
            'default_og_image'   => '',
            'robots_index'       => '1',
            'robots_follow'      => '1',
            'robots_crawl_delay' => '',
            // Single source of truth for every WhatsApp CTA on the site —
            // see app/Helpers/whatsapp_helper.php.
            'whatsapp_number'    => '+62 822 8263 8682',
        ];

        foreach ($settings as $key => $value) {
            $this->db->table('settings')->insert([
                'setting_key'   => $key,
                'setting_value' => $value,
                'autoload'      => 1,
            ]);
        }

        // --- Auto-configuration on install (PRD ADDENDUM §1.1) ---------------
        // robots.txt and an initial (empty) sitemap.xml so a fresh install
        // already has both instead of a 404 until the first admin visit.
        (new \App\Libraries\SEO\RobotsTxtGenerator())->write();
        (new \App\Libraries\SEO\SitemapGenerator())->writeFiles();
    }
}
