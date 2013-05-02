<?php


class Util_HealthCalculator {
	var $best, $good, $bad, $bestRate, $goodRate, $badRate, $targetDiff;

	function __construct($targetDiff) {
		$this->best = BaseController::getOption('achieve_audience_best');
		$this->good = BaseController::getOption('achieve_audience_good');
		$this->bad = BaseController::getOption('achieve_audience_bad');

		$daysPerMonth = 365/12;
		$this->targetDiff = $targetDiff;
		$this->bestRate = $targetDiff/($daysPerMonth*$this->best);
		$this->goodRate = $targetDiff/($daysPerMonth*$this->good);
		$this->badRate = $targetDiff/($daysPerMonth*$this->bad);
	}

	/**
	 * Calculates a health score for the given gains-per-day value (between 0 and 100) using the four boundaries (0, best, good, bad)
	 * @param $value
	 * @return float|int
	 */
	function getHealth($value) {
		if ($value < 0 || $value <= $this->badRate) {
			return 0;
		} else if ($this->targetDiff < 0 || $value >= $this->bestRate) {
			return 100;
		} else if ($value >= $this->goodRate) {
			return 50 + 50*($value - $this->goodRate)/($this->bestRate - $this->goodRate);
		} else {
			return 50*($value - $this->badRate)/($this->goodRate - $this->badRate);
		}
	}

	/**
	 * Returns an array of required rates and dates to achieve $targetDiff, one for each of the boundaries (best, good, bad)
	 * @param $date
	 * @return array
	 */
	function requiredRates($date) {
		$rates = array();
		if ($this->bestRate > 0) {
			$rates[] = array($this->bestRate, date('F Y', strtotime($date . ' +' . $this->best . ' months')));
		}
		if ($this->goodRate > 0) {
			$rates[] = array($this->goodRate, date('F Y', strtotime($date . ' +' . $this->good . ' months')));
		}
		if ($this->badRate > 0) {
			$rates[] = array($this->badRate, date('F Y', strtotime($date . ' +' . $this->bad . ' months')));
		}
		return $rates;
	}
}

