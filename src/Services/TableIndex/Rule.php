<?php

namespace Outlandish\SocialMonitor\Services\TableIndex;
use Outlandish\SocialMonitor\TableIndex\Header\Header;

/**
 * This class defines a rule that is used by the TableIndexGuard
 *
 * It determines what columns should be blacklisted from the index table for a given user level
 *
 * @package Outlandish\SocialMonitor\Services\TableIndex
 */
class Rule implements Interfaces\Rule
{
	/**
	 * @var int
	 */
	private $userLevel;
	/**
	 * @var array|\Outlandish\SocialMonitor\TableIndex\Header\Header[]
	 */
	private $blackListedColumns;

	/**
	 * TableIndexGuardRules constructor.
	 * @param integer $userLevel          one of the predefined user levels on \Model_User
	 * @param Header[]   $blackListedColumns a list of Header objects that should be blacklisted for the user
	 */
	public function __construct($userLevel, array $blackListedColumns)
	{
		$this->userLevel = $userLevel;
		$this->blackListedColumns = $blackListedColumns;
	}

	/**
	 * Determines whether this rule is for the given $user
	 *
	 * @param \Model_User|null $user
	 * @return boolean
	 */
	public function isFor(\Model_User $user = null)
	{
		$level = $user ? $user->user_level : 1;
		return $level == $this->userLevel;
	}

	/**
	 * Determines whether this rule allows the given $header to be seen
	 *
	 * @param Header $header
	 * @return boolean
	 */
	public function canSee(Header $header)
	{
		$filtered = array_filter($this->blackListedColumns, function(Header $h) use($header) {
			return $header->getName() == $h->getName();
		});

		return empty($filtered);
	}
}