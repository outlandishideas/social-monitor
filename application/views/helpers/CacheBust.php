<?php

class Zend_View_Helper_CacheBust extends Zend_View_Helper_Abstract
{
	public function cacheBust($path)
	{
		$path = ltrim($path, '/\\');

		if (file_exists($path)) {

			$time = filemtime($path);

			return $this->view->baseUrl($path) . '?v=' . $time;
		}

		return $path;

	}

}
