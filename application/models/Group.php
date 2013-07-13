<?php

class Model_Group extends Model_Campaign {

    const ICON_TYPE = 'icon-th-large';

    protected function fetch($clause = null, $args = array()) {
		if ($clause) {
			$clause .= ' AND ';
		}
		$clause .= ' is_country = 0';
		return parent::fetch($clause, $args);
	}

    public function fromArray($data) {
        if (array_key_exists('presences', $data)) {
            unset($data['presences']);
        }
        parent::fromArray($data);
    }

    public function groupInfo(){
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


    public static function generateGroupData($dayRange){
        $endDate = new DateTime('now');
        $startDate = new DateTime("now -$dayRange days");

        //todo include week data in the data that we send out as json
        $data = Model_Badge::getAllData('month', $startDate, $endDate);

        $campaignIds = array();
        foreach ($data as $row) {
            $campaignIds[$row->campaign_id] = 1;
        }

        $badgeTypes = Model_Badge::$ALL_BADGE_TYPES;

        //set up an object for each country
        $campaigns = array();
        foreach(array_keys($campaignIds) as $id){
            /** @var Model_Group $group */
            $group = Model_Group::fetchById($id);
            if ($group) {
                $row = (object)array(
                    'group' => $group->country,
                    'name' => $group->display_name,
                    'id'=>intval($id),
                    //'targetAudience' => $group->getTargetAudience(),
                    'presenceCount' => count($group->presences),
                    'presences' => array()
                );

                //foreach badge, add a place to put scores to the country object
                foreach ($badgeTypes as $type) {
                    $row->$type = array();
//		            for($i=1; $i<=$dayRange; $i++) {
//			            $row->$type[
//		            }
                }
                $campaigns[$id] = $row;
            }
        }

        //now that we have country objects set up, go though the data and assign it to the appropriate object
        foreach($data as $row){
            if(array_key_exists($row->campaign_id, $campaigns)){

                //calculate the number of days since this row of data was created
                $rowDate = new DateTime($row->date);
                $rowDiff = $rowDate->diff($endDate);
                //turn it around so that the most recent data is the has the highest score
                //this is because jquery slider has a value going 0-30 (left to right) and we want time to go in reverse
                $days = $dayRange-$rowDiff->days;

                //each metric property in a country object is an array of days (0-30)
                //if the day doesn't already exist, create it with a 0 value
                foreach ($badgeTypes as $type) {
                    if ($type != Model_Badge::BADGE_TYPE_TOTAL) {
                        if(!isset($campaigns[$row->campaign_id]->{$type}[$days])) {
                            $campaigns[$row->campaign_id]->{$type}[$days] = 0;
                        }
                        //add the badge values for this row to the appropriate country model
                        //multiple values per country, because countries have more than one presence
                        $campaigns[$row->campaign_id]->{$type}[$days] += $row->$type;
                    }
                }
            }
        }

        //calculate the total scores for each day for each country object
        foreach($campaigns as $campaign){

            //setup the total score
            $campaign->total = array();

            //go though each day in each badge in each country and convert the score into an score/label object for geochart
            foreach($badgeTypes as $type){
                if ($type != Model_Badge::BADGE_TYPE_TOTAL) {
                    foreach($campaign->$type as $day => $value){
                        $value /= $campaign->presenceCount;
                        $object = (object)array('score'=>$value, 'label'=>round($value).'%');
                        $campaign->{$type}[$day] = $object;
                        if(!isset($campaign->total[$day])) {
                            $campaign->total[$day] = 0;
                        }
                        $campaign->total[$day] += $value;
                    }
                }
            }

            foreach($campaign->total as $v => $value){
                $value /= (count($badgeTypes)-1);
                $object = (object)array('score'=>$value, 'label'=>round($value).'%');
                $campaign->total[$v] = $object;
            }

        }
        return $campaigns;

    }
}
