<?php

namespace Outlandish\SocialMonitor\Services\Colours;

/**
 * This class generates colour ranges for the front page map
 * 
 * It also allows you to alter the colour range of the map to be blank depening on the user
 * level of the logged in user. Non-logged in guests will be given a user level of 1 for
 * this purpose.
 * 
 * @package Outlandish\SocialMonitor\Services\Colours
 * @author Matthew Kendon <mkendon@gmail.com>
 */
class MapColours
{
	/**
	 * @var ColourDefinitions
	 */
	private $colours;
	/**
	 * @var array Array of Model_User::$userLevels that defines what users cannot see colours
	 */
	private $userLevels;

	public function __construct(ColourDefinitions $colours, array $userLevels)
	{
		$this->colours = $colours;
		$this->userLevels = $userLevels;
	}

	/**
	 * Get the range of values to match with colours for the map
	 *
	 * @param \Model_User $user
	 * @return array
	 */
	public function getRange(\Model_User $user = null)
	{
		if ($this->canSeeColours($user)) {
			return [0, 1, 20, 50, 80, 100];
		} else {
			return [0, 1];
		}
	}

	/**
	 * @param \Model_User $user
	 * @return array
	 */
	public function getColours(\Model_User $user = null)
	{
		if ($this->canSeeColours($user)) {
			return [
				$this->colours->grey,
				$this->colours->red,
				$this->colours->red,
				$this->colours->yellow,
				$this->colours->green,
				$this->colours->green
			];
		} else {
			return [
				$this->colours->grey,
				$this->colours->white,
			];
		}
	}

	/**
	 * Checks the current level of the user against the disallowed user levels
	 * 
	 * @param \Model_User|null $user  if user is null, the user level be 1
	 * @return bool return true       if the user can see colours, false if not.
	 */
	private function canSeeColours(\Model_User $user = null)
	{
		$level = $user ? $user->user_level : 1;
		return !in_array($level, $this->userLevels);
	}
}