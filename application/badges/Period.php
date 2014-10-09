<?php

class Badge_Period extends NewModel_Enum
{
	const MONTH = 'month';
	const WEEK  = 'week';

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
		return $date->sub(new \DateInterval($intervalString));
	}
}