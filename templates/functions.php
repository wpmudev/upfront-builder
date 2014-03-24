<?php

// Include current theme style
function current_theme_enqueue_styles() {
  wp_enqueue_style('current_theme', get_stylesheet_uri(), array(), null);
}
add_action('wp_head', 'current_theme_enqueue_styles', 200);
