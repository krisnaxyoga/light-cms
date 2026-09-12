<?php

use App\Libraries\WordPress\Registry;

/**
 * WP_Widget and the widget factory. Widget instances are stored in the
 * `widget_{id_base}` option and assigned to sidebars through
 * `sidebars_widgets`, exactly like WordPress, so a wp_options dump from a
 * real site drops straight in.
 */
if (! class_exists('WP_Widget')) {
    #[AllowDynamicProperties]
    class WP_Widget
    {
        public $id_base;
        public $name;
        public $option_name;
        public $alt_option_name = '';
        public $widget_options = [];
        public $control_options = [];
        public $number = false;
        public $id = false;
        public $updated = false;

        public function __construct($id_base, $name, $widget_options = [], $control_options = [])
        {
            $this->id_base = $id_base !== '' ? strtolower($id_base) : strtolower(static::class);
            $this->name    = $name;
            $this->option_name = 'widget_' . $this->id_base;

            $this->widget_options  = wp_parse_args($widget_options, ['classname' => $this->option_name, 'customize_selective_refresh' => false]);
            $this->control_options = wp_parse_args($control_options, ['id_base' => $this->id_base]);
        }

        /** Overridden by the widget: renders front-end output. */
        public function widget($args, $instance)
        {
        }

        /** Overridden by the widget: sanitises the admin form. */
        public function update($new_instance, $old_instance)
        {
            return $new_instance;
        }

        /** Overridden by the widget: renders the admin form. */
        public function form($instance)
        {
            echo '<p class="no-options-widget">' . esc_html(__('There are no options for this widget.')) . '</p>';

            return 'noform';
        }

        public function get_field_name($field_name)
        {
            $pos = strpos($field_name, '[');

            if ($pos !== false) {
                return 'widget-' . $this->id_base . '[' . $this->number . '][' . substr_replace($field_name, '][', $pos, 1);
            }

            return 'widget-' . $this->id_base . '[' . $this->number . '][' . $field_name . ']';
        }

        public function get_field_id($field_name)
        {
            return 'widget-' . $this->id_base . '-' . $this->number . '-' . trim(str_replace(['[]', '[', ']'], ['', '-', ''], $field_name), '-');
        }

        public function _set($number)
        {
            $this->number = $number;
            $this->id     = $this->id_base . '-' . $number;
        }

        public function _register()
        {
            Registry::$widgets[static::class] = $this;
        }

        public function get_settings()
        {
            $settings = get_option($this->option_name, []);

            return is_array($settings) ? $settings : [];
        }

        public function save_settings($settings)
        {
            update_option($this->option_name, $settings);
        }

        /** Called by dynamic_sidebar() for each placed instance. */
        public function display_callback($args, $widget_args = 1)
        {
            if (is_numeric($widget_args)) {
                $widget_args = ['number' => $widget_args];
            }

            $widget_args = wp_parse_args($widget_args, ['number' => -1]);
            $this->_set((int) $widget_args['number']);

            $settings = $this->get_settings();
            $instance = $settings[$this->number] ?? ($settings === [] ? [] : reset($settings));

            $instance = apply_filters('widget_display_callback', $instance, $this, $args);

            if ($instance !== false) {
                $this->widget($args, is_array($instance) ? $instance : []);
            }
        }
    }
}

if (! class_exists('WP_Widget_Factory')) {
    class WP_Widget_Factory
    {
        public array $widgets = [];

        public function register($widget)
        {
            $instance = is_string($widget) ? new $widget() : $widget;
            $this->widgets[$instance::class] = $instance;
            Registry::$widgets[$instance::class] = $instance;
        }

        public function unregister($widget)
        {
            $class = is_string($widget) ? $widget : $widget::class;
            unset($this->widgets[$class], Registry::$widgets[$class]);
        }

        public function get_widget_object(string $idBase)
        {
            foreach ($this->widgets as $widget) {
                if ($widget->id_base === $idBase) {
                    return $widget;
                }
            }

            return null;
        }
    }
}
