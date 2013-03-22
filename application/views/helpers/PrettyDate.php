<?php

class Zend_View_Helper_PrettyDate extends Zend_View_Helper_Abstract
{

	public function prettyDate($timeOrDate, $default = '') {
		if (!$timeOrDate || $timeOrDate == '0000-00-00 00:00:00') {
			return $default;
		}

		if (!is_int($timeOrDate)) {
			$date = DateTime::createFromFormat('Y-m-d H:i:s', $timeOrDate, new DateTimeZone('UTC'));
			$time = $date->getTimestamp();
		} else {
			$time = $timeOrDate;
		}

		$now = time();

		$midnight = mktime(0, 0, 0);
		if ($time > $midnight) { //today
			$difference = $now - $time;
			if ($difference < 3600) {
				$count = round($difference / 60);
				$type = 'minute';
			} else {
				$count = round($difference / 3600);
				$type = 'hour';
			}
			$prettyDate = "$count $type" . ($count == 1 ? '' : 's') . ' ago (' . date('H:i', $time) . ')';
		} elseif ($time > $midnight - 3600 * 24) { //yesterday
			$prettyDate = 'Yesterday ' . date('H:i', $time);
		} elseif ($time > $midnight - 3600 * 24 * 5) { //this week
			$prettyDate = date('l H:i', $time);
		} else { //other time
			$prettyDate = date('j M Y H:i', $time);
		}
		
		return $prettyDate;
	}

	/*
	private function is_bst() {

		$date = time();
		$Year = date("Y");
		$MarLast = $Year . "-03-31";
		$OctLast = $Year . "-10-31";

		//Find the last Sunday in March
		if (date("w", strtotime($MarLast)) == 0) //Sunday
		{
			$LastMarSun = strtotime($MarLast);
		} else {
			$LastMarSun = strtotime($MarLast . " last sunday");
		}

		//Find the last Sunday in October
		if (date("w", strtotime($OctLast)) == 0) //Sunday
		{
			$LastOctSun = strtotime($OctLast);
		} else {
			$LastOctSun = strtotime($OctLast . " last sunday");
		}

		$BSTStart = strtotime(date("Y-m-d", $LastMarSun) . " 01:00:00");
		$BSTEnd = strtotime(date("Y-m-d", $LastOctSun) . " 01:00:00");

		return (($date >= $BSTStart) && ($date <= $BSTEnd));
	}
	*/
}

?>