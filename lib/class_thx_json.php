<?php

/**
 * JSON handling exporter abstraction.
 */
class Thx_Json {

	const STATUS_ERROR = 400;
	const STATUS_OK = 200;

	/**
	 * JSON output wrapper.
	 * It's here for possible data wrapping we might be doing down the road.
	 *
	 * @param mixed $data JSON response
	 * @param int $header Response status header. Defaults to OK
	 */
	public function out ($data, $header=false) {
		$header = !empty($header) && is_numeric($header)
			? $header
			: Thx_Json::STATUS_OK
		;
		status_header($header);
		wp_send_json($data);
	}

	/**
	 * Error message output handler.
	 *
	 * @param string $message Error message
	 * @param string $code Error type description
	 */
	public function error_msg ($message, $code='generic_error') {
		$this->out(array(
			'error' => array(
				'message' => $message, 
				'code' => $code
			)
		), Thx_Json::STATUS_ERROR);
	}

	/**
	 * Convert PHP values to strings.
	 *
	 * @param mixed $object PHP value to stringify
	 * @param int $deep Recursion depth
	 *
	 * @return strng Stringified PHP value
	 */
	public function stringify_php ($object, $deep=0) {

		/// EXPERIMENTAL!!!
		/// Using `var_export` here seems to be producing *excellent* results!
		/// Well, except for the obvious `stdClass::__set_state` issue, which is a known PHP bug:
		/// https://bugs.php.net/bug.php?id=67918
		$str = var_export($object, true);
		
		// Fix anonymous classes typecasting
		$str = preg_replace('/' . preg_quote('stdClass::__set_state' , '/') . '\b/', '(array)', $str);

		// Fix single-quoted variables
		$str = preg_replace('/\s=>\s[\'](\$[a-z][a-z_0-9]+)[\'],?\s*$/im', ' => $1,', $str);

		return $str;

// --- Up to here --- //

		$elements = array();
		$ob = is_object($object) ? (array)$object : $object;
		$assoc = $this->_is_assoc($ob);

		$tabs = '';
		for ($i=0; $i < $deep; $i++) {
			$tabs .= "\t";
		}


		$separator = ', ';
		if ($assoc) {
			$separator .= "\n" . $tabs;
		}

		foreach ($ob as $key => $value) {
			$string = "";
			
			if ($assoc) {
				$string .= '"' . $key . '" => ';
			}

			if(is_string($value)) {
				//$string .= '"' . addslashes($value) . '"'; // This escapes all quotes - NOT what we want
				$string .= '"' . addcslashes($value, '"\\') . '"'; // This escapes double quotes only.
			} else if(is_object($value) || is_array($value)) {
				$string .= $this->stringify_php($value, $deep + 1);
			} else if(is_bool($value)) {
				$string .= $value ? 'true' : 'false';
			} else {
				$string .= empty($value) && !is_numeric($value) ? "''" : $value;
			}

			array_push($elements, $string);
		}

		$output = $tabs . 'array(';
		$linebreak = $assoc ? "\n" . $tabs : '';

		return 'array(' . $linebreak . implode($separator, $elements) . $linebreak . ')';
	}

	public function parse_php ($string){
		//..someday we will have this done
	}

	private function _is_assoc ($array){
		return !!array_diff_key($array, array_keys($array));
	}

}