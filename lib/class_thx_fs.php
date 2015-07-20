<?php

require_once dirname(__FILE__) . '/class_thx_fs_abstract_writer.php';

/**
 * FS ops hub class.
 * Serves as FS writers dispatching factory,
 * as well as path constants hub.
 */
abstract class Thx_Fs {

	const PATH_ICONS = 'icon-fonts';
	const PATH_STYLES = 'element-styles';
	const PATH_IMAGES = 'images';
	const PATH_LAYOUTS = 'layouts';
	const PATH_UI = 'ui';
	const PATH_REGIONS = 'global-regions';
	const PATH_LIGHTBOXES = 'lightboxes';
	const PATH_TEMPLATES = 'templates';
	const PATH_POSTPARTS = 'postparts';
	const PATH_POSTLAYOUTS = 'postlayouts';

	/**
	 * Factory method.
	 *
	 * @param string $name Theme name to be used as root point anchor.
	 *
	 * @return Thx_Fs_AbstractWriter FS writer instance
	 */
	public static function get ($name) {
		if ('theme' === $name) $name = false;
		return new Thx_Fs_ThemeDir($name);
	}

	/**
	 * This will be used as helper method in determining the proper writer interface to use.
	 * At the moment, not implemented.
	 *
	 * @param string $path Test path to check
	 *
	 * @return bool
	 */
	private static function _can_write_directly ($path) {
		if (is_writable($path)) return true;
		else if (function_exists(''))
		return false;
	}
}


/**
 * WP themes directory FS writer implementation.
 * All the main functionality is directly inherited from the writer abstraction.
 */
class Thx_Fs_ThemeDir extends Thx_Fs_AbstractWriter {}