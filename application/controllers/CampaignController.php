<?php

abstract class CampaignController extends GraphingController
{

	public function downloadAsCsv($name, $badgeData, $campaigns, $tableHeaders) {

        $data = array();

        $columns = array();
        $headers = array();
        foreach($tableHeaders as $key=>$csv){
            if ($csv){
		        $column = self::tableHeader($key, $csv);
		        $columns[] = $column;
                $headers[] = $column->title;
            }
        }

        $data[] = $headers;

	    /** @var Model_Campaign[] $campaigns */
        foreach($campaigns as $campaign){
            $row = array();
            $currentBadge = (array_key_exists($campaign->id, $badgeData)) ? $badgeData[$campaign->id] : null ;

            $kpiData = $campaign->getKpiAverages();
            foreach($columns as $column){
                $output = null;
                switch($column->name){
                    case('name'):
                        $output = $campaign->display_name;
                        break;
                    case('country'):
                        $output = $campaign->getName() . ' (' . $campaign->country . ')';
                        break;
                    case('total-rank'):
                        $output = (!empty($currentBadge)) ? (int)$currentBadge->total_rank : "N/A";
                        break;
                    case('total-score'):
                        $output = (!empty($currentBadge)) ? (float)round($currentBadge->total) : "N/A";
                        break;
                    case('current-audience'):
                        $output = number_format($campaign->popularity);
                        break;
                    case('target-audience'):
                        $output = number_format($campaign->getTargetAudience());
                        break;
                    case('presences'):
                        $presenceNames = array();
                        foreach($campaign->getPresences() as $presence){
                            $presenceNames[] = $presence->handle;
                        }
                        $output = implode(' ', $presenceNames);
                        break;
	                case 'digital-population':
						if ($campaign instanceof Model_Country) {
							$output = $campaign->getDigitalPopulation();
						}
		                break;
	                case 'digital-population-health':
						if ($campaign instanceof Model_Country) {
							$output = $campaign->getDigitalPopulationHealth();
						}
		                break;
                    default:
                        if( array_key_exists($column->name, $kpiData) ){
                            $output = $kpiData[$column->name];
                        }
                }
                $row[] = $output;
            }

            $data[] = $row;

        }

	    header("Content-type: text/csv");
	    header("Content-Disposition: attachment; filename=$name.csv");
	    header("Pragma: no-cache");
	    header("Expires: 0");

	    self::outputCSV($data);
		exit;
    }

    /**
     * method to produce CSV for download action from $data
     * @param $data
     */
    function outputCSV($data) {
        $output = fopen("php://output", "w");
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
    }

    /**
     * function to generate tableIndexHeaders for tbe campaign index pages
     * refer to tableHeader() in GraphingController
     * @return array
     */
    public static function tableIndexHeaders() {

        $return = array(
            'name' => true,
            'total-rank' => true,
            'total-score' => true,
            'target-audience' => true
        );

        foreach(self::tableMetrics() as $name => $title){
            $return[$name] = true;
        }
        $return['presences'] = true;
        $return['options'] = false;

        return $return;
    }

    public function managePresencesList()
    {
        $presences = array();
        foreach(NewModel_PresenceType::enumValues() as $type) {
            /** @var NewModel_PresenceType $type */
            $presences[] = array(
                'type' => $type->getValue(),
                'title' => "Available " . $type->getTitle() . " Presences",
                'presences' => NewModel_PresenceFactory::getPresencesByType($type),
                'sign' => $type->getSign()
            );
        }
        return $presences;
    }
}

