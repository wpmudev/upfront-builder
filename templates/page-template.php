<?php
/**
 * Template Name: %name% Page template
 *
 * @package WordPress
 * @subpackage %slug%
 */

the_post();
$layout = Upfront_Output::get_layout(array('specificity' => '%layout%'));

get_header();
echo $layout->apply_layout();
get_footer();