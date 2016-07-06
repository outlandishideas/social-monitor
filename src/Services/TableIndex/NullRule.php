<?php

namespace Outlandish\SocialMonitor\Services\TableIndex;
use Outlandish\SocialMonitor\Services\TableIndex\Interfaces\Rule;
use Outlandish\SocialMonitor\TableIndex\Header\Header;

/**
 * A null rule always allows any user to see any column.
 *
 * This is used if now rule is defined for a given user level. If no rule is defined then
 * this rule is used instead and always allows any user without a defined rule views on all
 * columns in the index tables.
 *
 * @package Outlandish\SocialMonitor\Services\TableIndex
 */
class NullRule implements Rule
{

	/**
	 * Determines whether this rule is for the given $user
	 *
	 * @param \Model_User|null $user
	 * @return boolean
	 */
	public function isFor(\Model_User $user = null)
	{
		return true;
	}

	/**
	 * Determines whether this rule allows the given $header to be seen
	 *
	 * @param Header $header
	 * @return boolean
	 */
	public function canSee(Header $header)
	{
		return true;
	}
}