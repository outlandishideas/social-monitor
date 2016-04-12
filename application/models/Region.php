<?php

class Model_Region extends Model_Campaign {

    const ICON_TYPE = 'icon-globe';

	public static $campaignType = '2';

    public function setDisplay_name($name){
        $this->setProperty('display_name', $name);
    }

    public function fromArray($data) {
        if (array_key_exists('presences', $data)) {
            unset($data['presences']);
        }
        parent::fromArray($data);
    }

    public function getBadgeHistory(DateTime $start, DateTime $end)
    {
        $ret = array();
        foreach ($this->getCountries() as $c) {
            $data = $c->getBadgeHistory($start, $end);
            $temp = array();
            foreach ($data as $d) {
                if (!array_key_exists($d->date, $temp)) $temp[$d->date] = array();
                $temp[$d->date][] = $d;
            }
            foreach ($temp as $date => $scores) {
                $reach = 0;
                $engagement = 0;
                $quality = 0;
                foreach ($scores as $s) {
                    $reach += $s->reach;
                    $engagement += $s->engagement;
                    $quality += $s->quality;
                }
                $reach /= count($scores);
                $engagement /= count($scores);
                $quality /= count($scores);

                $ret[] = (object) array(
                    'daterange' => $s->daterange,
                    'reach' => round($reach),
                    'engagement' => round($engagement),
                    'quality' => round($quality),
                    'date' => $date,
                    'campaign_id' => $c->id
                );
            }
        }
        return $ret;
    }

    /**
     * @return Model_Country[]
     */
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
                $this->presenceIds = $statement->fetchAll(\PDO::FETCH_COLUMN);
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
        if ($target == 0) {
            return null;
        }
        $pop = 0;
        foreach ($this->getPresences() as $p) {
            $pop += $p->getPopularity();
        }
        return ($pop/$target);
    }

    protected static function mappingSql()
    {
        return '
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
        ';
    }

    public function assignCountries($countryIds)
    {
        $db = $this->_db;

		// remove any existing ones
        $stmt = $db->prepare("UPDATE campaigns SET parent = 0 WHERE parent = :id");
		$stmt->execute(array(':id' => $this->id));

		// add the new ones
        $stmt = $db->prepare("UPDATE campaigns SET parent = :id WHERE id = :cid");
        foreach($countryIds as $cid) {
            $stmt->execute(array(':id' => $this->id, ':cid' => $cid));
        }
    }
}
