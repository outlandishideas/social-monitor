<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 19/05/2016
 * Time: 16:33
 */

namespace Outlandish\SocialMonitor\Helper;


class ReCaptcha
{
	/** @var string */
	private $key;
	/** @var string */
	private $secret;

	/**
	 * A wrapper class for the Recaptcha class from Google, as well as a way of generating the HTML
	 * @param $key    The recaptcha site key
	 * @param $secret The recaptcha secret
	 */
	public function __construct($key, $secret)
	{
		$this->key = $key;
		$this->secret = $secret;
	}

	/**
	 * Wrapper for the google Recaptcha class. Verifies the gRecaptcha response
	 *
	 * @param $gRecaptchaResponse
	 * @param $remoteIp
	 * @return \ReCaptcha\Response
	 */
	public function verify($gRecaptchaResponse, $remoteIp)
	{
		$rc = new \ReCaptcha\ReCaptcha($this->secret);
		return $rc->verify($gRecaptchaResponse, $remoteIp);
	}

	/**
	 * generates the script tag needed for the recaptcha widget
	 *
	 * @return string
	 */
	public function generateScript()
	{
		return "<script src='https://www.google.com/recaptcha/api.js'></script>";
	}

	/**
	 * Generates the html to display the recaptcha widget
	 *
	 * @return string
	 */
	public function generateHtml()
	{
		return "<div class=\"g-recaptcha\" data-sitekey=\"{$this->key}\"></div>";
	}
}