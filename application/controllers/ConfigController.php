<?php


class ConfigController extends BaseController {
	/**
	 * Lists all configurable values
	 * @user-level manager
	 */
	function indexAction() {
		$this->view->title = 'Settings';
		$this->view->titleIcon = 'icon-cog';
		$values = array(

            'fb_min'=>array('label'=>'Facebook Minimum Audience (% of total)'),
			'fb_opt'=>array('label'=>'Facebook Optimum Audience (% of total)'),

            'tw_min'=>array('label'=>'Twitter Minimum Audience (% of total)'),
			'tw_opt'=>array('label'=>'Twitter Optimum Audience (% of total)'),

            'updates_per_day'=>array('label'=>'Updates Per Day'),
			'updates_per_day_ok_range'=>array('label'=>'Updates Per Day OK range', 'hint'=>'Number above or below [updates per day] that is considered OK'),
			'updates_per_day_bad_range'=>array('label'=>'Updates Per Day bad range', 'hint'=>'Number above or below [updates per day] that is considered too much or too little'),

            'links_per_day'=>array('label'=>'Links Per Day'),
            'links_per_day_ok_range'=>array('label'=>'Links Per Day OK range', 'hint'=>'Number above or below [links per day] that is considered OK'),
            'links_per_day_bad_range'=>array('label'=>'Links Per Day bad range', 'hint'=>'Number above or below [links per day] that is considered too much or too little'),

            'likes_per_post_best'=>array('label'=>'Best likes Per Post','hint'=>'The presence will get the best score if the average likes per post is equal to or more than this'),
            'likes_per_post_good'=>array('label'=>'Good likes Per Post', 'hint'=>'The presence will get a good score if the average likes per post is equal to or more than this'),

            'replies_to_number_posts_best'=>array('label'=>'Best ratio of replies to number of posts', 'hint'=>'The presence will get the best score if the ratio of replies to the number of posts from others falls below this number'),
            'replies_to_number_posts_good'=>array('label'=>'Good ratio of replies to number of posts', 'hint'=>'The presence will get a medium score if the ratio of replies to the number of posts from others falls below this number'),

            'achieve_audience_best'=>array('label'=>'Target audience best score (months)', 'hint'=>'The number of months the target audience should be reached within to get the best score'),
			'achieve_audience_good'=>array('label'=>'Target audience good score (months)', 'hint'=>'The number of months the target audience should be reached within to get a medium score'),
			'achieve_audience_bad'=>array('label'=>'Target audience bad score (months)', 'hint'=>'If the target audience will be reached after this number of months, the presence will get a bad score'),

            'response_time_best'=>array('label'=>'Perfect response time (hours)'),
			'response_time_good'=>array('label'=>'Good response time (hours)'),
			'response_time_bad'=>array('label'=>'Bad response time (hours)')

		);
		foreach ($values as $key=>$args) {
			$values[$key] = (object)$values[$key];
			$args = $values[$key];
			$args->key = $key;
			$args->value = $this->getOption($key);
			$args->error = null;
			if (!isset($args->type)) {
				$args->type = 'numeric';
			}
			if (!isset($args->hint)) {
				$args->hint = null;
			}
		}

		if ($this->_request->isPost()) {
			$valid = true;
			foreach ($values as $args) {
				if (array_key_exists($args->key, $_POST)) {
					$args->value = $_POST[$args->key];
				}
				switch ($args->type) {
					case 'numeric':
						if (!is_numeric($args->value)) {
							$args->error = 'Value must be numeric';
							$valid = false;
						}
						break;
				}
			}

			if ($valid) {
				foreach ($values as $args) {
					$this->setOption($args->key, $args->value);
				}
				$this->_helper->FlashMessenger(array('info' => 'Settings saved'));
				$this->_helper->redirector->gotoSimple('');
			} else {
				$this->_helper->FlashMessenger(array('error' => 'Invalid values. Please check before saving'));
			}
		}
		$this->view->values = $values;
	}
}