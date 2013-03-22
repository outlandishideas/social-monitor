<?php

require_once('OpenAmplify.php');

class Model_FacebookPost extends Model_StatusBase {
	protected $_tableName = 'facebook_stream';
	
	public function extractTopics() {
		return OpenAmplify::extractTopics('post', $this->message, $this);
	}
}