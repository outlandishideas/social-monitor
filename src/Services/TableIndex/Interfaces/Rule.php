<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 06/07/2016
 * Time: 12:44
 */

namespace Outlandish\SocialMonitor\Services\TableIndex\Interfaces;


use Outlandish\SocialMonitor\TableIndex\Header\Header;

/**
 * This interface determines what a rule should do.
 * 
 * @package Outlandish\SocialMonitor\Services\TableIndex\Interfaces
 * @author Matthew Kendon <matt@outlandish.com>
 */
interface Rule
{
	/**
	 * Determines whether this rule is for the given $user
	 * 
	 * @param \Model_User|null $user
	 * @return boolean
	 */
	public function isFor(\Model_User $user = null);

	/**
	 * Determines whether this rule allows the given $header to be seen
	 * 
	 * @param Header $header
	 * @return boolean
	 */
	public function canSee(Header $header);
}