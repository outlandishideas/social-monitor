<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 05/07/2016
 * Time: 13:29
 */

namespace Outlandish\SocialMonitor\Helper;


class Favicon
{
	private $iconName;

	public function __construct($iconName)
	{
		$this->iconName = $iconName;
		$this->ensureFileExists();
	}

	public function getPath()
	{
		return "img/favicon/{$this->iconName}";
	}

	/**
	 * Ensures the file exists else defaults to default file
	 */
	private function ensureFileExists()
	{
		if(!file_exists(APP_PUBLIC_PATH . '/' . $this->getPath())) {
			$this->iconName = 'default.ico';
		};
	}

}