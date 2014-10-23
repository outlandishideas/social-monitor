<?php

class Model_Region extends Model_Campaign {

    const ICON_TYPE = 'icon-compass';

	public static $campaignType = '2';

    public function fromArray($data) {
        if (array_key_exists('presences', $data)) {
            unset($data['presences']);
        }
        parent::fromArray($data);
    }

    public function regionInfo(){
        return array(
            'pages' => (object)array(
                'title' => 'Facebook Pages',
                'value' => count($this->getFacebookPages()),
            ),
            'handles' => (object)array(
                'title' => 'Twitter Accounts',
                'value' => count($this->getTwitterAccounts()),
            ),
            'notes' => (object)array(
                'title' => 'Notes',
                'value' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum lacus eros, vulputate non mollis sed, egestas ut enim. Sed hendrerit dui nibh. Morbi bibendum feugiat nulla a tempus. Nam mattis egestas nisl a pulvinar. Curabitur non libero quis dolor ultricies tincidunt suscipit condimentum justo.' //$this->notes
            )
        );
    }

    public function getCountries()
    {
        return Model_Country::fetchAll('parent = ?', array($this->id));
    }

    function getPresenceIds($mapping = null) {
        if (!isset($this->presenceIds)) {
            if (isset($mapping[$this->id])) {
                $this->presenceIds = $mapping[$this->id];
            } else {
                $statement = $this->_db->prepare('
                    SELECT
                        presence_id
                    FROM
                        campaign_presences
                    WHERE
                        campaign_id IN (
                            SELECT `id` FROM `campaigns` WHERE `parent` = :cid
                        )
                ');
                $statement->execute(array(':cid'=>$this->id));
                $this->presenceIds = $statement->fetchAll(PDO::FETCH_COLUMN);
            }
        }
        return $this->presenceIds;
    }

    /**
     * calculates the digital population from the country population and internet penetration in that country (penetration presented as a percentage)
     * @return int
     */
    public function getDigitalPopulation()
    {
        $total = 0;
        foreach ($this->getCountries() as $country) {
            $total += $country->getDigitalPopulation();
        }
        return $total == 0 ? null : $total;
    }

    /**
     * turns country target audience into a percentage of country's digital pop
     * @return float
     */
    public function getDigitalPopulationHealth()
    {
        $audience = $this->getAudience();
        $pop = $this->getDigitalPopulation();
        if($pop && $audience)
        {
            return ( $audience / $pop) * 100;
        }
        else
        {
            return null;
        }
    }

    public function getAudience()
    {
        $audience = 0;
        foreach ($this->getCountries() as $country) {
            $audience += $country->audience;
        }
        return $audience;
    }

    public function getTargetAudience()
    {
        return $this->getAudience();
    }

    public function getPercentTargetAudience()
    {
        $target = $this->getTargetAudience();
        if ($target == 0) return null;
        $pop = 0;
        foreach ($this->getPresences() as $p) {
            $pop += $p->getPopularity();
        }
        return ($pop/$target);
    }

    public static function badgesData(){
        $badgeTypes = Badge_Factory::getBadgeNames();
        $keyedData = Badge_Factory::badgesData(true);

        // get all of the campaign-presence relationships for this type (country or group)
        /** @var PDO $db */
        $db = Zend_Registry::get('db')->getConnection();
        $stmt = $db->prepare('
            SELECT
                c.parent AS campaign_id,
                cp.presence_id
            FROM
                campaigns AS c
                LEFT OUTER JOIN campaign_presences AS cp ON cp.campaign_id = c.id
            WHERE
                c.parent IN (
                    SELECT
                        id
                    FROM
                        campaigns
                    WHERE
                        campaign_type = :campaign_type
                )
        ');
        $args = array(':campaign_type'=>self::campaignType());
        $stmt->execute($args);
        $mapping = $stmt->fetchAll(PDO::FETCH_OBJ);

        // calculate averages badge scores for each campaign
        $allCampaigns = array();
        $template = array('count'=>0);
        foreach ($badgeTypes as $badgeType) {
            $template[$badgeType] = 0;
        }
        foreach ($mapping as $row) {
            if (!isset($allCampaigns[$row->campaign_id])) {
                $campaign = (object)$template;
                $allCampaigns[$row->campaign_id] = $campaign;
            } else {
                $campaign = $allCampaigns[$row->campaign_id];
            }
            if (array_key_exists($row->presence_id, $keyedData)) {
                $campaign->count++;
                foreach ($badgeTypes as $badgeType) {
                    if ($badgeType != Model_Badge::BADGE_TYPE_TOTAL) {
                        $campaign->$badgeType += $keyedData[$row->presence_id]->$badgeType;
                    }
                }
            }
        }
        foreach ($allCampaigns as $campaign) {
            if ($campaign->count > 0) {
                foreach ($badgeTypes as $badgeType) {
                    $campaign->$badgeType /= $campaign->count;
                }
            }
        }

        // calculate the total scores for each campaign, and calculate ranks for all badge types
        foreach ($allCampaigns as $campaign) {
            Model_Badge::calculateTotalScore($campaign);
        }
        foreach ($badgeTypes as $badgeType) {
            Model_Badge::assignRanks($allCampaigns, $badgeType);
        }
        return $allCampaigns;
    }
}
