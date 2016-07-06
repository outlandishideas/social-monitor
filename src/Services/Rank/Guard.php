<?php

namespace Outlandish\SocialMonitor\Services\Rank;

/**
 * This guard stops the rendering of the rank on the presence and campaign view pages
 *
 * @package Outlandish\SocialMonitor\Services\Rank
 * @author Matthew Kendon <mkendon@gmail.com>
 */
class Guard
{
	/**
	 * @var array
	 */
	private $userLevels;

	/**
	 * @param array $userLevels the user levels that should not be able to see the rank
	 */
	public function __construct(array $userLevels)
	{
		$this->userLevels = $userLevels;
	}

	/**
	 * Determines whether to the user (based on their user_level) should be able to see the rank
	 *
	 * @param \Model_User $user
	 * @return bool
	 */
	public function canSee(\Model_User $user)
	{
		$level = $user ? $user->user_level : 1;

		return !in_array($level, $this->userLevels);
	}
}