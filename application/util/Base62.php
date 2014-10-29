<?php

class Util_Base62
{
	protected static $base62Chars = array(
		'0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
		'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j',
		'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't',
		'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D',
		'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
		'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
		'Y', 'Z'
	);

	public static function base10to62($input)
	{
		$output = '';
		$input = (int) $input;
		while ($input > 0) {
			$key = $input % 62;
			$output = self::$base62Chars[$key] . $output;
			$input = floor($input/62);
		}
		return $output;
	}

	public static function base62to10($input)
	{
		$output = 0;
		$power = 0;
		for ($i = strlen($input) - 1; $i >= 0; $i--) {
			$x = array_search($input{$i}, self::$base62Chars);
			$output += $x * pow(62, $power);
			$power++;
		}
		return $output;
	}
}