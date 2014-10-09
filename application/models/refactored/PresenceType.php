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
				return "fa-weibo";
				break;
			case self::FACEBOOK:
				return "fa-facebook";
				break;
			case self::TWITTER:
				return "fa-twitter";
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
		switch ($this->value) {
			case self::SINA_WEIBO:
				return array(
					Metric_Factory::getMetric(Metric_Popularity::getName()),
					Metric_Factory::getMetric(Metric_PopularityTime::getName()),
					Metric_Factory::getMetric(Metric_SignOff::getName()),
					Metric_Factory::getMetric(Metric_Branding::getName()),
					Metric_Factory::getMetric(Metric_Relevance::getName()),
					Metric_Factory::getMetric(Metric_ActionsPerDay::getName())
				);
				break;
			case self::FACEBOOK:
				return array(
					Metric_Factory::getMetric(Metric_Popularity::getName()),
					Metric_Factory::getMetric(Metric_PopularityTime::getName()),
					Metric_Factory::getMetric(Metric_SignOff::getName()),
					Metric_Factory::getMetric(Metric_Branding::getName()),
					Metric_Factory::getMetric(Metric_Relevance::getName()),
					Metric_Factory::getMetric(Metric_ActionsPerDay::getName())
				);
				break;
			case self::TWITTER:
				return array(
					Metric_Factory::getMetric(Metric_Popularity::getName()),
					Metric_Factory::getMetric(Metric_PopularityTime::getName()),
					Metric_Factory::getMetric(Metric_SignOff::getName()),
					Metric_Factory::getMetric(Metric_Branding::getName()),
					Metric_Factory::getMetric(Metric_Relevance::getName()),
					Metric_Factory::getMetric(Metric_ActionsPerDay::getName())
				);
				break;
			default:
				throw new \LogicException("Not implemented yet.");
		}
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
}