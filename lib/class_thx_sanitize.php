<?php

abstract class Thx_Sanitize {

	/**
	 * Strip all non-alphanumeric characters from a string.
	 *
	 * @param string $what String to process
	 * @param string $rpl String to replace with (optional)
	 *
	 * @return string Clean string
	 */
	public static function alnum ($what, $rpl='') {
		$str = preg_replace('/[^a-z0-9]/i', $rpl, $what);
		if (empty($rpl)) return $str;

		$q = preg_quote($rpl, '/');
		return preg_replace("/{$q}+/", $q, $str);
	}

	/**
	 * Strip all non-alphanumeric characters from a string,
	 * except underscore and dash.
	 *
	 * @param string $what String to process
	 * @param string $rpl String to replace with (optional)
	 *
	 * @return string Clean string
	 */
	public static function extended_alnum ($what, $rpl='') {
		$str = preg_replace('/[^-_a-z0-9]/i', $rpl, $what);
		if (empty($rpl)) return $str;

		$q = preg_quote($rpl, '/');
		return preg_replace("/{$q}+/", $rpl, $str);
	}

	/**
	 * Make a string safe to be used in PHP context (e.g. as a var name)
	 *
	 * @param string $what String to process
	 *
	 * @return mixed Sanitized string or (bool)false on failure
	 */
	public static function php_safe ($what) {
		$str = preg_replace('/_+/', '-', preg_replace('/[^_a-z0-9]/i', '_', trim($what)));

		// We can't start with number, or have only numbers
		if (preg_match('/^[0-9]+$/', $str)) return false; // Only numbers, can't do this
		if (preg_match('/^[0-9]/', $str)) $str = "uf-{$str}"; // Can't start with numbers

		return Thx_Sanitize::is_not_reserved($str) && Thx_Sanitize::is_not_declared($str)
			? $str
			: false
		;
	}

	/**
	 * Strip all non-alphanumeric characters from a string,
	 * except underscore, dash and colon.
	 * Colon character is whitelisted because we could be dealing with
	 * Windows-type paths.
	 *
	 * @param string $what String to process
	 *
	 * @return string Clean string
	 */
	public static function path_fragment ($what) {
		return preg_replace('/[^-_:a-z0-9]/i', '', $what); // We have a colon here because we could be dealing with Win
	}

	/**
	 * Sanitizes a path fragment.
	 * Fragment here means either a directory name, of file name.
	 *
	 * @param string $frag Path fragment
	 *
	 * @return string Clean path fragment
	 */
	public static function path_endpoint ($frag) {
		if (!stristr($frag, '.')) return Thx_Sanitize::path_fragment($frag);

		// Do not allow parent directory reference
		if (strpos($frag, '..') !== false) return false;

		// We have a dot or dots. Let's treat this (potential domain name in directory structure)
		$parts = explode('.', $frag);
		$result = '';
		foreach ($parts as $part) {
			$result .= Thx_Sanitize::path_endpoint($part) . '.';
		}

		return rtrim($result, '.');
	}

	/**
	 * Check if the string is also a declared class/function.
	 *
	 * @param string $what String to check
	 *
	 * @return bool
	 */
	public static function is_not_declared ($what) {
		$functions = get_defined_functions();
		if (!empty($functions['user']) && in_array($what, $functions['user'])) return false;
		if (!empty($functions['internal']) && in_array($what, $functions['internal'])) return false;
		
		$known_classes = get_declared_classes();
		if (is_array($known_classes) && !empty($known_classes)) {
			$known_classes = array_map('strtolower', $known_classes);
		} else $known_classes = array();
		if (in_array(strtolower($what), $known_classes)) return false;
		return true;
	}

	/**
	 * Check if the string is also a reserved PHP word.
	 *
	 * @param string $what String to check
	 *
	 * @return bool
	 */
	public static function is_not_reserved ($what) {
		$reserved = array(
			"__halt_compiler",
			"abstract",
			"and",
			"array",
			"as",
			"break",
			"callable",
			"case",
			"catch",
			"class",
			"clone",
			"const",
			"continue",
			"declare",
			"default",
			"die",
			"do",
			"echo",
			"else",
			"elseif",
			"empty",
			"enddeclare",
			"endfor",
			"endforeach",
			"endif",
			"endswitch",
			"endwhile",
			"eval",
			"exit",
			"extends",
			"final",
			"finally",
			"for",
			"foreach",
			"function",
			"global",
			"goto",
			"if",
			"implements",
			"include",
			"include_once",
			"instanceof",
			"insteadof",
			"interface",
			"isset",
			"list",
			"namespace",
			"new",
			"or",
			"print",
			"private",
			"protected",
			"public",
			"require",
			"require_once",
			"return",
			"static",
			"switch",
			"throw",
			"trait",
			"try",
			"unset",
			"use",
			"var",
			"while",
			"xor",
			"yield",
		);
		return !in_array($what, $reserved);
	}

	/**
	 * Sanitizes php variable name using Thx_Sanitize::php_safe and makes sure _ is not used
	 *
	 *
	 * @uses Thx_Sanitize::php_safe
	 * @param $what
	 * @return mixed
	 */
	public static function php_safe_variable_name($what){
		return str_replace( "-", "_", self::php_safe( $what ) );
	}

}
