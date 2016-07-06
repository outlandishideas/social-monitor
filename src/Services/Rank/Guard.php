<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 06/07/2016
 * Time: 15:20
 */

namespace Outlandish\SocialMonitor\Services\Rank;


class Guard
{
	/**
	 * @var array
	 */
	private $userLevels;

	public function __construct(array $userLevels)
	{
		$this->userLevels = $userLevels;
	}
	
	public function canSee(\Model_User $user)
	{
		$level = $user ? $user->user_level : 1;
		
		return !in_array($level, $this->userLevels);
	}
}