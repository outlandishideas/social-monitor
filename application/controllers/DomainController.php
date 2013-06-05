<?php


class DomainController extends BaseController {
	/**
	 * Lists the domains of links mentioned by presences, allowing them to be marked as belonging to british council or not
	 * @user-level user
	 */
	function indexAction() {
		if ($this->_request->isPost()) {
			if (!$this->view->user->isManager) {
				$this->_helper->FlashMessenger(array('error' => 'You do not have sufficient access to change this data'));
			} else {
				$bc = $this->_request->is_bc;
				$db = self::db();
				$db->exec('UPDATE domains SET is_bc = 0');
				if ($bc) {
					$db->exec('UPDATE domains SET is_bc = 1 WHERE id IN (' . implode(',', array_keys($bc)) . ')');
				}
				$this->_helper->FlashMessenger(array('info' => 'Domains updated'));
			}
			$this->_helper->redirector->gotoSimple('');
		}
		$this->view->domains = Model_Domain::fetchAll();
		$this->view->title = 'Domains';
		$this->view->titleIcon = 'icon-laptop';
		$this->view->canEdit = $this->view->user->isManager;
	}
}