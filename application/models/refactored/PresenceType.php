<?php

class NewModel_PresenceType extends NewModel_Enum
{
	const TWITTER 		= 'twitter';
	const FACEBOOK 	= 'facebook';
	const SINA_WEIBO 	= 'sina_weibo';

    public static function TWITTER() { return self::get(self::TWITTER); }
    public static function FACEBOOK() { return self::get(self::FACEBOOK); }
    public static function SINA_WEIBO() { return self::get(self::SINA_WEIBO); }

    protected $sign = '';
    protected $title = '';
    protected $relevancePercentage = 0;
    protected $applicableMetrics = array();
    protected $badges = array();
    /** @var Metric_Abstract[] */
    protected $metrics = array();

    protected function __construct($value)
    {
        parent::__construct($value);
        switch ($value) {
            case self::SINA_WEIBO:
                $this->sign = "fa fa-weibo";
                $this->title = "Sina Weibo";
                $this->relevancePercentage = BaseController::getOption('sina_weibo_relevance_percentage');
                $this->applicableMetrics = array(
                    Metric_ActionsPerDay::getName(),
                    Metric_Branding::getName(),
                    Metric_Popularity::getName(),
                    Metric_PopularityTime::getName(),
                    Metric_Relevance::getName(),
                    Metric_SignOff::getName()
                );
                $this->badges = array(
                    Badge_Reach::getInstance()
                );
                break;
            case self::FACEBOOK:
                $this->sign = "fa fa-facebook";
                $this->title = 'Facebook';
                $this->relevancePercentage = BaseController::getOption('facebook_relevance_percentage');
                $this->applicableMetrics = array(
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
                $this->sign = "fa fa-twitter";
                $this->title = 'Twitter';
                $this->relevancePercentage = BaseController::getOption('twitter_relevance_percentage');
                $this->applicableMetrics = array(
                    Metric_ActionsPerDay::getName(),
                    Metric_Branding::getName(),
                    Metric_Popularity::getName(),
                    Metric_PopularityTime::getName(),
                    Metric_Relevance::getName(),
                    Metric_SignOff::getName(),
                    Metric_ResponseTime::getName(),
                    Metric_Klout::getName()
                );
                break;
            default:
                throw new \LogicException("Not implemented yet.");
        }
        $this->relevancePercentage = floatval($this->relevancePercentage);
        if (!$this->relevancePercentage) {
            $this->relevancePercentage = 60;
        }
        $metrics = array();
        foreach ($this->applicableMetrics as $metricName) {
            $metrics[$metricName] = Metric_Factory::getMetric($metricName);
        }
        $this->metrics = $metrics;
    }


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
        return $this->sign;
	}

	public function getTitle()
	{
        return $this->title;
	}

	public function getMetrics()
	{
        return $this->metrics;
	}

	public function getBadges()
	{
        return $this->badges;
	}

	public function getRelevancePercentage()
	{
        return $this->relevancePercentage;
	}

	public function isMetricApplicable($metric)
	{
        if ($metric instanceof Metric_Abstract) {
            $metric = $metric->getName();
        }
		return in_array($metric, $this->getApplicableMetrics());
	}

	public function getApplicableMetrics()
	{
        return $this->applicableMetrics;
	}

    /**
     * Gets the metrics from the given list that are applicable to this presence type
     * @param $metrics
     * @return array
     */
    public function filterMetrics($metrics) {
        $filtered = array();
        foreach ($this->metrics as $metric) {
            if (in_array($metric->getName(), $metrics)) {
                $filtered[] = $metric;
            }
        }
        return $filtered;
    }

    /**
     * Gets the metric, but only if applicable to this presence type
     * @param $metric
     * @return Metric_Abstract|null
     */
    public function getMetric($metric) {
        if (isset($this->metrics[$metric])) {
            return $this->metrics[$metric];
        }
        return null;
    }
}