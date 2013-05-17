<?php

include APP_ROOT_PATH."/lib/facebook/facebook.php";

class Util_Facebook {
	private static $_fb;

	/**
	 * @static
	 * @return Facebook instance of Facebook API object
	 */
	public static function fb() {

		if (!isset(self::$_fb)) {
			$config = Zend_Registry::get('config');
			self::$_fb = new Facebook(array(
				'appId' => $config->facebook->appId,
				'secret' => $config->facebook->secret
			));
		}

		return self::$_fb;
	}

	/**
	 * Gets information about the given facebook page
	 * @param $name string
	 * @param $fields array
	 * @return array
	 */
	public static function pageInfo($name, $fields = array('page_id', 'name', 'username', 'pic_square', 'page_url', 'fan_count')) {
		$data = Util_Facebook::query('SELECT ' . implode(',', $fields) . ' FROM page WHERE username = "' . $name.'"');
		return $data[0];
	}

	/**
	 * Fetches facebook posts from the given page
	 * @param $pageId
	 * @param null $since
	 * @param array $fields
	 * @return array
	 */
	public static function pagePosts($pageId, $since = null, $fields = array('post_id', 'message', 'created_time', 'actor_id', 'comments', 'likes', 'permalink', 'type')) {
		$posts = array();
		if ($pageId) {
			if (!in_array('post_id', $fields)) {
				$fields[] = 'post_id';
			}
			$config = Zend_Registry::get('config');
			$max = time();
			do {
				$clauses = array('source_id = ' . $pageId);
				if ($since) {
					$clauses[] = 'created_time > ' . $since;
				}
				if ($max) {
					$clauses[] = 'created_time < ' . $max;
				}
				$fql = 'SELECT ' . implode(',', $fields) . '
					FROM stream
					WHERE ' . implode(' AND ', $clauses) . '
					ORDER BY created_time ASC
					LIMIT ' . $config->facebook->fetch_per_page;
				try {
					$newPosts = self::query($fql);
					foreach ($newPosts as $i=>$post) {
						$newPosts[$i] = (object)$post;
					}
					$posts = array_merge($posts, $newPosts);
					$repeat = count($newPosts) > 0;
					if ($repeat) {
						$since = $newPosts[count($newPosts)-1]->created_time;
					}
				} catch (Exception_FacebookNotFound $e) {
					//ignore not-found exceptions
					$repeat = false;
				}
			} while ($repeat);
		}

		return $posts;
	}

	public static function responses($pageId, $postIds = array(), $fields = array('post_id', 'text', 'time', 'id', 'fromid')) {
		$replies = array();
		if ($pageId && $postIds) {
			$postIds = array_map(function($a) { return "'" . $a . "'"; }, $postIds);
			if (!in_array('post_id', $fields)) {
				$fields[] = 'post_id';
			}
			$fql = 'SELECT ' . implode(',', $fields) . '
					FROM comment
					WHERE post_id IN (' . implode(',', $postIds) . ')
					AND fromid = \'' . $pageId . '\'';
			try {
				$replies = self::query($fql);
				foreach ($replies as $i=>$post) {
					$replies[$i] = (object)$post;
				}
			} catch (Exception_FacebookNotFound $e) {
			}
		}
		return $replies;
	}

	/**
	 * Query Facebook API
	 * @param $fql string FQL query string
	 * @throws Exception_FacebookApi
	 * @throws Exception_FacebookNotFound
	 * @return mixed
	 */
	public static function query($fql) {
		try {
			$ret = self::fb()->api( array(
				'method' => 'fql.query',
				'query' => $fql,
			));
		} catch (Exception $e) {
			throw new Exception_FacebookApi('Failed to execute query: ' . $e->getMessage(), $e->getCode(), $fql);
		}
		if (!$ret) {
			throw new Exception_FacebookNotFound('Facebook API Error: Not found', -1, $fql);
		}
		return $ret;
	}

	/**
	 * Query Facebook API
	 * @param $queries array FQL query strings
	 * @throws Exception_FacebookApi
	 * @throws Exception_FacebookNotFound
	 * @return mixed
	 */
	public static function multiquery($queries) {
		try {
			$ret = self::fb()->api( array(
				'method' => 'fql.multiquery',
				'queries' => $queries,
			));
		} catch (Exception $e) {
			throw new Exception_FacebookApi('Failed to execute multiquery: ' . $e->getMessage(), $e->getCode(), $queries);
		}
		if (!$ret) {
			throw new Exception_FacebookNotFound('Facebook API Error: Not found', -1, $queries);
		}
		return $ret;
	}

}
