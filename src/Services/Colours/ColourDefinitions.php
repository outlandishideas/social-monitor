<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 06/07/2016
 * Time: 11:04
 */

namespace Outlandish\SocialMonitor\Services\Colours;


class ColourDefinitions
{
	/**
	 * @var array  Associative array of colours 
	 */
	private $colours;

	public function __construct(array $colours)
	{
		$this->colours = $colours;
	}
	
	public function __get($name)
	{
		return $this->hasColour($name) ? $this->getColour($name) : $this->getDefault();
	}
	
	private function hasColour($name) {
		return array_key_exists($name, $this->colours);
	}
	
	private function getColour($name) {
		return $this->colours[$name];
	}

	/**
	 * @return string
	 */
	private function getDefault()
	{
		return '#d2d2d2';
	}
}