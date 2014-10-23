<?php

class NewModel_PresenceType extends NewModel_Enum
{
	const TWITTER 		= 'twitter';
	const FACEBOOK 	= 'facebook';
	const SINA_WEIBO 	= 'sina_weibo';

    public static function TWITTER() { return self::get(self::TWITTER); }
    public static function FACEBOOK() { return self::get(self::FACEBOOK); }
    public static function SINA_WEIBO() { return self::get(self::SINA_WEIBO); }

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
			case self::FACEBOOK:
				return "fa fa-facebook";
			case self::TWITTER:
				return "fa fa-twitter";
			default:
				throw new \LogicException("Not implemented yet.");
		}
	}

	public function getTitle()
	{
		switch ($this->value) {
			case self::SINA_WEIBO:
				return "Sina Weibo";
			case self::FACEBOOK:
				return "Facebook";
			case self::TWITTER:
				return "Twitter";
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
			case self::FACEBOOK:
				return array();
			case self::TWITTER:
				return array();
			default:
				throw new \LogicException("Not implemented yet.");
		}
	}

	public function getRelevancePercentage()
	{
		switch ($this->value) {
			case self::SINA_WEIBO:
				return BaseController::getOption('sina_weibo_relevance_percentage');
			case self::FACEBOOK:
				return BaseController::getOption('facebook_relevance_percentage');
			case self::TWITTER:
				return BaseController::getOption('twitter_relevance_percentage');
            default:
                throw new \LogicException("Not implemented yet.");
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
            default:
                throw new \LogicException("Not implemented yet.");
		}
	}
}