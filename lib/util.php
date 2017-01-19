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
		if (!empty($form['thx-theme-slug'])) return $form['thx-theme-slug'];
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
		preg_match('#' . UpfrontThemeExporter::get_root_slug() . '/([-_a-z0-9]+)#i', $uri, $matches);
		if (isset($matches[1])) return $matches[1];
	}

	// Then check if builder is referer for dynamic resources
	// TODO find a way to check if upfront is loading dynamic resource in this
	// TODO request
	if (upfront_exporter_is_exporter_referer()) {
		$referer = $_SERVER['HTTP_REFERER'];
		$matches = array();
		preg_match('#' . UpfrontThemeExporter::get_root_slug() . '/([-_a-z0-9]+)#i', $referer, $matches);
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
	$is_builder_uri = strpos($uri, UpfrontThemeExporter::get_root_slug()) !== false
		&& strpos($uri, UpfrontThemeExporter::get_root_slug() . '/post') === false
		&& strpos($uri, UpfrontThemeExporter::get_root_slug() . '/page') === false;

	return $is_builder_uri;
}

function upfront_exporter_is_exporter_referer() {
	if ( !empty($_GET['_uf_no_referer']) && 1 === (int)$_GET['_uf_no_referer'] ) return false;
	$referer = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
	$is_builder_referer = strpos($referer, UpfrontThemeExporter::get_root_slug()) !== false
		&& strpos($referer, UpfrontThemeExporter::get_root_slug() . '/post') === false
		&& strpos($referer, UpfrontThemeExporter::get_root_slug() . '/page') === false;

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

/**
 * Check if the current theme is an Upfront child theme
 *
 * @return bool False if not, stylesheet name (true-ish) if it is.
 */
function upfront_thx_is_current_theme_upfront_child () {
	$current = wp_get_theme(get_option('stylesheet'));
	$parent = $current->parent();

	if (empty($parent)) return false; // Current theme is not a child theme, carry on...
	if ('upfront' !== $parent->get_template()) return false; // Not an Upfront child, carry on...

	return $current->get_stylesheet();
}

/**
 * Checks if the current theme is Upfront core, or an Upfront child theme
 *
 * Used to check whether we can do builder stuff or not
 *
 * @return bool
 */
function upfront_thx_is_current_theme_upfront_related () {
	// Check if wp_get_current_user method exist
	wp_get_current_user_exist();

	$current = wp_get_theme(get_option('stylesheet'));
	if ('upfront' === $current->get_template()) return true;

	return (bool)upfront_thx_is_current_theme_upfront_child();
}

/**
 * Checks if wp_get_current_user exist
 *
 * @return bool Status
 */
function wp_get_current_user_exist() {
	$status = false;

	if (is_multisite()) {
		// Check if we're network-active
		$active = get_site_option('active_sitewide_plugins');
		$active = is_array($active) ? $active : array();
		if (!empty($active)) {
			$exporter = preg_grep('/' . preg_quote(THX_BASENAME) . '/', array_keys($active));
			if (!empty($exporter)) return $status;
		}
	}

	// Check if function exist
	if ( ! function_exists( 'wp_get_current_user' ) ) {

		$pluggable = ABSPATH . "wp-includes/pluggable.php";

		// Check if file exist
		if ( file_exists( $pluggable ) ) {
			require_once( $pluggable );
			$status = true;
		}
	}

	return $status;
}

/**
 * Clears autoconversion cache
 *
 * @param  string $slug Theme slug to clear the cache for
 *
 * @return bool
 */
function upfront_exporter_clear_conversion_cache ($slug) {
	if (empty($slug)) return false;
	global $wpdb;
	$rx = '_transient_(timeout_)?' . $slug . '.*_ver[0-9]';
	$sql = "DELETE FROM {$wpdb->options} WHERE option_name REGEXP %s";
	return !!$wpdb->query($wpdb->prepare($sql, $rx));
}


/**
 * Checks if we have upfront core present at all
 *
 * @return bool
 */
function upfront_exporter_has_upfront_core () {
	$core = wp_get_theme('upfront');
	return $core->exists() && 'upfront' === $core->get_template();
}

/**
 * Checks if we have a required upfront version
 *
 * Without version parameter, will just check presence.
 *
 * @param string $version Optional upfront version to check.
 *
 * @return bool
 */
function upfront_exporter_has_upfront_version ($version=false) {
	$upfront = wp_get_theme('upfront');
	if (!$upfront->exists()) return false; // No core whatsoever

	$core_ver = $upfront->get('Version');

	if (empty($core_ver)) return false; // No core version whatsoever
	if (empty($version)) return true; // No specific version check requested

	return version_compare($core_ver, $version, 'ge');
}
