<?php

namespace Outlandish\SocialMonitor\Services\Colours;

/**
 * Define colours in an associative array and have them be available as an object
 * 
 * This class expects an associative array of colours with the key as the name of the colour
 * and the value as the hex value of the colour.
 * 
 * It uses magic methods to get the colour from the associative array.
 * 
 * @package Outlandish\SocialMonitor\Services\Colours
 * @author Matthew Kendon <matt@outlandish.com>
 */
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