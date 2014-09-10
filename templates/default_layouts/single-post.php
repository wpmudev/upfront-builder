<?php
return '<?php
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
		"content" => "<h3>Single Post</h3>",
		"element_id" => "default-nav-text-object",
		"class" => "c24",
		"type" => "PlainTxtModel",
		"has_settings" => 1
	)
));
$regions->add($main_nav);

$main = upfront_create_region(array(
	"name" => "main",
	"title" => __("Main Area"),
	"scope" => "local",
	"type" => "wide",
	"default" => true,
	"allow_sidebar" => true
), array(
	"row" => 140,
	"background_type" => "color",
	"background_color" => "#c5d0db"
));

$main->add_element("ThisPost", array(
	"id" => "default-post",
	"columns" => 24,
	"rows" => 20,
	"margin_top" => 1,
	"options" => array(
		"post_data" => array("date"),
		"disable_resize" => false,
		"disable_drag" => false,
		"layout" => array(
			array("classes" => "c24 clr", "objects"=> array(array("slug" => "title", "classes" => "post-part c24"))),
			array("classes" => "c24 clr", "objects"=> array(array("slug" => "date", "classes" => " post-part c24"))),
			array("classes" => "c24 clr", "objects"=> array(array("slug" => "contents", "classes" => " post-part c24")))
		)
	),
	"sticky" => true
));
$main->add_element("Ucomment", array(
	"id" => "default-comment",
	"columns" => 24,
	"rows" => 10
));

$regions->add($main);';
