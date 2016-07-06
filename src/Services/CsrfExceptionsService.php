<?php

namespace Outlandish\SocialMonitor\Services;
use Zend_Controller_Request_Abstract;

/**
 * Allows certain routes to be excempt from csrf token.
 *
 * This was introduced to allow goethe to login to the site from a form elsewhere.
 * It shouldn't be used for anything else, but I've made it so it can just in case.
 *
 * @package Outlandish\SocialMonitor\Services
 * @author Matthew Kendon <matt@outlandish.com>
 */
class CsrfExceptionsService
{
	/**
	 * @var array
	 */
	private $routes;

	/**
	 * CsrfExceptionsService constructor.
	 * @param array $routes
	 */
	public function __construct(array $routes)
	{
		$this->routes = $routes;
	}

	public function checkCsrfTokenForRoute(Zend_Controller_Request_Abstract $request)
	{
		$controller = $request->{$request->getControllerKey()};
		$action = $request->{$request->getActionKey()};


		return !(array_key_exists($controller, $this->routes) &&
			is_array($this->routes[$controller]) &&
			in_array($action,$this->routes[$controller]));
	}
}