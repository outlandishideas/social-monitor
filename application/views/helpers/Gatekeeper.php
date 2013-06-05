<?php

class Zend_View_Helper_Gatekeeper extends Zend_View_Helper_Abstract
{
	static $_cache = array();

	public function gatekeeper()
	{
		return new Zend_View_Helper_Gatekeeper($this->view);
	}

	public function __construct($view = null) {
		$this->view = $view;
	}

	public function getRequiredUserLevel($controller, $action) {
		//convert multiple-word-action to multipleWordAction
		if (strpos($action, '-') !== false) {
			$action = str_replace(' ', '', ucwords(str_replace('-', ' ', $action)));
			$action[0] = strtolower($action[0]);
		}

		if (isset(self::$_cache[$controller][$action])) {
			$level = self::$_cache[$controller][$action];
		} else {
			$controllerClassName = ucfirst($controller) . 'Controller';
			$methodReflector = null;

			// Do reflection on the given controller/action combination.
			// Assume controller file has already been included. If that fails, manually require it using
			// Zend's controller directory list
			try {
				$methodReflector = new ReflectionMethod($controllerClassName, $action . 'Action');
			} catch (Exception $ex) {
				foreach (Zend_Controller_Front::getInstance()->getControllerDirectory() as $dir) {
					$file = $dir . DIRECTORY_SEPARATOR . $controllerClassName . '.php';
					if (file_exists($file)) {
						require_once($file);
					} else { echo 'cannot find: ' . $file; }

					try {
						$methodReflector = new ReflectionMethod($controllerClassName, $action . 'Action');
						break;
					} catch (Exception $ex) {}
				}
			}

			$level = null;
			if ($methodReflector && preg_match('/@user-level\s+([\w_]+)/', $methodReflector->getDocComment(), $matches)) {
				$level = $matches[1];
			}

			// cache the permission
			if (!array_key_exists($controller, self::$_cache)) {
				self::$_cache[$controller] = array();
			}
			self::$_cache[$controller][$action] = $level;
		}
		return $level;
	}

	public function userCanAccess($urlArgs) {
		$controller = $urlArgs['controller'];
		$action = $urlArgs['action'];
		$level = $this->getRequiredUserLevel($controller, $action);
		return $this->view->user->canPerform($level, $controller, $urlArgs['id']);
	}

	private function populateMissingArgs(&$urlArgs) {
		if (empty($urlArgs['controller'])) {
			$urlArgs['controller'] = Zend_Controller_Front::getInstance()->getRequest()->getControllerName();
		}
		if (empty($urlArgs['action'])) {
			$urlArgs['action'] = Zend_Controller_Front::getInstance()->getRequest()->getActionName();
		}
		if (empty($urlArgs['id'])) {
			$urlArgs['id'] = null;
		}
	}

	/**
	 * Returns a modified $urlArgs, containing the best controller/action combination that the user is allowed to perform.
	 * Assumes that index/index is universally performable
	 * @param $urlArgs
	 * @return array
	 */
	public function fallbackUrlArgs($urlArgs) {
		$this->populateMissingArgs($urlArgs);
		$ok = $this->userCanAccess($urlArgs);
		if (!$ok && $urlArgs['action'] != 'index') {
			$urlArgs['action'] = 'index';
			$ok = $this->userCanAccess($urlArgs);
		}
		if (!$ok && $urlArgs['controller'] != 'index') {
			$urlArgs['controller'] = 'index';
		}
		return $urlArgs;
	}

	/**
	 * Checks the permission required for the controller/action arguments given in $urlArgs,
	 * then returns the text, after any template string replacements
	 * valid replacements are:
	 * > %url% - replaced with $view->url($urlArgs, $name, $reset, $encode)
	 * > %controller% - replaced with appropriate controller name (either current, or from $urlArgs)
	 * > %action% - replaced with appropriate action name (either current, or from $urlArgs)
	 *
	 * @param $text
	 * @param array $urlArgs
	 * @param null $name
	 * @param bool $reset
	 * @param bool $encode
	 * @return mixed|null
	 */
	public function filter($text, $urlArgs = array(), $name = null, $reset = false, $encode = true) {
		$this->populateMissingArgs($urlArgs);
		if ($this->userCanAccess($urlArgs)) {
			$text = str_replace('%url%', $this->view->url($urlArgs, $name, $reset, $encode), $text);
			$text = str_replace('%controller%', $urlArgs['controller'], $text);
			$text = str_replace('%action%', $urlArgs['action'], $text);
			return $text;
		} else {
			return null;
		}
	}

	public function filterAll($list, $name = null, $reset = false, $encode = true) {
		$filtered = array();
		foreach ($list as $text => $urlArgs) {
			$f = $this->filter($text, $urlArgs, $name, $reset, $encode);
			if ($f) {
				$filtered[] = $f;
			}
		}
		return $filtered;
	}
}
