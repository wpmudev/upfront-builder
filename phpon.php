<?php
/*
	PHP Object Notation. It is like JSON but for PHP ;)
*/

class PHPON {
	public static function stringify($object, $deep){
		$elements = array();
		$deep = $deep ? $deep : 0;
		$ob = is_object($object) ? (array) $object : $object;
		$assoc = self::is_assoc($ob);

		$tabs = '';
		for ($i=0; $i < $deep; $i++)
				$tabs .= "\t";


		$separator = ', ';
		if($assoc){
			$separator .= "\n" . $tabs;
		}

		foreach($ob as $key => $value){
			$string = "";
			if($assoc)
				$string .= '"' . $key . '" => ';

			if(is_string($value))
				$string .= '"' . $value . '"';
			else if(is_object($value) || is_array($value))
				$string .= self::stringify($value, $deep + 1);
			else if(is_bool($value))
				$string .= $value ? 'true' : 'false';
			else
				$string .= $value;

			array_push($elements, $string);
		}

		$output = $tabs . 'array(';
		$linebreak = $assoc ? "\n" . $tabs : '';

		return 'array(' . $linebreak . implode($separator, $elements) . $linebreak . ')';
	}

	public static function parse($string){
		//..someday we will have this done
	}

	private static function is_assoc($array){
		return !!array_diff_key($array,array_keys($array));
	}
}