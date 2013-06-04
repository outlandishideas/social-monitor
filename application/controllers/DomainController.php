<?php


class DomainController extends BaseController {
	function indexAction() {
		if ($this->_request->isPost()) {
			$bc = $this->_request->is_bc;
			$db = self::db();
			$db->exec('UPDATE domains SET is_bc = 0');
			if ($bc) {
				$db->exec('UPDATE domains SET is_bc = 1 WHERE id IN (' . implode(',', array_keys($bc)) . ')');
			}
			$this->_helper->FlashMessenger(array('info' => 'Domains updated'));
			$this->_helper->redirector->gotoSimple('');
		}
		$this->view->domains = Model_Domain::fetchAll();
		$this->view->title = 'Domains';
		$this->view->titleIcon = 'icon-laptop';
	}
}