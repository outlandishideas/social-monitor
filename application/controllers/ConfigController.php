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
                'description' => 'Something about the Reach Badge',
                'kpis' => array(
                    (object)array(
                        'title' => 'Target Audience',
                        'description' => 'Here goes a description about Target Audience',
                        'values' => array(
                            'popularity_weighting'=>array('label'=>'Target Audience Weighting', 'hint'=>'A higher weighting will make this metric more important when calculating the Badge Score'),
                            'fb_min'=>array('label'=>'Facebook Minimum Audience (% of total)'),
                            'fb_opt'=>array('label'=>'Facebook Optimum Audience (% of total)'),

                            'tw_min'=>array('label'=>'Twitter Minimum Audience (% of total)'),
                            'tw_opt'=>array('label'=>'Twitter Optimum Audience (% of total)'),
                        )
                    ),
                    (object)array(
                        'title' => 'Time to Target Audience',
                        'description' => 'Here goes a description about Time to Target Audience',
                        'values' => array(
                            'popularity_time_weighting'=>array('label'=>'Time to Target Audience Weighting', 'hint'=>'A higher weighting will make this metric more important when calculating the Badge Score'),
                            'achieve_audience_best'=>array('label'=>'Target audience best score (months)', 'hint'=>'The number of months the target audience should be reached within to get the best score'),
                            'achieve_audience_good'=>array('label'=>'Target audience good score (months)', 'hint'=>'The number of months the target audience should be reached within to get a medium score'),
                            'achieve_audience_bad'=>array('label'=>'Target audience bad score (months)', 'hint'=>'If the target audience will be reached after this number of months, the presence will get a bad score'),
                        )
                    ),
                    (object)array(
                        'title' => 'Retweets/Shares',
                        'description' => 'Here goes a description about Retweets/Shares',
                        'values' => array(
                            'sharing_weighting'=>array('label'=>'Retweets/Shares Weighting', 'hint'=>'A higher weighting will make this metric more important when calculating the Badge Score'),
                            'fb_share'=>array('label'=>'Average shares per post target (% of total audience)'),
                            'tw_retweet'=>array('label'=>'Average retweets target (% of total audience)'),
                        )
                    ),

                )
            ),
            (object)array(
                'title' => 'Engagement',
                'description' => 'Something about the Engagement Badge',
                'kpis' => array(
                    (object)array(
                        'title' => 'Replies to Number of Posts',
                        'description' => 'Here goes a description about Replies to Number of Posts',
                        'values' => array(
                            'replies_to_posts_weighting'=>array('label'=>' Replies to Number of Posts Weighting', 'hint'=>'A higher weighting will make this metric more important when calculating the Badge Score'),
                            'replies_to_number_posts_best'=>array('label'=>'Best ratio of replies to number of posts', 'hint'=>'The presence will get the best score if the ratio of replies to the number of posts from others falls below this number'),
                            'replies_to_number_posts_good'=>array('label'=>'Good ratio of replies to number of posts', 'hint'=>'The presence will get a medium score if the ratio of replies to the number of posts from others falls below this number'),
                        )
                    ),
                    (object)array(
                        'title' => 'Response Time',
                        'description' => 'Here goes a description about Response Time',
                        'values' => array(
                            'response_time_weighting'=>array('label'=>'Response Time Weighting', 'hint'=>'A higher weighting will make this metric more important when calculating the Badge Score'),
                            'response_time_best'=>array('label'=>'Perfect response time (hours)'),
                            'response_time_good'=>array('label'=>'Good response time (hours)'),
                            'response_time_bad'=>array('label'=>'Bad response time (hours)')
                        )
                    ),
                )
            ),
            (object)array(
                'title' => 'Quality',
                'description' => 'Something about the Quality Badge',
                'kpis' => array(
                    (object)array(
                        'title' => 'Updates Per Day',
                        'description' => 'Here goes a description about Updates per Day',
                        'values' => array(
                            'posts_per_day_weighting'=>array('label'=>'Updates per Day Weighting', 'hint'=>'A higher weighting will make this metric more important when calculating the Badge Score'),
                            'updates_per_day'=>array('label'=>'Updates Per Day'),
                            'updates_per_day_ok_range'=>array('label'=>'Updates Per Day OK range', 'hint'=>'Number above or below [updates per day] that is considered OK'),
                            'updates_per_day_bad_range'=>array('label'=>'Updates Per Day bad range', 'hint'=>'Number above or below [updates per day] that is considered too much or too little'),
                        )
                    ),
                    (object)array(
                        'title' => 'Links Per Day',
                        'description' => 'Here goes a description about Links per Day',
                        'values' => array(
                            'links_per_day_weighting'=>array('label'=>'Links Per Day Weighting', 'hint'=>'A higher weighting will make this metric more important when calculating the Badge Score'),
                            'links_per_day'=>array('label'=>'Links Per Day'),
                            'links_per_day_ok_range'=>array('label'=>'Links Per Day OK range', 'hint'=>'Number above or below [links per day] that is considered OK'),
                            'links_per_day_bad_range'=>array('label'=>'Links Per Day bad range', 'hint'=>'Number above or below [links per day] that is considered too much or too little'),
                        )
                    ),
                    (object)array(
                        'title' => 'Likes Per Post',
                        'description' => 'Here goes a description about Likes per Post',
                        'values' => array(
                            'likes_per_post_weighting'=>array('label'=>'Likes per Post Weighting', 'hint'=>'A higher weighting will make this metric more important when calculating the Badge Score'),
                            'likes_per_post_best'=>array('label'=>'Best likes Per Post','hint'=>'The presence will get the best score if the average likes per post is equal to or more than this'),
                            'likes_per_post_good'=>array('label'=>'Good likes Per Post', 'hint'=>'The presence will get a good score if the average likes per post is equal to or more than this'),
                        )
                    ),
                    (object)array(
                        'title' => 'Sign Off',
                        'description' => 'Here goes a description about Sign Off',
                        'values' => array(
                            'sign_off_weighting'=>array('label'=>'Sign Off Weighting', 'hint'=>'A higher weighting will make this metric more important when calculating the Badge Score'),
                        )
                    ),
                    (object)array(
                        'title' => 'Branding',
                        'description' => 'Here goes a description about Branding',
                        'values' => array(
                            'branding_weighting'=>array('label'=>'Branding Weighting', 'hint'=>'A higher weighting will make this metric more important when calculating the Badge Score'),
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