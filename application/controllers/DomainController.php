<?php


use Outlandish\SocialMonitor\Helper\Gatekeeper;

class DomainController extends BaseController {

	/**
	 * Lists the domains of links mentioned by presences, allowing them to be marked as belonging to british council or not
	 * @user-level manager
	 */
	function indexAction() {
		if ($this->_request->isPost()) {
			if (!$this->view->user->isManager) {
                $this->flashMessage($this->translator->trans('route.domain.index.message.access-error'), 'error'); //'You do not have sufficient access to change this data', 'error');
			} else {
				$bc = $this->_request->getParam('is_bc');
				$db = self::db();
				$db->exec('UPDATE domains SET is_bc = 0');
				if ($bc) {
					$db->exec('UPDATE domains SET is_bc = 1 WHERE id IN (' . implode(',', array_keys($bc)) . ')');
				}
                $this->flashMessage($this->translator->trans('route.domain.index.message.success')); //'Domains updated');
			}
			$this->_helper->redirector->gotoSimple('');
		}
		$this->view->canEdit = $this->view->user->isManager;
		$this->view->company = $this->getCompanyName();
	}

    /**
     * Called via ajax to show the list of statuses that contain a given url
	 * @user-level manager
     */
    function statusListAction() {
        $id = $this->_request->getParam('id');
        /** @var Model_Domain $domain */
        $domain = $id ? Model_Domain::fetchById($id) : null;
        if(!$domain){
            $this->_helper->viewRenderer->setNoRender(true);
        } else {
            $twitterLookup = $this->db()->prepare('SELECT * FROM twitter_tweets WHERE id = :id');
            $facebookLookup = $this->db()->prepare('SELECT * FROM facebook_stream WHERE id = :id');

            $statuses = array();
            foreach ($domain->getLinksForUrl($this->_request->getParam('url')) as $link) {
                $lookup = $link->type == 'facebook' ? $facebookLookup : $twitterLookup;
                $lookup->execute(array(':id'=>$link->status_id));
                $status = $lookup->fetchAll(PDO::FETCH_OBJ);
                if ($status) {
                    $status = array_pop($status);
                    $status->presence = Model_PresenceFactory::getPresenceById($status->presence_id);
                    if ($status->presence) {
                        $statuses[] = $status;
                    }
                }
            }
            usort($statuses, function($a, $b) { return strcasecmp($b->created_time, $a->created_time); });
            $this->view->statuses = $statuses;
        }
        $this->_helper->layout()->disableLayout();
    }

	/**
	 * Ajax function for getting a page of domains
	 * @user-level manager
	 */
	function listAction() {
		Zend_Session::writeClose(); //release session on long running actions

		$search = $this->getRequestSearchQuery();
		$order = $this->getRequestOrdering();
		$limit = $this->getRequestLimit();
		$offset = $this->getRequestOffset();

		$clauses = array();
		$args = array();
		if ($search) {
			$clauses[] = 'd.domain LIKE :search';
			$args[':search'] = '%' . $search . '%';
		}
		$ordering = array();
		foreach ($order as $column=>$dir) {
			$ordering[] = $column . ' ' . $dir;
		}

		$sql = 'SELECT SQL_CALC_FOUND_ROWS d.*, COUNT(l.id) AS links
			FROM domains AS d
			INNER JOIN status_links AS l
				ON d.domain = l.domain';
		if ($clauses) {
			$sql .= ' WHERE ' . implode(' AND ', $clauses);
		}
		$sql .= ' GROUP BY d.domain';
		$sql .= ' ORDER BY ' . implode(',', $ordering);
		if ($limit != -1) {
			$sql .= ' LIMIT '.$limit;
		}
		if ($offset != -1) {
			$sql .= ' OFFSET '.$offset;
		}

		$db = $this->db();
		$query = $db->prepare($sql);
		$query->execute($args);
		$domains = $query->fetchAll(PDO::FETCH_OBJ);
		$totalCount = $db->lastRowCount();

		$tableData = array();
		foreach ($domains as $domain) {
			$url = $this->view->gatekeeper()->filter(Gatekeeper::PLACEHOLDER_URL, array('action'=>'view', 'id'=>$domain->id));
			$tableData[] = array(
				'id'=>$domain->id,
				'domain'=>$domain->domain,
				'links'=>$domain->links,
				'url'=>$url,
				'can_edit'=>$this->view->user->isManager ? '1' : '0',
				'is_bc'=>$domain->is_bc
			);
		}
		$count = count($tableData);

		//return CSV or JSON?
		if ($this->_request->getParam('format') == 'csv') {
			$this->returnCsv($tableData, 'domains.csv');
		} else {
			$apiResult = array(
				'sEcho' => $this->_request->getParam('sEcho'),
				'iTotalRecords' => $count,
				'iTotalDisplayRecords' => $totalCount,
				'aaData' => $tableData
			);
			$this->apiSuccess($apiResult);
		}
	}

	/**
	 * Shows details about how a domain has been used
	 * @user-level manager
	 */
	function viewAction() {


		/** @var Model_Domain $domain */
		$domain = Model_Domain::fetchById($this->_request->getParam('id'));
		$this->validateData($domain);

		$twitterLookup = $this->db()->prepare('SELECT * FROM twitter_tweets WHERE id = :id');
		$facebookLookup = $this->db()->prepare('SELECT * FROM facebook_stream WHERE id = :id');

		$links = array();
		foreach ($domain->getLinks() as $link) {
			$lookup = $link->type == 'facebook' ? $facebookLookup : $twitterLookup;
			$lookup->execute(array(':id'=>$link->status_id));
			$status = $lookup->fetchAll(PDO::FETCH_OBJ);
			if ($status) {
				$status = array_pop($status);
				$status->presence = Model_PresenceFactory::getPresenceById($status->presence_id);
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
		$this->updatePageTitle(['domain' => $domain->domain]);
	}
}