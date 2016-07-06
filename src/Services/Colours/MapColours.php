<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 06/07/2016
 * Time: 11:03
 */

namespace Outlandish\SocialMonitor\Services\Colours;


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

	private function canSeeColours(\Model_User $user = null)
	{
		$level = $user ? $user->user_level : 1;
		return !in_array($level, $this->userLevels);
	}
}