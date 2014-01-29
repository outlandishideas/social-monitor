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

            (object)array(
                'title' => 'Reach',
                'description' => Model_Badge::BADGE_TYPE_REACH_DESC,
                'kpis' => array(
                    (object)array(
                        'title' => 'Target Audience',
                        'description' => 'Each presence has a target audience set against it. The score is given based on how close the presence is to it’s target',
                        'values' => array(
                            'popularity_weighting'=>array('label'=>'Target Audience Weighting'),
                            'fb_min'=>array('label'=>'Facebook Minimum Audience (% of total)'),
                            'fb_opt'=>array('label'=>'Facebook Optimum Audience (% of total)'),

                            'tw_min'=>array('label'=>'Twitter Minimum Audience (% of total)'),
                            'tw_opt'=>array('label'=>'Twitter Optimum Audience (% of total)'),
                        )
                    ),
                    (object)array(
                        'title' => 'Time to Target Audience',
                        'description' => 'Each presence has a target audience set against it. The score is given based on how close the presence is to it’s target',
                        'values' => array(
                            'popularity_time_weighting'=>array('label'=>'Time to Target Audience Weighting'),
                            'achieve_audience_best'=>array('label'=>'Target audience best score (months)', 'hint'=>'The number of months the target audience should be reached within to get the best score'),
                            'achieve_audience_good'=>array('label'=>'Target audience good score (months)', 'hint'=>'The number of months the target audience should be reached within to get a medium score'),
                            'achieve_audience_bad'=>array('label'=>'Target audience bad score (months)', 'hint'=>'If the target audience will be reached after this number of months, the presence will get a bad score'),
                        )
                    ),
                    (object)array(
                        'title' => 'Retweets/Shares',
                        'description' => 'How often each presence’s posts are shared (Facebook) or retweeted (Twitter)',
                        'values' => array(
                            'sharing_weighting'=>array('label'=>'Retweets/Shares Weighting'),
                            'fb_share'=>array('label'=>'Average shares per post target (% of total audience)'),
                            'tw_retweet'=>array('label'=>'Average retweets target (% of total audience)'),
                        )
                    ),

                )
            ),
            (object)array(
                'title' => 'Engagement',
                'description' => Model_Badge::BADGE_TYPE_ENGAGEMENT_DESC,
                'kpis' => array(
                    (object)array(
                        'title' => 'Replies to Number of Posts',
                        'description' => 'The more replies that each post receives the higher the engagement score will be',
                        'values' => array(
                            'replies_to_posts_weighting'=>array('label'=>' Replies to Number of Posts Weighting'),
                            'replies_to_number_posts_best'=>array('label'=>'Best ratio of replies to number of posts', 'hint'=>'The presence will get the best score if the ratio of replies to the number of posts from others falls below this number'),
                            'replies_to_number_posts_good'=>array('label'=>'Good ratio of replies to number of posts', 'hint'=>'The presence will get a medium score if the ratio of replies to the number of posts from others falls below this number'),
                        )
                    ),
                    (object)array(
                        'title' => 'Response Time',
                        'description' => 'The response time score measures how quickly the presence responds to individual posts',
                        'values' => array(
                            'response_time_weighting'=>array('label'=>'Response Time Weighting'),
                            'response_time_best'=>array('label'=>'Perfect response time (hours)'),
                            'response_time_good'=>array('label'=>'Good response time (hours)'),
                            'response_time_bad'=>array('label'=>'Bad response time (hours)')
                        )
                    ),
                    (object)array(
                        'title' => 'Klout Score (Twitter only)',
                        'description' => 'The Klout Score is a third party measurement of your engagement with your audience (Twitter only)',
                        'values' => array(
                            'klout_score_weighting'=>array('label'=>'Klout Score Weighting'),
                            'klout_score_target'=>array('label'=>'Klout Score Target', 'hint'=>'The presence will score 100% if it meets or excedes this target, but will only receive 0% if it does not meet it.')
                        )
                    ),
                    (object)array(
                        'title' => 'Facebook Engagement Score (Facebook only)',
                        'description' => 'The Facebook Engagement Score is based on Social Baker\'s Daily Page Engagment Rate calculation.',
                        'values' => array(
                            'fb_engagement_score_weighting'=>array('label'=>'Facebook Engagement Score Weighting'),
                            'fb_engagment_score_target'=>array('label'=>'Facebook Engagement Score Target', 'hint'=>'The presence will score 100% if it meets or excedes this target, but will only receive 0% if it does not meet it.')
                        )
                    ),
                )
            ),
            (object)array(
                'title' => 'Quality',
                'description' => Model_Badge::BADGE_TYPE_QUALITY_DESC,
                'kpis' => array(
                    (object)array(
                        'title' => 'Actions Per Day',
                        'description' => 'A measurement of the average number of actions per day against the benchmark',
                        'values' => array(
                            'posts_per_day_weighting'=>array('label'=>'Actions per Day Weighting'),
                            'updates_per_day'=>array('label'=>'Actions Per Day'),
                            'updates_per_day_ok_range'=>array('label'=>'Actions Per Day OK range', 'hint'=>'Number above or below [actions per day] that is considered OK'),
                            'updates_per_day_bad_range'=>array('label'=>'Actions Per Day bad range', 'hint'=>'Number above or below [actions per day] that is considered too much or too little'),
                        )
                    ),
                    (object)array(
                        'title' => 'Links Per Day',
                        'description' => 'Measures the average number of links used within posts per day',
                        'values' => array(
                            'links_per_day_weighting'=>array('label'=>'Links Per Day Weighting'),
                            'links_per_day'=>array('label'=>'Links Per Day'),
                            'links_per_day_ok_range'=>array('label'=>'Links Per Day OK range', 'hint'=>'Number above or below [links per day] that is considered OK'),
                            'links_per_day_bad_range'=>array('label'=>'Links Per Day bad range', 'hint'=>'Number above or below [links per day] that is considered too much or too little'),
                        )
                    ),
                    (object)array(
                        'title' => 'Likes Per Post',
                        'description' => 'Measures the average number of likes from users on each post',
                        'values' => array(
                            'likes_per_post_weighting'=>array('label'=>'Likes per Post Weighting'),
                            'likes_per_post_best'=>array('label'=>'Best likes Per Post','hint'=>'The presence will get the best score if the average likes per post is equal to or more than this'),
                            'likes_per_post_good'=>array('label'=>'Good likes Per Post', 'hint'=>'The presence will get a good score if the average likes per post is equal to or more than this'),
                        )
                    ),
                    (object)array(
                        'title' => 'Sign Off',
                        'description' => 'The presence has been signed off by key stakeholders',
                        'values' => array(
                            'sign_off_weighting'=>array('label'=>'Sign Off Weighting'),
                        )
                    ),
                    (object)array(
                        'title' => 'Branding',
                        'description' => 'The presence has the correct branding implemented',
                        'values' => array(
                            'branding_weighting'=>array('label'=>'Branding Weighting'),
                        )
                    )
                )
            ),

		);
        foreach($values as $section){
            foreach ($section->kpis as $k=>$kpi) {
                foreach ($kpi->values as $key=>$args) {
                    $kpi->values[$key] = (object)$kpi->values[$key];
                    $args = $kpi->values[$key];
                    $args->key = $key;
                    $args->value = $this->getOption($key);
	                if (preg_match('/weighting$/', $key)) {
		                if (!$args->value) {
			                $args->value = 1;
		                }
		                $args->hint = 'A higher weighting will make this metric more important when calculating the ' . $section->title . ' Badge Score';
	                }
                    $args->error = null;
                    if (!isset($args->type)) {
                        $args->type = 'numeric';
                    }
                    if (!isset($args->hint)) {
                        $args->hint = null;
                    }
                }
            }
        }

		if ($this->_request->isPost()) {
			$valid = true;
            foreach($values as $section){
                foreach ($section->kpis as $k=>$kpi) {
                    foreach ($kpi->values as $args) {
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
                }
			}
            $adapter = new Zend_File_Transfer();

            $adapter->addFilter('Rename', array('target' => APPLICATION_PATH . '/../data/uploads/kpis.pdf',
                'overwrite' => true));

            if (!$adapter->receive()) {
                $messages = $adapter->getMessages();
                foreach($messages as $message){
                    $this->_helper->FlashMessenger(array('error' => $message));
                }
            } else {
                $this->_helper->FlashMessenger(array('info' => 'File Successfully uploaded'));
            }

			if ($valid) {
                foreach($values as $section){
                    foreach($section->kpis as $kpi){
                        foreach ($kpi->values as $args) {
                            $this->setOption($args->key, $args->value);
                        }
                    }
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