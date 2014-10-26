<?php

class Model_Domain extends Model_Base {
	protected static $tableName = 'domains';
	protected static $sortColumn = 'domain';

	function getLinks() {
		$stmt = $this->_db->prepare('SELECT * FROM status_links WHERE domain = :domain');
		$stmt->execute(array(':domain'=>$this->domain));
		return $stmt->fetchAll(PDO::FETCH_OBJ);
	}

	function getLinkCount() {
		$stmt = $this->_db->prepare('SELECT COUNT(1) FROM status_links WHERE domain = :domain');
		$stmt->execute(array(':domain'=>$this->domain));
		return $stmt->fetchColumn();
	}

    function getLinksForUrl($url) {
        $stmt = $this->_db->prepare('SELECT * FROM status_links WHERE domain = :domain AND url = :url');
        $stmt->execute(array(':domain'=>$this->domain, 'url'=>$url));
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}