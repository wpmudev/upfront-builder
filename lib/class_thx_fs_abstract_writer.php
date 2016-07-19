<?php

if (!class_exists('Thx_Sanitize')) require_once(dirname(__FILE__) . '/class_thx_sanitize.php');

abstract class Thx_Fs_AbstractWriter {

	protected $_theme_name;
	protected $_theme_path;
	protected $_root_path;

	public function __construct ($theme) {
		if (!empty($theme)) $this->set_theme($theme);
	}

	/**
	 * Get the recursive list of all files in root theme dir
	 *
	 * @return array
	 */
	public function ls () {
		$root = $this->get_root_path();
		return $this->_list_from($root);
	}

	/**
	 * Recursive list working method
	 *
	 * @param string $path Path
	 *
	 * @return array
	 */
	protected function _list_from ($path) {
		$list = array();

		$all = glob(trailingslashit(wp_normalize_path($path)) . '/*');
		foreach ($all as $item) {
			if ('.' === $item || '..' === $item || $path === $item) continue;
			$item = wp_normalize_path($item);

			$list[] = $item;
			
			if (!is_dir($item)) continue;

			$tmp = $this->_list_from($item);
			foreach ($tmp as $tmp_item) {
				$list[] = $tmp_item;
			}
		}

		return $list;
	}

	/**
	 * Sets up internal names which are used in FS resolution.
	 *
	 * @param string $theme Theme name
	 */
	public function set_theme ($theme) {
		$this->_theme_name = $this->_escape_fragment($theme);
		$this->_root_path = get_theme_root();
		$this->_theme_path = false;
		if (!empty($this->_theme_name)) {
			$this->_theme_path = $this->get_path(array(
				$this->_theme_name,
			), false);
		}
	}

	/**
	 * Constructs a path.
	 * It will always work on relative path fragment.
	 * Constructed path can be theme-based or relative.
	 * "Relative" in this context means "not based on current theme root path".
	 *
	 * @param mixed $parts Relative path fragment - can be a string or array of path fragments.
	 * @param bool $relative Relative flag - (bool)false means that the current root path will be prepended to other fragments. Defaults to true.
	 *
	 * @return string Constructed path
	 */
	public function construct_path ($parts, $relative=true) {
		if (empty($parts)) return false;
		if (!is_array($parts)) $parts = array($parts);

		if (!$relative) array_unshift($parts, $this->get_root_path());

		return rtrim(join('/', $parts), '/');
	}

	/**
	 * Constructs a theme rooted path.
	 * Wrapper for `$this->_construct_path($parts, false)`
	 *
	 * @param mixed $parts Relative path fragments
	 *
	 * @return string Constructed theme rooted path.
	 */
	public function construct_theme_path ($parts) {
		return $this->construct_path($parts, false);
	}

	/**
	 * Get theme path string from relative path fragments.
	 * Optionally (and by default) check for its existence.
	 *
	 * @param mixed $path Relative path fragments
	 * @param bool $check_existence (optional) Check for path existence too. Default to true.
	 *
	 * @return mixed Path string, or (bool)false on failure
	 */
	public function get_path ($path, $check_existence=true) {
		$fspath = $this->construct_theme_path($path);
		if (empty($fspath)) return false;

		if (!empty($check_existence) && !$this->exists($fspath)) {
			$fspath = false;
		}

		return !empty($fspath) ? wp_normalize_path($fspath) : false;
	}

	/**
	 * Get current path root.
	 * This will either be a theme path or root path,
	 * depending on whether the actual theme has been set.
	 *
	 * @return string Root path
	 */
	public function get_root_path () {
		return !empty($this->_theme_path)
			? $this->_theme_path
			: $this->_root_path
		;
	}

	/**
	 * Write to a file within theme-relative path.
	 *
	 * @param mixed $path Theme-relative path fragments
	 * @param string $content The actual content to write
	 *
	 * @return bool
	 */
	public function write ($path, $content) {
		$path = $this->get_path($path, false);

		if (empty($path)) return false;

		$path = $this->_escape_path($path);
		if (!$this->within_theme(dirname($path))) return false;

		return file_put_contents($path, $content);
	}


	/**
	 * Delete a file within theme-relative path.
	 *
	 * @param mixed $path Theme-relative path fragments
	 *
	 * @return bool
	 */
	public function drop ($path) {
		$path = $this->get_path($path);

		if (empty($path)) return false;
		if (!$this->within_theme($path)) return false;

		return unlink($path);
	}

	/**
	 * Check filesystem path for existence.
	 *
	 * @param string $fspath Full path to a file
	 *
	 * @return bool
	 */
	public function exists ($fspath) {
		if (empty($fspath)) return false;
		return file_exists($fspath);
	}

	/**
	 * Create a directory indicated by filesystem path argument.
	 *
	 * @param string $fspath Full path to a new directory
	 *
	 * @return bool
	 */
	public function mkdir ($fspath) {
		if (empty($fspath) || $this->exists($fspath)) return false;

		$fspath = $this->_escape_path($fspath);
		if (!$this->within_root(dirname($fspath))) return false; // This is also used to create child theme path, so check root

		return mkdir($fspath);
	}

	/**
	 * Create a directory tree within current theme root.
	 *
	 * @param array $parts Path fragments relative to current theme root
	 *
	 * @return bool
	 */
	public function mkdir_p ($parts) {
		$path = $this->get_root_path();
		$success = true;

		foreach ($parts as $part) {
			$part = $this->_escape_fragment($part);
			$path = $this->construct_path(array($path, $part), true);
			if ($this->exists($path)) continue;

			if (!$this->within_theme(dirname($path))) {
				$success = false;
				break;
			}

			$success = $this->mkdir($path);
			if (!$success) break;
		}

		return $success;
	}

	/**
	 * Test if a path is within the root directory.
	 *
	 * @param string $fscheck Path to check
	 *
	 * @return bool
	 */
	public function within_root ($fspath) {
		return $this->_within($fspath, $this->_root_path);
	}

	/**
	 * Test if a path is within the theme directory
	 *
	 * @param string $fspath Path to check
	 *
	 * @return bool
	 */
	public function within_theme ($fspath) {
		return $this->_within($fspath, $this->_theme_path);
	}

	/**
	 * Helper for path ahcnorage checking.
	 * Compares two paths and determines if the first one is a subdirectory of the second one.
	 *
	 * Uses `realpath` internally, so can't check non-existing paths.
	 *
	 * @param string $fscheck Path to check
	 * @param string $fsroot Root path to check against
	 *
	 * @return bool
	 */
	protected function _within ($fscheck, $fsroot) {
		if (empty($fscheck) || empty($fsroot)) return false;

		$cfscheck = wp_normalize_path(realpath($fscheck));
		$cfsroot = wp_normalize_path(realpath($fsroot));
		if (empty($fscheck) || empty($fsroot)) return false;

		$is_root = preg_match('/^' . preg_quote($cfsroot, '/') . '/', $cfscheck);

		return $is_root;
	}

	/**
	 * Sanitizes a path fragment.
	 * Fragment here means either a directory name, of file name.
	 * No paths - to escape paths, see `_escape_path`
	 *
	 * @param string $frag Path fragment
	 *
	 * @return string Clean path fragment
	 */
	protected function _escape_fragment ($frag) {
		return Thx_Sanitize::path_endpoint($frag);
	}

	/**
	 * Sanitizes a full path.
	 * Uses `_escape_fragment` to sanitize each path fragment in turn.
	 *
	 * @param string $fspath Path to sanitize
	 *
	 * @return string Sanitized path
	 */
	protected function _escape_path ($fspath) {
		$fspath = wp_normalize_path($fspath);

		$parts = explode('/', $fspath);
		$parts = array_values(array_filter(array_map(array($this, '_escape_fragment'), $parts)));

		$clean_path = trim(join('/', $parts), '/');
		if ('/' === substr($fspath, 0, 1)) { // We had an absolute path to begin with
			$clean_path = "/{$clean_path}";
		}

		return $clean_path;
	}
}
