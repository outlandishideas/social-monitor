<?php

namespace Outlandish\SocialMonitor\PresenceType;

use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class PresenceType
{
    /**
     * @var ContainerInterface
     */
    protected static $container;
	protected static $presenceTypes = null;

    public static function TWITTER() { return self::get(TwitterType::NAME); }
    public static function FACEBOOK() { return self::get(FacebookType::NAME); }
    public static function SINA_WEIBO() { return self::get(SinaWeiboType::NAME); }
    public static function INSTAGRAM() { return self::get(InstagramType::NAME); }
    public static function YOUTUBE() { return self::get(YoutubeType::NAME); }
    public static function LINKEDIN() { return self::get(LinkedinType::NAME); }

    protected $value = '';
    protected $sign = '';
    protected $title = '';
    protected $relevancePercentage = 0;
    /** @var \Metric_Abstract */
    protected $engagementMetric;
    /** @var \Metric_Abstract[] */
    protected $metrics = array();
	/** @var \Provider_Abstract */
    protected $provider;
	/** @var bool */
    protected $requiresAccessToken;

	/**
	 * @param string $value
	 * @param string $sign
	 * @param string $title
	 * @param int|string $relevancePercentage
	 * @param \Metric_Abstract $engagementMetric
	 * @param \Metric_Abstract[] $metrics
	 * @param bool $requiresAccessToken
	 * @throws \Exception
	 */
	public function __construct($value, $sign, $title, $relevancePercentage, $engagementMetric, $metrics, $requiresAccessToken = false)
	{
		$this->value = $value;
		$this->sign = $sign;
		$this->title = $title;
		$this->relevancePercentage = is_string($relevancePercentage) ? floatval(\BaseController::getOption($relevancePercentage)) : $relevancePercentage;
        if (!$this->relevancePercentage) {
            $this->relevancePercentage = 60;
        }
		$this->engagementMetric = $engagementMetric;
		$this->metrics = array();
		foreach ($metrics as $metric) {
			$this->metrics[$metric->getName()] = $metric;
		}
		$this->requiresAccessToken = $requiresAccessToken;
	}

	public function setProvider($provider)
	{
		$this->provider = $provider;
	}

	/**
	 * @param string $value
	 * @return PresenceType
	 */
	public static function get($value)
	{
		$all = self::getAll();
		if (array_key_exists($value, $all)) {
			return $all[$value];
		}
		throw new \LogicException("Not implemented yet: " . $value);
	}

	/**
	 * @return PresenceType[]
	 */
	public static function getAll()
	{
		if (!self::$presenceTypes) {
			$container = self::getContainer();
			/** @var PresenceType[] $presenceTypes */
			$presenceTypes = array(
				$container->get('presence-type.twitter'),
				$container->get('presence-type.facebook'),
				$container->get('presence-type.instagram'),
				$container->get('presence-type.youtube'),
				$container->get('presence-type.linkedin'),
				$container->get('presence-type.sina-weibo'),
			);
			self::$presenceTypes = array();
			foreach ($presenceTypes as $type) {
				self::$presenceTypes[$type->getValue()] = $type;
			}
		}
		return self::$presenceTypes;
	}

    public function getProvider() {
		return $this->provider;
	}

	public function getValue()
	{
        return $this->value;
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

	public function getRelevancePercentage()
	{
        return $this->relevancePercentage;
	}

	public function isMetricApplicable($metric)
	{
        if ($metric instanceof \Metric_Abstract) {
            $metric = $metric->getName();
        }
		return array_key_exists($metric, $this->metrics);
	}

    public function getEngagementMetric()
    {
        return $this->engagementMetric;
    }

    public function getRequiresAccessToken()
    {
        return $this->requiresAccessToken;
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
     * @return \Metric_Abstract|null
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
     * @throws \Exception
     */
    private static function getContainer()
    {
        $container = static::$container;
        if (!($container instanceof ContainerInterface)) {
            throw new \Exception("Container has not been set");
        }

        return $container;
    }

}