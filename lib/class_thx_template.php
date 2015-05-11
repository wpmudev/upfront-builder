<?php

/**
 * Factory class.
 */
abstract class Thx_Template {

	private static $_plugin;
	private static $_theme;

	public static function plugin () {
		if (!empty(self::$_plugin)) return self::$_plugin;
		self::$_plugin = new Thx_Template_Plugin;
		return self::$_plugin;
	}

	public static function theme () {
		if (!empty(self::$_theme)) return self::$_theme;
		self::$_theme = new Thx_Template_Theme;
		return self::$_theme;
	}
}

/**
 * Template abstraction
 * This class holds all the utilities for template manipulation.
 * The concrete implementations only deal with path specifics
 */
abstract class Thx_Template_Abstract {

	private $_base_path;
	protected $_directory;

	public function __construct () {
		$this->_base_path = untrailingslashit(
			wp_normalize_path(
				dirname(dirname(__FILE__))
			)
		) . '/templates';
	}

	/**
	 * Resolve template path
	 *
	 * @param string $tpl Template name
	 * @param bool $check_existence Whether to check the file existence first, defaults to true
	 *
	 * @return string Resolved template path
	 */
	public function path ($tpl, $check_existence=true) {
		$path = wp_normalize_path(
			untrailingslashit($this->_base_path) .
			'/' .
			Thx_Sanitize::path_fragment($this->_directory) .
			'/' .
			Thx_Sanitize::path_fragment($tpl) . '.php'
		);
		if ($check_existence) {
			$path = file_exists($path) ? $path : false;
		}
		return $path;
	}

	/**
	 * Loads a template
	 *
	 * @param string $tpl Template name
	 * @param array $data Optional data to pass to template
	 */
	public function load ($tpl, $data=array()) {
		$__path = $this->path($tpl);
		if (empty($__path)) return false;

		if (!empty($data)) extract($data);
		include $__path;
	}
}

class Thx_Template_Plugin extends Thx_Template_Abstract {
	protected $_directory = 'plugin';
}

class Thx_Template_Theme extends Thx_Template_Abstract {
	protected $_directory = 'theme';
}