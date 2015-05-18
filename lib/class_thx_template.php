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
		if (empty($this->_base_path) || empty($this->_directory)) return false;
		
		return $this->filepath("{$tpl}.php", $check_existence);
	}

	/**
	 * Resolve directory path
	 *
	 * @param string $dir_relpath Relative path to directory
	 * @param bool $check_existence Whether to check the file existence first, defaults to true
	 *
	 * @return string Resolved directory path
	 */
	public function dirpath ($dir_relpath, $check_existence=true) {
		if (empty($this->_base_path) || empty($this->_directory)) return false;

		$dir_relpath = join('/', array_map(array('Thx_Sanitize', 'path_fragment'), explode('/', $dir_relpath)));
		$path = wp_normalize_path(
			untrailingslashit($this->_base_path) .
			'/' .
			Thx_Sanitize::path_fragment($this->_directory) .
			'/' .
			trim($dir_relpath, '/')
		);
		if ($check_existence) {
			$path = file_exists($path) ? $path : false;
		}
		return $path;
	}

	/**
	 * Resolve filepath path
	 *
	 * @param string $file_relpath Relative path to file
	 * @param bool $check_existence Whether to check the file existence first, defaults to true
	 *
	 * @return string Resolved file path
	 */
	public function filepath ($file_relpath, $check_existence=true) {
		if (empty($this->_base_path) || empty($this->_directory)) return false;

		$path_bits = explode('/', $file_relpath);
		$file = Thx_Sanitize::path_endpoint(array_pop($path_bits));
		$file_relpath = join('/', array_map(array('Thx_Sanitize', 'path_fragment'), $path_bits));

		$path = wp_normalize_path(
			untrailingslashit($this->_base_path) .
			'/' .
			Thx_Sanitize::path_fragment($this->_directory) .
			'/' .
			trim($file_relpath, '/') .
			'/' . $file
		);
		if ($check_existence) {
			$path = file_exists($path) ? $path : false;
		}
		return $path;
	}

	/**
	 * Resolve filepath file URL
	 *
	 * @param string $dir_relpath Relative path to file
	 * @param bool $check_existence Whether to check the file existence first, defaults to true
	 *
	 * @return string Resolved file URL
	 */
	public function url ($file_relpath, $check_existence=true) {
		if (empty($this->_base_path) || empty($this->_directory)) return false;

		$filepath = $this->filepath($file_relpath, $check_existence);
		if (empty($filepath)) return false;

		$dirpath = trailingslashit($this->dirpath(''));
		$base_url = trailingslashit(
			untrailingslashit(plugin_dir_url($dirpath)) . '/' . trim($this->_directory, '/')
		);

		$file_url = preg_replace('/^' . preg_quote($dirpath, '/') . '/', $base_url, $filepath);

		return $file_url;
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