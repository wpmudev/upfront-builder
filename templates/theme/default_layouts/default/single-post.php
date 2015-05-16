<?php
return '<?php
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
		"layout" => array(
			array("classes" => "c24 clr", "objects"=> array(array("slug" => "title", "classes" => "post-part c24"))),
			array("classes" => "c24 clr", "objects"=> array(array("slug" => "date", "classes" => " post-part c24"))),
			array("classes" => "c24 clr", "objects"=> array(array("slug" => "contents", "classes" => " post-part c24")))
		)
	),
	"sticky" => true,
    "disable_resize" => true,
    "disable_drag" => false,
));
$main->add_element("Ucomment", array(
	"id" => "default-comment",
	"columns" => 24,
	"rows" => 10
));

$regions->add($main);';
