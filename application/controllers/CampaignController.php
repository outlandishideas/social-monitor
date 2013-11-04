<?php

abstract class CampaignController extends GraphingController
{

    public function downloadAction() {
        /*if (userHasNoPermissions) {
            $this->view->msg = 'This file cannot be downloaded!';
            $this->_forward('error', 'download');
            return FALSE;
        }*/

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=presence_index.csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        $data = array();

        $headers = array();
        foreach(static::tableIndexHeaders() as $header){
            if(isset($header->csv)){
                $headers[] = $header->title;
            }
        }

        $data[] = $headers;

        $badgeData = static::getAllBadgeData();
        $campaigns = static::getAllCampaigns();

        foreach($campaigns as $campaign){
            $row = array();
            $currentBadge = (array_key_exists($campaign->id, $badgeData)) ? $badgeData[$campaign->id] : null ;

            $kpiData = $campaign->getKpiAverages();
            foreach(self::tableIndexHeaders() as $header){
                $output = null;
                if(isset($header->csv)){
                    switch($header->name){
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
                        default:
                            if( array_key_exists($header->name, $kpiData) ){
                                $output = $kpiData[$header->name];
                            }
                    }
                    $row[] = $output;
                }
            }

            $data[] = $row;

        }

        self::outputCSV($data);


        // disable layout and view
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }

    function outputCSV($data) {
        $output = fopen("php://output", "w");
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
    }

    public function getAllBadgeData(){
        return array();
    }

    public function getAllCampaigns(){
        return array();
    }

    public static function tableIndexHeaders() {

        $return = array(
            (object)array(
                'name' => 'name',
                'sort' => 'auto',
                'title' => 'Name',
                'csv' => true
            ),
            (object)array(
                'name' => 'total-rank',
                'sort' => 'numeric',
                'title' => 'Global Rank',
                'csv' => true
            ),
            (object)array(
                'name' => 'total-score',
                'sort' => 'numeric',
                'title' => 'Overall Score',
                'csv' => true
            ),
            (object)array(
                'name' => 'target-audience',
                'sort' => 'fuzzy-numeric',
                'title' => 'Target Audience',
                'csv' => true
            )
        );

        foreach(self::tableMetrics() as $name => $title){
            $return[] = (object)array(
                'name' => $name,
                'sort' => 'traffic-light',
                'width' => '150px',
                'title' => $title,
                'csv' => true
            );
        }

        $return[] = (object)array(
            'name' => 'presences',
            'title' => 'Presences',
            'csv' => true
        );

        $return[] = (object)array(
            'name' => 'options',
            'width' => '150px'
        );

        return $return;
    }
}

