<?php


use Outlandish\SocialMonitor\Helper\Gatekeeper;

class HashtagController extends BaseController {

	/**
	 * Lists the hashtags of posts mentioned by presences, allowing them to be marked as relevant or not
	 * @user-level user
	 */
	function indexAction() {
		if ($this->_request->isPost()) {
			if (!$this->view->user->isManager) {
				$this->flashMessage($this->translator->trans('route.hashtag.index.message.access-error'), 'error'); //'You do not have sufficient access to change this data', 'error');
			} else {
				$relevant = $this->_request->getParam('is_relevant');
				$db = self::db();
				$db->exec('UPDATE hashtags SET is_relevant = 0');
				if ($relevant) {
					$db->exec('UPDATE hashtags SET is_relevant = 1 WHERE id IN (' . implode(',', array_keys($relevant)) . ')');
				}
				$this->flashMessage($this->translator->trans('route.hashtag.index.message.success')); //'Domains updated');
			}
			$this->_helper->redirector->gotoSimple('');
		}
		$this->view->canEdit = $this->view->user->isManager;
	}

	/**
	 * Ajax function for getting a page of hashtags
	 * @user-level user
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
			$clauses[] = 'h.hashtag LIKE :search';
			$args[':search'] = '%' . $search . '%';
		}
		$ordering = array();
		foreach ($order as $column=>$dir) {
			$ordering[] = $column . ' ' . $dir;
		}

		$sql = 'SELECT SQL_CALC_FOUND_ROWS h.*, COUNT(p.id) AS posts
			FROM hashtags AS h
			INNER JOIN posts_hashtags AS p
				ON h.id = p.hashtag';
		if ($clauses) {
			$sql .= ' WHERE ' . implode(' AND ', $clauses);
		}
		$sql .= ' GROUP BY h.hashtag';
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
		$hashtags = $query->fetchAll(PDO::FETCH_OBJ);
		$totalCount = $db->lastRowCount();

		$tableData = array();
		foreach ($hashtags as $hashtag) {
			$url = $this->view->gatekeeper()->filter(Gatekeeper::PLACEHOLDER_URL, array('action'=>'view', 'id'=>$hashtag->id));
			$tableData[] = array(
				'id'=>$hashtag->id,
				'hashtag'=>$hashtag->hashtag,
				'posts'=>$hashtag->posts,
				'can_edit'=>$this->view->user->isManager ? '1' : '0',
				'is_relevant'=>$hashtag->is_relevant
			);
		}
		$count = count($tableData);

		//return CSV or JSON?
		if ($this->_request->getParam('format') == 'csv') {
			$this->returnCsv($tableData, 'hashtags.csv');
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
}