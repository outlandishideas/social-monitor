<?php

use mikehaertl\wkhtmlto\Pdf;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\Report\ReportablePresence;
use Outlandish\SocialMonitor\Report\ReportGenerator;
use Outlandish\SocialMonitor\TableIndex\Header\ActionsPerDay;
use Outlandish\SocialMonitor\TableIndex\Header\Branding;
use Outlandish\SocialMonitor\TableIndex\Header\CurrentAudience;
use Outlandish\SocialMonitor\TableIndex\Header\Handle;
use Outlandish\SocialMonitor\TableIndex\Header\Header;
use Outlandish\SocialMonitor\TableIndex\Header\Options;
use Outlandish\SocialMonitor\TableIndex\Header\ResponseTime;
use Outlandish\SocialMonitor\TableIndex\Header\SignOff;
use Outlandish\SocialMonitor\TableIndex\Header\TargetAudience;
use Outlandish\SocialMonitor\TableIndex\Header\TotalRank;
use Outlandish\SocialMonitor\TableIndex\Header\TotalScore;
use Outlandish\SocialMonitor\TableIndex\TableIndex;

class StatusesController extends GraphingController
{
    protected static $publicActions = array();
    protected $providers = array();

    public function init()
    {
        parent::init();
        foreach (Enum_PresenceType::enumValues() as $type) {
            $this->providers[] = $type->getProvider();
        }
    }

    /**
     * Statuses main page
     * @user-level user
     */
    public function indexAction()
    {
        Model_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());
        $presences = Model_PresenceFactory::getPresences();

        $this->view->title = 'Statuses';
        $this->view->presences = $presences;
        $this->view->sortCol = Handle::getName();
        $this->view->queryOptions = [
            ['name' => 'type', 'label' => 'Social Media', 'options' => Enum_PresenceType::enumValues()],
            ['name' => 'country', 'label' => 'Countries', 'options' => Model_Country::fetchAll()],
            ['name' => 'region', 'label' => 'Regions', 'options' => Model_Region::fetchAll()],
            ['name' => 'sbu', 'label' => 'SBUs', 'options' => Model_Group::fetchAll()],
            ['name' => 'sort', 'label' => 'Sort', 'options' =>
                [
                    ['value' => 'date', 'title' => 'Date'],
                    ['value' => 'engagement', 'title' => 'Engagement']
                ]
            ]
        ];
    }

    /**
     * AJAX function for fetching the statuses
     */
    public function listAction()
    {
        Zend_Session::writeClose(); //release session on long running actions

        $dateRange = $this->getRequestDateRange();
        if (!$dateRange) {
            $this->apiError('Missing date range');
        }

        /** @var Model_Presence $presence */
        $format = $this->_request->getParam('format');
        $types = null;
        $presences = array();

        /** If id is set, we only want statuses for this presence */
        if ($this->_request->getParam('id')) {
            $presences = [Model_PresenceFactory::getPresenceById($this->_request->getParam('id'))];
        } else {
            /** Otherwise we build an array of presence Ids */

            $countryParamString = $this->_request->getParam('country');
            $regionParamString = $this->_request->getParam('region');
            $sbuParamString = $this->_request->getParam('sbu');
            $typeParamString = $this->_request->getParam('type');

            /** Update $presences to include all presences in specified countries */
            if (isset($countryParamString)) {
                $countryParams = explode(',', $countryParamString);
            } else {
                $countries = Model_Country::fetchAll();
                $countryParams = array_map(function ($c) {
                    return $c->id;
                }, $countries);

            }

            foreach ($countryParams as $cid) {
                if ($cid) {
                    $countryPresences = Model_PresenceFactory::getPresencesByCampaign($cid);
                    $presences = array_merge($presences, $countryPresences);
                }
            }

            /** Add presences in specified regions */
            if (isset($regionParamString)) {
                $regionParams = explode(',', $regionParamString);
            } else {
                $regions = Model_Region::fetchAll();
                $regionParams = array_map(function ($c) {
                    return $c->id;
                }, $regions);
            }
            foreach ($regionParams as $rid) {
                if ($rid) {
                    $countries = Model_Country::getCountriesByRegion($rid);
                    foreach ($countries as $c) {
                        $countryPresences = Model_PresenceFactory::getPresencesByCampaign($c->id);
                        $presences = array_merge($presences, $countryPresences);
                    }
                }
            }

            /** Add presences in SBUs */
            if (isset($sbuParamString)) {
                $sbuParams = explode(',', $sbuParamString);
            } else {
                $sbus = Model_Group::fetchAll();
                $sbuParams = array_map(function ($c) {
                    return $c->id;
                }, $sbus);
            }
            foreach ($sbuParams as $sid) {
                if ($sid) {
                    $sbuPresences = Model_PresenceFactory::getPresencesByCampaign($sid);
                    $presences = array_merge($presences, $sbuPresences);
                }
            }

            /** Filter presences by type */
            if (isset($typeParamString)) {
                $types = array();
                if ($typeParamString) {
                    $typeParams = explode(',', $typeParamString);
                    foreach ($typeParams as $type) {
                        $types[] = Enum_PresenceType::get($type);
                    }
                }
            }
        }

        $limit = $this->getRequestLimit();

        $streams = $this->getStatusStream(
            $presences,
            $types,
            $dateRange[0],
            $dateRange[1],
            $this->getRequestSearchQuery(),
            $this->getRequestOrdering(),
            $limit,
            $this->getRequestOffset()
        );

        $tableData = array();
        $count = 0;
        foreach ($streams as $data) {
            $stream = $data->stream;

            if (!$stream) {
                continue;
            }

            $count = $count + $data->total;
            $tableData = array_merge($stream,$tableData);
        }

        //return CSV or JSON?
        if ($this->_request->getParam('format') == 'csv') {
            $type = $presence ? $presence->type : 'all-presence';
            $this->returnCsv($tableData, $type . 's.csv');
        } else {
            // sort according to request param
            if($this->_request->getParam('sort') === 'engagement') {
                usort($tableData, function ($a, $b) {
                    $aAfterB = $a['engagement']['comparable'] > $b['engagement']['comparable'];
                    return $aAfterB ? -1 : 1;
                });
            } else {
                usort($tableData, function ($a, $b) {
                    $aAfterB = $a['created_time'] > $b['created_time'];
                    return $aAfterB ? -1 : 1;
                });
            }
            $displayRows = array_slice($tableData, 0, $limit);
            $apiResult = array(
                'sEcho' => $this->_request->getParam('sEcho'),
                'iTotalRecords' => $count, // total rows before filtering
                'iTotalDisplayRecords' => $count, // total rows after filtering. these are the same as we don't filter
                'aaData' => $displayRows
            );
            $this->apiSuccess($apiResult);
        }

    }

    private function getStatusStream($presences, $types = null, \DateTime $start, \DateTime $end, $search = null,
                                     $order = null, $limit = null, $offset = null)
    {
        $statuses = array();
        if(!$presences || !count($presences)) {
            return $statuses;
        }
        /** @var Provider_Abstract $provider */
        foreach ($this->providers as $provider) {
            if ($types !== null) {
                if(count($types) && in_array($provider->getType(), $types)) {
                    $data = $provider->getStatusStreamMulti($presences, $start, $end, $search, $order, $limit, $offset);
                    $data->type = $provider->getType();
                    $statuses[] = $data;
                }
            } else {
                $data = $provider->getStatusStreamMulti($presences, $start, $end, $search, $order, $limit, $offset);
                $data->type = $provider->getType();
                $statuses[] = $data;
            }
        }

        return $statuses;
    }

    // creates an array of ordering arguments (propName=>direction) from the datatables request args
    protected function getRequestOrdering()
    {
        $sort = $this->_request->getParam('sort');
        if(!$sort) {
            $sort = 'date';
        }
        return [ $sort => 'desc' ];
    }

}
