<?php
$main_nav = upfront_create_region(array(
	"name" => "main_nav",
	"title" => "Navigation",
	"type" => "wide",
	"scope" => "local"
), array(
	"background_type" => "color",
	"background_color" => "#fff"
));
$main_nav->add_element("PlainTxt", array(
	"columns" => "24",
	"margin_left" => "0",
	"margin_right" => "0",
	"margin_top" => "6",
	"margin_bottom" => "0",
	"id" => "default-nav-text-module",
	"rows" => 12,
	"options" => array(
		"view_class" => "PlainTxtView",
		"id_slug" => "plaintxt",
		"content" => "<h3>Single Page</h3>",
		"element_id" => "default-nav-text-object",
		"class" => "c24",
		"type" => "PlainTxtModel",
		"has_settings" => 1
	)
));
$regions->add($main_nav);


$main->add_element('ThisPage', array(	
	'id' => 'default-page-title',
	'columns' => 24,
	'rows' => 3,
	'margin_top' => 3,
	'options' => array(
		'display' => 'title',
		'disable_resize' => false,
		'disable_drag' => false
	),
	'sticky' => true
));

$regions->add($main);