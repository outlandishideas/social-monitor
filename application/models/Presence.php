<?php

use Outlandish\SocialMonitor\Cache\KpiCacheEntry;
use Outlandish\SocialMonitor\Database\Database;
use Outlandish\SocialMonitor\Engagement\EngagementScore;
use Outlandish\SocialMonitor\Models\AccessToken;
use Outlandish\SocialMonitor\Models\PresenceMetadata;
use Outlandish\SocialMonitor\PresenceType\FacebookType;
use Outlandish\SocialMonitor\PresenceType\InstagramType;
use Outlandish\SocialMonitor\PresenceType\LinkedinType;
use Outlandish\SocialMonitor\PresenceType\PresenceType;
use Outlandish\SocialMonitor\PresenceType\SinaWeiboType;
use Outlandish\SocialMonitor\PresenceType\TwitterType;
use Outlandish\SocialMonitor\PresenceType\YoutubeType;

class Model_Presence
{
    protected $provider;
    protected $db;
    protected $metrics;
    protected static $badges = array();
    protected $kpiData = array();

    protected $presenceHistoryColumns = array(
        'popularity', 'klout_score', 'facebook_engagement', 'sina_weibo_engagement',
        'instagram_engagement', 'youtube_engagement', 'linkedin_engagement'
    );

    //these should be public to mimic existing Presence Class
    public $id;
    public $handle;
    /** @var PresenceType */
    public $type;
    public $name;
    public $label;
    public $uid;
    public $sign_off;
    public $branding;
    public $popularity;
    public $klout_id;
    public $klout_score;
    public $facebook_engagement;
    public $instagram_engagement;
    public $sina_weibo_engagement;
    public $youtube_engagement;
    public $linkedin_engagement;
    public $page_url;
    public $image_url;
    public $owner;
    public $last_updated;
    public $last_fetched;
    public $size;
    /**
     * @var Model_User|null
     */
    public $user;

    protected $accessToken;

    /**
     * Creates a new presence
     * Provider and metrics are passed in so that they can be mocked out for testing
     * @param Database $db
     * @param array $internals
     * @param Provider_Abstract $provider
     * @param array $metrics
     * @throws InvalidArgumentException
     */
    public function __construct(Database $db, array $internals, Provider_Abstract $provider, array $metrics = array())
    {
        $this->db = $db;
        $this->provider = $provider;
        $this->metrics = $metrics;

        if (!array_key_exists('id', $internals)) {
            throw new \InvalidArgumentException('Missing id for Presence');
        }
        if (!array_key_exists('handle', $internals)) {
            throw new \InvalidArgumentException('Missing handle for Presence');
        }
        $this->id = $internals['id'];
        $this->handle = $internals['handle'];
        $this->setType($internals['type']);
        $this->name = $internals['name'];
        $this->uid = $internals['uid'];
        $this->sign_off = $internals['sign_off'];
        $this->branding = $internals['branding'];
        $this->popularity = $internals['popularity'];
        $this->klout_score = $internals['klout_score'];
        $this->facebook_engagement = $internals['facebook_engagement'];
        $this->sina_weibo_engagement = $internals['sina_weibo_engagement'];
        if ($this->type === PresenceType::INSTAGRAM()) {
            $this->instagram_engagement = $internals['instagram_engagement'];
        } else if ($this->type === PresenceType::YOUTUBE()) {
            $this->youtube_engagement = $internals['instagram_engagement'];
        } else if ($this->type === PresenceType::LINKEDIN()) {
            $this->linkedin_engagement = $internals['instagram_engagement'];
        }
        $this->page_url = $internals['page_url'];
        $this->image_url = $internals['image_url'];
        $this->last_updated = $internals['last_updated'];
        $this->last_fetched = $internals['last_fetched'];
        $this->setUserFromId($internals['user_id']);
        if (array_key_exists('size', $internals)) $this->size = $internals['size'];
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Metric_Abstract[]
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    public function getLastFetched()
    {
        return $this->last_fetched;
    }

    public function getLastUpdated()
    {
        return $this->last_updated;
    }

    public function getHandle()
    {
        return $this->handle;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($typeName)
    {
        $this->type = PresenceType::get($typeName);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPageUrl()
    {
        return $this->page_url;
    }

    public function getImageUrl()
    {
        return $this->image_url;
    }

    public function getLabel()
    {
        return $this->getName() ?: $this->getHandle();
    }

    public function getUID()
    {
        return $this->uid;
    }

    public function getSignOff()
    {
        return $this->sign_off;
    }

    public function getBranding()
    {
        return $this->branding;
    }

    public function getKloutId()
    {
        return $this->klout_id;
    }

    public function getKloutScore()
    {
        return floatval($this->klout_score);
    }

    public function getPopularity()
    {
        return $this->popularity;
    }

    /**
     * @return mixed
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return array
     */
    public static function getSizes()
    {
        return array(
            0 => self::$translator->trans('models.presence.sizes.small'),
            1 => self::$translator->trans('models.presence.sizes.medium'),
            2 => self::$translator->trans('models.presence.sizes.large'),
            3 => self::$translator->trans('models.presence.sizes.extra-large')
        );
    }

    /**
     * @return mixed|null
     */
    public function getSizeLabel()
    {
        $size = $this->getSize();
        $sizes = $this->getSizes();
        if (array_key_exists($size, $sizes)) {
            return $sizes[$size];
        } else {
            return null;
        }

    }

    /**
     * @param mixed $size
     */
    public function setSize($size)
    {
        $sizes = $this->getSizes();
        if (array_key_exists($size, $sizes)) {
            $this->size = $size;
        }
    }

    /**
     * @return mixed
     */
    public function getFacebookEngagement()
    {
        return $this->facebook_engagement;
    }

    /**
     * @return mixed
     */
    public function getInstagramEngagement()
    {
        return $this->instagram_engagement;
    }

    /**
     * @return mixed
     */
    public function getSinaWeiboEngagement()
    {
        return $this->sina_weibo_engagement;
    }

    /**
     * @return mixed
     */
    public function getYoutubeEngagement()
    {
        return $this->youtube_engagement;
    }

    /**
     * @return mixed
     */
    public function getLinkedinEngagement()
    {
        return $this->linkedin_engagement;
    }

    public function getPresenceSign()
    {
        return $this->getType()->getSign();
    }

    public function isForTwitter()
    {
        return $this->getType()->getValue() == TwitterType::NAME;
    }

    public function isForFacebook()
    {
        return $this->getType()->getValue() == FacebookType::NAME;
    }

    public function isForSinaWeibo()
    {
        return $this->getType()->getValue() == SinaWeiboType::NAME;
    }

    public function isForInstagram()
    {
        return $this->getType()->getValue() == InstagramType::NAME;
    }

    public function isForYoutube()
    {
        return $this->getType()->getValue() == YoutubeType::NAME;
    }

    public function isForLinkedin()
    {
        return $this->getType()->getValue() == LinkedinType::NAME;
    }

    /**
     * @return Model_Campaign|null
     */
    public function getOwner()
    {
        if (!$this->owner) {
            Model_Base::setDb($this->db);
            $this->owner = Model_Campaign::fetchOwner($this->getId());
        }
        return $this->owner;
    }

    /**
     * This method gets the target audience figure from the owner of this presence
     *
     * If the presence has no owner the target returned is null.
     *
     * If the presence has an owner the presence will use the target audience figure provided by that owner. Additionally
     * the target audience will be a percentage of the owner's target with the actual percentage being based on the type
     * of presence in question. These values are defined in the config and are saved to the database.
     *
     * Furthermore, if the presence is a Group/SBU, then the target audience will be further split based on the size of the presence
     * in question. Large presences will take a larger proportion of the target audience from the owner, then small presences.
     *
     * @return int|null
     */
    public function getTargetAudience()
    {
        $target = null;
        $owner = $this->getOwner();
        if ($owner) {
            $target = $owner->getTargetAudience();
            if ($target > 0) {

                if ($owner instanceof Model_Group) {
                    $target = $this->updateTargetBasedOnSize($owner, $target);
                }

                $percent = 0;
                switch ($this->getType()->getValue()) {
                    case SinaWeiboType::NAME:
                        $percent = BaseController::getOption('sw_min');
                        break;
                    case FacebookType::NAME:
                        $percent = BaseController::getOption('fb_min');
                        break;
                    case TwitterType::NAME:
                        $percent = BaseController::getOption('tw_min');
                        break;
                    case InstagramType::NAME:
                        $percent = BaseController::getOption('ig_min');
                        break;
                    case YoutubeType::NAME:
                        $percent = BaseController::getOption('yt_min');
                        break;
                    case LinkedinType::NAME:
                        $percent = BaseController::getOption('in_min');
                        break;
                }
                $target *= $percent;
                $target /= 100;
            }
        }
        return $target;
    }

    public function getMetricValue($metric, \DateTime $startDate = null, \DateTime $endDate = null)
    {
        if ($metric instanceof Metric_Abstract) {
            $metric = $metric->getName();
        }
        $kpiData = $this->getKpiData(new KpiCacheEntry($startDate, $endDate));
        if ($kpiData && isset($kpiData[$metric])) {
            return $kpiData[$metric];
        }
        return null;
    }

    public function getTargetAudienceDate()
    {
        $score = $this->getMetricValue(Metric_PopularityTime::NAME);
        if ($score) {
            return new DateTime('now +' . round($score) . ' months');
        }
        return null;
    }

    public function getRatioRepliesToOthersPosts(\DateTime $startDate, \DateTime $endDate)
    {
        if (!$this->isForFacebook()) {
            return null;
        }

        $clauses = array(
            'presence_id = :pid',
            'created_time >= :start_date',
            'created_time <= :end_date'
        );

        $tableName = $this->provider->getTableName();
        $clauseString = implode(' AND ', $clauses);
        $sql = "
		SELECT t1.replies/t2.posts as replies_to_others_posts FROM
		(
			SELECT presence_id, COUNT(*) as replies
			FROM {$tableName}
			WHERE {$clauseString}
			AND in_response_to IS NOT NULL
			GROUP BY presence_id
		) as t1,
		(
			SELECT presence_id, COUNT(*) as posts
			FROM {$tableName}
			WHERE {$clauseString}
			AND posted_by_owner = 0
			GROUP BY presence_id
		) as t2";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':pid'			=> $this->id,
			':start_date'	=> $startDate->format('Y-m-d H:i:s'),
			':end_date'		=> $endDate->format('Y-m-d H:i:s')
		));
		return floatval($stmt->fetchColumn());
	}

	public function updateKpiData(KpiCacheEntry $cacheEntry, $useCache = false)
	{
		$insertStmt = $this->db->prepare("
                INSERT INTO `kpi_cache`
                    (`presence_id`, `metric`, `start_date`, `end_date`, `value`)
                VALUES
                    (:id, :metric, :start, :end, :value)
                ON DUPLICATE KEY UPDATE
                    `value` = VALUES(`value`)
            ");

		//start (re)calculation when data not available, or when indicated not to use cache
		$cachedValues = array();
		if ($useCache) {
			$selectStmt = $this->db->prepare(
				"SELECT metric, value FROM `kpi_cache`
				WHERE `presence_id` = :pid
				AND `start_date` = :start
				AND `end_date` = :end");
			$args = array(
				':pid' => $this->getId(),
				':start' => $cacheEntry->startString,
				':end' => $cacheEntry->endString
			);
			$selectStmt->execute($args);
			$cachedValues = $selectStmt->fetchAll(\PDO::FETCH_KEY_PAIR);
		}

		foreach($this->getMetrics() as $metric) {
			$metricName = $metric->getName();
			if(!array_key_exists($metricName, $cachedValues)) {
				$result = $metric->calculate($this, $cacheEntry->start, $cacheEntry->end);
                $insertStmt->execute(array(
					':id' => $this->getId(),
					':metric' => $metricName,
					':start' => $cacheEntry->startString,
					':end' => $cacheEntry->endString,
					':value' => $result
				));
				$cachedValues[$metricName] = $result;
			}
		}

		$this->kpiData[$cacheEntry->key] = $cachedValues;
	}

	public function getKpiData(KpiCacheEntry $cacheEntry = null, $useCache = true)
	{
		if (!$cacheEntry) {
			$cacheEntry = new KpiCacheEntry();
		}
		if(!array_key_exists($cacheEntry->key, $this->kpiData) || !$useCache) {
			$this->updateKpiData($cacheEntry, $useCache);
		}

		return $this->kpiData[$cacheEntry->key];
	}

    public function getHistoricData(\DateTime $start, \DateTime $end, $type = null)
    {
        return $this->provider->getHistoricData($this, $start, $end, $type);
    }

    public function getHistoricStream(\DateTime $start, \DateTime $end, $search = null, $order = null, $limit = null, $offset = null)
    {
        return $this->provider->getHistoricStream($this, $start, $end, $search, $order, $limit, $offset);
    }

    public function getHistoricStreamMeta(\DateTime $start, \DateTime $end, $ownPostsOnly = false)
    {
        return $this->provider->getHistoricStreamMeta($this, $start, $end, !!$ownPostsOnly);
    }

    public function fetch()
    {
        $count = $this->provider->fetchStatusData($this);
        $this->last_fetched = gmdate('Y-m-d H:i:s');
        return $count;
    }

    /**
     * method for updating a presence's info
     * if successful we also update the presence_history table with the latest info
     *
     * @return array|null
     */
    public function update()
    {
        $this->provider->update($this);
        $this->last_updated = gmdate('Y-m-d H:i:s');
    }

	/**
	 * @param PresenceMetadata $metadata
	 */
	public function updateFromMetadata($metadata)
	{
		$this->uid = $metadata->uid;
		$this->name = $metadata->name;
		$this->page_url = $metadata->page_url;
		$this->popularity = $metadata->popularity;
		$this->image_url = $metadata->image_url;
	}

    public function updateHistory()
    {
        $date = gmdate('Y-m-d H:i:s');
        //if the presence was updated, update presence_history
        $stmt = $this->db->prepare("
        	INSERT INTO `presence_history`
        	(`presence_id`, `datetime`, `type`, `value`)
        	VALUES
        	(:id, :datetime, :type, :value)
        	ON DUPLICATE KEY UPDATE
        	`value` = VALUES(`value`)
        ");
        foreach ($this->presenceHistoryColumns as $type) {
            $value = $this->$type;
            if (!is_null($value)) {
                $stmt->execute(array(
                    ':id' => $this->getId(),
                    ':datetime' => $date,
                    ':type' => $type,
                    ':value' => $value
                ));
            }
        }
    }

    public function getBadgeScores(DateTime $date, Enum_Period $range)
    {
        if (!$date || !$range) {
            throw new LogicException('date cannot be null');
        }
        $args = array(
            'id' => $this->getId(),
            'date' => $date->format('Y-m-d'),
            'range' => (string)$range
        );
        $stmt = $this->db->prepare("SELECT * FROM `badge_history` WHERE `presence_id` = :id AND `date` = :date AND `daterange` = :range");
        $stmt->execute($args);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if ($results) {
            return $results[0];
        }
        return null;
    }

    public function getBadges()
    {
        $badges = static::getAllBadges();
        if (isset($badges[$this->getId()])) {
            return $badges[$this->getId()];
        }
        return null;
    }

    public function getBadgeHistory(DateTime $start, DateTime $end)
    {
        return Badge_Factory::getAllCurrentData(Enum_Period::MONTH(), $start, $end, array($this->getId()));
    }

    public static function getAllBadges()
    {
        if (empty(static::$badges)) {
            $badgeData = Badge_Factory::badgesData();
            $badgeNames = Badge_Factory::getBadgeNames();

            $totalBadgeName = Badge_Total::NAME;
            $keyedData = array();
            foreach ($badgeData as $presenceData) {
                $presenceData->$totalBadgeName = 0;
                foreach ($badgeNames as $name) {
                    if ($name != $totalBadgeName) {
                        //add average to total score
                        $presenceData->$totalBadgeName += $presenceData->$name;
                    }
                }
                //divide the total score by the number of badges (-1 for the totalbadge)
                $presenceData->$totalBadgeName /= count($badgeNames) - 1;
                $presenceData->denominator = count($badgeData);
                $keyedData[$presenceData->presence_id] = (array)$presenceData;
            }

            Badge_Abstract::doRanking($keyedData, $totalBadgeName, $totalBadgeName . '_rank');

            static::$badges = $keyedData;
        }
        return static::$badges;
    }


    /**
     * DEPRECATED: Use getHistoricData()
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     */
    public function getPopularityData(DateTime $start, DateTime $end)
    {
        return $this->getHistoricData($start, $end, Metric_Popularity::NAME);
    }

    public function getActionsPerDayData(DateTime $start, DateTime $end)
    {
        return $this->provider->getHistoricStreamMeta($this, $start, $end);
    }

    /**
     * @param DateTime $start
     * @param DateTime $end
     * @return mixed
     */
    public function getResponseData(DateTime $start, DateTime $end)
    {
        return $this->provider->getResponseData($this, $start, $end);
    }

    public function save()
    {
        if (!$this->id) {
            return;
        }



        switch ($this->type) {
            case PresenceType::INSTAGRAM():
                $instagramEngagement = $this->instagram_engagement;
                break;
            case PresenceType::YOUTUBE():
                $instagramEngagement = $this->youtube_engagement;
                break;
            case PresenceType::LINKEDIN():
                $instagramEngagement = $this->linkedin_engagement;
                break;
            default:
                $instagramEngagement = null;
        }

        if ($this->getType()->getRequiresAccessToken() && $this->user) {
            $token = $this->user->getAccessToken($this->getType());
            if ($token && $token->isExpired()) {
                $this->user->deleteAccessToken($this->getType());
            }
        }

        $data = array(
            'type' => $this->getType()->getValue(),
            'handle' => $this->getHandle(),
            'uid' => $this->getUID(),
            'image_url' => $this->getImageUrl(),
            'name' => $this->getName(),
            'page_url' => $this->getPageUrl(),
            'popularity' => $this->getPopularity(),
            'klout_id' => $this->getKloutId(),
            'klout_score' => $this->getKloutScore(),
            'facebook_engagement' => $this->facebook_engagement,
            'sina_weibo_engagement' => $this->sina_weibo_engagement,
            'instagram_engagement' => $instagramEngagement,
            'last_updated' => $this->getLastUpdated(),
            'last_fetched' => $this->getLastFetched(),
            'sign_off' => $this->getSignOff(),
            'branding' => $this->getBranding(),
            'size' => $this->getSize(),
            'user_id' => $this->getUser() ? $this->getUser()->id : null
        );

        $query = 'UPDATE ' . Model_PresenceFactory::TABLE_PRESENCES . ' ' .
            'SET ' . implode('=?, ', array_keys($data)) . '=? ' .
            'WHERE id=?';
        //add id to fill last placeholder
        $data[] = $this->getId();

        $statement = $this->db->prepare($query);
        $statement->execute(array_values($data));
    }

    public function delete()
    {
        $tables = array(
            'badge_history',
            'kpi_cache',
            'campaign_presences',
            'presence_history',
            $this->provider->getTableName()
        );
        foreach ($tables as $table) {
            $this->db->prepare("DELETE FROM $table WHERE presence_id = :pid")
                ->execute(array(':pid' => $this->id));
        }
        $tableName = Model_PresenceFactory::TABLE_PRESENCES;
        $this->db->prepare("DELETE FROM {$tableName} WHERE id = ?")
            ->execute(array($this->id));
    }

    /**
     * Returns the access token for this presence if presence requires one for fetching
     *
     * Returns null if presence doesn't require an access token, or doesn't have one.
     * Returns an AccessToken by getting the User associated with this presence.
     *
     * @return null|AccessToken
     */
    public function getAccessToken()
    {
        if (!$this->getType()->getRequiresAccessToken() || !$this->getUser()) {
            return null;
        }

        if (!($this->accessToken instanceof AccessToken)) {
            $this->accessToken = $this->getUser()->getAccessToken($this->getType());
        }

        return $this->accessToken;

    }

    /**
     * @return Model_User|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param AccessToken $token
     */
    public function setAccessToken(AccessToken $token)
    {
        if ($this->getType()->getRequiresAccessToken()) {
            $this->accessToken = $token;
        }
    }

    /**
	 * Split the target of the owner by the size of the presence in question
	 *
	 * If Model_Group is the owner then we divide up the target population amongst the presences
	 * we use the size of the presence to calculate how much of the target population should be
	 * used by them.
	 *
	 * eg. SBU has two large / six medium / eight small presences. the two large presences take 50%/2
	 * of the target population as their target, the medium take 30%/6 of the target population as their target, etc.
	 *
	 * @param Model_Campaign $owner
	 * @param $target
	 * @return float
	 */
	private function updateTargetBasedOnSize($owner, $target)
	{
		$size = $this->getSize();
		// get the number of presences of the same size as $this
		$presenceCount = count($owner->getPresencesBySize($size));

		$sizePercent = BaseController::getOption("size_{$size}_presences");

		$target *= $sizePercent / 100 / $presenceCount;

		return $target;
	}

    public function getStatusStream(\DateTime $start, \DateTime $end, $search = null, $order = null, $limit = null, $offset = null)
    {
        return $this->provider->getStatusStream($this, $start, $end, $search, $order, $limit, $offset);
    }

    /**
     * @param DateTime $start the date from which to fetch historic data from (inclusive)
     * @param DateTime $end the date from which to fetch historic data to (inclusive)
     * @param array $types the types of data to be returned from the history table (if empty all types will be returned)
     * @return array
     */
    public function getHistoryData(\DateTime $start, \DateTime $end, $types = [])
    {
        return $this->provider->getHistoryData($this, $start, $end, $types);
    }

	/**
	 * Gets the symfony service IDs of the applicable charts for this presence
	 * @return array
	 */
    public function chartOptionNames()
    {
        switch ($this->getType()) {
            case InstagramType::NAME:
            case YoutubeType::NAME:
            case LinkedinType::NAME:
            case SinaWeiboType::NAME:
                return array(
                    'chart.compare',
                    'chart.popularity',
                    'chart.popularity-trend',
                    'chart.actionsPerDay'
                );
            case TwitterType::NAME:
			case FacebookType::NAME:
            default:
                return array(
                    'chart.compare',
                    'chart.popularity',
                    'chart.popularity-trend',
                    'chart.actionsPerDay',
                    'chart.response-time'
                );
        }
    }

    private function setUserFromId($userId)
    {
        $this->user = Model_User::fetchById($userId);
    }

	public function getRegion()
	{
		$parent = $this->getOwner();
		return $parent ? $parent->getRegion() : null;
	}

    /**
     *
     * @return null
     */
    public function testUpdate()
    {
        $this->provider->testAdapter($this);
    }

	/**
	 * Gets the raw engagement score, based on the appropriate engagement metric from this presence's list of metrics
	 * @param $monthlyAverage bool - set to true to get a weighted average over the past month
	 * @return EngagementScore
	 */
	public function getEngagementScore($monthlyAverage = false)
	{
		if ($this->isForTwitter()) {
			return new EngagementScore(self::$translator->trans('models.presence.engagement-score-name'), 'klout', $this->getKloutScore());
		}

		$engagementMetric = null;
		foreach ($this->metrics as $metric) {
			if ($metric instanceof Metric_AbstractEngagement) {
				$engagementMetric = $metric;
				break;
			}
		}

		if ($engagementMetric) {
			$type = $this->getType()->getValue();
			$title = $this->getType()->getTitle() . ' engagement score';
			$property = $type . '_engagement';
			$metricValue = $monthlyAverage ? $this->getMetricValue($property) : floatval($this->$property);
			$score = $engagementMetric->convertToScore($metricValue);
			return new EngagementScore($title, str_replace('_', '-', $type), $score);
		}

		return null;
	}

    public function getEngagementValue() {
        switch($this->getType()) {
            case PresenceType::FACEBOOK():
                return $this->facebook_engagement;
            case PresenceType::INSTAGRAM():
                return $this->instagram_engagement;
            case PresenceType::TWITTER():
                return $this->klout_score;
            case PresenceType::SINA_WEIBO():
                return $this->sina_weibo_engagement;
            case PresenceType::LINKEDIN():
                return $this->linkedin_engagement;
            case PresenceType::YOUTUBE():
                return $this->youtube_engagement;
        }
        return null;
    }

    public function getReachScore() {
        return $this->getBadgeScore('reach');
    }

    public function getQualityScore() {
        return $this->getBadgeScore('quality');
    }

    public function getBadgeScore($badgeName) {
        $date = new \Carbon\Carbon();
        $date->subDay();
        $scores = $this->getBadgeScores($date, Enum_Period::MONTH());
        if($scores && array_key_exists($badgeName,$scores)) {
            return $scores[$badgeName];
        } else {
            return 0;
        }
    }


}