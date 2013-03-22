<?php

class Zend_View_Helper_FlashMessages extends Zend_View_Helper_Abstract
{
	public function flashMessages()
	{
		$flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

		//merge current and previous flash messages
		$messages = $flashMessenger->getMessages();
		if ($flashMessenger->hasCurrentMessages()) {
			$messages = array_merge(
				$messages,
				$flashMessenger->getCurrentMessages()
			);
			//don't show current on next request
			$flashMessenger->clearCurrentMessages();
		}
		
		if (!$messages) return '';

		//process messages
		$output = '<ol class="messages">';
		foreach ($messages as $message)
		{
			$type = 'error';
			if (is_array($message)) {
				list($type, $message) = each($message);
			}
			$output .= '<li class="'.$type.'">'.$message.'</li>';
		}
		$output .= '</ol>';

		return $output;
	}
}
