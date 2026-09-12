<?php

use App\Libraries\WordPress\Registry;

/**
 * Customizer shim.
 *
 * Themes register their options through `customize_register`; LightCMS
 * collects those registrations and renders them as a plain settings form
 * at admin/wp/customize (Admin\WPCustomizeController). Live preview and
 * postMessage transport are not emulated — saving reloads the site — but
 * every registered setting is editable and get_theme_mod() reads it back.
 */
if (! class_exists('WP_Customize_Setting')) {
    #[AllowDynamicProperties]
    class WP_Customize_Setting
    {
        public $id;
        public $type = 'theme_mod';
        public $capability = 'edit_theme_options';
        public $theme_supports = '';
        public $default = '';
        public $transport = 'refresh';
        public $sanitize_callback = '';
        public $sanitize_js_callback = '';

        public function __construct($manager, $id, $args = [])
        {
            $this->id = $id;

            foreach ($args as $key => $value) {
                $this->{$key} = $value;
            }
        }

        public function value()
        {
            return $this->type === 'option'
                ? get_option($this->id, $this->default)
                : get_theme_mod($this->id, $this->default);
        }

        public function save($value = null)
        {
            $value ??= $this->value();

            if (is_callable($this->sanitize_callback)) {
                $value = ($this->sanitize_callback)($value, $this);
            }

            if ($this->type === 'option') {
                update_option($this->id, $value);
            } else {
                set_theme_mod($this->id, $value);
            }
        }

        public function json()
        {
            return ['value' => $this->value(), 'transport' => $this->transport, 'default' => $this->default];
        }
    }
}

if (! class_exists('WP_Customize_Control')) {
    #[AllowDynamicProperties]
    class WP_Customize_Control
    {
        public $id;
        public $section = '';
        public $label = '';
        public $description = '';
        public $type = 'text';
        public $settings = 'default';
        public $priority = 10;
        public $choices = [];
        public $input_attrs = [];
        public $active_callback = '';

        public function __construct($manager, $id, $args = [])
        {
            $this->id = $id;

            foreach ($args as $key => $value) {
                $this->{$key} = $value;
            }

            if ($this->settings === 'default') {
                $this->settings = $id;
            }
        }

        public function render_content()
        {
        }
    }
}

if (! class_exists('WP_Customize_Color_Control')) {
    class WP_Customize_Color_Control extends WP_Customize_Control
    {
        public $type = 'color';
    }
}

if (! class_exists('WP_Customize_Media_Control')) {
    class WP_Customize_Media_Control extends WP_Customize_Control
    {
        public $type = 'media';
    }
}

if (! class_exists('WP_Customize_Image_Control')) {
    class WP_Customize_Image_Control extends WP_Customize_Media_Control
    {
        public $type = 'image';
    }
}

if (! class_exists('WP_Customize_Upload_Control')) {
    class WP_Customize_Upload_Control extends WP_Customize_Media_Control
    {
        public $type = 'upload';
    }
}

if (! class_exists('WP_Customize_Panel')) {
    #[AllowDynamicProperties]
    class WP_Customize_Panel
    {
        public $id;
        public $title = '';
        public $description = '';
        public $priority = 160;

        public function __construct($manager, $id, $args = [])
        {
            $this->id = $id;

            foreach ($args as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }
}

if (! class_exists('WP_Customize_Section')) {
    #[AllowDynamicProperties]
    class WP_Customize_Section
    {
        public $id;
        public $title = '';
        public $description = '';
        public $panel = '';
        public $priority = 160;

        public function __construct($manager, $id, $args = [])
        {
            $this->id = $id;

            foreach ($args as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }
}

if (! class_exists('WP_Customize_Selective_Refresh')) {
    class WP_Customize_Selective_Refresh
    {
        public array $partials = [];

        public function add_partial($id, $args = [])
        {
            $this->partials[$id] = $args;

            return $args;
        }

        public function get_partial($id)
        {
            return $this->partials[$id] ?? null;
        }

        public function remove_partial($id): void
        {
            unset($this->partials[$id]);
        }
    }
}

if (! class_exists('WP_Customize_Manager')) {
    #[AllowDynamicProperties]
    class WP_Customize_Manager
    {
        public $selective_refresh;

        public function __construct()
        {
            $this->selective_refresh = new WP_Customize_Selective_Refresh();
        }

        public function add_panel($id, $args = [])
        {
            $panel = is_object($id) ? $id : new WP_Customize_Panel($this, $id, $args);
            Registry::$customize['panels'][$panel->id] = $panel;

            return $panel;
        }

        public function add_section($id, $args = [])
        {
            $section = is_object($id) ? $id : new WP_Customize_Section($this, $id, $args);
            Registry::$customize['sections'][$section->id] = $section;

            return $section;
        }

        public function add_setting($id, $args = [])
        {
            $setting = is_object($id) ? $id : new WP_Customize_Setting($this, $id, $args);
            Registry::$customize['settings'][$setting->id] = $setting;

            return $setting;
        }

        public function add_control($id, $args = [])
        {
            $control = is_object($id) ? $id : new WP_Customize_Control($this, $id, $args);
            Registry::$customize['controls'][$control->id] = $control;

            return $control;
        }

        public function get_setting($id)
        {
            return Registry::$customize['settings'][$id] ?? null;
        }

        public function get_control($id)
        {
            return Registry::$customize['controls'][$id] ?? null;
        }

        public function get_section($id)
        {
            return Registry::$customize['sections'][$id] ?? null;
        }

        public function get_panel($id)
        {
            return Registry::$customize['panels'][$id] ?? null;
        }

        public function remove_setting($id): void
        {
            unset(Registry::$customize['settings'][$id]);
        }

        public function remove_control($id): void
        {
            unset(Registry::$customize['controls'][$id]);
        }

        public function remove_section($id): void
        {
            unset(Registry::$customize['sections'][$id]);
        }

        public function remove_panel($id): void
        {
            unset(Registry::$customize['panels'][$id]);
        }

        public function is_preview(): bool
        {
            return false;
        }

        public function register_control_type($type): void
        {
        }

        public function get_previewable_devices(): array
        {
            return [];
        }
    }
}
