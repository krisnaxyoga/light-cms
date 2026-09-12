<?php

/**
 * Walker + Walker_Nav_Menu. Real themes very often ship a custom walker
 * (Bootstrap navwalkers and friends) that extends Walker_Nav_Menu and
 * overrides start_lvl/start_el, so the tree traversal has to behave the
 * way WP's does — including $args being an object and the by-reference
 * $output parameter.
 */
if (! class_exists('Walker')) {
    #[AllowDynamicProperties]
    class Walker
    {
        public $tree_type = '';
        public $db_fields = ['parent' => 'parent', 'id' => 'id'];
        public $max_pages = 1;
        public $has_children = false;

        public function start_lvl(&$output, $depth = 0, $args = null) {}

        public function end_lvl(&$output, $depth = 0, $args = null) {}

        public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0) {}

        public function end_el(&$output, $data_object, $depth = 0, $args = null) {}

        public function display_element($element, &$children_elements, $max_depth, $depth, $args, &$output)
        {
            if (! $element) {
                return;
            }

            $idField = $this->db_fields['id'];
            $id      = $element->{$idField};

            $this->has_children = ! empty($children_elements[$id]);

            if (isset($args[0]) && is_array($args[0])) {
                $args[0]['has_children'] = $this->has_children;
            }

            $this->start_el($output, $element, $depth, ...array_values($args));

            if (($max_depth === 0 || $max_depth > $depth + 1) && isset($children_elements[$id])) {
                foreach ($children_elements[$id] as $child) {
                    if (! isset($newlevel)) {
                        $newlevel = true;
                        $this->start_lvl($output, $depth, ...array_values($args));
                    }

                    $this->display_element($child, $children_elements, $max_depth, $depth + 1, $args, $output);
                }

                unset($children_elements[$id]);
            }

            if (isset($newlevel) && $newlevel) {
                $this->end_lvl($output, $depth, ...array_values($args));
            }

            $this->end_el($output, $element, $depth, ...array_values($args));
        }

        public function walk($elements, $max_depth, ...$args)
        {
            $output = '';

            if ($max_depth < -1 || $elements === []) {
                return $output;
            }

            $parentField = $this->db_fields['parent'];
            $idField     = $this->db_fields['id'];

            $top      = [];
            $children = [];

            // Elements arrive flat; split them into roots and a
            // parent-id => children map, then walk the roots.
            $rootId = 0;

            foreach ($elements as $element) {
                if (empty($element->{$parentField})) {
                    $top[] = $element;
                } else {
                    $children[$element->{$parentField}][] = $element;
                }
            }

            foreach ($top as $element) {
                $this->display_element($element, $children, $max_depth, 0, $args, $output);
            }

            // Orphans (a parent that was filtered out) still get rendered,
            // exactly like WP, so nothing silently disappears from a menu.
            if ($children !== []) {
                foreach ($children as $orphans) {
                    foreach ($orphans as $orphan) {
                        $this->display_element($orphan, $children, $max_depth, 0, $args, $output);
                    }
                }
            }

            unset($rootId, $idField);

            return $output;
        }

        public function paged_walk($elements, $max_depth, $page_num, $per_page, ...$args)
        {
            return $this->walk($elements, $max_depth, ...$args);
        }

        public function get_number_of_root_elements($elements)
        {
            $parentField = $this->db_fields['parent'];
            $count       = 0;

            foreach ($elements as $element) {
                if (empty($element->{$parentField})) {
                    $count++;
                }
            }

            return $count;
        }

        public function unset_children($element, &$children_elements)
        {
            if (! $element || ! $children_elements) {
                return;
            }

            $idField = $this->db_fields['id'];
            $id      = $element->{$idField};

            if (! empty($children_elements[$id]) && is_array($children_elements[$id])) {
                foreach ((array) $children_elements[$id] as $child) {
                    $this->unset_children($child, $children_elements);
                }
            }

            unset($children_elements[$id]);
        }
    }
}

if (! class_exists('Walker_Nav_Menu')) {
    class Walker_Nav_Menu extends Walker
    {
        public $tree_type = ['post_type', 'taxonomy', 'custom'];
        public $db_fields = ['parent' => 'menu_item_parent', 'id' => 'db_id'];

        public function start_lvl(&$output, $depth = 0, $args = null)
        {
            $indent  = str_repeat("\t", $depth);
            $classes = ['sub-menu'];
            $class   = 'class="' . esc_attr(implode(' ', $classes)) . '"';

            $output .= "\n{$indent}<ul {$class}>\n";
        }

        public function end_lvl(&$output, $depth = 0, $args = null)
        {
            $output .= str_repeat("\t", $depth) . "</ul>\n";
        }

        public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0)
        {
            $item    = $data_object;
            $indent  = $depth ? str_repeat("\t", $depth) : '';
            $args    = (object) (array) $args;
            $classes = array_merge(['menu-item', 'menu-item-' . $item->ID], (array) ($item->classes ?? []));

            $classes  = apply_filters('nav_menu_css_class', array_filter($classes), $item, $args, $depth);
            $classAttr = $classes ? ' class="' . esc_attr(implode(' ', $classes)) . '"' : '';
            $idAttr    = ' id="menu-item-' . (int) $item->ID . '"';

            $output .= $indent . '<li' . $idAttr . $classAttr . '>';

            $atts = [
                'title'  => $item->attr_title ?? '',
                'target' => $item->target ?? '',
                'rel'    => $item->xfn ?? '',
                'href'   => $item->url ?? '',
            ];
            $atts = apply_filters('nav_menu_link_attributes', $atts, $item, $args, $depth);

            $attributes = '';

            foreach ($atts as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $attributes .= ' ' . $key . '="' . esc_attr((string) $value) . '"';
                }
            }

            $title = apply_filters('the_title', $item->title ?? '', $item->ID ?? 0);
            $title = apply_filters('nav_menu_item_title', $title, $item, $args, $depth);

            $output .= ($args->before ?? '') . '<a' . $attributes . '>'
                . ($args->link_before ?? '') . $title . ($args->link_after ?? '')
                . '</a>' . ($args->after ?? '');
        }

        public function end_el(&$output, $data_object, $depth = 0, $args = null)
        {
            $output .= "</li>\n";
        }
    }
}

if (! class_exists('Walker_Comment')) {
    class Walker_Comment extends Walker
    {
        public $tree_type = 'comment';
        public $db_fields = ['parent' => 'comment_parent', 'id' => 'comment_ID'];

        public function start_lvl(&$output, $depth = 0, $args = null)
        {
            $output .= "<ol class=\"children\">\n";
        }

        public function end_lvl(&$output, $depth = 0, $args = null)
        {
            $output .= "</ol><!-- .children -->\n";
        }

        public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0)
        {
            $comment = $data_object;

            $output .= '<li id="comment-' . (int) $comment->comment_ID . '" class="comment">'
                . '<article class="comment-body">'
                . '<footer class="comment-meta"><b class="fn">' . esc_html($comment->comment_author) . '</b> '
                . '<time datetime="' . esc_attr($comment->comment_date) . '">' . esc_html((string) mysql2date((string) get_option('date_format', 'F j, Y'), $comment->comment_date)) . '</time>'
                . '</footer><div class="comment-content">' . wpautop(esc_html($comment->comment_content)) . '</div>'
                . '</article>';
        }

        public function end_el(&$output, $data_object, $depth = 0, $args = null)
        {
            $output .= "</li>\n";
        }
    }
}
