<?php

function upfront_exporter_is_creating() {
	return isset($_POST['action']) && $_POST['action'] === 'upfront_thx-create-theme';
}

function upfront_exporter_is_start_page() {
	return isset($_POST['storage_key']) && $_POST['storage_key'] === 'upfront_new';
}

function upfront_exporter_get_stylesheet() {
	// Special case when create theme request is made
	if (upfront_exporter_is_creating()) {
		$form = array();
		parse_str($_POST['form'], $form);
		return $form['thx-theme-slug'];
	}

	//if (isset($_POST['data']['theme']) && isset($_POST['stylesheet']) && $_POST['stylesheet'] === 'upfront') {
	if (!empty($_POST['data']) && is_array($_POST['data']) && array_key_exists('theme', $_POST['data']) && isset($_POST['stylesheet']) && $_POST['stylesheet'] === 'upfront') {
		return $_POST['data']['theme'];
	}

	// We'll follow same order as in upfront_exporter_is_running function.
	// First check for mode set in javascript request
	if (upfront_exporter_mode() && isset($_POST['stylesheet']))
	 	return $_POST['stylesheet'];

	// Than check if this is main document.
	if (upfront_exporter_is_exporter_uri()) {
		$uri = $_SERVER['REQUEST_URI'];
		$matches = array();
		preg_match('#create_new/([a-z\-]+)#', $uri, $matches);
		if (isset($matches[1])) return $matches[1];
	}

	// Then check if builder is referer for dynamic resources
	// TODO find a way to check if upfront is loading dynamic resource in this
	// TODO request
	if (upfront_exporter_is_exporter_referer()) {
		$referer = $_SERVER['HTTP_REFERER'];
		$matches = array();
		preg_match('#create_new/([a-z\-]+)#', $referer, $matches);
		if (isset($matches[1])) return $matches[1];
	}

	return false;
}

function upfront_exporter_mode() {
	$mode = isset($_POST['mode']) ? $_POST['mode'] : false;
	return $mode === 'theme';
}

function upfront_exporter_is_exporter_uri() {
	$uri = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
	$is_builder_uri = strpos($uri, 'create_new') !== false
		&& strpos($uri, 'create_new/post') === false
		&& strpos($uri, 'create_new/page') === false;

	return $is_builder_uri;
}

function upfront_exporter_is_exporter_referer() {
	$referer = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
	$is_builder_referer = strpos($referer, 'create_new') !== false
		&& strpos($referer, 'create_new/post') === false
		&& strpos($referer, 'create_new/page') === false;

	return $is_builder_referer;
}
function upfront_exporter_is_running() {
	// First try with set value, this is for ajax actions that are called from
	// javascript, this is the cleanest way to find out if we're in builder
	if (upfront_exporter_mode()) return true;

	// Than try to see if this is main document loading
	if (upfront_exporter_is_exporter_uri()) return true;

	// Than try to see if this was called from referer builder since stylesheets
	// and other dynamic assets will have builder for referer. This is a bit
	// sketchy, we'll if this will need to be adjusted
	return upfront_exporter_is_exporter_referer();
}
