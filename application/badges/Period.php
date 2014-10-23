<?php

class Badge_Period extends NewModel_Enum
{
	const MONTH = 'month';
	const WEEK  = 'week';

    public static function MONTH() { return self::get(self::MONTH); }
    public static function WEEK() { return self::get(self::WEEK); }

	public function getBegin(\DateTime $date)
	{
		switch ($this->value) {
			case static::WEEK:
				$intervalString = 'P1W';
				break;
			case static::MONTH:
				$intervalString = 'P1M';
				break;
			default:
				throw new LogicException("Not implemented yet");
				break;
		}
		$ret = clone $date;
		return $ret->sub(new \DateInterval($intervalString));
	}
}