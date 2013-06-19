<?php

class Model_Campaign extends Model_Base {
	protected static $tableName = 'campaigns';
	protected static $sortColumn = 'display_name';

	public function delete() {
		$this->_db->prepare('DELETE FROM campaign_presences WHERE campaign_id = :cid')->execute(array(':cid'=>$this->id));
		parent::delete();
	}

	function getPresenceIds() {
		if (!isset($this->presenceIds)) {
			$statement = $this->_db->prepare('SELECT presence_id FROM campaign_presences WHERE campaign_id = :cid');
			$statement->execute(array(':cid'=>$this->id));
			$this->presenceIds = $statement->fetchAll(PDO::FETCH_COLUMN);
		}
		return $this->presenceIds;
	}

	/**
	 * @return Model_Presence[]
	 */
	function getPresences() {
		if (!isset($this->presences)) {
			$ids = $this->getPresenceIds();
			if ($ids) {
				$clause = 'id IN (' . implode(',', $ids) . ')';
				$this->presences = Model_Presence::fetchAll($clause);
			} else {
				$this->presences = array();
			}
		}
		return $this->presences;
	}

	function assignPresences($ids) {
		$this->_db->prepare('DELETE FROM campaign_presences WHERE campaign_id = :cid')->execute(array(':cid'=>$this->id));
		if ($ids) {
			$toInsert = array();
			foreach ($ids as $id) {
				$toInsert[] = array('campaign_id'=>$this->id, 'presence_id'=>$id);
			}
			$this->insertData('campaign_presences', $toInsert);
		}
	}

	function getFacebookPages() {
		return array_filter($this->getPresences(), function($a) { return $a->type == 'facebook'; });
	}

	function getTwitterAccounts() {
		return array_filter($this->getPresences(), function($a) { return $a->type == 'twitter'; });
	}

	function getKpiData(){
		$return = array();

		// some KPIs need to be based on a date range. Use the last month's worth(?)
		$endDate = new DateTime();
		$startDate = new DateTime();
		$startDate->sub(DateInterval::createFromDateString('1 month'));

		foreach ($this->getPresences() as $presence) {
			$row = array('name'=>$presence->name, 'id'=>$presence->id);
			$row = array_merge($row, $presence->getKpiData($startDate, $endDate));
			$return[] = $row;
		}

		return $return;
	}

	function getKpiAverages() {
		if (!isset($this->kpiAverages)) {
			$scores = array();
			$metrics = Model_Presence::$ALL_METRICS;
			foreach ($metrics as $m) {
				$scores[$m] = array();
			}
			foreach ($this->getKpiData() as $p) {
				foreach ($metrics as $m) {
					if (array_key_exists($m, $p)) {
						$scores[$m][] = $p[$m];
					}
				}
			}
			$averages = array();
			foreach ($scores as $key=>$s) {
				$total = 0;
				$count = 0;
				foreach ($s as $value) {
					if ($value !== null) {
						$total += $value;
						$count++;
					}
				}
				$average = $count > 0 ? $total/$count : null;
				$averages[$key] = $average;
			}
			$this->kpiAverages = $averages;
		}

		return $this->kpiAverages;
	}

    /**
     * Returns an array of badge objects that contain the score, ranking and individual kpis for each badge
     * @return array
     */
    public function getBadges() {

        //go though list of badges and get the Badge object for each (with metrics)
        $badges = array();
        $countCampaigns = count(self::fetchAll());
        $class = get_called_class();
        $presences = $this->getPresences();
        foreach($presences as $p => $presence){
            $presences[$presence->handle] = $presence;
            unset($presences[$p]);
        }

        foreach(Model_Presence::ALL_BADGES() as $badge => $array){
            $badges[$badge] = new Model_Badge($presences, $badge, $class, $countCampaigns);
        }

        //foreach badge get the ranking
        foreach($badges as $badge){
            $badge->getRanking($this->id);
        }

        //add the Total badge, which has a score based the sum of other badge scores
        $totalBadge = new Model_Badge($badges, 'total', $class, $countCampaigns);
        $totalBadge->getRanking($this->id);

        $badges = array( 'total' => $totalBadge ) + $badges;


        return $badges;

    }


    /**
     * Returns the badges for a campaign by calculating the scores from the badges of each presence
     * @return array
     */
    public function getBadgesScores(){

        //get campaigns and foreach, presence get its badges
        $campaignBadges = array();
        $presences = $this->getPresences();

        //if no presences return an empty array
        if(empty($presences)) return array();

        foreach($presences as $presence){
            $presenceBadges[] = $presence->getBadgesScore();
        }

        //foreach presence and each badge add up its score
        $badges = array();
        foreach($presenceBadges as $presences){
            foreach($presences as $badge){
                $badges[$badge->type] += $badge->score;
            }
        }

        //go through the scores and convert each one into a badge object and divide the score by the number of presences
        foreach($badges as $s => $badge){
            $badges[$s] = (object)array(
                'score' => $badge/count($presences),
                'title' => ucfirst($s),
                'type' => $s
            );

            //get the ranking for each badge
            $this->badgeRanking($badges[$s]);
        }

        return $badges;
    }

    public function getScore($type){

        //get the presences of the campaign
        $presences = $this->getPresences();

        //for each presence get the score of badge $type and add it to the $score
        $score = 0;
        foreach($presences as $presence){
            $score += $presence->getScore($type);
        }

        //$divide the $score by the number of presences and return it
        $score /= count($presences);

        return $score;

    }

    public function badgeRanking(&$badge){

        //fetch all campaigns and add their count() as the rankingTotal
        $allCampaigns = self::fetchAll();
        $badge->rankingTotal = count($allCampaigns);

        //get the score of each campaign and add it to the scores array
        $scores = array();
        foreach($allCampaigns as $campaign){
            $score = $campaign->getScore($badge->type);
            $scores[] = (object)array(
                'id'=>$campaign->id,
                'score'=>$score
            );
        }

        //sort the scores array by the score of each campaign
        usort($scores, function($a, $b){
            if($a->score == $b->score) return 0;
            return ($a->score < $b->score) ? 1 : -1 ;
        });

        //go through each score to determine the ranking of the campaign in question
        $ranking = 0;
        for($i=0;$i<count($scores);$i++){

            //if its the first score, set the ranking to 1 (for 1st)
            //else if the score does not match the previous score increase the ranking
            if($i == 0) {
                $ranking++;
            } else {
                if($scores[$i]->score != $scores[$i-1]->score){
                    $ranking++;
                }
            }

            //if the current id matches this campaign's id break out of the loop and add the current ranking as this badges ranking
            if($scores[$i]->id == $this->id){
                $badge->ranking = $ranking;
                break;
            }
        }
    }

    public function getOverallKpi(){
        $ranking = count(self::fetchAll());
        $presences = $this->getPresences();
        $countPresences = count($presences);

        $return = array();

        foreach($presences as $presence){
            $badges = $presence->getOverallKpi();
            foreach($badges as $b=>$badge) {

                if(!isset($return[$b])) $return[$b] = (object)array();

                $return[$b]->score += $badge->score;

                if(!isset($return[$b]->presences)) $return[$b]->presences = array();

                $return[$b]->presences[$presence->label] = $badge;

                if(isset($badge->kpis)){
                    if(!isset($return[$b]->kpis)) $return[$b]->kpis = array();
                    foreach($badge->kpis as $k => $kpi){

                        if(!isset($return[$b]->kpis[$k])) $return[$b]->kpis[$k] = (object)array();

                        $return[$b]->kpis[$k]->score += $kpi->score;

                        $return[$b]->kpis[$k]->title = $kpi->title;
                    }
                }

            }
        }

        foreach($return as $b=>$badge){

            $return[$b]->title = ucfirst($b);
            $return[$b]->ranking = rand(1,$ranking);
            $return[$b]->rankingTotal = $ranking;
            $return[$b]->score /= $countPresences;

            if(isset($badge->kpis)){
                foreach($badge->kpis as $k=>$kpi){
                    $return[$b]->kpis[$k]->score = $kpi->score/$countPresences;
                }
            }
        }

        return $return;
    }

    public static function getBadgeData() {

        $date = new DateTime();
        $startDate = $date->format('Y-m-d');

        $types = array('reach','engagement','quality');

        $args[':start_date'] = $startDate . ' 00:00:00';
        $args[':end_date'] = $startDate . ' 23:59:59';

        $sql =
            'SELECT m.presence_id, m.type, m.value, m.datetime, c.campaign_id
            FROM campaign_presences as c
            INNER JOIN (
                SELECT p.id as presence_id, ph.type, ph.value, ph.datetime
                FROM presences as p
                LEFT JOIN presence_history as ph
                ON ph.presence_id = p.id
                WHERE ph.datetime >= :start_date
                AND ph.datetime <= :end_date
                AND ph.type IN ("'. implode('","',$types) .'")
                ORDER BY p.id, p.type, ph.datetime DESC
            ) as m
            ON m.presence_id = c.presence_id
            ORDER BY c.campaign_id';

        $stmt = Zend_Registry::get('db')->prepare($sql);
        $stmt->execute($args);
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        if(empty($data)){

            $presences = Model_Presence::fetchAll();
            $setHistoryArgs = array();

            foreach($presences as $presence){
                foreach(Model_Presence::ALL_BADGES() as $badgeType => $metrics){
                    $setHistoryArgs[] = (array)$presence->calculateMetrics($badgeType, $metrics, $date->format('Y-m-d H-i-s'));
                }
            }

            Model_Base::insertData('presence_history', $setHistoryArgs);

            $data = self::getBadgeData();

        }

        return $data;
    }

    public function organizeBadgeData($data){

        $badgeData = array();

        foreach($data as $row){

            if(!isset($badgeData[$row->type])) $badgeData[$row->type] = (object)array('score'=>array(), 'rank'=>array());

            if(!isset($badgeData[$row->type]->score[$row->campaign_id])) $badgeData[$row->type]->score[$row->campaign_id] = array();

            if(!isset($badgeData[$row->type]->score[$row->campaign_id][$row->presence_id])) $badgeData[$row->type]->score[$row->campaign_id][$row->presence_id] = $row->value;

        }

        foreach($badgeData as $b => $badge){

                $badge->score = array_map(function($a){
                    $sum = array_sum($a);
                    $count = count($a);
                    return $sum/$count;
                },$badge->score);

        }

        return $badgeData;
    }
}
