<?php

class NewModel_PresenceType extends NewModel_Enum
{
	const TWITTER 		= 'twitter';
	const FACEBOOK 	= 'facebook';
	const SINA_WEIBO 	= 'sina_weibo';

	public function getProvider(PDO $db) {
		switch ($this->value) {
			case self::SINA_WEIBO:
				return new NewModel_SinaWeiboProvider($db);
				break;
			case self::FACEBOOK:
				return new NewModel_FacebookProvider($db);
				break;
			case self::TWITTER:
				return new NewModel_TwitterProvider($db);
				break;
			default:
				throw new \LogicException("Not implemented yet.");
		}
	}

	public function getSign()
	{
		switch ($this->value) {
			case self::SINA_WEIBO:
				return "fa fa-weibo";
				break;
			case self::FACEBOOK:
				return "fa fa-facebook";
				break;
			case self::TWITTER:
				return "fa fa-twitter";
				break;
			default:
				throw new \LogicException("Not implemented yet.");
		}
	}

	public function getTitle()
	{
		switch ($this->value) {
			case self::SINA_WEIBO:
				return "Sina Weibo";
				break;
			case self::FACEBOOK:
				return "Facebook";
				break;
			case self::TWITTER:
				return "Twitter";
				break;
			default:
				throw new \LogicException("Not implemented yet.");
		}
	}

	public function getMetrics()
	{
		$ret = array();
		foreach ($this->getApplicableMetrics() as $metricName) {
			$ret[] = Metric_Factory::getMetric($metricName);
		}
		return $ret;
	}

	public function getBadges()
	{
		switch ($this->value) {
			case self::SINA_WEIBO:
				return array(
					Badge_Factory::getBadge(Badge_Reach::getName())
				);
				break;
			case self::FACEBOOK:
				return array();
				break;
			case self::TWITTER:
				return array();
				break;
			default:
				throw new \LogicException("Not implemented yet.");
		}
	}

	public function getRelevancePercentage()
	{
		switch ($this->value) {
			case self::SINA_WEIBO:
				return BaseController::getOption('sina_weibo_relevance_percentage');
				break;
			case self::FACEBOOK:
				return BaseController::getOption('facebook_relevance_percentage');
				break;
			case self::TWITTER:
				return BaseController::getOption('twitter_relevance_percentage');
				break;
		}
	}

	public function isMetricApplicable($metricName)
	{
		return in_array($metricName, $this->getApplicableMetrics());
	}

	public function getApplicableMetrics()
	{
		switch ($this->value) {
			case self::SINA_WEIBO:
				return array(
					Metric_ActionsPerDay::getName(),
					Metric_Branding::getName(),
					Metric_Popularity::getName(),
					Metric_PopularityTime::getName(),
					Metric_Relevance::getName(),
					Metric_SignOff::getName()
				);
				break;
			case self::FACEBOOK:
				return array(
					Metric_ActionsPerDay::getName(),
					Metric_Branding::getName(),
					Metric_Popularity::getName(),
					Metric_PopularityTime::getName(),
					Metric_Relevance::getName(),
					Metric_SignOff::getName(),
					Metric_FBEngagement::getName(),
					Metric_LikesPerPost::getName(),
					Metric_ResponseRatio::getName(),
					Metric_ResponseTime::getName()
				);
				break;
			case self::TWITTER:
				return array(
					Metric_ActionsPerDay::getName(),
					Metric_Branding::getName(),
					Metric_Popularity::getName(),
					Metric_PopularityTime::getName(),
					Metric_Relevance::getName(),
					Metric_SignOff::getName(),
					Metric_ResponseRatio::getName(),
					Metric_ResponseTime::getName(),
					Metric_Klout::getName()
				);
				break;
		}
	}
}