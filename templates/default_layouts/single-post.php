<?php
$main_nav = upfront_create_region(
        array(
"name" => "main_nav",
"title" => "Navigation",
"type" => "Navigation",
"scope" => "local"
),
        array(
"background_type" => "color",
"background_color" => "#fff"
)
        );
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
	"content" => "<h3>Single post</h3>",
	"element_id" => "default-nav-text-object",
	"class" => "c24",
	"type" => "PlainTxtModel",
	"has_settings" => 1
	)
));

$regions->add($main_nav);
$content = upfront_create_region(
        array(
"name" => "content",
"title" => "Content Area",
"type" => "Content Area",
"scope" => "local"
),
        array(
"row" => 80,
"background_type" => "color",
"background_color" => "#c5d0db"
)
        );
$content->add_element("PlainTxt", array(
"columns" => "24",
"margin_left" => "0",
"margin_right" => "0",
"margin_top" => "6",
"margin_bottom" => "0",
"id" => "default-content-text-module",
"rows" => 2,
"options" => array(
	"view_class" => "PlainTxtView",
	"id_slug" => "plaintxt",
	"has_settings" => 1,
	"content" => "<p style=\"text-align:center;\">Text element in content</p>",
	"element_id" => "default-content-text-object",
	"class" => "c24",
	"type" => "PlainTxtModel"
	)
));

$regions->add($content);
