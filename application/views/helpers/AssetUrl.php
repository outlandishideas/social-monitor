<?php

require_once 'Zend/View/Helper/BaseUrl.php';

class Zend_View_Helper_AssetUrl extends Zend_View_Helper_BaseUrl
{
	public function assetUrl($file) {
		$baseUrl = $this->getBaseUrl();

		$file = ltrim($file, '/\\');

		return $baseUrl . '/public/' . $file;
	}

}