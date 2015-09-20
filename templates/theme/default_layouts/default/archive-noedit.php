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

$main->add_element("PlainTxt", array(
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

$main->add_element("Posts", array (
  "columns" => "24",
  "margin_left" => "0",
  "margin_top" => "6",
  "class" => "upfront-posts_module",
  "id" => "module-1442668077434-1795",
  "options" => 
  array (
    "type" => "PostsModel",
    "view_class" => "PostsView",
    "has_settings" => 1,
    "class" => "c24 uposts-object",
    "id_slug" => "posts",
    "display_type" => "list",
    "list_type" => "generic",
    "offset" => 1,
    "taxonomy" => "",
    "term" => "",
    "content" => "excerpt",
    "limit" => 5,
    "pagination" => "",
    "sticky" => "",
    "posts_list" => "",
    "post_parts" => 
      array (
        0 => "date_posted",
        1 => "author",
        2 => "gravatar",
        3 => "comment_count",
        4 => "featured_image",
        5 => "title",
        6 => "content",
        7 => "read_more",
        8 => "tags",
        9 => "categories",
      ),
    "enabled_post_parts" => 
      array (
        0 => "date_posted",
        1 => "author",
        2 => "gravatar",
        3 => "comment_count",
        4 => "featured_image",
        5 => "title",
        6 => "content",
        7 => "read_more",
        8 => "tags",
        9 => "categories",
      ),
    "default_parts" => 
      array (
        0 => "date_posted",
        1 => "author",
        2 => "gravatar",
        3 => "comment_count",
        4 => "featured_image",
        5 => "title",
        6 => "content",
        7 => "read_more",
        8 => "tags",
        9 => "categories",
        10 => "meta",
      ),
    "date_posted_format" => "F j, Y g:i a",
    "categories_limit" => 3,
    "tags_limit" => 3,
    "comment_count_hide" => 0,
    "content_length" => 120,
    "resize_featured" => "1",
    "gravatar_size" => 200,
  ),
  "row" => 42,
  "wrapper_id" => "wrapper-1442668095508-1224",
  "new_line" => true,
  
));

$regions->add($main);';
