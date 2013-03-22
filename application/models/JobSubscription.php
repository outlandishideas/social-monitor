<?php

class Model_JobSubscription extends Model_Base
{
	protected $_tableName = 'job_subscriptions';

	public function getJob() {
		return Model_Job::fetchById($this->job_id);
	}
}
