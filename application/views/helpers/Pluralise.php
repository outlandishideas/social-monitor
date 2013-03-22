<?php

class Zend_View_Helper_Pluralise extends Zend_View_Helper_Abstract
{
	// returns '$number $word' or '$number $pluralWord', depending on the value of $number.
	// if $pluralWord is not provided, a guess at pluralising $word is used
	public function pluralise($word, $number, $pluralWord = null) {
		$string = $number . ' ';
		if ($number == 1) {
			$string .= $word;
		} else {
			if (!$pluralWord) {
				// guess a plural by adding s/es
				if (in_array($word[strlen($word)-1], array('s'))) {
					$pluralWord = $word . 'es';
				} else {
					$pluralWord = $word . 's';
				}
			}
			$string .= $pluralWord;
		}
		return $string;
	}
}

?>