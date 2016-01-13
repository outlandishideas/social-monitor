<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Outlandish\SocialMonitor\Engagement\EngagementMetric;
use Outlandish\SocialMonitor\Adapter\FacebookAdapter;

class Enum_PresenceType extends Enum_Abstract
{
	const TWITTER 		= 'twitter';
	const FACEBOOK 	= 'facebook';
	const SINA_WEIBO 	= 'sina_weibo';
    const INSTAGRAM = 'instagram';
    const YOUTUBE = 'youtube';
    const LINKEDIN = 'linkedin';
    /**
     * @var ContainerInterface
     */
    protected static $container;

    public static function TWITTER() { return self::get(self::TWITTER); }
    public static function FACEBOOK() { return self::get(self::FACEBOOK); }
    public static function SINA_WEIBO() { return self::get(self::SINA_WEIBO); }
    public static function INSTAGRAM() { return self::get(self::INSTAGRAM); }
    public static function YOUTUBE() { return self::get(self::YOUTUBE); }
    public static function LINKEDIN() { return self::get(self::LINKEDIN); }

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
                    Metric_SignOff::getName(),
                    Metric_SinaWeiboEngagementLeveled::getName()
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
                    Metric_FBEngagementLeveled::getName(),
                    Metric_LikesPerPost::getName(),
                    Metric_ResponseRatio::getName(),
                    Metric_ResponseTimeNew::getName()
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
                    Metric_ResponseTimeNew::getName(),
                    Metric_Klout::getName()
                );
                break;
            case self::INSTAGRAM:
                $this->sign = "fa fa-instagram";
                $this->title = "Instagram";
                $this->relevancePercentage = BaseController::getOption('instagram_relevance_percentage');
                $this->applicableMetrics = array(
                    Metric_Popularity::getName(),
                    Metric_PopularityTime::getName(),
                    Metric_ActionsPerDay::getName(),
                    Metric_Branding::getName(),
                    Metric_SignOff::getName(),
                    Metric_LikesPerPost::getName(),
                    Metric_InstagramEngagementLeveled::getName()
                );
                break;
            case self::YOUTUBE:
                $this->sign = "fa fa-youtube";
                $this->title = "Youtube";
                $this->relevancePercentage = 10;
                $this->applicableMetrics = array(
                    Metric_Popularity::getName(),
                    Metric_PopularityTime::getName(),
                    Metric_Branding::getName(),
                    Metric_SignOff::getName(),
                    Metric_YoutubeEngagementLeveled::getName(),
                    Metric_LikesPerView::getName()
                );
                break;
            case self::LINKEDIN:
                $this->sign = "fa fa-linkedin";
                $this->title = "LinkedIn";
                $this->relevancePercentage = 10;
                $this->applicableMetrics = array(
                    Metric_Popularity::getName(),
                    Metric_PopularityTime::getName(),
                    Metric_Branding::getName(),
                    Metric_SignOff::getName(),
                    Metric_ActionsPerDay::getName(),
                    Metric_Relevance::getName(),
                    Metric_LinkedinEngagementLeveled::getName()
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


    public function getProvider() {
        $container = $this->getContainer();
        /** @var PDO $db */
        $db = $container->get('pdo');
		switch ($this->value) {
			case self::SINA_WEIBO:
				return new Provider_SinaWeibo($db);
				break;
			case self::FACEBOOK:
                /** @var EngagementMetric $engagementMetric */
                $engagementMetric = $container->get('facebook_engagement.weighted');
                /** @var FacebookAdapter $facebookAdapter */
                $facebookAdapter = $container->get('adapter.facebook');
                return new Provider_Facebook($db, $facebookAdapter, $engagementMetric);
				break;
			case self::TWITTER:
				return new Provider_Twitter($db, new \Outlandish\SocialMonitor\Adapter\TwitterAdapter());
				break;
            case self::INSTAGRAM:
                $instagramAdapter = $container->get('adapter.instagram');
                return new Provider_Instagram($db, $instagramAdapter);
            case self::YOUTUBE:
                $youtubeAdapter = $container->get('adapter.youtube');
                return new Provider_Youtube($db, $youtubeAdapter);
            case self::LINKEDIN:
                $adapter = $container->get('adapter.linkedin');
                return new Provider_Linkedin($db, $adapter);
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

    /**
     * Sets the container on this class to be used when instantiating a type;
     *
     * @param ContainerInterface $container
     */
    public static function setContainer(ContainerInterface $container)
    {
        static::$container = $container;
    }

    /**
     * Gets the container
     *
     * @return ContainerInterface
     * @throws Exception
     */
    private function getContainer()
    {
        $container = static::$container;
        if (!($container instanceof ContainerInterface)) {
            throw new Exception("Container has not been set");
        }

        return $container;
    }

}