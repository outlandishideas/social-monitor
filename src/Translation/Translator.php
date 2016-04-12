<?php

namespace Outlandish\SocialMonitor\Translation;

class Translator extends \Symfony\Component\Translation\Translator
{
	/**
	 * Loads all files from the given directory, if the filename matches the pattern: [anything].[locale].[format]
	 * @param string $format File format, eg csv
	 * @param string $dirName The directory containing the language files
	 */
	public function loadFromDirectory($format, $dirName)
	{
		$dirName = APP_ROOT_PATH . DIRECTORY_SEPARATOR . $dirName;
		$files = scandir($dirName);
		foreach ($files as $file) {
			if (preg_match("/.*\.([a-z]{2})\.$format/", $file, $matches)) {
				$this->addResource($format, $dirName . DIRECTORY_SEPARATOR . $file, $matches[1]);
			}
		}
	}
}