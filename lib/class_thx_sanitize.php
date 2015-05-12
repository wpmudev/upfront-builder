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
		if (in_array($what, get_declared_classes())) return false;
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
}