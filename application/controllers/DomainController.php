<?php


class DomainController extends BaseController {

	public function init() {
		parent::init();
		$this->view->titleIcon = 'icon-laptop';
	}

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
		$this->view->canEdit = $this->view->user->isManager;
	}

	/**
	 * Shows details about how a domain has been used
	 * @user-level user
	 */
	function viewAction() {
		$domain = Model_Domain::fetchById($this->_request->id);
		$this->validateData($domain);

		$twitterLookup = $this->db()->prepare('SELECT * FROM twitter_tweets WHERE id = :id');
		$facebookLookup = $this->db()->prepare('SELECT * FROM facebook_stream WHERE id = :id');

		$links = array();
		foreach ($domain->getLinks(true) as $link) {
			$lookup = $link->type == 'facebook' ? $facebookLookup : $twitterLookup;
			$lookup->execute(array(':id'=>$link->status_id));
			$status = $lookup->fetchAll(PDO::FETCH_OBJ);
			if ($status) {
				$status = array_pop($status);
				$status->presence = Model_Presence::fetchById($status->presence_id);
				if ($status->presence) {
					if (!array_key_exists($link->url, $links)) {
						$link->statuses = array($status);
						$links[$link->url] = $link;
					} else {
						$links[$link->url]->statuses[] = $status;
					}
				}
			}
		}
		usort($links, function($a, $b) {
			$ca = count($a->statuses);
			$cb = count($b->statuses);
			if ($ca == $cb) {
				return strcasecmp($a->url, $b->url);
			}
			return $ca > $cb ? -1 : 1;
		});
		foreach ($links as $data) {
			usort($data->statuses, function($a, $b) { return strcasecmp($b->created_time, $a->created_time); });
		}
		$this->view->domain = $domain;
		$this->view->links = $links;
		$this->view->title = $domain->domain;
	}
}