<?php


class ConfigController extends BaseController {
	/**
	 * Lists all configurable values
	 * @user-level manager
	 */
	function indexAction() {
		$reachBadge = self::getContainer()->get('badge.reach');
		$qualityBadge = self::getContainer()->get('badge.quality');
		$engagementBadge = self::getContainer()->get('badge.engagement');

		$values = array(

            (object)array(
                'title' => $this->translator->trans('Config.index.sections.general.title'),
                'description' => '',
                'kpis' => array(
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.general.feedback.title'),
                        'description' => $this->translator->trans('Config.index.sections.general.feedback.description'),
                        'values' => array(
                            'email-feedback-to-address'=>
								array(
									'label'=>$this->translator->trans('Global.email-address'),
									'type'=>'email'
								)
                        )
                    )
                )
            ),
            (object)array(
                'title' => $reachBadge->getTitle(),
                'description' => $reachBadge->getDescription(),
                'kpis' => array(
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.reach.target-audience.title'),
						'description' => $this->translator->trans('Config.index.sections.reach.target-audience.description'),
						'values' => array(
							'popularity_weighting' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.weighting')
							),

							'fb_min' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.fb-min')
							),
							'fb_opt' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.fb-opt')
							),

							'tw_min' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.tw-min')
							),
							'tw_opt' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.tw-opt')
							),

							'sw_min' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.sw-min')
							),
							'sw_opt' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.sw-opt')
							),

							'ig_min' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.ig-min')
							),
							'ig_opt' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.ig-opt')
							),

							'yt_min' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.yt-min')
							),
							'yt_opt' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.yt-opt')
							),

							'in_min' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.in-min')
							),
							'in_opt' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.in-opt')
							),
							'size_3_presences' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.shared-audience-extra-large')
							),
							'size_2_presences' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.shared-audience-large')
							),
							'size_1_presences' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.shared-audience-medium')
							),
							'size_0_presences' => array(
								'label' => $this->translator->trans('Config.index.sections.reach.target-audience.shared-audience-small')
							),
						)
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.reach.time-to-target-audience.title'),
                        'description' => $this->translator->trans('Config.index.sections.reach.time-to-target-audience.description'),
                        'values' => array(
                            'popularity_time_weighting'=>array(
								'label'=>$this->translator->trans('Config.index.sections.reach.time-to-target-audience.weighting'),
							),
                            'achieve_audience_best'=>array(
								'label'=>$this->translator->trans('Config.index.sections.reach.time-to-target-audience.best-score-label'),
								'hint'=>$this->translator->trans('Config.index.sections.reach.time-to-target-audience.best-score-hint'),
							),
							'achieve_audience_good'=>array(
								'label'=>$this->translator->trans('Config.index.sections.reach.time-to-target-audience.good-score-label'),
								'hint'=>$this->translator->trans('Config.index.sections.reach.time-to-target-audience.good-score-hint'),
							),
							'achieve_audience_bad'=>array(
								'label'=>$this->translator->trans('Config.index.sections.reach.time-to-target-audience.bad-score-label'),
								'hint'=>$this->translator->trans('Config.index.sections.reach.time-to-target-audience.bad-score-hint'),
							),
                        )
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.reach.sharing.title'),
                        'description' => $this->translator->trans('Config.index.sections.reach.sharing.description'),
                        'values' => array(
                            'sharing_weighting'=>array(
								'label'=>$this->translator->trans('Config.index.sections.reach.sharing.weighting'),
							),
                            'fb_share'=>array(
								'label'=>$this->translator->trans('Config.index.sections.reach.sharing.fb-target'),
							),
                            'tw_retweet'=>array(
								'label'=>$this->translator->trans('Config.index.sections.reach.sharing.tw-target'),
							),
                        )
                    ),

                )
            ),
            (object)array(
                'title' => $engagementBadge->getTitle(),
                'description' => $engagementBadge->getDescription(),
                'kpis' => array(
					//TODO: Remove this as we currently aren't using ResponseRatio
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
                        'title' => $this->translator->trans('Config.index.sections.engagement.response-time.title'),
                        'description' => $this->translator->trans('Config.index.sections.engagement.response-time.description'),
                        'values' => array(
                            'response_time_weighting'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.response-time.weighting'),
							),
                            'response_time_best'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.response-time.best-label'),
							),
                            'response_time_good'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.response-time.good-label'),
							),
                            'response_time_bad'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.response-time.bad-label'),
							)
                        )
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.engagement.klout.title'),
                        'description' => $this->translator->trans('Config.index.sections.engagement.klout.description'),
                        'values' => array(
                            'klout_score_weighting'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.klout.weighting'),
							),
							//TODO: remove this - we don't use a target for Klout
                            'klout_score_target'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.klout.target-label'),
								'hint'=>$this->translator->trans('Config.index.sections.engagement.klout.target-hint')
							)
                        )
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.engagement.fb.title'),
                        'description' => $this->translator->trans('Config.index.sections.engagement.fb.description'),
                        'values' => array(
                            'fb_active_user_percentage_small'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.fb.active-users-small')
							),
                            'fb_active_user_percentage_medium'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.fb.active-users-medium')
							),
                            'fb_active_user_percentage_large'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.fb.active-users-large')
							),
                            'fb_active_user_percentage_xlarge'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.fb.active-users-extra-large')
							),
                            'facebook_engagement_weighting'=>array(
								'label'=>$this->translator->trans('Config.index.sections.engagement.fb.weighting'),
							)
                        )
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.engagement.sw.title'),
                        'description' => $this->translator->trans('Config.index.sections.engagement.sw.description'),
                        'values' => array(
                            'sw_active_user_percentage_small'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.sw.active-users-small')),
                            'sw_active_user_percentage_medium'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.sw.active-users-medium')),
                            'sw_active_user_percentage_large'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.sw.active-users-large')),
                            'sina_weibo_engagement_weighting'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.sw.weighting')),
                        )
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.engagement.ig.title'),
                        'description' => $this->translator->trans('Config.index.sections.engagement.ig.description'),
                        'values' => array(
                            'ig_active_user_percentage_small'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.ig.active-users-small')),
                            'ig_active_user_percentage_medium'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.ig.active-users-medium')),
                            'ig_active_user_percentage_large'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.ig.active-users-large')),
                            'instagram_engagement_weighting'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.ig.weighting'),)
                        )
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.engagement.yt.title'),
                        'description' => $this->translator->trans('Config.index.sections.engagement.yt.description'),
                        'values' => array(
                            'yt_active_user_percentage_small'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.yt.active-users-small')),
                            'yt_active_user_percentage_medium'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.yt.active-users-medium')),
                            'yt_active_user_percentage_large'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.yt.active-users-large')),
                            'youtube_engagement_weighting'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.yt.weighting')),
                        )
                    ),
                    (object)array(
						'title' => $this->translator->trans('Config.index.sections.engagement.in.title'),
                        'description' => $this->translator->trans('Config.index.sections.engagement.in.description'),
                        'values' => array(
                            'in_active_user_percentage_small'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.in.active-users-small')),
                            'in_active_user_percentage_medium'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.in.active-users-medium')),
                            'in_active_user_percentage_large'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.in.active-users-large')),
                            'linkedin_engagement_weighting'=>array('label'=>$this->translator->trans('Config.index.sections.engagement.in.weighting'))
                        )
                    )
                )
            ),
            (object)array(
                'title' => $qualityBadge->getTitle(),
                'description' => $qualityBadge->getDescription(),
                'kpis' => array(
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.quality.actions-per-day.title'),
                        'description' => $this->translator->trans('Config.index.sections.quality.actions-per-day.description'),
                        'values' => array(
                            'posts_per_day_weighting'=>array('label'=>$this->translator->trans('Config.index.sections.quality.actions-per-day.weighting')),
                            'updates_per_day'=>array('label'=>$this->translator->trans('Config.index.sections.quality.actions-per-day.target-label')),
                            'updates_per_day_ok_range'=>array(
								'label'=>$this->translator->trans('Config.index.sections.quality.actions-per-day.ok-range-label'),
								'hint'=>$this->translator->trans('Config.index.sections.quality.actions-per-day.ok-range-hint')),
                            'updates_per_day_bad_range'=>array(
								'label'=>$this->translator->trans('Config.index.sections.quality.actions-per-day.bad-range-label'),
								'hint'=>$this->translator->trans('Config.index.sections.quality.actions-per-day.bad-range-hint'))
                        )
                    ),
					// TODO: Remove if we're not using this
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
                        'title' => $this->translator->trans('Config.index.sections.quality.likes-per-post.title'),
                        'description' => $this->translator->trans('Config.index.sections.quality.likes-per-post.description'),
                        'values' => array(
                            'likes_per_post_weighting'=>array('label'=>$this->translator->trans('Config.index.sections.quality.likes-per-post.weighting')),
                            'likes_per_post_best'=>array(
								'label'=>$this->translator->trans('Config.index.sections.quality.likes-per-post.best-label'),
								'hint'=>$this->translator->trans('Config.index.sections.quality.likes-per-post.best-hint')),
                            'likes_per_post_good'=>array(
								'label'=>$this->translator->trans('Config.index.sections.quality.likes-per-post.good-label'),
								'hint'=>$this->translator->trans('Config.index.sections.quality.likes-per-post.good-hint'))
                        )
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.quality.likes-per-view.title'),
                        'description' => $this->translator->trans('Config.index.sections.quality.likes-per-view.description'),
                        'values' => array(
                            'likes_per_view_weighting'=>array('label'=>$this->translator->trans('Config.index.sections.quality.likes-per-view.weighting')),
                            'likes_per_view_best'=>array(
								'label'=>$this->translator->trans('Config.index.sections.quality.likes-per-view.best-label'),
								'hint'=>$this->translator->trans('Config.index.sections.quality.likes-per-view.best-hint')),
                            'likes_per_view_good'=>array(
								'label'=>$this->translator->trans('Config.index.sections.quality.likes-per-view.good-label'),
								'hint'=>$this->translator->trans('Config.index.sections.quality.likes-per-view.good-hint'))
                        )
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.quality.sign-off.title'),
                        'description' => $this->translator->trans('Config.index.sections.quality.sign-off.description'),
                        'values' => array(
                            'sign_off_weighting'=>array('label'=>$this->translator->trans('Config.index.sections.quality.sign-off.weighting')),
                        )
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.quality.branding.title'),
                        'description' => $this->translator->trans('Config.index.sections.quality.branding.description'),
                        'values' => array(
                            'branding_weighting'=>array('label'=>$this->translator->trans('Config.index.sections.quality.branding.weighting')),
                        )
                    ),
                    (object)array(
                        'title' => $this->translator->trans('Config.index.sections.quality.relevance.title'),
                        'description' => $this->translator->trans('Config.index.sections.quality.relevance.description'),
                        'values' => array(
                            'relevance_weighting'=>array('label'=>$this->translator->trans('Config.index.sections.quality.relevance.weighting')),
                            'facebook_relevance_percentage'=>array(
								'label'=>$this->translator->trans('Config.index.sections.quality.relevance.facebook-target'),
							),
                            'twitter_relevance_percentage'=>array(
								'label'=>$this->translator->trans('Config.index.sections.quality.relevance.twitter-target'),
							),
                            'sina_weibo_relevance_percentage'=>array(
								'label'=>$this->translator->trans('Config.index.sections.quality.relevance.sina-weibo-target'),
							),
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
		                $args->hint = str_replace(
							'[]',
							$section->title,
							$this->translator->trans('Config.index.sections.all.weighting-hint')
						);
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
                                    $args->error = $this->translator->trans('Config.index.numeric-error');
                                    $valid = false;
                                }
                                break;
                            case 'email':
                                if (!filter_var($args->value, FILTER_VALIDATE_EMAIL)) {
                                    $args->error = str_replace(
										'[]',
										'('.$args->value.')',
										$this->translator->trans('Config.index.email-error')
									);
                                    $valid = false;
                                }
                                break;
                        }
                    }
                }
			}
            $adapter = new Zend_File_Transfer('Http', false, array('ignoreNoFile'=>true));

            if ($adapter->isUploaded()) {
                $adapter->addFilter('Rename', array(
                        'target' => APPLICATION_PATH . '/../data/uploads/kpis.pdf',
                        'overwrite' => true
                    ));
                if (!$adapter->receive()) {
                    $messages = $adapter->getMessages();
                    foreach($messages as $message){
                        $this->flashMessage($message, 'error');
                    }
                } else {
                    $this->flashMessage($this->translator->trans('Config.index.upload-success'));
                }
            }

			if ($valid) {
                $args = array();
                foreach($values as $section){
                    foreach($section->kpis as $kpi){
                        foreach($kpi->values as $value) {
                            $args[$value->key] = $value->value;
                        }
                    }
                }
                self::setOptions($args);
                $this->flashMessage($this->translator->trans('Config.index.settings-saved'));
				$this->_helper->redirector->gotoSimple('');
			} else {
                $this->flashMessage($this->translator->trans('Config.index.invalid-values'), 'error');
			}
		}
		$this->view->values = $values;
	}
}