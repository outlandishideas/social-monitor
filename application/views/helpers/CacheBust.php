<?php

class Zend_View_Helper_CacheBust extends Zend_View_Helper_Abstract
{
	public function cacheBust($path)
	{

		if (file_exists($path)) {

			$time = filemtime($path);

			return $this->view->assetUrl($path) . '?v=' . $time;
		}

		return $path;

	}
	
}
